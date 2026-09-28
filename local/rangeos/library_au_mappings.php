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

$_requeststart = microtime(true);

use local_rangeos\environment_manager;
use local_rangeos\content_patcher;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:manageaumappings', $context);

// Package ID is the selected package.
$packageid = optional_param('packageid', 0, PARAM_INT);
$envid = optional_param('envid', 0, PARAM_INT);
$currentpage = optional_param('page', 0, PARAM_INT);

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

$_perfdata = [];
$_addperf = static function (string $label, float $ms, string $context = '') use (&$_perfdata): void {
    $_perfdata[] = ['label' => $label, 'ms' => $ms, 'context' => $context];
    if (debugging('', DEBUG_NORMAL)) {
        error_log(sprintf('[rangeos_perf] %-55s %7.1fms%s', $label, $ms, $context ? "  ($context)" : ''));
    }
};
$_perf = static function (string $label, float $start, string $context = '') use ($_addperf): void {
    $_addperf($label, (microtime(true) - $start) * 1000, $context);
};

// Load package names for the project search suggestions.
$_t = microtime(true);
$packages = $DB->get_records('cmi5_packages', [], 'title ASC', 'id, title, latestversion');
$_perf('DB: load packages list', $_t, count($packages) . ' packages');

// The local library owns both the row count and pagination. Remote mappings only enrich these rows.
$_t = microtime(true);
$librarypage = \local_rangeos\library_au_listing::get_page($packageid, $currentpage);
$_perf('DB: library AU page', $_t, $librarypage['total'] . ' total, ' . count($librarypage['aus']) . ' rows');
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
        // Resolve this page's mappings from the short-lived cache, fetching misses concurrently.
        $_t = microtime(true);
        $aumappings = $client->get_au_mappings_by_ids(array_column($aus, 'auid'));
        foreach ($aumappings as $mapping) {
            foreach ($mapping['scenarios'] ?? [] as $scenario) {
                $uuid = is_array($scenario)
                    ? ($scenario['uuid'] ?? $scenario['scenarioId'] ?? $scenario['id'] ?? '')
                    : (string) $scenario;
                if ($uuid) {
                    $scenariouuids[$uuid] = true;
                }
            }
        }
        $mappingstats = $client->get_last_mapping_read_stats();
        $_perf('API/cache: get AU mappings', $_t, sprintf(
            '%d hits, %d misses, %d HTTP waves; auth %.1fms, HTTP %.1fms',
            $mappingstats['cachehits'],
            $mappingstats['cachemisses'],
            $mappingstats['waves'],
            $mappingstats['authms'],
            $mappingstats['httpms']
        ));

        // Fetch all scenarios in one call — 'limit' is the correct param name for this API.
        $scenariobynamelookup = []; // name => uuid
        if (!empty($aus)) {
            $_t = microtime(true);
            $scenarioresponse = $client->get_cached_content_scenarios(['limit' => 1000]);
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
            $_perf(
                'API: list_content_scenarios',
                $_t,
                count($scenarioresponse['data'] ?? []) . ' items, ' . count($scenariolookup) . ' resolved, cache '
                    . ($client->was_last_content_scenario_cache_hit() ? 'hit' : 'miss')
            );
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
    ];
}

// Build AU IRI → cmi5 activity lookup from local DB.
$aulookup = []; // auid => [{activityname, coursename, cmid}]
$allauids = array_values(array_unique(array_column($aus, 'auid')));
if (!empty($allauids)) {
    $_t = microtime(true);
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
    $_perf('DB: AU activity lookup', $_t, count($records) . ' activity rows');
}

