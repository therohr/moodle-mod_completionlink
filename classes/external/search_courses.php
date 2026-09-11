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

namespace mod_completionlink\external;

use core_course_category;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use context_course;

/**
 * AJAX search datasource for the target-course autocomplete field in mod_form.php.
 *
 * The activity settings form used to preload every course on the site into a single
 * <select>, which broke down on tenants with more than {@see self::MAX_RESULTS}
 * courses (results silently stopped after the cap, alphabetically). This external
 * function lets the form search on demand instead, so the result set never has to
 * hold the whole catalog in memory or in the page.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_courses extends external_api {
    /** Maximum number of matching courses returned per search request. */
    const MAX_RESULTS = 100;

    /**
     * Describe the parameters accepted by {@see self::execute()}.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query'    => new external_value(PARAM_RAW, 'Search string typed so far', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT, 'Id of the host course the activity belongs to'),
        ]);
    }

    /**
     * Search for courses to link to, excluding the host course itself.
     *
     * Reuses core_course_category::search_courses(), the same tenant-aware lookup
     * (overridden by Workplace to enforce tenant isolation) that populated the
     * original static dropdown, but scoped to the typed query and capped to a
     * page of results instead of the whole catalog.
     *
     * @param  string $query    Search string typed by the user.
     * @param  int    $courseid Id of the host course (excluded from results).
     * @return array            List of ['id' => int, 'label' => string] matches.
     */
    public static function execute(string $query, int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'query'    => $query,
            'courseid' => $courseid,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('mod/completionlink:addinstance', $context);

        $courses = core_course_category::search_courses(
            ['search' => $params['query']],
            ['offset' => 0, 'limit' => self::MAX_RESULTS, 'sort' => ['fullname' => 1]]
        );

        $results = [];
        foreach ($courses as $course) {
            if ((int) $course->id === $params['courseid']) {
                // Exclude the host course — linking to itself is meaningless.
                continue;
            }
            $results[] = [
                'id'    => (int) $course->id,
                'label' => format_string($course->fullname) . ' (' . format_string($course->shortname) . ')',
            ];
        }

        return $results;
    }

    /**
     * Describe the shape of the data returned by {@see self::execute()}.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id'    => new external_value(PARAM_INT, 'Course id'),
                'label' => new external_value(PARAM_RAW, 'Display label: full name (short name)'),
            ])
        );
    }
}
