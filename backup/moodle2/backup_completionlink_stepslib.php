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
 * Backup structure step for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML structure of a completionlink backup.
 */
class backup_completionlink_activity_structure_step extends backup_activity_structure_step {
    /**
     * Define the backup XML structure for a completionlink instance.
     *
     * targetcourseid is stored as a plain value. It is an external reference to a
     * course that is not part of this backup, so annotating it would cause
     * unknown_context_mapping errors during restore. Same-site duplicates and
     * restores preserve the original course ID, which is the correct behaviour.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        $completionlink = new backup_nested_element('completionlink', ['id'], [
            'name',
            'intro',
            'introformat',
            'targetcourseid',
            'completiontracking',
            'timecreated',
            'timemodified',
        ]);

        $completionlink->set_source_table('completionlink', ['id' => backup::VAR_ACTIVITYID]);

        // Include images/files embedded in the description; restore adds them back
        // in restore_completionlink_activity_structure_step::after_execute().
        $completionlink->annotate_files('mod_completionlink', 'intro', null);

        return $this->prepare_activity_structure($completionlink);
    }
}
