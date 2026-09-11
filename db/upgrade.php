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
 * Upgrade steps for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run upgrade steps for mod_completionlink.
 *
 * There are no database changes since the first release. Add upgrade steps here, each guarded by
 * `if ($oldversion < YYYYMMDDXX)` and ending with upgrade_mod_savepoint().
 *
 * @param int $oldversion The version the plugin is being upgraded from.
 * @return bool Always true.
 */
function xmldb_completionlink_upgrade($oldversion) {
    return true;
}
