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

namespace mod_completionlink\completion;

use core_completion\activity_custom_completion;
use completion_info;
use COMPLETION_COMPLETE;
use COMPLETION_INCOMPLETE;

/**
 * Custom completion rules for mod_completionlink.
 *
 * This class implements cross-course completion tracking: an instance of
 * mod_completionlink is considered complete when the student has completed the
 * configured target course.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Fetch the completion state for a specific custom rule.
     *
     * Moodle calls this method for each rule name returned by
     * {@see get_defined_custom_rules()}.
     *
     * @param  string $rule The rule identifier.
     * @return int          COMPLETION_COMPLETE or COMPLETION_INCOMPLETE.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        // Cast customdata to array (base class may return an object); use the standard
        // 'customcompletionrules' structure populated by completionlink_get_coursemodule_info().
        $customdata = (array) $this->cm->customdata;

        // If the teacher has not enabled this completion rule, short-circuit.
        if (empty($customdata['customcompletionrules']['completiontracking'])) {
            return COMPLETION_INCOMPLETE;
        }

        // Prefer cached targetcourseid from customdata; fall back to a DB query.
        $targetcourseid = $customdata['targetcourseid']
            ?? $DB->get_field('completionlink', 'targetcourseid', ['id' => $this->cm->instance]);

        if (!$targetcourseid) {
            return COMPLETION_INCOMPLETE;
        }

        // Query mdl_course_completions for the target course.
        $completed = $DB->record_exists_select(
            'course_completions',
            'userid = :userid AND course = :course AND timecompleted IS NOT NULL',
            [
                'userid' => $this->userid,
                'course' => (int) $targetcourseid,
            ]
        );

        return $completed ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Return the list of custom completion rule identifiers defined by this module.
     *
     * @return string[] Array of rule names.
     */
    public static function get_defined_custom_rules(): array {
        return ['completiontracking'];
    }

    /**
     * Return human-readable descriptions of each active custom rule.
     *
     * Used by the completion UI to explain to the student what they must do.
     *
     * @return array Associative array of rule => description string.
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $customdata     = (array) $this->cm->customdata;
        $targetcourseid = $customdata['targetcourseid'] ?? null;
        // Not get_course(): it throws if the target course was deleted, which would
        // break the course page for every user who can see this activity.
        $targetcourse   = $targetcourseid
            ? $DB->get_record('course', ['id' => $targetcourseid], 'id, fullname')
            : null;
        $coursename     = $targetcourse
            ? $targetcourse->fullname
            : get_string('targetcoursemissing', 'mod_completionlink');

        return [
            'completiontracking' => get_string(
                'completiondetail:targetcourse',
                'mod_completionlink',
                $coursename
            ),
        ];
    }

    /**
     * Return the sort order for custom completion rules.
     *
     * This controls display order in the activity completion UI.
     *
     * @return string[] Ordered list of rule names.
     */
    public function get_sort_order(): array {
        return ['completiontracking'];
    }
}
