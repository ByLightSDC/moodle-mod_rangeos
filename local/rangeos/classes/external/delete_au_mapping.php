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

class delete_au_mapping extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'auid' => new external_value(PARAM_RAW, 'AU IRI'),
        ]);
    }

    public static function execute(int $envid, string $auid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid, 'auid' => $auid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:manageaumappings', $context);

        $client = \local_rangeos\api_client::from_environment($params['envid']);
        $client->delete_au_mapping($params['auid']);

        return ['success' => true, 'auid' => $params['auid']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'auid' => new external_value(PARAM_RAW, 'AU IRI'),
        ]);
    }
}