// Cache config files per version, including when browsing all library packages.
$configms = 0.0;
$configcount = 0;
$configsbyversion = [];
$audata = [];
foreach ($aus as $au) {
    $mapping = $aumappings[$au->auid] ?? null;
    $scenarios = $mapping['scenarios'] ?? [];

    $scenariobadges = [];
    $scenariooptions = [];
    foreach ($scenarios as $s) {
        $uuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
        $name = $scenariolookup[$uuid] ?? '';
        $scenariobadges[] = $name ?: $uuid;
        if ($uuid) {
            $scenariooptions[] = ['id' => $uuid, 'name' => $name ?: $uuid];
        }
    }

    // Read config.json for RangeOS AU detection (only when we have a version).
    $israngeos = false;
    $classmode = false;
    $defaultclassid = '';
    $scenarioname = '';
    $auversionid = (int) $au->versionid;
    if (!empty($auversionid) && !empty($au->url)) {
        if (!array_key_exists($auversionid, $configsbyversion)) {
            $_configstart = microtime(true);
            $configsbyversion[$auversionid] = content_patcher::get_all_au_configs($auversionid);
            $configms += (microtime(true) - $_configstart) * 1000;
            $configcount += count($configsbyversion[$auversionid]);
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

    $defaultscenariomissing = !empty($scenarioname) && !isset($scenariobynamelookup[$scenarioname]);

    // Stable ids so the row's toggle can point at its own detail region. The AU IRI
    // itself can't be one: it carries slashes and colons.
    $rowindex = count($audata);

    $audata[] = [
        'rowid' => 'rangeos-au-' . $rowindex,
        'detailid' => 'rangeos-au-detail-' . $rowindex,
        'auid' => $au->auid,
        'title' => $au->title,
        'israngeos' => $israngeos,
        'scenarioname' => $scenarioname,
        'defaultscenariomissing' => $defaultscenariomissing,
        'ismapped' => !empty($scenarios),
        'scenario_badges' => $scenariobadges,
        'scenario_count' => count($scenarios),
        'scenarios_json' => json_encode($scenariooptions),
        'mapping_name' => $mapping['name'] ?? '',
        'classmode' => $classmode,
        'defaultclassid' => $defaultclassid,
        'activities' => $activities,
        'hasactivities' => !empty($activities),
        'packagetitle' => format_string($au->packagetitle),
        'haspackageinfo' => true,
    ];
}
$_addperf(
    'Files: load AU config.json',
    $configms,
    count($configsbyversion) . ' versions, ' . $configcount . ' configs'
);

// Preserve the library filters when the shared AMD handler changes the environment.
$baseurl = (new moodle_url('/local/rangeos/library_au_mappings.php', [
    'packageid' => $packageid,
]))->out(false);

$pagingurl = new moodle_url('/local/rangeos/library_au_mappings.php', [
    'envid'     => $envid,
    'packageid' => $packageid,
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
]);

if (debugging('', DEBUG_NORMAL)) {
    // Capture the slowest phase before appending the total, which would otherwise always win.
    $slowest = null;
    foreach ($_perfdata as $entry) {
        if ($slowest === null || $entry['ms'] > $slowest['ms']) {
            $slowest = $entry;
        }
    }
    $_perf(
        'Total: RangeOS page preparation',
        $_requeststart,
        'after Moodle bootstrap; excludes final HTML transmission'
    );
    $table = new html_table();
    $table->head = ['Phase', 'Time (ms)', 'Details'];
    $table->attributes['class'] = 'table table-sm table-striped mb-0';
    foreach ($_perfdata as $entry) {
        $table->data[] = [
            s($entry['label']),
            number_format($entry['ms'], 1),
            s($entry['context']),
        ];
    }
    $summary = $slowest
        ? html_writer::div(
            'Slowest measured phase: ' . s($slowest['label']) . ' — '
                . number_format($slowest['ms'], 1) . ' ms',
            'alert alert-info m-3'
        )
        : '';
    echo html_writer::div(
        html_writer::tag('h5', 'Library AU mapping performance', ['class' => 'card-header mb-0'])
            . html_writer::div($summary . html_writer::table($table), 'card-body p-0'),
        'card mt-3 mb-3'
    );
}

// Keep pagination available whenever the library contains rows.
if ($totalitems > 0) {
    echo html_writer::div(
        html_writer::div($OUTPUT->paging_bar($totalitems, $currentpage, $pagesize, $pagingurl), 'd-flex align-items-center'),
        'rangeos-pagination d-flex flex-wrap align-items-center justify-content-center mt-3'
    );
}
echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
