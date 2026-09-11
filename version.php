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
 * Plugin version definition.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Minimum Moodle 4.5 — required for activity_custom_completion API and Workplace 5.0 compatibility.
$plugin->component = 'mod_completionlink';
$plugin->version   = 2026091103;
$plugin->requires  = 2024100700; // Moodle 4.5.
$plugin->supported = [405, 502];  // Moodle 4.5 to 5.2 (see .github/workflows/moodle-plugin-ci.yml).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.1';
