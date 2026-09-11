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
 * Backup activity task for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/completionlink/backup/moodle2/backup_completionlink_stepslib.php');

/**
 * Backup task for a completionlink activity instance.
 */
class backup_completionlink_activity_task extends backup_activity_task {
    /**
     * No module-level settings beyond the defaults.
     */
    protected function define_my_settings() {
    }

    /**
     * Register the single structure step that writes completionlink.xml.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_completionlink_activity_structure_step(
            'completionlink_structure',
            'completionlink.xml'
        ));
    }

    /**
     * Encode links to completionlink view pages so they can be remapped on restore.
     *
     * Paired with restore_completionlink_activity_task::define_decode_rules().
     *
     * @param string $content HTML content that may contain links to this module.
     * @return string         Content with links replaced by restore placeholders.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot . '/mod/completionlink', '#');

        // Link to a completionlink activity by course module id.
        $pattern = '#(' . $base . '/view\.php\?id=)([0-9]+)#';
        return preg_replace($pattern, '$@COMPLETIONLINKVIEWBYID*$2@$', $content);
    }
}
