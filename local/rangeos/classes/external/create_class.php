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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Create (prestage) a batch of scenario instances for a class.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_class extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'scenarioid' => new external_value(PARAM_RAW, 'Content scenario UUID'),
            'classid' => new external_value(PARAM_TEXT, 'Class identifier'),
            'count' => new external_value(PARAM_INT, 'Number of slots to prestage'),
            'enddate' => new external_value(PARAM_TEXT, 'End date (ISO 8601)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $envid, string $scenarioid, string $classid, int $count, string $enddate = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid,
            'scenarioid' => $scenarioid,
            'classid' => $classid,
            'count' => $count,
            'enddate' => $enddate,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:managecontent', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);
        $response = $client->create_class(
            $params['scenarioid'], $params['classid'], $params['count'], $params['enddate']
        );

        return [
            'success' => true,
            'classid' => $params['classid'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether creation succeeded'),
            'classid' => new external_value(PARAM_TEXT, 'The class ID that was created'),
        ]);
    }
}
