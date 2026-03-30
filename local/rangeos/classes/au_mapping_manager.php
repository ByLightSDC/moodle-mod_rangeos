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
