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

namespace mod_completionlink;

/**
 * Tests for lib.php callbacks.
 *
 * @package   mod_completionlink
 * @category  test
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::completionlink_cm_info_dynamic
 * @covers    ::completionlink_warn_if_no_enrolment_route
 */
final class lib_test extends \advanced_testcase {
    /**
     * Enrolled students keep the direct link; others are sent to view.php.
     */
    public function test_link_depends_on_access_to_target_course(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $host = $generator->create_course(['enablecompletion' => 1]);
        $target = $generator->create_course(['enablecompletion' => 1]);
        $insider = $generator->create_and_enrol($host, 'student');
        $generator->enrol_user($insider->id, $target->id, 'student');
        $outsider = $generator->create_and_enrol($host, 'student');

        $this->setAdminUser();
        $cm = $generator->create_module('completionlink', ['course' => $host->id, 'targetcourseid' => $target->id]);
        \core\notification::fetch();

        $this->assertStringContainsString('window.open', get_fast_modinfo($host, $insider->id)->get_cm($cm->cmid)->onclick);
        $this->assertSame('', get_fast_modinfo($host, $outsider->id)->get_cm($cm->cmid)->onclick);
    }

    /**
     * Saving an activity warns the teacher only when students have no way into the target course.
     */
    public function test_warning_when_no_enrolment_route(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $host = $generator->create_course();
        $closed = $generator->create_course(['fullname' => 'Closed course']);
        $open = $generator->create_course(['fullname' => 'Open course']);
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_ENABLED, ['courseid' => $open->id, 'enrol' => 'guest']);

        $this->setAdminUser();
        \core\notification::fetch();

        $generator->create_module('completionlink', ['course' => $host->id, 'targetcourseid' => $closed->id]);
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Closed course', $messages[0]);

        $generator->create_module('completionlink', ['course' => $host->id, 'targetcourseid' => $open->id]);
        $this->assertSame([], \core\notification::fetch());
    }
}
