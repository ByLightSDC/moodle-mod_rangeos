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

function xmldb_local_rangeos_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026040800) {
        $table = new xmldb_table('local_rangeos_environments');

        // Add lightlogo column.
        $lightlogo = new xmldb_field('lightlogo', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'keycloakscope');
        if (!$dbman->field_exists($table, $lightlogo)) {
            $dbman->add_field($table, $lightlogo);
        }

        // Add darklogo column.
        $darklogo = new xmldb_field('darklogo', XMLDB_TYPE_CHAR, '1333', null, null, null, null, 'lightlogo');
        if (!$dbman->field_exists($table, $darklogo)) {
            $dbman->add_field($table, $darklogo);
        }

        // Drop old slidelogo column.
        $slidelogo = new xmldb_field('slidelogo');
        if ($dbman->field_exists($table, $slidelogo)) {
            $dbman->drop_field($table, $slidelogo);
        }

        // Re-sync all launch profiles to update the parameter JSON.
        $envids = $DB->get_fieldset_select('local_rangeos_environments', 'id', '');
        foreach ($envids as $envid) {
            \local_rangeos\environment_manager::sync_launch_profile((int) $envid);
        }

        upgrade_plugin_savepoint(true, 2026040800, 'local', 'rangeos');
    }

    return true;
}
