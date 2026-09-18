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
    /**
     * Get one page of AUs from the latest version of each library package.
     *
     * Rows represent package AUs: an IRI shared by two packages remains visible in both.
     * The same query scope is used for counting and selecting, with a stable sort order.
     *
     * @param int $packageid Package ID, or zero for all library packages.
     * @param int $page Zero-based requested page, clamped to the available range.
     * @param int $pagesize Requested size; unsupported values use the default of 20.
     * @return array Page records, total count, effective page, and page size.
     */
    public static function get_page(int $packageid, int $page, int $pagesize): array {
        global $DB;

        $pagesize = in_array($pagesize, [20, 50, 100], true) ? $pagesize : 20;
        $from = "FROM {cmi5_package_aus} pa
                  JOIN {cmi5_packages} p ON p.latestversion = pa.versionid";
        $where = $packageid !== 0 ? 'WHERE p.id = :packageid' : '';
        $params = $packageid !== 0 ? ['packageid' => $packageid] : [];
        $total = $DB->count_records_sql("SELECT COUNT(1) $from $where", $params);
        $lastpage = $total > 0 ? (int) floor(($total - 1) / $pagesize) : 0;
        $page = max(0, min($page, $lastpage));
        $aus = $DB->get_records_sql(
            "SELECT pa.*, p.id AS packageid, p.title AS packagetitle
               $from $where
           ORDER BY p.title, p.id, pa.sortorder, pa.id",
            $params,
            $page * $pagesize,
            $pagesize
        );
        return ['aus' => $aus, 'total' => $total, 'page' => $page, 'pagesize' => $pagesize];
    }
}
