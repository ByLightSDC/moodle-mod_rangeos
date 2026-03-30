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
 * Library functions for local_rangeos.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject a "RangeOS Environment" dropdown into the cmi5 activity form.
 *
 * @param \moodleform_mod $formwrapper The form wrapper.
 * @param \MoodleQuickForm $mform The form object.
 */
function local_rangeos_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    if ($formwrapper->get_current()->modulename !== 'cmi5') {
        return;
    }

    $mform->addElement('header', 'rangeoshdr',
        get_string('rangeos_integration', 'local_rangeos'));

    // Environment selector.
    $environments = \local_rangeos\environment_manager::list_environments();
    $options = [0 => get_string('none', 'local_rangeos')];
    foreach ($environments as $env) {
        $label = format_string($env->name);
        if ($env->isdefault) {
            $label .= ' (' . get_string('default') . ')';
        }
        $options[$env->id] = $label;
    }
    $mform->addElement('select', 'rangeos_environment',
        get_string('environment', 'local_rangeos'), $options);
    $mform->addHelpButton('rangeos_environment', 'environment', 'local_rangeos');

    // Pre-select the environment matching the activity's current profileid.
    $current = $formwrapper->get_current();
    if (!empty($current->instance)) {
        $cmi5 = $DB->get_record('cmi5', ['id' => $current->instance]);
        if ($cmi5 && !empty($cmi5->profileid)) {
            $env = \local_rangeos\environment_manager::get_environment_by_profile((int) $cmi5->profileid);
            if ($env) {
                $mform->setDefault('rangeos_environment', $env->id);
            }
        }
    }

    // Link to per-activity AU mapping page (only when editing existing instance).
    if (!empty($current->instance)) {
        $cmid = $current->coursemodule;
        $url = new moodle_url('/local/rangeos/activity_au_mappings.php', ['cmid' => $cmid]);
        $mform->addElement('static', 'rangeos_aumappings_link',
            get_string('aumappings', 'local_rangeos'),
            html_writer::link($url, get_string('manage_au_mappings', 'local_rangeos')));
    }
}

/**
 * Handle RangeOS environment selection on cmi5 activity save.
 *
 * @param \stdClass $data The form data.
 * @param \stdClass $course The course record.
 * @return \stdClass Modified form data.
 */
function local_rangeos_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if ($data->modulename !== 'cmi5') {
        return $data;
    }

    $envid = $data->rangeos_environment ?? 0;
    if ($envid > 0) {
        $env = $DB->get_record('local_rangeos_environments', ['id' => $envid]);
        if ($env && $env->profileid) {
            $DB->set_field('cmi5', 'profileid', $env->profileid, ['id' => $data->instance]);
        }
    }

    return $data;
}

/**
 * Provide a "RangeOS" item for the Moodle Workplace app drawer (quick access grid).
 *
 * The Workplace theme calls get_plugins_with_function('theme_workplace_menu_items')
 * and renders returned items as icons in the grid popup triggered by the launcher (:::) icon.
 *
 * @return array Array of menu item arrays with 'url', 'name', and 'imageurl' keys.
 */
function local_rangeos_theme_workplace_menu_items(): array {
    global $OUTPUT;

    $syscontext = context_system::instance();

    $canmanagecontent = has_capability('local/rangeos:managecontent', $syscontext);
    $canmanageenv = has_capability('local/rangeos:manageenvironments', $syscontext);
    $canmanageaus = has_capability('local/rangeos:manageaumappings', $syscontext);

    if (!$canmanagecontent && !$canmanageenv && !$canmanageaus) {
        return [];
    }

    return [[
        'url' => new moodle_url('/local/rangeos/manage.php'),
        'name' => get_string('pluginname', 'local_rangeos'),
        'imageurl' => $OUTPUT->image_url('icon', 'local_rangeos')->out(false),
    ]];
}

/**
 * Extend navigation for cmi5 activity context.
 *
 * @param navigation_node $navigation The navigation node.
 * @param stdClass $course The course.
 * @param stdClass $module The module.
 * @param cm_info $cm The course module info.
 */
function local_rangeos_extend_navigation_module($navigation, $course, $module, $cm) {
    if ($cm->modname !== 'cmi5') {
        return;
    }

    $context = context_module::instance($cm->id);
    if (!has_capability('local/rangeos:viewaumappings', context_system::instance())) {
        return;
    }

    $url = new moodle_url('/local/rangeos/activity_au_mappings.php', ['cmid' => $cm->id]);
    $navigation->add(
        get_string('aumappings', 'local_rangeos'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'rangeos_aumappings'
    );
}
