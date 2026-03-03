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

class get_au_mappings extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'pagesize' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    public static function execute(int $envid, int $page, int $pagesize): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid, 'page' => $page, 'pagesize' => $pagesize,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:viewaumappings', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);
        $response = $client->list_au_mappings([
            'page' => $params['page'],
            'pageSize' => $params['pagesize'],
        ]);

        // Normalize response format.
        $items = $response['items'] ?? $response['data'] ?? $response;
        $mappings = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $mappings[] = [
                'auid' => $item['auId'] ?? $item['auid'] ?? '',
                'name' => $item['name'] ?? '',
                'scenarios_json' => json_encode($item['scenarios'] ?? []),
            ];
        }

        return [
            'mappings' => $mappings,
            'total' => $response['total'] ?? count($mappings),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mappings' => new external_multiple_structure(
                new external_single_structure([
                    'auid' => new external_value(PARAM_RAW, 'AU IRI'),
                    'name' => new external_value(PARAM_TEXT, 'Mapping name'),
                    'scenarios_json' => new external_value(PARAM_RAW, 'Scenarios as JSON'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total count'),
        ]);
    }
}
