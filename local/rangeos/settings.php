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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Top-level RangeOS category under Local plugins.
    $ADMIN->add('localplugins', new admin_category('local_rangeos',
        get_string('pluginname', 'local_rangeos')));

    // Environments management page.
    $ADMIN->add('local_rangeos', new admin_externalpage(
        'local_rangeos_environments',
        get_string('manageenvironments', 'local_rangeos'),
        new moodle_url('/local/rangeos/environment_profiles.php'),
        'local/rangeos:manageenvironments'
    ));

    // Scenario classes management — top-level for easy access.
    $ADMIN->add('local_rangeos', new admin_externalpage(
        'local_rangeos_scenario_classes',
        get_string('manageclasses', 'local_rangeos'),
        new moodle_url('/local/rangeos/scenario_classes.php'),
        'local/rangeos:managecontent'
    ));

    // Library AU mappings page.
    $ADMIN->add('local_rangeos', new admin_externalpage(
        'local_rangeos_library_aumappings',
        get_string('library_aumappings', 'local_rangeos'),
        new moodle_url('/local/rangeos/library_au_mappings.php'),
        'local/rangeos:manageaumappings'
    ));

    // Activity environment assignment page.
    $ADMIN->add('local_rangeos', new admin_externalpage(
        'local_rangeos_activity_environments',
        get_string('activityenvironments', 'local_rangeos'),
        new moodle_url('/local/rangeos/activity_environments.php'),
        'local/rangeos:manageenvironments'
    ));

}
