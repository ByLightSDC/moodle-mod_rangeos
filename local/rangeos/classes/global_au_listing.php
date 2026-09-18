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
 * Pagination for the environment-wide RangeOS AU mapping list.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/** Translate Moodle page numbers into the RangeOS offset/limit API. */
class global_au_listing {
    /** Supported sizes for the global mapping list. */
    public const PAGE_SIZES = [25, 50, 100];

    /**
     * Normalize a user-supplied page size.
     *
     * @param int $pagesize Requested size.
     * @return int Supported size.
     */
    public static function page_size(int $pagesize): int {
        return in_array($pagesize, self::PAGE_SIZES, true) ? $pagesize : self::PAGE_SIZES[0];
    }

    /**
     * Fetch one page of mappings.
     *
     * Uses the API's offset/limit paging, but falls back to slicing locally when the
     * server ignores those parameters and returns more records than requested.
     *
     * @param api_client $client Client for the selected environment.
     * @param int $page Zero-based requested page.
     * @param int $pagesize Requested page size.
     * @return array Mapping records, total count, effective page, and size.
     */
    public static function get_page(api_client $client, int $page, int $pagesize): array {
        $pagesize = self::page_size($pagesize);
        $page = max(0, min($page, intdiv(PHP_INT_MAX, $pagesize)));
        $params = ['offset' => $page * $pagesize, 'limit' => $pagesize, 'sortBy' => 'dateCreated', 'sort' => 'asc'];
        $response = $client->list_au_mappings($params);
        $items = self::records($response);
        if (count($items) > $pagesize) {
            // The server ignored offset/limit and sent the full list.
            return self::slice($items, $page, $pagesize);
        }
        $total = self::total($response, $params['offset'] + count($items));
        $lastpage = $total > 0 ? (int) floor(($total - 1) / $pagesize) : 0;
        if ($page > $lastpage) {
            $page = $lastpage;
            if ($total > 0) {
                $params['offset'] = $page * $pagesize;
                $response = $client->list_au_mappings($params);
                $items = self::records($response);
                if (count($items) > $pagesize) {
                    return self::slice($items, $page, $pagesize);
                }
                $total = self::total($response, $total);
            }
        }
        return [
            'mappings' => $total > 0 ? $items : [],
            'total' => $total,
            'page' => $page,
            'pagesize' => $pagesize,
        ];
    }

    /**
     * Extract the mapping records from an API response.
     *
     * @param array $response Decoded API response.
     * @return array Mapping records.
     */
    private static function records(array $response): array {
        if (array_is_list($response)) {
            return $response;
        }
        return array_values($response['data'] ?? $response['items'] ?? []);
    }

    /**
     * Read the total count from an API response.
     *
     * @param array $response Decoded API response.
     * @param int $fallback Count to use when the response has none.
     * @return int Total number of mappings.
     */
    private static function total(array $response, int $fallback): int {
        if (array_is_list($response)) {
            return $fallback;
        }
        return max(0, (int) ($response['totalCount'] ?? $response['total'] ?? $fallback));
    }

    /**
     * Paginate a complete mapping list locally.
     *
     * @param array $items Every mapping record.
     * @param int $page Zero-based requested page.
     * @param int $pagesize Normalized page size.
     * @return array Mapping records, total count, effective page, and size.
     */
    private static function slice(array $items, int $page, int $pagesize): array {
        $total = count($items);
        $page = min($page, (int) floor(($total - 1) / $pagesize));
        return [
            'mappings' => array_slice($items, $page * $pagesize, $pagesize),
            'total' => $total,
            'page' => $page,
            'pagesize' => $pagesize,
        ];
    }
}
