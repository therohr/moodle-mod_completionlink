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

namespace mod_completionlink\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API implementation for mod_completionlink.
 *
 * mod_completionlink stores no user data of its own. It reads from
 * mdl_course_completions (owned by core_completion) to determine
 * completion state. No personal data export or deletion is required.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data this plugin stores or accesses.
     *
     * @param  collection $collection The metadata collection to populate.
     * @return collection             The populated collection.
     */
    public static function get_metadata(collection $collection): collection {
        // This plugin reads course completion data owned by core_completion.
        // It does not write any personal data itself.
        $collection->add_subsystem_link(
            'core_completion',
            [],
            'privacy:metadata:core_completion'
        );

        return $collection;
    }

    /**
     * Return the contexts that contain personal data for the given user.
     *
     * Because this plugin stores no personal data, the list is always empty.
     *
     * @param  int         $userid The user id.
     * @return contextlist         An empty contextlist.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * Return the users who have personal data within the given contexts.
     *
     * Because this plugin stores no personal data, the list is always empty.
     *
     * @param  userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        // No personal data stored — nothing to add.
    }

    /**
     * Export personal data for the given approved contexts.
     *
     * Nothing to export.
     *
     * @param  approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        // No personal data stored — nothing to export.
    }

    /**
     * Delete all personal data for all users in the given context.
     *
     * Nothing to delete.
     *
     * @param  \context $context The context to act on.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // No personal data stored — nothing to delete.
    }

    /**
     * Delete personal data for the given approved contextlist.
     *
     * Nothing to delete.
     *
     * @param  approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // No personal data stored — nothing to delete.
    }

    /**
     * Delete personal data for the given approved userlist.
     *
     * Nothing to delete.
     *
     * @param  approved_userlist $userlist The approved userlist.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        // No personal data stored — nothing to delete.
    }
}
