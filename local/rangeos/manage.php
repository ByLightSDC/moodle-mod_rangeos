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
 * RangeOS management dashboard — hub page for all RangeOS admin areas.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();

// Require at least one RangeOS capability.
$canmanagecontent = has_capability('local/rangeos:managecontent', $context);
$canmanageenv = has_capability('local/rangeos:manageenvironments', $context);
$canmanageaus = has_capability('local/rangeos:manageaumappings', $context);

if (!$canmanagecontent && !$canmanageenv && !$canmanageaus) {
    throw new moodle_exception('nopermissions', 'error', '', 'access RangeOS management');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/rangeos/manage.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage_dashboard', 'local_rangeos'));
$PAGE->set_heading(get_string('manage_dashboard', 'local_rangeos'));

echo $OUTPUT->header();
echo \local_rangeos\output\dashboard::start('manage', 'dashboarddescription');
echo $OUTPUT->render_from_template('local_rangeos/dashboard_overview', [
    'cards' => \local_rangeos\output\dashboard::areas(),
]);
echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
