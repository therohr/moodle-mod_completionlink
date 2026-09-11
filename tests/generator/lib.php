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
 * mod_completionlink data generator.
 *
 * @package   mod_completionlink
 * @category  test
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_completionlink_generator extends testing_module_generator {
    /**
     * Create a completionlink instance, creating a target course if none is given.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record + ['completiontracking' => 1];
        if (empty($record['targetcourseid'])) {
            $record['targetcourseid'] = $this->datagenerator->create_course(['enablecompletion' => 1])->id;
        }
        return parent::create_instance($record, (array) $options);
    }
}
