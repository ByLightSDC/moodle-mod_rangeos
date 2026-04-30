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
 * Global AU-to-scenario mapping management page.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:manageaumappings', $context);

$envid = optional_param('envid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$pagesize = optional_param('pagesize', 25, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url('/local/rangeos/au_mappings.php', ['envid' => $envid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('aumappings_global', 'local_rangeos'));
$PAGE->set_heading(get_string('aumappings_global', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/au_mappings', 'init');

$environments = environment_manager::list_environments();

// Select default environment if none chosen.
if ($envid === 0) {
    $default = environment_manager::get_default_environment();
    if ($default) {
        $envid = $default->id;
    } else if (!empty($environments)) {
        $envid = reset($environments)->id;
    }
}

$_perf_start = microtime(true);
$_perf = debugging('', DEBUG_NORMAL)
    ? static function (string $label, float $start, string $context = ''): void {
        $ms = round((microtime(true) - $start) * 1000, 1);
        error_log(sprintf('[rangeos_perf] %-55s %7.1fms%s', $label, $ms, $context ? "  ($context)" : ''));
    }
    : static function (string $_label, float $_start, string $_context = ''): void {};

// Fetch data from APIs.
$mappings = [];
$totalcount = 0;
$scenariolookup = []; // UUID => name from content API.
$error = '';
if ($envid > 0) {
    try {
        $client = \local_rangeos\api_client::from_environment($envid);

        // Fetch all scenarios in one call — 'limit' is the correct param name for this API.
        $_t = microtime(true);
        $scenarioresponse = $client->list_content_scenarios(['limit' => 10000]);
        foreach ($scenarioresponse['data'] ?? [] as $s) {
            $s = (array) $s;
            $uuid = $s['uuid'] ?? '';
            $name = $s['name'] ?? '';
            if ($uuid) {
                $scenariolookup[$uuid] = $name;
            }
        }
        $_perf('API: list_content_scenarios', $_t, count($scenarioresponse['data'] ?? []) . ' items');

        // Get AU mappings for current page.
        $_t = microtime(true);
        $response = $client->list_au_mappings([
            'page' => $page,
            'pageSize' => $pagesize,
        ]);
        $mappings = $response['data'] ?? $response['items'] ?? $response;
        $totalcount = $response['totalCount'] ?? $response['total'] ?? count($mappings);
        $_perf('API: list_au_mappings', $_t, count($mappings) . ' items, page ' . $page);
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// Build AU IRI → cmi5 activity lookup from local DB.
$aulookup = []; // auid => [{activityname, coursename, cmid}]
$allauids = [];
foreach ($mappings as $m) {
    $m = (array) $m;
    $auid = $m['auId'] ?? $m['auid'] ?? '';
    if ($auid) {
        $allauids[] = $auid;
    }
}
if (!empty($allauids)) {
    global $DB;
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
    $_t = microtime(true);
    $records = $DB->get_records_sql($sql, $inparams);
    $_perf('DB: AU activity lookup', $_t, count($records) . ' rows, ' . count($allauids) . ' AUs');
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

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aumappings_global', 'local_rangeos'));

// Build template data.
$envoptions = [];
foreach ($environments as $env) {
    $envoptions[] = [
        'id' => $env->id,
        'name' => format_string($env->name),
        'selected' => ($env->id == $envid),
    ];
}

$mappingdata = [];
foreach ($mappings as $mapping) {
    $mapping = (array) $mapping;
    $scenarios = $mapping['scenarios'] ?? [];

    // Resolve scenario UUIDs to names via content API lookup.
    $scenariobadges = [];
    foreach ($scenarios as $s) {
        $uuid = is_array($s) ? ($s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '') : (string) $s;
        $name = $scenariolookup[$uuid] ?? '';
        $scenariobadges[] = $name ?: $uuid;
    }

    $auid = $mapping['auId'] ?? $mapping['auid'] ?? '';
    // Truncate AU IRI for display.
    $auidshort = $auid;
    if (strlen($auid) > 50) {
        $parts = explode('/', $auid);
        $auidshort = '.../' . end($parts);
    }

    // Find matching local cmi5 activities.
    $activities = [];
    if (isset($aulookup[$auid])) {
        foreach ($aulookup[$auid] as $act) {
            $activities[] = [
                'activityname' => format_string($act->activityname),
                'coursename' => format_string($act->coursename),
                'cmid' => $act->cmid,
            ];
        }
    }

    $mappingdata[] = [
        'auid' => $auid,
        'auid_short' => $auidshort,
        'name' => $mapping['name'] ?? '',
        'scenario_badges' => $scenariobadges,
        'scenario_count' => count($scenarios),
        'scenarios_json' => json_encode($scenarios),
        'activities' => $activities,
        'hasactivities' => !empty($activities),
    ];
}

echo $OUTPUT->render_from_template('local_rangeos/au_mappings', [
    'environments' => $envoptions,
    'hasenvironments' => !empty($envoptions),
    'envid' => $envid,
    'mappings' => $mappingdata,
    'hasmappings' => !empty($mappingdata),
    'error' => $error,
    'haserror' => !empty($error),
    'baseurl' => (new moodle_url('/local/rangeos/au_mappings.php'))->out(false),
]);

echo $OUTPUT->footer();
