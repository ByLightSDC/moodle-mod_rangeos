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
 * Admin page for managing RangeOS environment profiles.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;
use local_rangeos\form\environment_form;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:manageenvironments', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$id = optional_param('id', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));

$pagepath = '/local/rangeos/environment_profiles.php';
$baseurl = (new moodle_url($pagepath))->out(false);

$PAGE->set_context($context);
$PAGE->set_url($pagepath, ['action' => $action, 'id' => $id, 'search' => $search]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('environments', 'local_rangeos'));
$PAGE->set_heading(get_string('environments', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/confirm', 'init');

// Handle delete.
if ($action === 'delete' && $id && confirm_sesskey()) {
    environment_manager::delete_environment($id);
    redirect(new moodle_url('/local/rangeos/environment_profiles.php'),
        get_string('environmentdeleted', 'local_rangeos'));
}

// Handle test connection.
if ($action === 'test' && $id) {
    require_sesskey();
    try {
        $client = \local_rangeos\api_client::from_environment($id);
        $client->authenticate();
        $classes = $client->list_scenario_classes();
        $count = is_array($classes) ? count($classes) : 0;
        redirect(new moodle_url('/local/rangeos/environment_profiles.php'),
            get_string('testconnection_success', 'local_rangeos', $count), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Exception $e) {
        redirect(new moodle_url('/local/rangeos/environment_profiles.php'),
            get_string('testconnection_fail', 'local_rangeos', $e->getMessage()), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

// Handle add/edit form.
if ($action === 'edit' || $action === 'add') {
    $formdata = null;
    if ($action === 'edit' && $id) {
        $formdata = $DB->get_record('local_rangeos_environments', ['id' => $id], '*', MUST_EXIST);
    }

    $form = new environment_form(
        new moodle_url('/local/rangeos/environment_profiles.php', ['action' => $action, 'id' => $id])
    );

    if ($formdata) {
        $form->set_data($formdata);
    }

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/local/rangeos/environment_profiles.php'));
    }

    if ($data = $form->get_data()) {
        environment_manager::save_environment($data);
        redirect(new moodle_url('/local/rangeos/environment_profiles.php'),
            get_string('environmentsaved', 'local_rangeos'));
    }

    echo $OUTPUT->header();
    echo \local_rangeos\output\dashboard::start('environment_profiles', 'manageenvironments_desc');
    $heading = ($action === 'edit')
        ? get_string('editenvironment', 'local_rangeos')
        : get_string('addenvironment', 'local_rangeos');
    echo \local_rangeos\output\dashboard::heading($heading);
    echo html_writer::start_div('rangeos-form-panel');
    $form->display();
    echo html_writer::end_div();
    echo \local_rangeos\output\dashboard::end();
    echo $OUTPUT->footer();
    exit;
}

// Default: list environments.
$environments = environment_manager::list_environments();

// The search is applied here rather than in SQL: a site has a handful of environments, and
// list_environments() is what the rest of the plugin reads them through.
if ($search !== '') {
    $needle = core_text::strtolower($search);
    $environments = array_filter($environments, function($env) use ($needle) {
        return str_contains(core_text::strtolower($env->name), $needle)
            || str_contains(core_text::strtolower($env->apibaseurl), $needle);
    });
}

echo $OUTPUT->header();
echo \local_rangeos\output\dashboard::start('environment_profiles', 'manageenvironments_desc');

echo $OUTPUT->render_from_template('local_rangeos/environment_profiles', [
    'environments' => array_values(array_map(function($env) use ($pagepath) {
        return [
            'id' => $env->id,
            'name' => format_string($env->name),
            'apibaseurl' => $env->apibaseurl,
            'isdefault' => (bool) $env->isdefault,
            'hasprofile' => !empty($env->profileid),
            'editurl' => (new moodle_url($pagepath,
                ['action' => 'edit', 'id' => $env->id]))->out(false),
            'deleteurl' => (new moodle_url($pagepath,
                ['action' => 'delete', 'id' => $env->id, 'sesskey' => sesskey()]))->out(false),
            'testurl' => (new moodle_url($pagepath,
                ['action' => 'test', 'id' => $env->id, 'sesskey' => sesskey()]))->out(false),
        ];
    }, $environments)),
    'hasenvironments' => !empty($environments),
    'rowsummary' => get_string('environments_count', 'local_rangeos', count($environments)),
    'search' => $search,
    'hasfilters' => ($search !== ''),
    'addurl' => (new moodle_url($pagepath, ['action' => 'add']))->out(false),
    'baseurl' => $baseurl,
    // Nothing configured at all is a different case from a search that matched nothing:
    // one offers the page that fixes it, the other offers to clear the search.
    'emptyrows' => $search !== ''
        ? \local_rangeos\output\empty_state::build('noenvironmentsmatch', [
            'label' => 'clearfilters',
            'url' => new moodle_url($pagepath),
        ])
        : \local_rangeos\output\empty_state::build('noenvironments', [
            'label' => 'addenvironment',
            'url' => new moodle_url($pagepath, ['action' => 'add']),
        ]),
]);

echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
