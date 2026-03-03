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

class apply_environment_profile extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'envid' => new external_value(PARAM_INT, 'Environment ID'),
            'cmi5ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'cmi5 activity instance ID'),
                'Activity instance IDs to apply profile to'
            ),
        ]);
    }

    public static function execute(int $envid, array $cmi5ids): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'envid' => $envid, 'cmi5ids' => $cmi5ids,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:manageenvironments', $context);

        $env = $DB->get_record('local_rangeos_environments', ['id' => $params['envid']], '*', MUST_EXIST);
        if (empty($env->profileid)) {
            throw new \moodle_exception('error:environmentnotfound', 'local_rangeos');
        }

        $updated = 0;
        foreach ($params['cmi5ids'] as $cmi5id) {
            if ($DB->record_exists('cmi5', ['id' => $cmi5id])) {
                $DB->set_field('cmi5', 'profileid', $env->profileid, ['id' => $cmi5id]);
                $updated++;
            }
        }

        return ['updated' => $updated];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'updated' => new external_value(PARAM_INT, 'Number of activities updated'),
        ]);
    }
}
