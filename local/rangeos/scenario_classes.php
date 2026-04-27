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
 * Scenario classes management page.
 *
 * @package    local_rangeos
 * @copyright  2026 Bylight
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_rangeos\environment_manager;

require_login();
$context = context_system::instance();
require_capability('local/rangeos:managecontent', $context);

$envid = optional_param('envid', 0, PARAM_INT);

$PAGE->set_context($context);
$PAGE->set_url('/local/rangeos/scenario_classes.php', ['envid' => $envid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manageclasses', 'local_rangeos'));
$PAGE->set_heading(get_string('manageclasses', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/scenario_classes', 'init');

$environments = environment_manager::list_environments();

// Select default environment.
if ($envid === 0) {
    $default = environment_manager::get_default_environment();
    if ($default) {
        $envid = $default->id;
    } else if (!empty($environments)) {
        $envid = reset($environments)->id;
    }
}

// Fetch classes from devops-api.
$classes = [];
$error = '';
if ($envid > 0) {
    try {
        $client = \local_rangeos\api_client::from_environment($envid);
        $response = $client->list_scenario_classes();

        foreach ($response as $item) {
            if (is_string($item)) {
                $classes[] = ['name' => $item, 'rangeid' => ''];
            } else {
                $item = (array) $item;
                $classes[] = [
                    'name' => $item['name'] ?? $item['class'] ?? (string) $item,
                    'rangeid' => $item['rangeId'] ?? '',
                ];
            }
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/rangeos/manage.php'),
    get_string('backtomanagement', 'local_rangeos'),
    ['class' => 'btn btn-secondary mb-3']
);
echo $OUTPUT->heading(get_string('manageclasses', 'local_rangeos'));

// Build template data.
$envoptions = [];
foreach ($environments as $env) {
    $envoptions[] = [
        'id' => $env->id,
        'name' => format_string($env->name),
        'selected' => ($env->id == $envid),
    ];
}

$classdata = [];
foreach ($classes as $cls) {
    $classdata[] = [
        'name' => $cls['name'],
        'rangeid' => $cls['rangeid'],
    ];
}

$baseurl = (new moodle_url('/local/rangeos/scenario_classes.php'))->out(false);

echo $OUTPUT->render_from_template('local_rangeos/scenario_classes', [
    'environments' => $envoptions,
    'hasenvironments' => !empty($envoptions),
    'envid' => $envid,
    'classes' => $classdata,
    'hasclasses' => !empty($classdata),
    'error' => $error,
    'haserror' => !empty($error),
    'baseurl' => $baseurl,
]);

echo $OUTPUT->footer();
