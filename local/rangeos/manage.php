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

// Build the list of management areas the user can access.
$items = [];

if ($canmanagecontent) {
    $items[] = [
        'url' => new moodle_url('/local/rangeos/scenario_classes.php'),
        'icon' => 'i/settings',
        'title' => get_string('manageclasses', 'local_rangeos'),
        'desc' => get_string('manageclasses_desc', 'local_rangeos'),
    ];
}

if ($canmanageenv) {
    $items[] = [
        'url' => new moodle_url('/local/rangeos/environment_profiles.php'),
        'icon' => 'i/settings',
        'title' => get_string('manageenvironments', 'local_rangeos'),
        'desc' => get_string('manageenvironments_desc', 'local_rangeos'),
    ];
}

if ($canmanageaus) {
    $items[] = [
        'url' => new moodle_url('/local/rangeos/library_au_mappings.php'),
        'icon' => 'i/settings',
        'title' => get_string('library_aumappings', 'local_rangeos'),
        'desc' => get_string('library_aumappings_desc', 'local_rangeos'),
    ];
}

// Render as a card grid.
echo html_writer::start_div('container-fluid mt-3');
echo html_writer::start_div('row');
foreach ($items as $item) {
    echo html_writer::start_div('col-sm-6 col-lg-4 col-xl-3 mb-3');
    echo html_writer::start_tag('a', [
        'href' => $item['url']->out(false),
        'class' => 'card h-100 text-decoration-none',
    ]);
    echo html_writer::start_div('card-body d-flex flex-column');
    echo html_writer::tag('h5', $item['title'], ['class' => 'card-title']);
    echo html_writer::tag('p', $item['desc'], ['class' => 'card-text text-muted small']);
    echo html_writer::end_div();
    echo html_writer::end_tag('a');
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
