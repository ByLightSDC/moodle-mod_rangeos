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
 * Activity environment assignment page.
 *
 * Shows all cmi5 activities across all courses and lets admins change which
 * RangeOS environment each activity is pointing at from a single place.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:manageenvironments', $context);

$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url('/local/rangeos/activity_environments.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('activityenvironments', 'local_rangeos'));
$PAGE->set_heading(get_string('activityenvironments', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/activity_environments', 'init');

$environments = environment_manager::list_environments();

// Build a profileid → environment lookup.
$profileenvmap = []; // profileid => environment record
foreach ($environments as $env) {
    if (!empty($env->profileid)) {
        $profileenvmap[(int) $env->profileid] = $env;
    }
}

// Fetch all cmi5 activities with their course info.
$sql = "SELECT c.id AS cmi5id, c.name AS activityname, c.profileid,
               co.id AS courseid, co.fullname AS coursename,
               cm.id AS cmid
          FROM {cmi5} c
          JOIN {course} co ON co.id = c.course
          JOIN {course_modules} cm ON cm.instance = c.id
               AND cm.module = (SELECT id FROM {modules} WHERE name = 'cmi5')";

$params = [];
if ($courseid > 0) {
    $sql .= " WHERE c.course = :courseid";
    $params['courseid'] = $courseid;
}

$sql .= " ORDER BY co.fullname ASC, c.name ASC";

$records = $DB->get_records_sql($sql, $params);

// Build course filter options.
$allcourses = $DB->get_records_sql(
    "SELECT DISTINCT co.id, co.fullname
       FROM {cmi5} c
       JOIN {course} co ON co.id = c.course
      ORDER BY co.fullname ASC"
);
$courseoptions = [['id' => 0, 'name' => get_string('allcourses', 'local_rangeos'), 'selected' => ($courseid === 0)]];
foreach ($allcourses as $co) {
    $courseoptions[] = [
        'id' => $co->id,
        'name' => format_string($co->fullname),
        'selected' => ($co->id == $courseid),
    ];
}

// Build environment options for the per-row dropdowns (same list every row).
$envoptions = [['id' => 0, 'name' => get_string('none', 'local_rangeos')]];
foreach ($environments as $env) {
    $envoptions[] = [
        'id' => $env->id,
        'name' => format_string($env->name),
    ];
}

// Build activity rows.
$activities = [];
foreach ($records as $rec) {
    $currentenvid = 0;
    $currentenvname = get_string('none', 'local_rangeos');
    if (!empty($rec->profileid) && isset($profileenvmap[(int) $rec->profileid])) {
        $env = $profileenvmap[(int) $rec->profileid];
        $currentenvid = (int) $env->id;
        $currentenvname = format_string($env->name);
    }

    // Build per-row env options with selected state.
    $rowenvoptions = [];
    foreach ($envoptions as $opt) {
        $rowenvoptions[] = [
            'id' => $opt['id'],
            'name' => $opt['name'],
            'selected' => ($opt['id'] == $currentenvid),
        ];
    }

    $activities[] = [
        'cmi5id'       => (int) $rec->cmi5id,
        'cmid'         => (int) $rec->cmid,
        'activityname' => format_string($rec->activityname),
        'coursename'   => format_string($rec->coursename),
        'courseid'     => (int) $rec->courseid,
        'currentenvid' => $currentenvid,
        'currentenvname' => $currentenvname,
        'hasenvironment' => ($currentenvid > 0),
        'envoptions'   => $rowenvoptions,
        'activityurl'  => (new moodle_url('/mod/cmi5/view.php', ['id' => $rec->cmid]))->out(false),
        'settingsurl'  => (new moodle_url('/course/modedit.php', ['update' => $rec->cmid]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('activityenvironments', 'local_rangeos'));

echo $OUTPUT->render_from_template('local_rangeos/activity_environments', [
    'activities'       => $activities,
    'hasactivities'    => !empty($activities),
    'courseoptions'    => $courseoptions,
    'hascourses'       => count($allcourses) > 1,
    'hasenvironments'  => !empty($environments),
    'noenvironments'   => empty($environments),
    'baseurl'          => (new moodle_url('/local/rangeos/activity_environments.php'))->out(false),
    'courseid'         => $courseid,
]);

echo $OUTPUT->footer();
