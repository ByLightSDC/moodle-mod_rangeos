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

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/**
 * Checks AU mapping status against the RangeOS devops-api.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class au_mapping_manager {

    /**
     * Check AU mappings for a content library package version.
     *
     * @param int $versionid Package version ID (cmi5_package_versions.id).
     * @param int $envid Environment ID.
     * @return array List of AU objects with has_mapping and mapping data.
     */
    public static function check_package_au_mappings(int $versionid, int $envid): array {
        global $DB;

        $aus = $DB->get_records('cmi5_package_aus', ['versionid' => $versionid], 'sortorder ASC');
        return self::enrich_aus_with_mappings($aus, $envid);
    }

    /**
     * Check AU mappings for a cmi5 activity instance.
     *
     * @param int $cmi5id cmi5 activity instance ID.
     * @param int $envid Environment ID.
     * @return array List of AU objects with has_mapping and mapping data.
     */
    public static function check_activity_au_mappings(int $cmi5id, int $envid): array {
        global $DB;

        $aus = $DB->get_records('cmi5_aus', ['cmi5id' => $cmi5id], 'sortorder ASC');
        return self::enrich_aus_with_mappings($aus, $envid);
    }

    /**
     * Enrich AU records with mapping data from devops-api.
     *
     * @param array $aus AU records from DB.
     * @param int $envid Environment ID.
     * @return array Enriched AU list.
     */
    private static function enrich_aus_with_mappings(array $aus, int $envid): array {
        if (empty($aus)) {
            return [];
        }

        $client = api_client::from_environment($envid);

        $result = [];
        foreach ($aus as $au) {
            $auid = $au->auid ?? $au->au_iri ?? '';
            if (empty($auid)) {
                continue;
            }

            $entry = [
                'id' => $au->id,
                'auid' => $auid,
                'title' => $au->title ?? '',
                'has_mapping' => false,
                'mapping' => null,
            ];

            try {
                $mapping = $client->get_au_mapping($auid);
                if ($mapping !== null) {
                    $entry['has_mapping'] = true;
                    $entry['mapping'] = $mapping;
                }
            } catch (\Exception $e) {
                $entry['error'] = $e->getMessage();
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Map all library AUs that declare a default RangeOS scenario to their matching scenario.
     *
     * Walks every package's latest-version AUs, reading all their config.json files in one
     * bulk query, and creates a mapping for any AU that has a rangeosScenarioName and is not
     * yet mapped.
     *
     * Returns a results array ready for template rendering, with keys:
     *   created, failed, skipped, hascreated, hasfailed, createdcount, failedcount,
     *   created_by_course, failed_by_course.
     *
     * @param int $envid Environment ID.
     * @return array Results array.
     */
    public static function map_all_defaults(int $envid): array {
        global $DB;

        $client = api_client::from_environment($envid);

        // Fetch all existing AU mappings to know which are already mapped.
        $mappedauids = [];
        foreach ($client->list_all_au_mappings() as $m) {
            $m = (array) $m;
            $auid = $m['auId'] ?? $m['auid'] ?? '';
            if ($auid) {
                $mappedauids[$auid] = true;
            }
        }

        // Fetch all content scenarios for a name → UUID lookup. The cached read is the same
        // one the listing page makes, so a run right after a page load costs no round trip.
        $scenariobynamelookup = [];
        $scenarioresponse = $client->get_cached_content_scenarios(['limit' => 1000]);
        foreach ($scenarioresponse['data'] ?? [] as $s) {
            $s = (array) $s;
            if (!empty($s['name']) && !empty($s['uuid'])) {
                $scenariobynamelookup[$s['name']] = $s['uuid'];
            }
        }

        // Every latest-version AU in one query, ordered so the representative package for a
        // shared AU IRI is deterministic.
        $packageaus = $DB->get_records_sql(
            "SELECT pa.id, pa.auid, pa.title, pa.url, pa.versionid, p.title AS packagetitle
               FROM {cmi5_package_aus} pa
               JOIN {cmi5_packages} p ON p.latestversion = pa.versionid
           ORDER BY p.title, p.id, pa.sortorder, pa.id"
        );

        // Bulk-fetch the config.json of every version involved, in a single file-storage query.
        $allconfigs = content_patcher::get_au_configs_for_versions(array_column($packageaus, 'versionid'));

        $results = ['created' => [], 'failed' => [], 'skipped' => 0];
        $seenauids = [];

        foreach ($packageaus as $pau) {
            $auid = $pau->auid ?? '';
            if (!$auid || isset($seenauids[$auid]) || empty($pau->url)) {
                continue;
            }
            $seenauids[$auid] = true;

            $config = $allconfigs[(int) $pau->versionid][content_patcher::au_url_to_filepath($pau->url)] ?? null;
            if ($config === null || empty($config['rangeosScenarioName'])) {
                continue;
            }

            if (isset($mappedauids[$auid])) {
                $results['skipped']++;
                continue;
            }

            $scenarioname = $config['rangeosScenarioName'];
            $entry = [
                'title'        => format_string($pau->title ?? $auid),
                'auid'         => $auid,
                'scenarioname' => $scenarioname,
                'packagetitle' => format_string($pau->packagetitle),
            ];

            if (!isset($scenariobynamelookup[$scenarioname])) {
                $entry['reason'] = 'Scenario not found in this environment';
                $results['failed'][] = $entry;
                continue;
            }

            try {
                $client->create_au_mapping($auid, $pau->title ?? '', [$scenariobynamelookup[$scenarioname]]);
                $results['created'][] = $entry;
                $mappedauids[$auid] = true;
            } catch (\Exception $e) {
                $entry['reason'] = $e->getMessage();
                $results['failed'][] = $entry;
            }
        }

        // Course names are only needed for the AUs we are about to report on, so the lookup
        // runs last and is keyed to that (usually small) set rather than the whole library.
        $courselookup = self::get_au_course_lookup(array_merge(
            array_column($results['created'], 'auid'),
            array_column($results['failed'], 'auid')
        ));
        foreach (['created', 'failed'] as $type) {
            foreach ($results[$type] as &$entry) {
                $course = $courselookup[$entry['auid']] ?? ['name' => '', 'id' => 0];
                $entry['coursename'] = $course['name'];
                $entry['courseid'] = $course['id'];
            }
            unset($entry);
        }

        $results['hascreated']   = !empty($results['created']);
        $results['hasfailed']    = !empty($results['failed']);
        $results['createdcount'] = \count($results['created']);
        $results['failedcount']  = \count($results['failed']);

        foreach (['created', 'failed'] as $type) {
            $grouped = [];
            $groupindex = [];
            foreach ($results[$type] as $entry) {
                $cn  = $entry['coursename'];
                $key = $cn !== '' ? $cn : '__none__';
                if (!isset($groupindex[$key])) {
                    $groupindex[$key] = \count($grouped);
                    $grouped[] = [
                        'coursename' => $cn !== '' ? $cn : 'No local course',
                        'courseid'   => $entry['courseid'] ?? 0,
                        'hascourse'  => ($cn !== ''),
                        'items'      => [],
                        'itemcount'  => 0,
                    ];
                }
                $idx = $groupindex[$key];
                $grouped[$idx]['items'][] = $entry;
                $grouped[$idx]['itemcount']++;
            }
            $results["{$type}_by_course"] = $grouped;
        }

        return $results;
    }

    /**
     * Resolve the primary local course for each of the given AU IRIs.
     *
     * An AU IRI can appear in activities across several courses; the alphabetically first
     * course wins, which keeps the results grouping stable.
     *
     * @param string[] $auids AU IRIs to look up.
     * @return array<string, array{name: string, id: int}> Course info keyed by AU IRI.
     */
    private static function get_au_course_lookup(array $auids): array {
        global $DB;

        $auids = array_values(array_unique(array_filter($auids)));
        if (empty($auids)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($auids, SQL_PARAMS_NAMED);
        $rows = $DB->get_recordset_sql(
            "SELECT ca.id, ca.auid, co.id AS courseid, co.fullname AS coursename
               FROM {cmi5_aus} ca
               JOIN {cmi5} c5 ON c5.id = ca.cmi5id
               JOIN {course} co ON co.id = c5.course
              WHERE ca.auid {$insql}
           ORDER BY co.fullname ASC, co.id ASC",
            $params
        );

        $lookup = [];
        foreach ($rows as $row) {
            if (!isset($lookup[$row->auid])) {
                $lookup[$row->auid] = ['name' => $row->coursename, 'id' => (int) $row->courseid];
            }
        }
        $rows->close();

        return $lookup;
    }

    /**
     * Get a summary of unmapped AUs for an activity.
     *
     * @param int $cmi5id cmi5 activity instance ID.
     * @param int $envid Environment ID.
     * @return array List of unmapped AU titles/IRIs.
     */
    public static function get_unmapped_aus(int $cmi5id, int $envid): array {
        $aus = self::check_activity_au_mappings($cmi5id, $envid);
        $unmapped = [];
        foreach ($aus as $au) {
            if (!$au['has_mapping']) {
                $unmapped[] = $au;
            }
        }
        return $unmapped;
    }
}
