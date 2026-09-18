<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AU-to-scenario mapping management — shows local library AUs with an optional package filter.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;
use local_rangeos\content_patcher;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:manageaumappings', $context);

// Package ID is the selected package.
$packageid = optional_param('packageid', 0, PARAM_INT);
$envid = optional_param('envid', 0, PARAM_INT);
$currentpage = optional_param('page', 0, PARAM_INT);
$pagesize = optional_param('perpage', 20, PARAM_INT);

// Handle bulk map-all-defaults action.
$mapresults = null;
if (optional_param('action', '', PARAM_ALPHA) === 'mapalldefaults') {
    require_sesskey();

    if ($envid === 0) {
        $default = environment_manager::get_default_environment();
        if ($default) {
            $envid = $default->id;
        }
    }

    if ($envid > 0) {
        $mapresults = \local_rangeos\au_mapping_manager::map_all_defaults($envid);
    }
}

$PAGE->set_context($context);
$PAGE->set_url('/local/rangeos/library_au_mappings.php', ['packageid' => $packageid, 'envid' => $envid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('library_aumappings', 'local_rangeos'));
$PAGE->set_heading(get_string('library_aumappings', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/au_mappings', 'init');

$environments = environment_manager::list_environments();

// Select default environment.
if ($envid === 0) {
    $default = environment_manager::get_default_environment();
    if ($default) {
        $envid = $default->id;
    } else if (!empty($environments)) {
        $envid = reset($environments)->id;
    }
}

global $DB;

$_perf_start = microtime(true);
$_perf = debugging('', DEBUG_NORMAL)
    ? static function (string $label, float $start, string $context = ''): void {
        $ms = round((microtime(true) - $start) * 1000, 1);
        error_log(sprintf('[rangeos_perf] %-55s %7.1fms%s', $label, $ms, $context ? "  ($context)" : ''));
    }
    : static function (string $_label, float $_start, string $_context = ''): void {};

// Load packages list for the filter selector.
$_t = microtime(true);
$packages = $DB->get_records('cmi5_packages', [], 'title ASC', 'id, title, latestversion');
$_perf('DB: load packages list', $_t, count($packages) . ' packages');

// The local library owns both the row count and pagination. Remote mappings only enrich these rows.
$librarypage = \local_rangeos\library_au_listing::get_page($packageid, $currentpage, $pagesize);
$aus = $librarypage['aus'];
$totalitems = $librarypage['total'];
$currentpage = $librarypage['page'];
$pagesize = $librarypage['pagesize'];
$package = $packageid > 0 ? $DB->get_record('cmi5_packages', ['id' => $packageid], '*', MUST_EXIST) : null;
$packagetitle = $package ? format_string($package->title) : '';
$versionid = $package ? (int) $package->latestversion : 0;

// Fetch AU mappings from devops-api.
$aumappings = []; // auid => mapping data.
$scenariolookup = []; // uuid => name.
$scenariobynamelookup = []; // name => uuid.
$scenariouuids = [];
$client = null;
$error = '';
if ($envid > 0 && $aus) {
    try {
        $client = \local_rangeos\api_client::from_environment($envid);
        // Look up only this page's AUs. Do not truncate mappings at a remote list-page boundary.
        foreach ($aus as $au) {
            if (array_key_exists($au->auid, $aumappings)) {
                continue;
            }
            $mapping = $client->get_au_mapping($au->auid);
            $aumappings[$au->auid] = $mapping;
            foreach ($mapping['scenarios'] ?? [] as $scenario) {
                $uuid = is_array($scenario)
                    ? ($scenario['uuid'] ?? $scenario['scenarioId'] ?? $scenario['id'] ?? '')
                    : (string) $scenario;
                if ($uuid) {
                    $scenariouuids[$uuid] = true;
                }
            }
        }

        // Fetch all scenarios in one call — 'limit' is the correct param name for this API.
        $scenariobynamelookup = []; // name => uuid
        if (!empty($aus)) {
            $_t = microtime(true);
            $scenarioresponse = $client->list_content_scenarios(['limit' => 1000]);
            foreach ($scenarioresponse['data'] ?? [] as $s) {
                $s = (array) $s;
                $uuid = $s['uuid'] ?? '';
                $name = $s['name'] ?? '';
                if ($uuid && isset($scenariouuids[$uuid])) {
                    $scenariolookup[$uuid] = $name;
                }
                if ($name && $uuid) {
                    $scenariobynamelookup[$name] = $uuid;
                }
            }
            $_perf('API: list_content_scenarios', $_t, count($scenarioresponse['data'] ?? []) . ' items, ' . count($scenariolookup) . ' resolved');
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo \local_rangeos\output\dashboard::start('library_au_mappings', 'library_aumappings_desc');

// Build template data.
$envoptions = [];
foreach ($environments as $env) {
    $envoptions[] = [
        'id' => $env->id,
        'name' => format_string($env->name),
        'selected' => ($env->id == $envid),
    ];
}

$packageoptions = [];
foreach ($packages as $pkg) {
    $packageoptions[] = [
        'id' => $pkg->id,
        'title' => format_string($pkg->title),
        'selected' => ($pkg->id == $packageid),
    ];
}

// Build AU IRI → cmi5 activity lookup from local DB.
$aulookup = []; // auid => [{activityname, coursename, cmid}]
$allauids = array_values(array_unique(array_column($aus, 'auid')));
if (!empty($allauids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($allauids, SQL_PARAMS_NAMED);
    $sql = "SELECT ca.id, ca.auid, ca.title AS autitle, c5.id AS cmi5id, c5.name AS activityname,
                   co.id AS courseid, co.fullname AS coursename, cm.id AS cmid
              FROM {cmi5_aus} ca
              JOIN {cmi5} c5 ON c5.id = ca.cmi5id
              JOIN {course_modules} cm ON cm.instance = c5.id AND cm.module = (
                  SELECT id FROM {modules} WHERE name = 'cmi5'
              )
              JOIN {course} co ON co.id = c5.course
             WHERE ca.auid {$insql}
          ORDER BY co.fullname, c5.name";
    $records = $DB->get_records_sql($sql, $inparams);
    foreach ($records as $rec) {
        if (!isset($aulookup[$rec->auid])) {
            $aulookup[$rec->auid] = [];
        }
        $aulookup[$rec->auid][] = (object) [
            'activityname' => $rec->activityname,
            'coursename' => $rec->coursename,
            'cmid' => $rec->cmid,
        ];
    }
}

// Cache config files per version, including when browsing all library packages.
$configsbyversion = [];
$audata = [];
foreach ($aus as $au) {
    $mapping = $aumappings[$au->auid] ?? null;
    $scenarios = $mapping['scenarios'] ?? [];

    $scenariobadges = [];
    foreach ($scenarios as $s) {
        $uuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
        $name = $scenariolookup[$uuid] ?? '';
        $scenariobadges[] = $name ?: $uuid;
    }

    // Read config.json for RangeOS AU detection (only when we have a version).
    $israngeos = false;
    $classmode = false;
    $defaultclassid = '';
    $scenarioname = '';
    $auversionid = (int) $au->versionid;
    if (!empty($auversionid) && !empty($au->url)) {
        if (!array_key_exists($auversionid, $configsbyversion)) {
            $configsbyversion[$auversionid] = content_patcher::get_all_au_configs($auversionid);
        }
        $config = $configsbyversion[$auversionid][content_patcher::au_url_to_filepath($au->url)] ?? null;
        if ($config !== null && !empty($config['rangeosScenarioUUID'])) {
            $israngeos = true;
            $scenarioname = $config['rangeosScenarioName'] ?? '';
            $classmode = !empty($config['promptClassId']);
            $defaultclassid = $config['defaultClassId'] ?? '';
        }
    }

    // A library AU with an existing scenario mapping is also a RangeOS AU.
    if (!$israngeos && !empty($scenarios)) {
        $israngeos = true;
    }

    // Get the first scenario UUID for class creation.
    $firstscenariouuid = '';
    foreach ($scenarios as $s) {
        $firstscenariouuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
        if ($firstscenariouuid) {
            break;
        }
    }

    // Find matching local cmi5 activities.
    $activities = [];
    if (isset($aulookup[$au->auid])) {
        foreach ($aulookup[$au->auid] as $act) {
            $activities[] = [
                'activityname' => format_string($act->activityname),
                'coursename' => format_string($act->coursename),
                'cmid' => $act->cmid,
            ];
        }
    }

    // Truncate long AU IRIs for display.
    $auidshort = $au->auid;
    if (strlen($au->auid) > 60) {
        $parts = explode('/', $au->auid);
        $auidshort = '.../' . end($parts);
    }

    $defaultscenariomissing = !empty($scenarioname) && !isset($scenariobynamelookup[$scenarioname]);

    $audata[] = [
        'auid' => $au->auid,
        'auid_short' => $auidshort,
        'title' => $au->title,
        'israngeos' => $israngeos,
        'scenarioname' => $scenarioname,
        'defaultscenariomissing' => $defaultscenariomissing,
        'ismapped' => !empty($scenarios),
        'scenario_badges' => $scenariobadges,
        'scenario_count' => count($scenarios),
        'scenarios_json' => json_encode($scenarios),
        'mapping_name' => $mapping['name'] ?? '',
        'classmode' => $classmode,
        'defaultclassid' => $defaultclassid,
        'firstscenariouuid' => $firstscenariouuid,
        'activities' => $activities,
        'hasactivities' => !empty($activities),
        'packagetitle' => format_string($au->packagetitle),
        'haspackageinfo' => true,
    ];
}

// Preserve the library filters when the shared AMD handler changes the environment.
$baseurl = (new moodle_url('/local/rangeos/library_au_mappings.php', [
    'packageid' => $packageid,
    'perpage' => $pagesize,
]))->out(false);

$pagingurl = new moodle_url('/local/rangeos/library_au_mappings.php', [
    'envid'     => $envid,
    'packageid' => $packageid,
    'perpage'   => $pagesize,
]);

echo $OUTPUT->render_from_template('local_rangeos/library_au_mappings', [
    'environments' => $envoptions,
    'hasenvironments' => !empty($envoptions),
    'envid' => $envid,
    'packages' => $packageoptions,
    'haspackages' => !empty($packageoptions),
    'packageid' => $packageid,
    'packagetitle' => $packagetitle,
    'versionid' => $versionid,
    'aus' => $audata,
    'hasaus' => !empty($audata),
    'rowsummary' => get_string('librarymapping_count', 'local_rangeos', (object) [
        'first' => $totalitems ? $currentpage * $pagesize + 1 : 0,
        'last' => min(($currentpage + 1) * $pagesize, $totalitems),
        'total' => $totalitems,
    ]),
    'hasselectedpackage' => ($packageid > 0),
    'showpackagecolumn' => ($packageid === 0),
    'error' => $error,
    'haserror' => !empty($error),
    'baseurl' => $baseurl,
    'libraryurl' => (new moodle_url('/mod/cmi5/library.php'))->out(false),
    'mapresults' => $mapresults,
    'hasmapresults' => ($mapresults !== null),
    'mapallurl' => (new moodle_url('/local/rangeos/library_au_mappings.php', [
        'envid' => $envid,
        'packageid' => $packageid,
        'action' => 'mapalldefaults',
        'sesskey' => sesskey(),
    ]))->out(false),
    'globalmappingsurl' => (new moodle_url('/local/rangeos/au_mappings.php', ['envid' => $envid]))->out(false),
]);

$perpageselect = html_writer::tag(
    'label',
    get_string('perpage', 'moodle') . ':',
    ['for' => 'rangeos-perpage-select', 'class' => 'mr-2 mb-0 small text-muted']
);
$perpageselect .= html_writer::select(
    [20 => '20', 50 => '50', 100 => '100'],
    'perpage',
    $pagesize,
    false,
    ['id' => 'rangeos-perpage-select', 'class' => 'custom-select custom-select-sm w-auto', 'style' => 'vertical-align: middle;']
);

// Keep the page-size control available even when the larger size fits on one page.
if ($totalitems > 0) {
    echo html_writer::div(
        html_writer::div($perpageselect, 'd-flex align-items-center mr-3') .
            html_writer::div($OUTPUT->paging_bar($totalitems, $currentpage, $pagesize, $pagingurl), 'd-flex align-items-center'),
        'rangeos-pagination d-flex flex-wrap align-items-center justify-content-center mt-3'
    );
}
echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
