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
 * AU-to-scenario mapping management — shows all RangeOS AU mappings with optional package filter.
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
$showall = optional_param('showall', 0, PARAM_INT);
// Adding pagination CCUI 2910
$currentpage = optional_param('page', 0, PARAM_INT);
$pagesize = optional_param('perpage', 20, PARAM_INT);
$totalpages = 1; // default, gets overwritten in all-mappings mode

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

// Load packages list for the filter selector.
$packages = $DB->get_records('cmi5_packages', [], 'title ASC', 'id, title, latestversion');

// Determine which AUs to show: filtered by package, or all from the API.
$aus = []; // auid => {auid, title, url, versionid}
$packagetitle = '';
$versionid = 0;

if ($packageid > 0) {
    // Package-filtered mode: show AUs from this specific package.
    $package = $DB->get_record('cmi5_packages', ['id' => $packageid]);
    if ($package) {
        $packagetitle = $package->title;
        $versionid = $package->latestversion;
        if ($versionid) {
            $packageaus = $DB->get_records('cmi5_package_aus', ['versionid' => $versionid], 'sortorder ASC');
            foreach ($packageaus as $pau) {
                $aus[$pau->auid] = $pau;
            }
        }
    }
}

// Fetch AU mappings from devops-api.
$aumappings = []; // auid => mapping data.
$scenariolookup = []; // uuid => name.
$scenariouuids = [];
$client = null;
$totalitems = null;
$error = '';
if ($envid > 0) {
    try {
        $client = \local_rangeos\api_client::from_environment($envid);
        if ($packageid > 0 && !empty($aus)) {
            // Package mode: fetch mappings per AU.
            // Building a lookup table of scenario UUID
            $scenariouuids = [];
            foreach ($aus as $au) {
                $mapping = $client->get_au_mapping($au->auid);
                if ($mapping) {
                    $aumappings[$au->auid] = $mapping;
                }
            }
        } else {
            // All-mappings mode: fetch all AU mappings from the API.
            $start_aumappings = microtime(true);
            $scenariouuids = [];
            $page = 0;
                //Grabbing upto 20 mappings
                $response = $client->list_au_mappings([
                    'page' => $currentpage,
                    'pageSize' => $pagesize,
                ]);
                $items = $response['data'] ?? $response['items'] ?? $response;
                foreach ($items as $m) {
                    $m = (array) $m;
                    $auid = $m['auId'] ?? $m['auid'] ?? '';
                    if (!$auid) {
                        continue;
                    }
                    //Create lookup table of AU mappings by auId. 
                    $aumappings[$auid] = $m;
                    // Build a synthetic AU entry if we don't already have one.
                    // This is another AU mapping lookup table, with slightly different properties.
                    if (!isset($aus[$auid])) {
                        $aus[$auid] = (object) [
                            'auid' => $auid,
                            'title' => $m['name'] ?? '',
                            'url' => '',
                            'versionid' => 0,
                        ];
                    }
                }
                $page++;
                $totalpages = $response['totalPages'] ?? 1;
                $totalitems = $response['totalCount'] ?? $response['total'] ?? null;

            }

    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// In all-mappings mode, also look up AU titles from local cmi5_aus table for any AUs missing titles.
if ($packageid === 0 && !empty($aus)) {
    $auids = array_keys($aus);
    list($insql, $inparams) = $DB->get_in_or_equal($auids, SQL_PARAMS_NAMED);
    $localaus = $DB->get_records_sql(
        "SELECT DISTINCT ca.auid, ca.title FROM {cmi5_aus} ca WHERE ca.auid {$insql}",
        $inparams
    );
    foreach ($localaus as $la) {
        if (isset($aus[$la->auid]) && empty($aus[$la->auid]->title)) {
            $aus[$la->auid]->title = $la->title;
        }
    }
}

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/rangeos/manage.php'),
    get_string('backtomanagement', 'local_rangeos'),
    ['class' => 'btn btn-secondary mb-3']
);
echo $OUTPUT->heading(get_string('library_aumappings', 'local_rangeos'));

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
$allauids = array_keys($aus);
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

// Also build a package lookup for all-mappings mode — which package does each AU belong to?
$aupackagelookup = []; // auid => {packageid, title}
if ($packageid === 0 && !empty($allauids)) {
    $sql = "SELECT pa.auid, p.id AS packageid, p.title AS packagetitle
              FROM {cmi5_package_aus} pa
              JOIN {cmi5_packages} p ON p.latestversion = pa.versionid
             WHERE pa.auid {$insql}";
    $pkgrecords = $DB->get_records_sql($sql, $inparams);
    foreach ($pkgrecords as $pr) {
        $aupackagelookup[$pr->auid] = (object) [
            'packageid' => $pr->packageid,
            'packagetitle' => $pr->packagetitle,
        ];
    }
}

$audata = [];
$totalaumappings = count($aus);
foreach ($aus as $auid => $au) {
    // In all-mappings mode, default to showing only AUs with local activities.
    if ($packageid === 0 && !$showall && !isset($aulookup[$au->auid])) {
        continue;
    }

    $mapping = $aumappings[$au->auid] ?? null;
    $scenarios = $mapping['scenarios'] ?? [];

    // after the filter check, before building the rest of audata
    foreach ($scenarios as $s) {
        $uuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
        if ($uuid) {
            $scenariouuids[$uuid] = true;
        }
    }

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
    $auversionid = ($packageid > 0) ? $versionid : ($au->versionid ?? 0);
    if (!empty($auversionid) && !empty($au->url)) {
        $config = content_patcher::get_au_config($auversionid, $au->url);
        if ($config !== null && !empty($config['rangeosScenarioUUID'])) {
            $israngeos = true;
            $scenarioname = $config['rangeosScenarioName'] ?? '';
            $classmode = !empty($config['promptClassId']);
            $defaultclassid = $config['defaultClassId'] ?? '';
        }
    }

    // In all-mappings mode without config.json access, treat all API mappings as RangeOS AUs.
    if ($packageid === 0 && !$israngeos && !empty($scenarios)) {
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

    // Package info for all-mappings mode.
    $pkginfo = $aupackagelookup[$au->auid] ?? null;

    // Truncate long AU IRIs for display.
    $auidshort = $au->auid;
    if (strlen($au->auid) > 60) {
        $parts = explode('/', $au->auid);
        $auidshort = '.../' . end($parts);
    }

    $audata[] = [
        'auid' => $au->auid,
        'auid_short' => $auidshort,
        'title' => $au->title,
        'israngeos' => $israngeos,
        'scenarioname' => $scenarioname,
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
        'packagetitle' => $pkginfo ? format_string($pkginfo->packagetitle) : '',
        'haspackageinfo' => !empty($pkginfo),
    ];
}

// Resolve scenario UUIDs via a single bulk fetch, then update scenario_badges.
if ($client && !empty($scenariouuids)) {
    $scenarioresponse = $client->list_content_scenarios(['page' => 0, 'pageSize' => 500]);
    $scenarioitems = $scenarioresponse['data'] ?? [];
    foreach ($scenarioitems as $s) {
        $s = (array) $s;
        $uuid = $s['uuid'] ?? '';
        if ($uuid) {
            $scenariolookup[$uuid] = $s['name'] ?? '';
        }
    }
    foreach ($audata as &$entry) {
        $scenarios = json_decode($entry['scenarios_json'], true) ?? [];
        $badges = [];
        foreach ($scenarios as $s) {
            $uuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
            $name = $scenariolookup[$uuid] ?? '';
            $badges[] = $name ?: $uuid;
        }
        $entry['scenario_badges'] = $badges;
    }
    unset($entry);
}

$baseurl = (new moodle_url('/local/rangeos/library_au_mappings.php'))->out(false);

$hiddencount = $totalaumappings - count($audata);

// Paginate $audata. The API may return more items than pageSize if it ignores the parameter,
// so we always PHP-slice as a safety net.
$totalaudata = count($audata);
if ($packageid === 0 && $totalpages <= 1 && $totalaudata > $pagesize) {
    // API didn't paginate — derive totalpages from actual item count.
    $totalpages = (int) ceil($totalaudata / $pagesize);
}
if ($totalitems === null) {
    $totalitems = $totalpages * $pagesize;
}
$audata = array_slice($audata, $currentpage * $pagesize, $pagesize);
$pagecount = count($audata);
$pagefirst = $currentpage * $pagesize + 1;
$pagelast = $pagefirst + $pagecount - 1;


$pagingurl = new moodle_url('/local/rangeos/library_au_mappings.php', [
    'envid'     => $envid,
    'packageid' => $packageid,
    'showall'   => $showall,
    'perpage'   => $pagesize,
]);

$pagesizeoptions = [];
foreach ([20, 50, 100] as $size) {
    $pagesizeoptions[] = ['size' => $size, 'selected' => ($size === $pagesize)];
}

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
    'hasselectedpackage' => ($packageid > 0),
    'showallmode' => ($packageid === 0),
    'showall' => (bool) $showall,
    'hiddencount' => $hiddencount,
    'hashidden' => ($hiddencount > 0),
    'error' => $error,
    'haserror' => !empty($error),
    'baseurl' => $baseurl,
    'libraryurl' => (new moodle_url('/mod/cmi5/library.php'))->out(false),
    'perpage' => $pagesize,
]);

$perpageselect = html_writer::tag('label',
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

// Only show pages if there is more than one page.
if ($pagecount >= $pagesize || $currentpage > 0) {
    echo html_writer::tag('style', '.pagination { margin-bottom: 0; }');
    echo html_writer::div(
        html_writer::div($perpageselect, 'd-flex align-items-center mr-3') .
        html_writer::div($OUTPUT->paging_bar($totalitems, $currentpage, $pagesize, $pagingurl), 'd-flex align-items-center'),
        'd-flex align-items-center justify-content-center mt-3'
    );
}
echo $OUTPUT->footer();