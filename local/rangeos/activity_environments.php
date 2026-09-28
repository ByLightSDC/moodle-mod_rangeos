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

$envfilter = optional_param('envfilter', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_TEXT));
$currentpage = optional_param('page', 0, PARAM_INT);
$pagesize = 25;

$pagepath = '/local/rangeos/activity_environments.php';
$filterparams = ['envfilter' => $envfilter, 'search' => $search];
$baseurl = (new moodle_url($pagepath))->out(false);

$PAGE->set_context($context);
$PAGE->set_url($pagepath, $filterparams);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('activityenvironments', 'local_rangeos'));
$PAGE->set_heading(get_string('activityenvironments', 'local_rangeos'));
$PAGE->requires->js_call_amd('local_rangeos/activity_environments', 'init');

$environments = environment_manager::list_environments();
$profileenvmap = [];
$envoptions = [['id' => 0, 'name' => get_string('none', 'local_rangeos')]];
$envfilteroptions = [
    ['id' => 0, 'name' => get_string('allenvironments', 'local_rangeos'), 'selected' => $envfilter === 0],
    ['id' => -1, 'name' => get_string('unassignedenvironment', 'local_rangeos'), 'selected' => $envfilter === -1],
];

foreach ($environments as $env) {
    $option = ['id' => $env->id, 'name' => format_string($env->name)];
    $envoptions[] = $option;
    $option['selected'] = (int) $env->id === $envfilter;
    $envfilteroptions[] = $option;

    if (!empty($env->profileid)) {
        $profileenvmap[(int) $env->profileid] = $env;
    }
}

$from = "FROM {cmi5} c
          JOIN {course} co ON co.id = c.course
          JOIN {course_modules} cm ON cm.instance = c.id
               AND cm.module = (SELECT id FROM {modules} WHERE name = 'cmi5')";

$where = [];
$params = [];

if ($search !== '') {
    $namelike = $DB->sql_like('c.name', ':searchname', false);
    $courselike = $DB->sql_like('co.fullname', ':searchcourse', false);
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = "({$namelike} OR {$courselike})";
    $params['searchname'] = $searchparam;
    $params['searchcourse'] = $searchparam;
}

if ($envfilter > 0) {
    $selectedprofileid = isset($environments[$envfilter]) ? (int) $environments[$envfilter]->profileid : 0;
    if ($selectedprofileid > 0) {
        $where[] = 'c.profileid = :envprofileid';
        $params['envprofileid'] = $selectedprofileid;
    } else {
        $where[] = '1 = 0';
    }
} elseif ($envfilter === -1 && $profileenvmap) {
    [$notinsql, $notinparams] = $DB->get_in_or_equal(array_keys($profileenvmap), SQL_PARAMS_NAMED, 'pid', false);
    $where[] = "(c.profileid IS NULL OR c.profileid {$notinsql})";
    $params += $notinparams;
}

$wheresql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$totalitems = $DB->count_records_sql("SELECT COUNT(DISTINCT c.id) {$from}{$wheresql}", $params);

$lastpage = $totalitems > 0 ? (int) ceil($totalitems / $pagesize) - 1 : 0;
$currentpage = max(0, min($currentpage, $lastpage));

$records = $DB->get_records_sql(
    "SELECT c.id AS cmi5id, c.name AS activityname, c.profileid,
            co.fullname AS coursename, cm.id AS cmid
     {$from}{$wheresql}
     ORDER BY co.fullname ASC, c.name ASC, c.id ASC",
    $params,
    $currentpage * $pagesize,
    $pagesize
);

$activities = [];
foreach ($records as $rec) {
    $currentenvid = 0;
    if (!empty($rec->profileid) && isset($profileenvmap[(int) $rec->profileid])) {
        $currentenvid = (int) $profileenvmap[(int) $rec->profileid]->id;
    }

    $rowenvoptions = [];
    foreach ($envoptions as $opt) {
        $rowenvoptions[] = [
            'id' => $opt['id'],
            'name' => $opt['name'],
            'selected' => ($opt['id'] == $currentenvid),
        ];
    }

    $activities[] = [
        'cmi5id' => (int) $rec->cmi5id,
        'activityname' => format_string($rec->activityname),
        'coursename' => format_string($rec->coursename),
        'currentenvid' => $currentenvid,
        'envoptions' => $rowenvoptions,
        'activityurl' => (new moodle_url('/mod/cmi5/view.php', ['id' => $rec->cmid]))->out(false),
    ];
}

echo $OUTPUT->header();
echo \local_rangeos\output\dashboard::start('activity_environments', 'activityenvironments_desc');

echo $OUTPUT->render_from_template('local_rangeos/activity_environments', [
    'activities' => $activities,
    'hasactivities' => !empty($activities),
    'envfilteroptions' => $envfilteroptions,
    'hasenvironments' => !empty($environments),
    'search' => $search,
    'hasfilters' => $envfilter !== 0 || $search !== '',
    'rowsummary' => get_string('activityenvironments_count', 'local_rangeos', (object) [
        'first' => $totalitems ? $currentpage * $pagesize + 1 : 0,
        'last' => min(($currentpage + 1) * $pagesize, $totalitems),
        'total' => $totalitems,
    ]),
    'baseurl' => $baseurl,
]);

if ($totalitems > $pagesize) {
    $pagingurl = new moodle_url($pagepath, $filterparams);
    echo html_writer::div(
        html_writer::div($OUTPUT->paging_bar($totalitems, $currentpage, $pagesize, $pagingurl), 'd-flex align-items-center'),
        'rangeos-pagination d-flex flex-wrap align-items-center justify-content-center mt-3'
    );
}

echo \local_rangeos\output\dashboard::end();
echo $OUTPUT->footer();
