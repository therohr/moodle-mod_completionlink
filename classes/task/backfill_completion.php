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
 * Ad-hoc task: backfill completionlink completion for all enrolled users.
 *
 * Queued by completionlink_add_instance() and completionlink_update_instance() so
 * that the form save returns immediately. Cron processes this task in the
 * background, typically within one minute.
 *
 * Custom data shape: { hostcourseid: int, instanceid: int, targetcourseid: int }
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backfill_completion extends \core\task\adhoc_task {
    /**
     * Execute the task.
     *
     * Calls the shared backfill helper which promotes and demotes completion
     * states for every enrolled user based on their target-course completion.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/mod/completionlink/lib.php');

        $data = $this->get_custom_data();

        completionlink_backfill_completion(
            (int) $data->hostcourseid,
            (int) $data->instanceid,
            (int) $data->targetcourseid
        );
    }
}
