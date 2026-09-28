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
echo \local_rangeos\output\dashboard::start('activity_au_mappings', 'manageaumappings_desc');
echo \local_rangeos\output\dashboard::heading(
    get_string('aumappings_activity', 'local_rangeos') . ': ' . format_string($cmi5->name));

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

// Rows for local_rangeos/au_row, the component the library listing uses.
//
// The mapping API stores scenarios as bare UUIDs, sometimes alongside a name. Whatever
// name it carries is used, falling back to the UUID; the mapping dialog replaces those
// with catalog names as soon as a search runs, so no extra API call is made here.
$audata = [];
foreach ($aus as $au) {
    $scenariobadges = [];
    $scenariooptions = [];
    foreach ($au['mapping']['scenarios'] ?? [] as $s) {
        if (is_array($s)) {
            $uuid = $s['uuid'] ?? $s['scenarioId'] ?? $s['id'] ?? '';
            $name = $s['name'] ?? '';
        } else {
            $uuid = (string) $s;
            $name = '';
        }
        $scenariobadges[] = $name ?: $uuid;
        if ($uuid !== '') {
            $scenariooptions[] = ['id' => $uuid, 'name' => $name ?: $uuid];
        }
    }

    // Stable ids so the row's toggle can point at its own detail region. The AU IRI
    // itself can't be one: it carries slashes and colons.
    $rowindex = count($audata);

    $audata[] = [
        'rowid' => 'rangeos-au-' . $rowindex,
        'detailid' => 'rangeos-au-detail-' . $rowindex,
        'auid' => $au['auid'],
        'title' => $au['title'],
        // Every AU in a cmi5 activity is a mapping candidate; the library has to read
        // config.json to tell, which this page does not do.
        'canmap' => true,
        'ismapped' => $au['has_mapping'],
        'scenario_badges' => $scenariobadges,
        'scenario_count' => count($scenariobadges),
        'scenarios_json' => json_encode($scenariooptions),
        'mapping_name' => $au['mapping']['name'] ?? $au['title'],
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
    'rowsummary' => get_string('aumapping_count', 'local_rangeos', count($audata)),
    'error' => $error,
    'haserror' => !empty($error),
    'canmanage' => $canmanage,
    // The row's optional parts. This view is already inside one activity, and it reads no
    // package or config.json, so the package, activity and class-mode detail stay off; the
    // status pill is on, because a flat Status column was what this page used to show.
    'hasstatus' => true,
    'emptyenvironments' => \local_rangeos\output\empty_state::no_environments(),
    'emptyrows' => \local_rangeos\output\empty_state::build('noaus'),
    'backurl' => $settingsurl->out(false),
    'baseurl' => (new moodle_url('/local/rangeos/activity_au_mappings.php', ['cmid' => $cmid]))->out(false),
]);

echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
