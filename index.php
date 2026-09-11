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
 * Lists all completionlink activities in a course.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course id.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course, true);

$event = \mod_completionlink\event\course_module_instance_list_viewed::create([
    'context' => context_course::instance($course->id),
]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strplural = get_string('modulenameplural', 'mod_completionlink');

$PAGE->set_url('/mod/completionlink/index.php', ['id' => $course->id]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title($course->shortname . ': ' . $strplural);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add($strplural);

echo $OUTPUT->header();
echo $OUTPUT->heading($strplural);

$cms = array_filter(
    get_fast_modinfo($course)->get_instances_of('completionlink'),
    static fn(cm_info $cm): bool => $cm->uservisible
);

if (empty($cms)) {
    notice(
        get_string('thereareno', 'moodle', $strplural),
        new moodle_url('/course/view.php', ['id' => $course->id])
    );
}

$table = new html_table();
$table->head = [get_string('name')];

foreach ($cms as $cm) {
    $table->data[] = [
        html_writer::link(
            new moodle_url('/mod/completionlink/view.php', ['id' => $cm->id]),
            format_string($cm->name),
            $cm->visible ? [] : ['class' => 'dimmed']
        ),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
