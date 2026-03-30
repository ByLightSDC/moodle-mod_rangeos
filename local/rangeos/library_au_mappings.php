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

$packageid = optional_param('packageid', 0, PARAM_INT);
$envid = optional_param('envid', 0, PARAM_INT);
$showall = optional_param('showall', 0, PARAM_INT);

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
$error = '';
if ($envid > 0) {
    try {
        $client = \local_rangeos\api_client::from_environment($envid);

        if ($packageid > 0 && !empty($aus)) {
            // Package mode: fetch mappings per AU.
            $scenariouuids = [];
            foreach ($aus as $au) {
                $mapping = $client->get_au_mapping($au->auid);
                if ($mapping) {
                    $aumappings[$au->auid] = $mapping;
                    foreach ($mapping['scenarios'] ?? [] as $s) {
                        $uuid = is_array($s) ? ($s['uuid'] ?? $s['id'] ?? '') : (string) $s;
                        if ($uuid) {
                            $scenariouuids[$uuid] = true;
                        }
                    }
                }
            }
        } else {
            // All-mappings mode: fetch all AU mappings from the API.
            $scenariouuids = [];
            $page = 0;
            do {
                $response = $client->list_au_mappings([
                    'page' => $page,
                    'pageSize' => 100,
                ]);
                $items = $response['data'] ?? $response['items'] ?? $response;
                foreach ($items as $m) {
                    $m = (array) $m;
                    $auid = $m['auId'] ?? $m['auid'] ?? '';
                    if (!$auid) {
                        continue;
                    }
                    $aumappings[$auid] = $m;
                    // Build a synthetic AU entry if we don't already have one.
                    if (!isset($aus[$auid])) {
                        $aus[$auid] = (object) [
                            'auid' => $auid,
                            'title' => $m['name'] ?? '',
                            'url' => '',
                            'versionid' => 0,
                        ];
                    }
                    foreach ($m['scenarios'] ?? [] as $s) {
                        $uuid = is_array($s) ? ($s['uuid'] ?? $s['id'] ?? '') : (string) $s;
                        if ($uuid) {
                            $scenariouuids[$uuid] = true;
                        }
                    }
                }
                $page++;
                $totalpages = $response['totalPages'] ?? 1;
            } while ($page < $totalpages);
        }

        // Resolve scenario UUIDs to names.
        if (!empty($scenariouuids)) {
            $scenariopage = 0;
            do {
                $scenarioresponse = $client->list_content_scenarios([
                    'page' => $scenariopage,
                    'pageSize' => 100,
                ]);
                foreach ($scenarioresponse['data'] ?? [] as $s) {
                    $s = (array) $s;
                    $uuid = $s['uuid'] ?? '';
                    if ($uuid && isset($scenariouuids[$uuid])) {
                        $scenariolookup[$uuid] = $s['name'] ?? '';
                    }
                }
                if (count($scenariolookup) >= count($scenariouuids)) {
                    break;
                }
                $scenariopage++;
                $scenariototal = $scenarioresponse['totalPages'] ?? 1;
            } while ($scenariopage < $scenariototal);
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

$baseurl = (new moodle_url('/local/rangeos/library_au_mappings.php'))->out(false);

$hiddencount = $totalaumappings - count($audata);

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
]);

echo $OUTPUT->footer();
