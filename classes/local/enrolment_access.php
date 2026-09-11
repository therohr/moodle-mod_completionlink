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

namespace mod_completionlink\local;

use context_course;
use moodle_url;

/**
 * Works out how students of a host course can get into the linked (target) course.
 *
 * The plugin never enrols anyone. It only reads the target course's own enrolment
 * methods so it can warn teachers when students have no way in, and point students
 * at the target course's enrolment options.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_access {
    /** A course meta link in the target course that syncs enrolments from the host course. */
    public const ROUTE_META = 'meta';

    /** Self enrolment in the target course that is currently accepting new enrolments. */
    public const ROUTE_SELF = 'self';

    /** Guest access to the target course. */
    public const ROUTE_GUEST = 'guest';

    /**
     * Return the routes by which host-course students can get into the target course.
     *
     * @param int $targetcourseid The linked course.
     * @param int $hostcourseid   The course containing the activity.
     * @return string[] Zero or more ROUTE_* constants.
     */
    public static function get_routes(int $targetcourseid, int $hostcourseid): array {
        $routes = [];
        foreach (self::get_enabled_instances($targetcourseid) as $instance) {
            if ($instance->enrol === 'meta' && (int) $instance->customint1 === $hostcourseid) {
                $routes[self::ROUTE_META] = true;
            } else if ($instance->enrol === 'self' && self::is_self_enrolment_open($instance)) {
                $routes[self::ROUTE_SELF] = true;
            } else if ($instance->enrol === 'guest') {
                $routes[self::ROUTE_GUEST] = true;
            }
        }
        return array_keys($routes);
    }

    /**
     * Whether a user can open the target course right now without any enrolment step.
     *
     * True for enrolled users, users who can view any course (managers, admins), and when
     * the course allows guest access without a password.
     *
     * @param int $targetcourseid The linked course.
     * @param int $userid         The user.
     * @return bool
     */
    public static function can_access(int $targetcourseid, int $userid): bool {
        $context = context_course::instance($targetcourseid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }
        if (is_enrolled($context, $userid, '', true) || has_capability('moodle/course:view', $context, $userid)) {
            return true;
        }
        foreach (self::get_enabled_instances($targetcourseid) as $instance) {
            if ($instance->enrol === 'guest' && (string) $instance->password === '') {
                return true;
            }
        }
        return false;
    }

    /**
     * The target course's enrolment page, if the current user has an option there.
     *
     * Returns a URL when self enrolment is available to the current user, or when guest
     * access with a password is enabled. The enrolment page itself handles enrolment keys,
     * capacity and dates.
     *
     * @param int $targetcourseid The linked course.
     * @return moodle_url|null
     */
    public static function get_enrolment_url(int $targetcourseid): ?moodle_url {
        foreach (self::get_enabled_instances($targetcourseid) as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            $available = ($instance->enrol === 'guest')
                || ($instance->enrol === 'self' && $plugin && $plugin->can_self_enrol($instance) === true);
            if ($available) {
                return new moodle_url('/enrol/index.php', ['id' => $targetcourseid]);
            }
        }
        return null;
    }

    /**
     * Enabled enrolment instances in a course whose enrolment plugin is also enabled.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    private static function get_enabled_instances(int $courseid): array {
        return array_filter(
            enrol_get_instances($courseid, true),
            static fn(\stdClass $instance): bool => enrol_is_enabled($instance->enrol)
        );
    }

    /**
     * Whether a self enrolment instance is accepting new enrolments, independent of any user.
     *
     * @param \stdClass $instance Row from the enrol table.
     * @return bool
     */
    private static function is_self_enrolment_open(\stdClass $instance): bool {
        $now = time();
        return !empty($instance->customint6)
            && (empty($instance->enrolstartdate) || $instance->enrolstartdate <= $now)
            && (empty($instance->enrolenddate) || $instance->enrolenddate >= $now);
    }
}
