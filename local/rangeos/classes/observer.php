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

namespace local_rangeos;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_rangeos.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle course_module_created for cmi5 activities.
     *
     * Assigns the default RangeOS environment profile to newly created cmi5
     * activities that don't already have a profile set.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function on_course_module_created(\core\event\course_module_created $event): void {
        global $DB;

        $data = $event->get_data();

        // Only act on cmi5 modules.
        if (($data['other']['modulename'] ?? '') !== 'cmi5') {
            return;
        }

        $cmid = $data['objectid'];
        $cm = get_coursemodule_from_id('cmi5', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $cmi5 = $DB->get_record('cmi5', ['id' => $cm->instance]);
        if (!$cmi5) {
            return;
        }

        $defaultprofileid = environment_manager::get_default_profile_id();
        if ($defaultprofileid && $cmi5->profileid != $defaultprofileid) {
            $DB->set_field('cmi5', 'profileid', $defaultprofileid, ['id' => $cmi5->id]);
        }
    }

    /**
     * Handle course_restored event.
     *
     * After a full course restore, assign the default RangeOS environment profile
     * to any cmi5 activities in the restored course that don't already have the
     * correct profile. Needed because course_module_created does not fire during restore.
     *
     * @param \core\event\course_restored $event The event.
     */
    public static function on_course_restored(\core\event\course_restored $event): void {
        global $DB;

        $defaultprofileid = environment_manager::get_default_profile_id();
        if (!$defaultprofileid) {
            return;
        }

        $courseid = $event->objectid;

        $moduleid = $DB->get_field('modules', 'id', ['name' => 'cmi5']);
        if (!$moduleid) {
            return;
        }

        $cmi5ids = $DB->get_fieldset_sql(
            "SELECT c.id FROM {cmi5} c
               JOIN {course_modules} cm ON cm.instance = c.id AND cm.module = :moduleid
              WHERE c.course = :courseid",
            ['moduleid' => $moduleid, 'courseid' => $courseid]
        );

        foreach ($cmi5ids as $cmi5id) {
            $DB->set_field_select('cmi5', 'profileid', $defaultprofileid,
                'id = :id AND (profileid IS NULL OR profileid != :profileid)',
                ['id' => $cmi5id, 'profileid' => $defaultprofileid]);
        }
    }

    /**
     * Handle the package_deployed event from local_rapidcmi5.
     *
     * 1. Assign the default RangeOS environment profile to the deployed activity.
     * 2. Check AU mappings and send admin notification for unmapped AUs.
     *
     * @param \core\event\base $event The event.
     */
    public static function on_package_deployed(\core\event\base $event): void {
        global $DB;

        $data = $event->get_data();
        $other = $data['other'] ?? [];
        $cmid = $other['cmid'] ?? 0;

        if (empty($cmid)) {
            return;
        }

        $cm = get_coursemodule_from_id('cmi5', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $cmi5 = $DB->get_record('cmi5', ['id' => $cm->instance]);
        if (!$cmi5) {
            return;
        }

        // 1. Assign default environment profile (only if activity doesn't already have one).
        if (empty($cmi5->profileid)) {
            $defaultprofileid = environment_manager::get_default_profile_id();
            if ($defaultprofileid) {
                $DB->set_field('cmi5', 'profileid', $defaultprofileid, ['id' => $cmi5->id]);
            }
        }

        // 2. Check AU mappings and notify admin of unmapped AUs.
        $defaultenv = environment_manager::get_default_environment();
        if (!$defaultenv) {
            return;
        }

        try {
            $unmapped = au_mapping_manager::get_unmapped_aus($cmi5->id, $defaultenv->id);
        } catch (\Exception $e) {
            debugging('local_rangeos: Failed to check AU mappings: ' . $e->getMessage());
            return;
        }

        if (empty($unmapped)) {
            return;
        }

        // Build notification.
        $course = get_course($cm->course);
        $aulist = "\n";
        foreach ($unmapped as $au) {
            $aulist .= "  - {$au['title']} ({$au['auid']})\n";
        }

        $a = new \stdClass();
        $a->activityname = format_string($cmi5->name);
        $a->coursename = format_string($course->fullname);
        $a->aulist = $aulist;

        // Send notification to all admins.
        $admins = get_admins();
        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'local_rangeos';
            $message->name = 'unmappedaus';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $admin;
            $message->subject = get_string('unmappedaus_subject', 'local_rangeos');
            $message->fullmessage = get_string('unmappedaus_body', 'local_rangeos', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = '';
            $message->smallmessage = get_string('unmappedaus_subject', 'local_rangeos');
            $message->notification = 1;

            try {
                message_send($message);
            } catch (\Exception $e) {
                debugging('local_rangeos: Failed to send notification: ' . $e->getMessage());
            }
        }
    }
}
