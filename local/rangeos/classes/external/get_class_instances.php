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
 * Get scenario instances belonging to a class.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_class_instances extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'classid' => new external_value(PARAM_TEXT, 'Class identifier'),
        ]);
    }

    public static function execute(int $envid, string $classid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid,
            'classid' => $classid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:viewaumappings', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);
        $response = $client->get_class_instances($params['classid']);

        global $DB;

        $items = $response['data'] ?? $response['items'] ?? $response;

        // Collect all studentUsername values to resolve to display names in one query.
        $studentusernames = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $su = $item['studentUsername'] ?? '';
            if ($su !== '') {
                $studentusernames[$su] = true;
            }
        }

        // Resolve Keycloak UUIDs / user IDs to Moodle display names.
        // studentUsername may be a Keycloak UUID (which is the Moodle username for OIDC users)
        // or a numeric Moodle user ID (from M2M deploys).
        $displaynames = [];
        $emails = [];
        if (!empty($studentusernames)) {
            $usernames = array_keys($studentusernames);
            // Try matching by username (covers Keycloak UUIDs used as Moodle usernames).
            list($insql, $inparams) = $DB->get_in_or_equal($usernames, SQL_PARAMS_NAMED);
            $users = $DB->get_records_select('user', "username {$insql}", $inparams,
                '', 'id, username, firstname, lastname, email');
            foreach ($users as $u) {
                $displaynames[$u->username] = fullname($u);
                $emails[$u->username] = $u->email;
            }
            // For any still unresolved, try matching by numeric user ID.
            foreach ($usernames as $uname) {
                if (!isset($displaynames[$uname]) && is_numeric($uname)) {
                    $u = $DB->get_record('user', ['id' => (int) $uname], 'id, username, firstname, lastname, email');
                    if ($u) {
                        $displaynames[$uname] = fullname($u);
                        $emails[$uname] = $u->email;
                    }
                }
            }
        }

        $instances = [];
        foreach ($items as $item) {
            $item = (array) $item;
            $assigned = !empty($item['assigned']);
            $su = $item['studentUsername'] ?? '';
            $instances[] = [
                'id' => (string) ($item['uuid'] ?? $item['id'] ?? ''),
                'scenarioname' => $item['name'] ?? $item['scenarioName'] ?? '',
                'status' => $item['status'] ?? 'unknown',
                'assigned' => $assigned,
                'studentid' => (string) ($item['studentId'] ?? ''),
                'username' => $su,
                'displayname' => $displaynames[$su] ?? '',
                'email' => $emails[$su] ?? '',
                'deployedby' => $item['deployedBy'] ?? '',
                'scenarioid' => $item['scenarioId'] ?? '',
            ];
        }

        return [
            'instances' => $instances,
            'total' => $response['totalCount'] ?? $response['total'] ?? count($instances),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'instances' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_RAW, 'Scenario instance UUID'),
                    'scenarioname' => new external_value(PARAM_TEXT, 'Scenario name'),
                    'status' => new external_value(PARAM_TEXT, 'Instance status'),
                    'assigned' => new external_value(PARAM_BOOL, 'Whether assigned to a student'),
                    'studentid' => new external_value(PARAM_RAW, 'Student/seat number'),
                    'username' => new external_value(PARAM_RAW, 'Student username (Keycloak UUID)'),
                    'displayname' => new external_value(PARAM_TEXT, 'Student display name'),
                    'email' => new external_value(PARAM_RAW, 'Student email'),
                    'deployedby' => new external_value(PARAM_RAW, 'Deployed by user ID'),
                    'scenarioid' => new external_value(PARAM_RAW, 'Source scenario UUID'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total instance count'),
        ]);
    }
}
