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
 * View page for mod_completionlink.
 *
 * Redirects the student directly to the target course. If the target course
 * has been deleted, renders an error page instead. The course_module_viewed
 * event and completion tracking are handled before any output or redirect.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use mod_completionlink\local\enrolment_access;

// Bootstrap: validate params, load records, check access.

$id = required_param('id', PARAM_INT); // Course-module id.

$cm       = get_coursemodule_from_id('completionlink', $id, 0, false, MUST_EXIST);
$course   = get_course($cm->course);
$instance = $DB->get_record('completionlink', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/completionlink:view', $context);

// Page setup — required even when redirecting so events are correctly attributed.
$PAGE->set_url('/mod/completionlink/view.php', ['id' => $cm->id]);
$PAGE->set_title($course->shortname . ': ' . $instance->name);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Trigger the viewed event and mark the module viewed for completion.
$event = \mod_completionlink\event\course_module_viewed::create([
    'objectid' => $instance->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('completionlink', $instance);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$targetcourse = empty($instance->targetcourseid) ? false : $DB->get_record('course', ['id' => $instance->targetcourseid]);

// Target course deleted: show an error page.
if (!$targetcourse) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('targetcoursemissing', 'mod_completionlink'), 'error');
    echo $OUTPUT->footer();
    exit;
}

// Users who can open the target course go straight there.
if (enrolment_access::can_access($targetcourse->id, $USER->id)) {
    redirect(new moodle_url('/course/view.php', ['id' => $targetcourse->id]));
}

// Everyone else gets an explanation and, where possible, a way in.
$coursename = format_string($targetcourse->fullname, true, ['context' => context_course::instance($targetcourse->id)]);

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('notenrolled', 'mod_completionlink', $coursename), 'info', false);

$enrolurl = enrolment_access::get_enrolment_url($targetcourse->id);
if ($enrolurl) {
    echo $OUTPUT->render(new single_button(
        $enrolurl,
        get_string('enrolmentoptions', 'mod_completionlink'),
        'get',
        single_button::BUTTON_PRIMARY
    ));
} else {
    echo html_writer::tag('p', get_string('notenrolled_contact', 'mod_completionlink'));
}

if (has_capability('moodle/course:manageactivities', $context) && !enrolment_access::get_routes($targetcourse->id, $course->id)) {
    echo $OUTPUT->notification(get_string('noenrolmentroute', 'mod_completionlink', $coursename), 'warning', false);
}

echo $OUTPUT->footer();
