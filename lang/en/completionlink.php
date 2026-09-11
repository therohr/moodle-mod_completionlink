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
 * Language strings for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['cannotlinktoself']              = 'A course completion link cannot point to the course it is in.';
$string['completiondetail:targetcourse'] = 'Complete the linked course: {$a}';
$string['completionlink:addinstance']    = 'Add a course completion link activity';
$string['completionlink:view']           = 'View a course completion link activity';
$string['completiontracking']            = 'Require completion of target course';
$string['completiontracking_help']       = 'When enabled, this activity is only marked complete once the student has completed the selected target course. Use the Restrict access settings on downstream activities to gate them on this completion.';
$string['enrolmentoptions']              = 'View enrolment options';
$string['modulename']                    = 'Course completion link';
$string['modulename_help']               = 'The Course completion link activity displays a link to another course and tracks whether the student has completed it. Downstream activities can be gated on that completion.';
$string['modulename_summary']            = 'Links to another course and marks itself complete when the student completes that course.';
$string['modulenameplural']              = 'Course completion links';
$string['nocourseselected']              = 'You must select a target course.';
$string['noenrolmentroute']              = 'Students in this course have no way into {$a}: it has no course meta link from this course, no open self enrolment and no guest access. Give them access another way (for example a course meta link or a Moodle Workplace program), otherwise they will not be able to open the linked course.';
$string['notenrolled']                   = 'You are not enrolled in {$a}, so you cannot open it yet.';
$string['notenrolled_contact']           = 'Ask your teacher or site administrator to enrol you in this course.';
$string['pluginadministration']          = 'Course completion link administration';
$string['pluginname']                    = 'Course completion link';
$string['privacy:metadata:core_completion'] =
    'The Course completion link activity reads course completion records from the Moodle ' .
    'core completion subsystem to determine whether a student has completed the ' .
    'linked course. No personal data is stored by this plugin directly.';
$string['targetcourseid']                = 'Target course';
$string['targetcourseid_help']           = 'Select the course whose completion will be tracked and linked from this activity.

This activity does not enrol students in the linked course. Make sure they can get in, for example with a course meta link from this course, self enrolment, guest access or a Moodle Workplace program. Students who are not enrolled see enrolment options or who to contact instead of the course.';
$string['targetcoursemissing']           = 'The target course could not be found. Please contact your course administrator.';
$string['task_check_completion']         = 'Course completion link: backfill activity completion';
