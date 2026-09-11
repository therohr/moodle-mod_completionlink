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
 * Scheduled task: backfill completionlink activity completion.
 *
 * Iterates every completionlink instance and ensures that any user who has
 * already completed the target course has their activity completion state
 * correctly set. This catches completions that occurred before the
 * completionlink was added, or that were missed by the real-time observer.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_completion extends \core\task\scheduled_task {
    /**
     * Return the localised task name shown in the admin scheduled-tasks UI.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_check_completion', 'mod_completionlink');
    }

    /**
     * Execute the task.
     *
     * Loads all completionlink instances and calls the shared backfill helper for
     * each one. Safe to run repeatedly — update_state() is idempotent.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/completionlink/lib.php');

        $instances = $DB->get_records('completionlink', null, '', 'id, course, targetcourseid');

        if (empty($instances)) {
            mtrace('mod_completionlink: no instances found, nothing to do.');
            return;
        }

        mtrace('mod_completionlink: checking completion for ' . count($instances) . ' instance(s).');

        $updated = 0;

        foreach ($instances as $instance) {
            if (empty($instance->targetcourseid)) {
                continue;
            }

            // Count affected users before and after to report changes.
            $before = $this->get_complete_count($instance->id);
            completionlink_backfill_completion(
                (int) $instance->course,
                (int) $instance->id,
                (int) $instance->targetcourseid
            );
            $after = $this->get_complete_count($instance->id);

            $diff = $after - $before;
            if ($diff > 0) {
                mtrace("  instance {$instance->id} (course {$instance->course}): {$diff} user(s) newly marked complete.");
                $updated += $diff;
            }
        }

        mtrace("mod_completionlink: task complete. {$updated} completion state(s) updated.");
    }

    /**
     * Return the number of users with COMPLETION_COMPLETE on a given completionlink cm.
     *
     * Used only for mtrace reporting — not part of the completion logic itself.
     *
     * @param  int $instanceid  The mdl_completionlink row id.
     * @return int
     */
    private function get_complete_count(int $instanceid): int {
        global $DB;

        $cmid = $DB->get_field_sql(
            'SELECT cm.id FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE m.name = :modname AND cm.instance = :instance',
            ['modname' => 'completionlink', 'instance' => $instanceid]
        );

        if (!$cmid) {
            return 0;
        }

        return (int) $DB->count_records('course_modules_completion', [
            'coursemoduleid'  => $cmid,
            'completionstate' => \COMPLETION_COMPLETE,
        ]);
    }
}
