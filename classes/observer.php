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

namespace mod_completionlink;

/**
 * Event observer for mod_completionlink.
 *
 * Each observer method extracts the minimum required data from the event and
 * queues an ad hoc task, then returns immediately. No modinfo lookups, no
 * completion writes, and no DB-heavy work are performed inside the observer —
 * all of that runs in the ad hoc task outside the originating HTTP request.
 *
 * This pattern prevents the observer from adding latency to the user's request
 * (which previously caused a 503 timeout when course completion fired during
 * a manual activity completion click).
 *
 * Three events are observed:
 *  - \core\event\course_completed        → queue sync_user_completion (promote)
 *  - \core\event\course_completion_updated → queue sync_user_completion (demote)
 *  - \core\event\course_reset_ended      → queue sync_course_reset (bulk demote)
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle the course_completed event.
     *
     * Queues an ad hoc task to promote the affected user's completionlink activity
     * completion state. Returns immediately so the originating HTTP request is
     * not blocked.
     *
     * @param  \core\event\course_completed $event The fired event.
     * @return void
     */
    public static function course_completed(\core\event\course_completed $event): void {
        $userid         = (int) $event->relateduserid;
        $targetcourseid = (int) $event->courseid;

        if (!$userid || !$targetcourseid) {
            return;
        }

        $task = new \mod_completionlink\task\sync_user_completion();
        $task->set_custom_data([
            'userid'         => $userid,
            'targetcourseid' => $targetcourseid,
            'promote'        => true,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Handle the course_completion_updated event.
     *
     * Queues an ad hoc task to demote the affected user's completionlink activity
     * when their target-course completion has been revoked or nulled out.
     * Returns immediately.
     *
     * Note: relateduserid on this event lives in $event->other['relateduserid'],
     * not on the top-level property (see MDL-44427).
     *
     * @param  \core\event\course_completion_updated $event The fired event.
     * @return void
     */
    public static function course_completion_updated(\core\event\course_completion_updated $event): void {
        $targetcourseid = (int) $event->courseid;
        $userid = isset($event->other['relateduserid']) ? (int) $event->other['relateduserid'] : 0;

        if (!$userid || !$targetcourseid) {
            return;
        }

        $task = new \mod_completionlink\task\sync_user_completion();
        $task->set_custom_data([
            'userid'         => $userid,
            'targetcourseid' => $targetcourseid,
            'promote'        => false,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    /**
     * Handle the course_reset_ended event.
     *
     * Queues an ad hoc task to demote all users whose target-course completion
     * was cleared by the reset. Returns immediately.
     *
     * @param  \core\event\course_reset_ended $event The fired event.
     * @return void
     */
    public static function course_reset(\core\event\course_reset_ended $event): void {
        $resetcourseid = (int) $event->courseid;

        if (!$resetcourseid) {
            return;
        }

        $task = new \mod_completionlink\task\sync_course_reset();
        $task->set_custom_data(['resetcourseid' => $resetcourseid]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
