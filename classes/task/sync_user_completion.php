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
 * Ad hoc task: re-evaluate completionlink activity completion for one user.
 *
 * Queued by the observer when a user completes or loses completion on a
 * target course. Runs outside the originating HTTP request so that
 * get_fast_modinfo() and update_state() cannot cause a web request timeout.
 *
 * Custom data keys:
 *  - userid          (int) The user whose completion state needs updating.
 *  - targetcourseid  (int) The course that was completed or revoked.
 *  - promote         (bool) true = check for promotion to complete;
 *                           false = check for demotion to incomplete.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_user_completion extends \core\task\adhoc_task {
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
     * Finds every completionlink instance that tracks the target course and
     * re-evaluates the completion state for the affected user. Groups by
     * host course to minimise get_fast_modinfo() calls.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/completionlib.php');

        $data           = $this->get_custom_data();
        $userid         = (int) $data->userid;
        $targetcourseid = (int) $data->targetcourseid;
        $promote        = (bool) $data->promote;

        if (!$userid || !$targetcourseid) {
            mtrace('mod_completionlink sync_user_completion: missing userid or targetcourseid — skipping.');
            return;
        }

        // When demoting, verify the user is actually no longer complete before
        // doing any further work. A duplicate task queued by a rapid sequence
        // of events may arrive after completion has been re-awarded.
        if (!$promote) {
            $stillcomplete = $DB->record_exists_select(
                'course_completions',
                'userid = :userid AND course = :course AND timecompleted IS NOT NULL',
                ['userid' => $userid, 'course' => $targetcourseid]
            );
            if ($stillcomplete) {
                mtrace("mod_completionlink sync_user_completion: user $userid is still complete in course"
                    . " $targetcourseid — demotion not needed.");
                return;
            }
        }

        $instances = $DB->get_records('completionlink', ['targetcourseid' => $targetcourseid]);

        if (empty($instances)) {
            return;
        }

        // Group by host course so get_fast_modinfo() is called once per course.
        $byhostcourse = [];
        foreach ($instances as $instance) {
            $byhostcourse[(int) $instance->course][] = $instance;
        }

        foreach ($byhostcourse as $hostcourseid => $hostinstances) {
            $modinfo = get_fast_modinfo($hostcourseid, $userid);

            $cmmap = [];
            foreach ($modinfo->get_instances_of('completionlink') as $cm) {
                $cmmap[(int) $cm->instance] = $cm;
            }

            $course     = get_course($hostcourseid);
            $completion = new \completion_info($course);

            foreach ($hostinstances as $instance) {
                $cminfo = $cmmap[(int) $instance->id] ?? null;

                if (!$cminfo) {
                    continue;
                }

                if (!$completion->is_enabled($cminfo)) {
                    continue;
                }

                $completion->update_state($cminfo, COMPLETION_UNKNOWN, $userid);
                $dir = $promote ? 'promoted' : 'demoted';
                mtrace("mod_completionlink sync_user_completion: user $userid $dir on"
                    . " completionlink instance {$instance->id} (host course $hostcourseid).");
            }
        }
    }
}
