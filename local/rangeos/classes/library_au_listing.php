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
 * Local library selection and pagination for the AU mappings page.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/** Keeps remote mapping pagination separate from the local content library. */
class library_au_listing {
    /** Number of library AUs shown on each page. */
    public const PAGE_SIZE = 10;

    /**
     * Get one page of AUs from the latest version of each library package.
     *
     * Each AU IRI appears once, even when it is shared by multiple packages. The lowest
     * package AU record ID provides a deterministic representative for display purposes.
     * The same package scope is used for counting and selecting.
     *
     * @param int $packageid Package ID, or zero for all library packages.
     * @param int $page Zero-based requested page, clamped to the available range.
     * @return array Page records, total count, effective page, and page size.
     */
    public static function get_page(int $packageid, int $page): array {
        global $DB;

        $from = "FROM {cmi5_package_aus} pa
                  JOIN {cmi5_packages} p ON p.latestversion = pa.versionid";
        $where = $packageid !== 0 ? 'WHERE p.id = :packageid' : '';
        $params = $packageid !== 0 ? ['packageid' => $packageid] : [];

        $total = $DB->count_records_sql("SELECT COUNT(DISTINCT pa.auid) $from $where", $params);
        $lastpage = $total > 0 ? intdiv($total - 1, self::PAGE_SIZE) : 0;
        $page = max(0, min($page, $lastpage));

        // One record per AU IRI: the lowest ID keeps the representative deterministic.
        $uniqueaus = "SELECT MIN(pa.id) AS id $from $where GROUP BY pa.auid";
        $aus = $DB->get_records_sql(
            "SELECT pa.*, p.id AS packageid, p.title AS packagetitle
               $from
               JOIN ($uniqueaus) uniqueau ON uniqueau.id = pa.id
           ORDER BY p.title, p.id, pa.sortorder, pa.id",
            $params,
            $page * self::PAGE_SIZE,
            self::PAGE_SIZE
        );

        return ['aus' => $aus, 'total' => $total, 'page' => $page, 'pagesize' => self::PAGE_SIZE];
    }
}
