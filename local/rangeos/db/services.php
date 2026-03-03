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

$functions = [
    'local_rangeos_get_au_mappings' => [
        'classname' => 'local_rangeos\external\get_au_mappings',
        'description' => 'List AU-to-scenario mappings from devops-api',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/rangeos:viewaumappings',
    ],
    'local_rangeos_create_au_mapping' => [
        'classname' => 'local_rangeos\external\create_au_mapping',
        'description' => 'Create an AU-to-scenario mapping via devops-api',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:manageaumappings',
    ],
    'local_rangeos_update_au_mapping' => [
        'classname' => 'local_rangeos\external\update_au_mapping',
        'description' => 'Update an AU-to-scenario mapping via devops-api',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:manageaumappings',
    ],
    'local_rangeos_delete_au_mapping' => [
        'classname' => 'local_rangeos\external\delete_au_mapping',
        'description' => 'Delete an AU-to-scenario mapping via devops-api',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:manageaumappings',
    ],
    'local_rangeos_sync_au_mappings' => [
        'classname' => 'local_rangeos\external\sync_au_mappings',
        'description' => 'Check package AUs against devops-api mapping status',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:manageaumappings',
    ],
    'local_rangeos_list_scenarios' => [
        'classname' => 'local_rangeos\external\list_scenarios',
        'description' => 'List available scenarios from devops-api',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/rangeos:viewaumappings',
    ],
    'local_rangeos_list_scenario_classes' => [
        'classname' => 'local_rangeos\external\list_scenario_classes',
        'description' => 'List scenario classes from devops-api',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/rangeos:viewaumappings',
    ],
    'local_rangeos_apply_environment_profile' => [
        'classname' => 'local_rangeos\external\apply_environment_profile',
        'description' => 'Apply a RangeOS environment profile to cmi5 activities',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:manageenvironments',
    ],
    'local_rangeos_patch_au_config' => [
        'classname' => 'local_rangeos\external\patch_au_config',
        'description' => 'Patch an AU config.json to toggle class mode',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:managecontent',
    ],
    'local_rangeos_get_class_instances' => [
        'classname' => 'local_rangeos\external\get_class_instances',
        'description' => 'Get scenario instances for a class',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/rangeos:viewaumappings',
    ],
    'local_rangeos_create_class' => [
        'classname' => 'local_rangeos\external\create_class',
        'description' => 'Create (prestage) a batch of scenario instances for a class',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:managecontent',
    ],
    'local_rangeos_get_local_activity_scenarios' => [
        'classname' => 'local_rangeos\external\get_local_activity_scenarios',
        'description' => 'Get local cmi5 activities with mapped scenarios',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/rangeos:viewaumappings',
    ],
    'local_rangeos_delete_scenario_instance' => [
        'classname' => 'local_rangeos\external\delete_scenario_instance',
        'description' => 'Delete a single scenario instance via devops-api',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/rangeos:managecontent',
    ],
];

$services = [
    'RangeOS Integration' => [
        'functions' => array_keys($functions),
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_rangeos',
    ],
];
