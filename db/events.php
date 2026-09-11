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
 * Event observer registration for mod_completionlink.
 *
 * All observers use internal => false so they run outside the originating
 * database transaction. This prevents a failure in update_state() from
 * rolling back the course completion record that triggered the event.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Fires when a user completes a course. Promotes any completionlink
        // activity that tracks the completed course to complete for that user.
        'eventname'   => '\core\event\course_completed',
        'callback'    => '\mod_completionlink\observer::course_completed',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 0,
    ],
    [
        // Fires when any field on a user's course_completions row is updated.
        // We use this to detect individual completion revocations (timecompleted
        // nulled out) and demote the corresponding completionlink activity in real time.
        // Note: relateduserid on this event lives in $event->other['relateduserid'].
        'eventname'   => '\core\event\course_completion_updated',
        'callback'    => '\mod_completionlink\observer::course_completion_updated',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 0,
    ],
    [
        // Fires after course/reset.php clears completion data. Re-evaluates all
        // completionlink activities pointing at the reset course so users who were
        // previously complete are correctly demoted back to incomplete.
        'eventname'   => '\core\event\course_reset_ended',
        'callback'    => '\mod_completionlink\observer::course_reset',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 0,
    ],
];
