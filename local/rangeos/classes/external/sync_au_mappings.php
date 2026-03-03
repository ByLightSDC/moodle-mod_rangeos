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
use local_rangeos\au_mapping_manager;

class sync_au_mappings extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'cmi5id' => new external_value(PARAM_INT, 'cmi5 activity instance ID'),
        ]);
    }

    public static function execute(int $envid, int $cmi5id): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid, 'cmi5id' => $cmi5id,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:manageaumappings', $context);

        $aus = au_mapping_manager::check_activity_au_mappings($params['cmi5id'], $params['envid']);

        $result = [];
        foreach ($aus as $au) {
            $result[] = [
                'auid' => $au['auid'],
                'title' => $au['title'],
                'has_mapping' => $au['has_mapping'],
            ];
        }

        return ['aus' => $result];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'aus' => new external_multiple_structure(
                new external_single_structure([
                    'auid' => new external_value(PARAM_RAW, 'AU IRI'),
                    'title' => new external_value(PARAM_TEXT, 'AU title'),
                    'has_mapping' => new external_value(PARAM_BOOL, 'Whether AU has a mapping'),
                ])
            ),
        ]);
    }
}
