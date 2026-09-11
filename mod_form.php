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
 * Activity settings form for mod_completionlink.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for adding and editing a completionlink activity instance.
 *
 * Teachers configure which course to link to and whether completion of that
 * course is required before this activity is marked complete.
 *
 * The target-course field searches on demand via AJAX (see
 * classes/external/search_courses.php) rather than preloading the whole course
 * catalog into a single <select> — the previous approach silently truncated
 * after 500 courses (alphabetically) on large Workplace tenants.
 */
class mod_completionlink_mod_form extends moodleform_mod {
    /**
     * Build the form definition.
     *
     * @return void
     */
    public function definition(): void {
        global $DB, $OUTPUT;

        $mform = $this->_form;

        // Standard: name + intro.
        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Target course selector.
        $mform->addElement('header', 'completionlinksettings', get_string('pluginname', 'mod_completionlink'));

        // Only preload the currently selected course (if any), so its label renders
        // correctly when editing an existing activity. Everything else is fetched
        // on demand by classes/external/search_courses.php as the teacher types,
        // via the 'ajax' datasource below — the field never holds the full catalog.
        $courseopts = [];
        if (!empty($this->current->targetcourseid)) {
            $targetcourse = $DB->get_record('course', ['id' => $this->current->targetcourseid]);
            if ($targetcourse) {
                $courseopts[$targetcourse->id] =
                    format_string($targetcourse->fullname) . ' (' . format_string($targetcourse->shortname) . ')';
            }
        }

        $mform->addElement(
            'autocomplete',
            'targetcourseid',
            get_string('targetcourseid', 'mod_completionlink'),
            $courseopts,
            [
                'ajax'          => 'mod_completionlink/form_completionlink_selector',
                'multiple'      => false,
                'noselectionstring' => get_string('search'),
                // Read by amd/src/form_completionlink_selector.js to exclude the host
                // course and scope the capability check in search_courses::execute().
                'data-courseid' => (int) $this->current->course,
            ]
        );
        $mform->addHelpButton('targetcourseid', 'targetcourseid', 'mod_completionlink');
        $mform->addRule('targetcourseid', get_string('nocourseselected', 'mod_completionlink'), 'required', null, 'client');
        $mform->setType('targetcourseid', PARAM_INT);

        // When editing, flag a target course that host-course students have no way into.
        $hasroute = empty($targetcourse)
            || \mod_completionlink\local\enrolment_access::get_routes((int) $targetcourse->id, (int) $this->current->course);
        if (!$hasroute) {
            $mform->addElement(
                'static',
                'enrolmentroutewarning',
                '',
                $OUTPUT->notification(
                    get_string('noenrolmentroute', 'mod_completionlink', format_string($targetcourse->fullname)),
                    'warning',
                    false
                )
            );
        }

        // Standard: grading + completion tabs added by parent.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Add completion-rule elements to the completion settings tab.
     *
     * Called by the parent form when building the completion tab. Elements added
     * here are shown only when the teacher selects automatic completion tracking.
     *
     * @return array Element names that are completion rules.
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'completiontracking',
            get_string('completiontracking', 'mod_completionlink'),
            '',
            [],
            [0, 1]
        );
        $mform->addHelpButton('completiontracking', 'completiontracking', 'mod_completionlink');
        $mform->setDefault('completiontracking', 1);

        return ['completiontracking'];
    }

    /**
     * Determine whether any completion rule is currently enabled.
     *
     * Moodle uses this to decide whether to show the custom completion section.
     *
     * @param  array $data Form data.
     * @return bool        True when at least one custom rule is active.
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completiontracking']);
    }

    /**
     * Server-side validation.
     *
     * @param  array $data  Submitted form data.
     * @param  array $files Uploaded files (unused).
     * @return array        Validation errors keyed by element name.
     */
    public function validation($data, $files): array {
        global $DB;

        $errors = parent::validation($data, $files);

        // The AJAX selector excludes the host course client-side only, so re-check here.
        if (empty($data['targetcourseid'])) {
            $errors['targetcourseid'] = get_string('nocourseselected', 'mod_completionlink');
        } else if ((int) $data['targetcourseid'] === (int) $this->current->course) {
            $errors['targetcourseid'] = get_string('cannotlinktoself', 'mod_completionlink');
        } else if (!$DB->record_exists('course', ['id' => $data['targetcourseid']])) {
            $errors['targetcourseid'] = get_string('targetcoursemissing', 'mod_completionlink');
        }

        return $errors;
    }
}
