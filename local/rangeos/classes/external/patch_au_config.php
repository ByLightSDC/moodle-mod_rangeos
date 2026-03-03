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
 * Patch an AU's config.json to toggle class mode.
 *
 * @package    local_rangeos
 * @copyright  2026 David Ropte
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class patch_au_config extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'versionid' => new external_value(PARAM_INT, 'Package version ID'),
            'auid' => new external_value(PARAM_RAW, 'AU IRI to identify the AU'),
            'classmode' => new external_value(PARAM_BOOL, 'Enable class mode (promptClassId)'),
            'defaultclassid' => new external_value(PARAM_TEXT, 'Default class ID string', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $versionid, string $auid, bool $classmode, string $defaultclassid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'versionid' => $versionid,
            'auid' => $auid,
            'classmode' => $classmode,
            'defaultclassid' => $defaultclassid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/rangeos:managecontent', $context);

        // Look up the AU to get its URL (which tells us the directory for config.json).
        $au = $DB->get_record('cmi5_package_aus', [
            'versionid' => $params['versionid'],
            'auid' => $params['auid'],
        ], '*', MUST_EXIST);

        $patches = [
            'promptClassId' => $params['classmode'],
        ];
        if ($params['defaultclassid'] !== '') {
            $patches['defaultClassId'] = $params['defaultclassid'];
        } else if (!$params['classmode']) {
            // When disabling class mode, clear the default class ID.
            $patches['defaultClassId'] = '';
        }

        \local_rangeos\content_patcher::patch_au_config(
            $params['versionid'],
            $au->url,
            $patches
        );

        return ['success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the patch was applied'),
        ]);
    }
}
