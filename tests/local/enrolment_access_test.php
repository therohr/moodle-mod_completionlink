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

/**
 * Tests for the enrolment access helper.
 *
 * @package   mod_completionlink
 * @category  test
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_completionlink\local\enrolment_access
 */
final class enrolment_access_test extends \advanced_testcase {
    /**
     * Get (or create) the enrolment instance of a type in a course and set its fields.
     *
     * @param \stdClass $course
     * @param string $type Enrolment plugin name.
     * @param array $fields Fields to set on the instance.
     * @return \stdClass The instance.
     */
    private function set_instance(\stdClass $course, string $type, array $fields): \stdClass {
        global $DB;

        $plugin = enrol_get_plugin($type);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $type]);
        if (!$instance) {
            $instance = $DB->get_record('enrol', ['id' => $plugin->add_instance($course)]);
        }
        foreach ($fields as $field => $value) {
            $instance->$field = $value;
        }
        $DB->update_record('enrol', $instance);
        return $DB->get_record('enrol', ['id' => $instance->id]);
    }

    /**
     * A new course has no route in: its default guest and self instances are disabled.
     */
    public function test_no_routes_by_default(): void {
        $this->resetAfterTest();
        $host = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();

        $this->assertSame([], enrolment_access::get_routes($target->id, $host->id));
    }

    /**
     * Open self enrolment is a route; closed or expired self enrolment is not.
     */
    public function test_self_enrolment_route(): void {
        $this->resetAfterTest();
        $host = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();

        $this->set_instance($target, 'self', ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        $this->assertSame([enrolment_access::ROUTE_SELF], enrolment_access::get_routes($target->id, $host->id));

        $this->set_instance($target, 'self', ['customint6' => 0]);
        $this->assertSame([], enrolment_access::get_routes($target->id, $host->id));

        $this->set_instance($target, 'self', ['customint6' => 1, 'enrolenddate' => time() - DAYSECS]);
        $this->assertSame([], enrolment_access::get_routes($target->id, $host->id));
    }

    /**
     * Guest access is a route.
     */
    public function test_guest_route(): void {
        $this->resetAfterTest();
        $host = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();

        $this->set_instance($target, 'guest', ['status' => ENROL_INSTANCE_ENABLED]);
        $this->assertSame([enrolment_access::ROUTE_GUEST], enrolment_access::get_routes($target->id, $host->id));
    }

    /**
     * A meta link is a route only when it syncs from the host course.
     */
    public function test_meta_link_route(): void {
        $this->resetAfterTest();
        $host = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $target = $this->getDataGenerator()->create_course();
        \core\plugininfo\enrol::enable_plugin('meta', true);

        enrol_get_plugin('meta')->add_instance($target, ['customint1' => $other->id]);
        $this->assertSame([], enrolment_access::get_routes($target->id, $host->id));

        enrol_get_plugin('meta')->add_instance($target, ['customint1' => $host->id]);
        $this->assertSame([enrolment_access::ROUTE_META], enrolment_access::get_routes($target->id, $host->id));
    }

    /**
     * Enrolled users, admins and open guest access can open the course; others cannot.
     */
    public function test_can_access(): void {
        $this->resetAfterTest();
        $target = $this->getDataGenerator()->create_course();
        $enrolled = $this->getDataGenerator()->create_and_enrol($target, 'student');
        $outsider = $this->getDataGenerator()->create_user();

        $this->assertTrue(enrolment_access::can_access($target->id, $enrolled->id));
        $this->assertTrue(enrolment_access::can_access($target->id, get_admin()->id));
        $this->assertFalse(enrolment_access::can_access($target->id, $outsider->id));

        $this->set_instance($target, 'guest', ['status' => ENROL_INSTANCE_ENABLED, 'password' => 'secret']);
        $this->assertFalse(enrolment_access::can_access($target->id, $outsider->id));

        $this->set_instance($target, 'guest', ['password' => '']);
        $this->assertTrue(enrolment_access::can_access($target->id, $outsider->id));
    }

    /**
     * The enrolment page is offered only when the user has an option there.
     */
    public function test_get_enrolment_url(): void {
        $this->resetAfterTest();
        $target = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertNull(enrolment_access::get_enrolment_url($target->id));

        $this->set_instance($target, 'self', ['status' => ENROL_INSTANCE_ENABLED, 'customint6' => 1]);
        $url = enrolment_access::get_enrolment_url($target->id);
        $this->assertInstanceOf(\moodle_url::class, $url);
        $this->assertSame('/enrol/index.php?id=' . $target->id, $url->out_as_local_url(false));
    }
}
