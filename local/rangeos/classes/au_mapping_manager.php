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
     * Walks every package's latest-version AUs, reads config.json in bulk per package,
     * and creates a mapping for any AU that has a rangeosScenarioName and is not yet mapped.
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
        $mappage = 0;
        do {
            $mapresponse = $client->list_au_mappings(['page' => $mappage, 'pageSize' => 500]);
            foreach ($mapresponse['data'] ?? $mapresponse['items'] ?? $mapresponse as $m) {
                $m = (array) $m;
                $auid = $m['auId'] ?? $m['auid'] ?? '';
                if ($auid) {
                    $mappedauids[$auid] = true;
                }
            }
            $mappage++;
        } while ($mappage < ($mapresponse['totalPages'] ?? 1));

        // Fetch all content scenarios for a name → UUID lookup.
        $scenariobynamelookup = [];
        $scpage = 0;
        do {
            $scresponse = $client->list_content_scenarios(['page' => $scpage, 'pageSize' => 100]);
            foreach ($scresponse['data'] ?? [] as $s) {
                $s = (array) $s;
                if (!empty($s['name']) && !empty($s['uuid'])) {
                    $scenariobynamelookup[$s['name']] = $s['uuid'];
                }
            }
            $scpage++;
        } while ($scpage < ($scresponse['totalPages'] ?? 1));

        // Build auid → primary course info lookup for results display.
        $aucourselookup = [];
        $courselookuprows = $DB->get_records_sql(
            "SELECT DISTINCT pa.auid, co.id AS courseid, co.fullname AS coursename
               FROM {cmi5_package_aus} pa
               JOIN {cmi5_packages} p ON p.latestversion = pa.versionid
               JOIN {cmi5_aus} ca ON ca.auid = pa.auid
               JOIN {cmi5} c5 ON c5.id = ca.cmi5id
               JOIN {course} co ON co.id = c5.course
              ORDER BY co.fullname ASC"
        );
        foreach ($courselookuprows as $clr) {
            if (!isset($aucourselookup[$clr->auid])) {
                $aucourselookup[$clr->auid] = ['name' => $clr->coursename, 'id' => (int) $clr->courseid];
            }
        }

        $packages = $DB->get_records('cmi5_packages', [], '', 'id, title, latestversion');
        $results = ['created' => [], 'failed' => [], 'skipped' => 0];
        $seenauids = [];

        foreach ($packages as $package) {
            if (empty($package->latestversion)) {
                continue;
            }
            $versionid = (int) $package->latestversion;
            $packageaus = $DB->get_records('cmi5_package_aus', ['versionid' => $versionid], 'sortorder ASC');

            // Bulk-fetch all config.json files for this package in one query.
            $allauconfigs = content_patcher::get_all_au_configs($versionid);

            foreach ($packageaus as $pau) {
                $auid = $pau->auid ?? '';
                if (!$auid || isset($seenauids[$auid]) || empty($pau->url)) {
                    continue;
                }
                $seenauids[$auid] = true;

                $config = $allauconfigs[content_patcher::au_url_to_filepath($pau->url)] ?? null;
                if ($config === null || empty($config['rangeosScenarioName'])) {
                    continue;
                }

                $scenarioname = $config['rangeosScenarioName'];
                $autitle = format_string($pau->title ?? $auid);
                $entrycourse = $aucourselookup[$auid] ?? ['name' => '', 'id' => 0];
                $entrypackagetitle = format_string($package->title);

                if (isset($mappedauids[$auid])) {
                    $results['skipped']++;
                    continue;
                }

                if (!isset($scenariobynamelookup[$scenarioname])) {
                    $results['failed'][] = [
                        'title'        => $autitle,
                        'auid'         => $auid,
                        'scenarioname' => $scenarioname,
                        'reason'       => 'Scenario not found in this environment',
                        'coursename'   => $entrycourse['name'],
                        'courseid'     => $entrycourse['id'],
                        'packagetitle' => $entrypackagetitle,
                    ];
                    continue;
                }

                try {
                    $client->create_au_mapping($auid, $pau->title ?? '', [$scenariobynamelookup[$scenarioname]]);
                    $results['created'][] = [
                        'title'        => $autitle,
                        'auid'         => $auid,
                        'scenarioname' => $scenarioname,
                        'coursename'   => $entrycourse['name'],
                        'courseid'     => $entrycourse['id'],
                        'packagetitle' => $entrypackagetitle,
                    ];
                    $mappedauids[$auid] = true;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'title'        => $autitle,
                        'auid'         => $auid,
                        'scenarioname' => $scenarioname,
                        'reason'       => $e->getMessage(),
                        'coursename'   => $entrycourse['name'],
                        'courseid'     => $entrycourse['id'],
                        'packagetitle' => $entrypackagetitle,
                    ];
                }
            }
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
