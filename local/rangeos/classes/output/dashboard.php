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
 * Shared presentation and capability-aware navigation for RangeOS management.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_rangeos\output;

defined('MOODLE_INTERNAL') || die();

/** Shared management page layout. */
class dashboard {
    /**
     * Get the management areas visible to the current user.
     *
     * @return array Template data shared by the overview cards and navigation.
     */
    public static function areas(): array {
        global $OUTPUT;

        $definitions = [
            'scenario_classes' => ['managecontent', 'manageclasses', 'manageclasses_desc', 'i/group'],
            'environment_profiles' => ['manageenvironments', 'manageenvironments', 'manageenvironments_desc', 'i/settings'],
            'library_au_mappings' => ['manageaumappings', 'library_aumappings', 'library_aumappings_desc', 'i/link'],
            'activity_environments' => ['manageenvironments', 'activityenvironments', 'activityenvironments_desc', 'i/course'],
            'au_mappings' => ['manageaumappings', 'aumappings_global', 'manageaumappings_desc', 'i/link'],
        ];
        $context = \context_system::instance();
        $areas = [];
        foreach ($definitions as $key => [$capability, $title, $description, $icon]) {
            if (!has_capability('local/rangeos:' . $capability, $context)) {
                continue;
            }
            $areas[] = [
                'key' => $key,
                'url' => (new \moodle_url('/local/rangeos/' . $key . '.php'))->out(false),
                'title' => get_string($title, 'local_rangeos'),
                'description' => get_string($description, 'local_rangeos'),
                'icon' => $OUTPUT->pix_icon($icon, ''),
            ];
        }
        return $areas;
    }

    /**
     * Open the scoped page wrapper and render the shared introduction/navigation.
     *
     * Call end() before the Moodle footer, including on form branches.
     *
     * @param string $active Page key (matching its filename without .php).
     * @param string $description Language string identifier for the introduction.
     * @return string HTML
     */
    public static function start(string $active, string $description): string {
        global $OUTPUT;

        $areas = self::areas();
        $navigation = [];
        if ($areas) {
            $navigation[] = [
                'url' => (new \moodle_url('/local/rangeos/manage.php'))->out(false),
                'title' => get_string('dashboardoverview', 'local_rangeos'),
                'active' => $active === 'manage',
            ];
        }
        foreach ($areas as $area) {
            $area['active'] = $area['key'] === $active;
            $navigation[] = $area;
        }
        return \html_writer::start_div('rangeos-dashboard') .
            $OUTPUT->render_from_template('local_rangeos/dashboard_header', [
                'description' => get_string($description, 'local_rangeos'),
                'hasnavigation' => !empty($navigation),
                'navigation' => $navigation,
            ]);
    }

    /**
     * Close the page wrapper opened by start().
     *
     * @return string HTML
     */
    public static function end(): string {
        return \html_writer::end_div();
    }
}
