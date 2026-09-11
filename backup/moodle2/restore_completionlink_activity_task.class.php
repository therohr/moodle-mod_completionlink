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
 * Restore activity task for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/completionlink/backup/moodle2/restore_completionlink_stepslib.php');
require_once($CFG->dirroot . '/mod/completionlink/lib.php');

/**
 * Restore task for a completionlink activity instance.
 */
class restore_completionlink_activity_task extends restore_activity_task {
    /**
     * No module-level settings beyond the defaults.
     */
    protected function define_my_settings() {
    }

    /**
     * Register the single structure step that reads completionlink.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_completionlink_activity_structure_step(
            'completionlink_structure',
            'completionlink.xml'
        ));
    }

    /**
     * Fields whose HTML may contain encoded links needing remapping.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents() {
        return [
            new restore_decode_content('completionlink', ['intro'], 'completionlink'),
        ];
    }

    /**
     * Decode rules paired with backup_completionlink_activity_task::encode_content_links().
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('COMPLETIONLINKVIEWBYID', '/mod/completionlink/view.php?id=$1', 'course_module'),
        ];
    }

    /**
     * Legacy log rules for restoring module-level log entries.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules() {
        return [
            new restore_log_rule('completionlink', 'add', 'view.php?id={course_module}', '{completionlink}'),
            new restore_log_rule('completionlink', 'update', 'view.php?id={course_module}', '{completionlink}'),
            new restore_log_rule('completionlink', 'view', 'view.php?id={course_module}', '{completionlink}'),
        ];
    }

    /**
     * No course-level restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules_for_course() {
        return [];
    }
}
