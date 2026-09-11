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

namespace mod_completionlink\task;

/**
 * Ad hoc task: re-evaluate completionlink completion for all users after a course reset.
 *
 * Queued by the observer when a target course is reset. Runs outside the
 * originating HTTP request so that bulk backfill cannot cause a timeout.
 *
 * Custom data keys:
 *  - resetcourseid  (int) The course that was reset.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_course_reset extends \core\task\adhoc_task {
    /**
     * Return the component that owns this task.
     *
     * @return string
     */
    public function get_component(): string {
        return 'mod_completionlink';
    }

    /**
     * Execute the task.
     *
     * Calls completionlink_backfill_completion() for every completionlink instance
     * that tracked the reset course. The backfill handles demotion for users
     * who were previously complete and are now not.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/completionlink/lib.php');

        $data          = $this->get_custom_data();
        $resetcourseid = (int) $data->resetcourseid;

        if (!$resetcourseid) {
            mtrace('mod_completionlink sync_course_reset: missing resetcourseid — skipping.');
            return;
        }

        $instances = $DB->get_records('completionlink', ['targetcourseid' => $resetcourseid]);

        if (empty($instances)) {
            mtrace("mod_completionlink sync_course_reset: no completionlink instances track course $resetcourseid.");
            return;
        }

        mtrace("mod_completionlink sync_course_reset: processing " . count($instances)
            . " instance(s) for reset course $resetcourseid.");

        foreach ($instances as $instance) {
            completionlink_backfill_completion(
                (int) $instance->course,
                (int) $instance->id,
                $resetcourseid
            );
            mtrace("  instance {$instance->id} (host course {$instance->course}) re-evaluated.");
        }
    }
}
