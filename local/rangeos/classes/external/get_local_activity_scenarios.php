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

namespace local_rangeos\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Return local cmi5 activities that have AU mappings with scenarios.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_local_activity_scenarios extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
        ]);
    }

    public static function execute(int $envid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:viewaumappings', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);

        // Get all cmi5 activities with their course names.
        $sql = "SELECT c5.id, c5.name AS activityname, c5.course, c5.packageversionid,
                       co.fullname AS coursename
                  FROM {cmi5} c5
                  JOIN {course} co ON co.id = c5.course
                 ORDER BY co.fullname, c5.name";
        $activities = $DB->get_records_sql($sql);

        // Fetch all AU mappings from the API in one call.
        $response = $client->list_au_mappings(['page' => 0, 'pageSize' => 500]);
        $items = $response['items'] ?? $response['data'] ?? $response;

        // Index mappings by AU IRI for fast lookup.
        $mappingsByAuid = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $auid = $item['auId'] ?? $item['auid'] ?? '';
            if ($auid !== '') {
                $mappingsByAuid[$auid] = $item;
            }
        }

        // First pass: collect matching AUs and their scenario UUIDs.
        $candidates = [];
        $scenariouuids = [];
        foreach ($activities as $act) {
            $versionid = (int) ($act->packageversionid ?? 0);
            $aus = $DB->get_records('cmi5_aus', ['cmi5id' => $act->id], 'sortorder ASC');

            foreach ($aus as $au) {
                $auid = $au->auid ?? '';
                if (empty($auid) || !isset($mappingsByAuid[$auid])) {
                    continue;
                }

                // Check if class mode is enabled in the AU's config.json.
                if ($versionid > 0 && !empty($au->url)) {
                    $config = \local_rangeos\content_patcher::get_au_config($versionid, $au->url);
                    if ($config === null || empty($config['promptClassId'])) {
                        continue;
                    }
                } else {
                    continue;
                }

                $mapping = $mappingsByAuid[$auid];
                $scenarios = $mapping['scenarios'] ?? [];
                if (empty($scenarios)) {
                    continue;
                }

                foreach ($scenarios as $s) {
                    if (is_string($s)) {
                        $uuid = $s;
                    } else {
                        $s = (array) $s;
                        $uuid = $s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '';
                    }
                    if (empty($uuid)) {
                        continue;
                    }

                    $scenariouuids[$uuid] = true;
                    $candidates[] = [
                        'activityid' => (int) $act->id,
                        'activityname' => $act->activityname,
                        'coursename' => $act->coursename,
                        'autitle' => $au->title ?? '',
                        'auid' => $auid,
                        'scenariouuid' => $uuid,
                    ];
                }
            }
        }

        // Look up scenario names from content scenarios API.
        $scenarionames = [];
        if (!empty($scenariouuids)) {
            $page = 0;
            $pagesize = 100;
            $found = 0;
            $needed = count($scenariouuids);
            do {
                $resp = $client->list_content_scenarios([
                    'offset' => $page * $pagesize,
                    'limit' => $pagesize,
                ]);
                $data = $resp['data'] ?? $resp;
                foreach ($data as $cs) {
                    $cs = (array) $cs;
                    $csuuid = $cs['uuid'] ?? '';
                    if (isset($scenariouuids[$csuuid])) {
                        $scenarionames[$csuuid] = $cs['name'] ?? '';
                        $found++;
                    }
                }
                $page++;
                $total = $resp['totalCount'] ?? count($data);
            } while ($found < $needed && ($page * $pagesize) < $total);
        }

        // Build final results with scenario names.
        $results = [];
        foreach ($candidates as $c) {
            $c['scenarioname'] = $scenarionames[$c['scenariouuid']] ?? '';
            $results[] = $c;
        }

        return ['activities' => $results];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'activities' => new external_multiple_structure(
                new external_single_structure([
                    'activityid' => new external_value(PARAM_INT, 'cmi5 activity ID'),
                    'activityname' => new external_value(PARAM_TEXT, 'Activity name'),
                    'coursename' => new external_value(PARAM_TEXT, 'Course name'),
                    'autitle' => new external_value(PARAM_TEXT, 'AU title'),
                    'auid' => new external_value(PARAM_RAW, 'AU IRI'),
                    'scenariouuid' => new external_value(PARAM_RAW, 'Scenario UUID'),
                    'scenarioname' => new external_value(PARAM_TEXT, 'Scenario name'),
                ])
            ),
        ]);
    }
}
