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
 * Search the environment's content scenario catalog for the mapping picker.
 *
 * The devops-api scenario list is neither searchable nor sortable, so the whole catalog is
 * read once through the shared cache and then filtered, sorted and paged here.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_scenarios extends external_api {
    /** Scenarios read from the API in one catalog request. Matches the library page's cache key. */
    private const CATALOG_LIMIT = 1000;

    /** Largest page the picker may ask for. */
    private const MAX_PAGE_SIZE = 100;

    /** Response keys that may carry the author, most preferred first. */
    private const AUTHOR_KEYS = ['author', 'authorName', 'createdBy', 'createdByName', 'creator', 'owner'];

    /** Response keys that may carry the creation date, most preferred first. */
    private const CREATED_KEYS = ['createdAt', 'created_at', 'dateCreated', 'createdDate', 'creationDate', 'created'];

    /** Response keys that may carry the last update date, most preferred first. */
    private const UPDATED_KEYS = [
        'updatedAt', 'updated_at', 'dateUpdated', 'updatedDate', 'modifiedAt', 'modifiedDate', 'lastModified',
    ];

    /**
     * Describe the call parameters.
     *
     * @return external_function_parameters Parameter definition.
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'search' => new external_value(PARAM_TEXT, 'Search term', VALUE_DEFAULT, ''),
            'classfilter' => new external_value(PARAM_TEXT, 'Unused, kept for compat', VALUE_DEFAULT, ''),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'pagesize' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Return one page of matching scenarios, most recently updated first.
     *
     * @param int $envid Environment ID.
     * @param string $search Case-insensitive substring matched against name, description and author.
     * @param string $classfilter Unused, kept for compatibility.
     * @param int $page Zero-based page, clamped to zero or above.
     * @param int $pagesize Results per page, clamped to the supported range.
     * @return array Page of scenarios, total matches, effective page, and page size.
     */
    public static function execute(int $envid, string $search, string $classfilter,
            int $page, int $pagesize): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid, 'search' => $search, 'classfilter' => $classfilter,
            'page' => $page, 'pagesize' => $pagesize,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:viewaumappings', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);
        $response = $client->get_cached_content_scenarios(['limit' => self::CATALOG_LIMIT]);
        $items = $response['data'] ?? $response['items'] ?? $response;

        $scenarios = [];
        foreach ($items as $item) {
            $scenario = self::build_scenario((array) $item);
            // Without a UUID a scenario cannot be mapped, so it is not offered.
            if ($scenario['id'] !== '') {
                $scenarios[] = $scenario;
            }
        }

        $scenarios = self::filter_by_search($scenarios, $params['search']);
        usort($scenarios, [self::class, 'compare_by_recency']);

        $pagesize = min(self::MAX_PAGE_SIZE, max(1, $params['pagesize']));
        $page = max(0, $params['page']);

        return [
            'scenarios' => array_slice($scenarios, $page * $pagesize, $pagesize),
            'total' => count($scenarios),
            'page' => $page,
            'pagesize' => $pagesize,
        ];
    }

    /**
     * Flatten one API scenario into the fields the picker displays.
     *
     * @param array $item Raw scenario from the API.
     * @return array Scenario with id, name, description, author, createdat and updatedat.
     */
    private static function build_scenario(array $item): array {
        $metadata = isset($item['metadata']) ? (array) $item['metadata'] : [];

        return [
            'id' => (string) ($item['uuid'] ?? $item['id'] ?? ''),
            'name' => (string) ($item['name'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'author' => self::read_name(self::read_value($item, $metadata, self::AUTHOR_KEYS)),
            'createdat' => (string) self::read_value($item, $metadata, self::CREATED_KEYS),
            'updatedat' => (string) self::read_value($item, $metadata, self::UPDATED_KEYS),
        ];
    }

    /**
     * Read the first of several alternative keys, falling back to the metadata object.
     *
     * The API's field names vary between deployments, so each value is looked up by a list
     * of known spellings rather than a single key.
     *
     * @param array $item Raw scenario.
     * @param array $metadata The scenario's metadata, if any.
     * @param array $keys Candidate keys, most preferred first.
     * @return mixed First value present, or an empty string.
     */
    private static function read_value(array $item, array $metadata, array $keys) {
        foreach ([$item, $metadata] as $source) {
            foreach ($keys as $key) {
                if (isset($source[$key]) && $source[$key] !== '') {
                    return $source[$key];
                }
            }
        }
        return '';
    }

    /**
     * Reduce a person-shaped value to a display name.
     *
     * @param mixed $value Name string, or a user object or array from the API.
     * @return string Display name.
     */
    private static function read_name($value): string {
        if (is_array($value) || is_object($value)) {
            $value = (array) $value;
            $value = $value['displayName'] ?? $value['name'] ?? $value['username'] ?? $value['email'] ?? '';
        }
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Keep the scenarios whose name, description or author contain the search term.
     *
     * @param array $scenarios Scenarios to filter.
     * @param string $search Search term; an empty term keeps everything.
     * @return array Matching scenarios.
     */
    private static function filter_by_search(array $scenarios, string $search): array {
        $search = \core_text::strtolower(trim($search));
        if ($search === '') {
            return $scenarios;
        }

        $matches = array_filter($scenarios, static function (array $scenario) use ($search): bool {
            $haystack = \core_text::strtolower(
                $scenario['name'] . ' ' . $scenario['description'] . ' ' . $scenario['author']
            );
            return str_contains($haystack, $search);
        });
        return array_values($matches);
    }

    /**
     * Order scenarios by most recently updated, then most recently created, then by name.
     *
     * @param array $left Scenario to compare.
     * @param array $right Scenario to compare against.
     * @return int Negative when $left sorts first, as usort() expects.
     */
    private static function compare_by_recency(array $left, array $right): int {
        $updated = self::to_timestamp($right['updatedat'] ?: $right['createdat'])
            <=> self::to_timestamp($left['updatedat'] ?: $left['createdat']);
        if ($updated !== 0) {
            return $updated;
        }

        $created = self::to_timestamp($right['createdat']) <=> self::to_timestamp($left['createdat']);
        return $created !== 0 ? $created : strcasecmp($left['name'], $right['name']);
    }

    /**
     * Read an API date as a Unix timestamp, accepting seconds, milliseconds or a date string.
     *
     * @param string $value API date value.
     * @return int Unix timestamp, or zero when unreadable.
     */
    private static function to_timestamp(string $value): int {
        if (is_numeric($value)) {
            $numeric = (int) $value;
            // Anything beyond the year 2286 in seconds is milliseconds.
            return $numeric > 9999999999 ? intdiv($numeric, 1000) : $numeric;
        }
        return strtotime($value) ?: 0;
    }

    /**
     * Describe the returned structure.
     *
     * @return external_single_structure Return definition.
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'scenarios' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_RAW, 'Scenario UUID'),
                    'name' => new external_value(PARAM_TEXT, 'Scenario name'),
                    'description' => new external_value(PARAM_RAW, 'Scenario description'),
                    'author' => new external_value(PARAM_TEXT, 'Scenario author'),
                    'createdat' => new external_value(PARAM_RAW, 'Creation date'),
                    'updatedat' => new external_value(PARAM_RAW, 'Last update date'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total matching scenarios'),
            'page' => new external_value(PARAM_INT, 'Zero-based result page'),
            'pagesize' => new external_value(PARAM_INT, 'Results per page'),
        ]);
    }
}
