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
 * Per-activity AU-to-scenario mapping view.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;
use local_rangeos\au_mapping_manager;

$cmid = required_param('cmid', PARAM_INT);
$envid = optional_param('envid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('cmi5', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/rangeos:viewaumappings', context_system::instance());

$PAGE->set_context($context);
$PAGE->set_url('/local/rangeos/activity_au_mappings.php', ['cmid' => $cmid, 'envid' => $envid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('aumappings_activity', 'local_rangeos'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('aumappings_activity', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/au_mappings', 'init');

$cmi5 = $DB->get_record('cmi5', ['id' => $cm->instance], '*', MUST_EXIST);
$environments = environment_manager::list_environments();

// Auto-select environment from activity's current profileid.
if ($envid === 0 && !empty($cmi5->profileid)) {
    $env = environment_manager::get_environment_by_profile((int) $cmi5->profileid);
    if ($env) {
        $envid = $env->id;
    }
}
if ($envid === 0) {
    $default = environment_manager::get_default_environment();
    if ($default) {
        $envid = $default->id;
    }
}

// Fetch AU mapping status.
$aus = [];
$error = '';
if ($envid > 0) {
    try {
        $aus = au_mapping_manager::check_activity_au_mappings($cmi5->id, $envid);
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('aumappings_activity', 'local_rangeos') . ': ' . format_string($cmi5->name));

// Build template data.
$envoptions = [];
foreach ($environments as $env) {
    $envoptions[] = [
        'id' => $env->id,
        'name' => format_string($env->name),
        'selected' => ($env->id == $envid),
    ];
}

$canmanage = has_capability('local/rangeos:manageaumappings', context_system::instance());

$audata = [];
foreach ($aus as $au) {
    $scenarios = '';
    if (!empty($au['mapping']['scenarios'])) {
        $names = array_map(function($s) {
            return is_array($s) ? ($s['name'] ?? '') : (string) $s;
        }, $au['mapping']['scenarios']);
        $scenarios = implode(', ', $names);
    }

    $audata[] = [
        'auid' => $au['auid'],
        'title' => $au['title'],
        'has_mapping' => $au['has_mapping'],
        'scenarios_display' => $scenarios,
        'canmanage' => $canmanage,
    ];
}

$settingsurl = new moodle_url('/course/modedit.php', ['update' => $cmid]);

echo $OUTPUT->render_from_template('local_rangeos/activity_au_mappings', [
    'cmid' => $cmid,
    'environments' => $envoptions,
    'hasenvironments' => !empty($envoptions),
    'envid' => $envid,
    'aus' => $audata,
    'hasaus' => !empty($audata),
    'error' => $error,
    'haserror' => !empty($error),
    'canmanage' => $canmanage,
    'backurl' => $settingsurl->out(false),
    'baseurl' => (new moodle_url('/local/rangeos/activity_au_mappings.php', ['cmid' => $cmid]))->out(false),
]);

echo $OUTPUT->footer();
