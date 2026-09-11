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
 * Library functions for mod_completionlink.
 *
 * These are the functions Moodle core calls by convention. They must exist in
 * lib.php and must be named {modname}_{hookname}.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die(); // phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded

// Core CRUD hooks.

/**
 * Add a new completionlink instance.
 *
 * Called by Moodle core when a teacher saves the activity settings form.
 *
 * @param  stdClass $data  Form data from mod_form.
 * @param  object   $mform The form object (unused but required by signature).
 * @return int             The new instance id.
 */
function completionlink_add_instance(stdClass $data, $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    $instanceid = $DB->insert_record('completionlink', $data);

    // Backfill completion for any users who have already completed the target
    // course before this activity was added. This must be deferred: core has not
    // yet written the instance id onto the course_modules row at this point, so
    // a synchronous backfill could not resolve the cm and would silently no-op.
    completionlink_queue_backfill((int) $data->course, $instanceid, (int) $data->targetcourseid);
    completionlink_warn_if_no_enrolment_route((int) $data->course, (int) $data->targetcourseid);

    return $instanceid;
}

/**
 * Update an existing completionlink instance.
 *
 * @param  stdClass $data  Form data from mod_form.
 * @param  object   $mform The form object (unused but required by signature).
 * @return bool            True on success.
 */
function completionlink_update_instance(stdClass $data, $mform = null): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timemodified = time();

    $result = $DB->update_record('completionlink', $data);

    // Re-backfill in case the target course was changed. Deferred so large
    // courses cannot time out the form submission.
    completionlink_queue_backfill((int) $data->course, (int) $data->id, (int) $data->targetcourseid);
    completionlink_warn_if_no_enrolment_route((int) $data->course, (int) $data->targetcourseid);

    return $result;
}

/**
 * Show the teacher a warning if host-course students have no way into the target course.
 *
 * The plugin does not enrol anyone; this only flags a target course with no course meta
 * link from the host course, no open self enrolment and no guest access.
 *
 * @param  int $hostcourseid   ID of the course containing the activity.
 * @param  int $targetcourseid ID of the linked course.
 * @return void
 */
function completionlink_warn_if_no_enrolment_route(int $hostcourseid, int $targetcourseid): void {
    global $DB;

    if (!$targetcourseid || \mod_completionlink\local\enrolment_access::get_routes($targetcourseid, $hostcourseid)) {
        return;
    }
    $name = $DB->get_field('course', 'fullname', ['id' => $targetcourseid]);
    if ($name !== false) {
        \core\notification::warning(get_string('noenrolmentroute', 'mod_completionlink', format_string($name)));
    }
}

/**
 * Adjust the activity link for the current user.
 *
 * Users who can open the target course keep the direct link to it. Everyone else is sent
 * to view.php, which explains that they are not enrolled and how to get access.
 *
 * @param  cm_info $cm Course module info for the current user.
 * @return void
 */
function completionlink_cm_info_dynamic(cm_info $cm): void {
    $userid = $cm->get_modinfo()->get_user_id();
    $customdata = (array) $cm->get_custom_data();
    $targetcourseid = (int) ($customdata['targetcourseid'] ?? 0);
    if (!$targetcourseid || $userid <= 0) {
        return;
    }
    if (!\mod_completionlink\local\enrolment_access::can_access($targetcourseid, $userid)) {
        $cm->set_on_click('');
    }
}

/**
 * Queue an ad hoc task that backfills completion for one completionlink instance.
 *
 * Used after add, update, duplicate and restore so the backfill runs on cron
 * rather than inside the originating web request.
 *
 * @param  int $hostcourseid    ID of the course containing the completionlink activity.
 * @param  int $instanceid      ID of the mdl_completionlink row.
 * @param  int $targetcourseid  ID of the course whose completion is tracked.
 * @return void
 */
function completionlink_queue_backfill(int $hostcourseid, int $instanceid, int $targetcourseid): void {
    if (!$targetcourseid) {
        return;
    }

    $task = new \mod_completionlink\task\backfill_completion();
    $task->set_custom_data([
        'hostcourseid'   => $hostcourseid,
        'instanceid'     => $instanceid,
        'targetcourseid' => $targetcourseid,
    ]);
    \core\task\manager::queue_adhoc_task($task, true);
}

/**
 * Update the completionlink activity completion state for all host-course users
 * who have already completed the target course.
 *
 * Called after add/update so that pre-existing course completions are not
 * silently ignored when the activity is first created or the target is changed.
 *
 * context_course::instance() is hoisted outside the per-user loop so it is
 * resolved once per call rather than once per enrolled user.
 *
 * @param  int $hostcourseid    ID of the course containing the completionlink activity.
 * @param  int $instanceid      ID of the mdl_completionlink row.
 * @param  int $targetcourseid  ID of the course whose completion is tracked.
 * @return void
 */
function completionlink_backfill_completion(int $hostcourseid, int $instanceid, int $targetcourseid): void {
    global $DB;

    if (!$targetcourseid) {
        return;
    }

    $cm = get_coursemodule_from_instance('completionlink', $instanceid, $hostcourseid, false, IGNORE_MISSING);
    if (!$cm) {
        return;
    }

    $course     = get_course($hostcourseid);
    $completion = new completion_info($course);
    if (!$completion->is_enabled($cm)) {
        return;
    }

    // Evaluate users who completed the target course (promote to complete) and
    // users who already have the completionlink marked complete (demote if the target
    // completion was reset). UNION ensures each user is processed at most once.
    $userids = $DB->get_fieldset_sql(
        'SELECT userid FROM {course_completions}
          WHERE course = :targetcourse AND timecompleted IS NOT NULL
         UNION
         SELECT userid FROM {course_modules_completion}
          WHERE coursemoduleid = :cmid AND completionstate > 0',
        ['targetcourse' => $targetcourseid, 'cmid' => $cm->id]
    );

    if (empty($userids)) {
        return;
    }

    // Hoist context resolution outside the per-user loop.
    $hostcontext = context_course::instance($hostcourseid);

    foreach ($userids as $userid) {
        if (!is_enrolled($hostcontext, (int) $userid, '', true)) {
            continue;
        }
        $completion->update_state($cm, COMPLETION_UNKNOWN, (int) $userid);
    }
}

/**
 * Reset user-generated activity data for a completionlink instance.
 *
 * Called by Moodle's course-reset UI. completionlink has no user-generated content
 * (no submissions, no grades); the only user state is activity completion, which
 * is handled by Moodle core's completion-reset path. We declare support here so
 * the reset UI does not show an "unsupported" warning for this module.
 *
 * @param  stdClass $data  Reset form data including the course id.
 * @return array           Status array (empty = nothing to report).
 */
function completionlink_reset_userdata(stdClass $data): array {
    return [];
}

/**
 * Declare reset-form elements for completionlink.
 *
 * completionlink stores no user-generated content, so nothing is added to the
 * reset form. Declaring this function prevents Moodle from reporting the
 * module as not supporting course reset.
 *
 * @param  MoodleQuickForm $mform The reset form.
 * @return void
 */
function completionlink_reset_course_form_definition(&$mform): void {
    // No user data to expose in the reset form.
}

/**
 * Delete a completionlink instance and any associated data.
 *
 * @param  int  $id The instance id.
 * @return bool     True on success.
 */
function completionlink_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('completionlink', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('completionlink', ['id' => $id]);

    return true;
}

// Feature support flags.

/**
 * Declare which Moodle features this module supports.
 *
 * @param  string $feature FEATURE_* constant from lib/moodlelib.php.
 * @return mixed           True, false, or null (null = unknown/unsupported).
 */
function completionlink_supports(string $feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_CONTENT;
        default:
            return null;
    }
}

// Course module info (customdata, direct link, description on course page).

/**
 * Return information about this activity instance for the course module cache.
 *
 * Sets the customcompletionrules key (required by activity_custom_completion base
 * class) and overrides the activity link so the course-page name goes directly
 * to the target course rather than to view.php.
 *
 * @param  stdClass $coursemodule Row from mdl_course_modules.
 * @return cached_cm_info        Populated info object.
 */
function completionlink_get_coursemodule_info($coursemodule) {
    global $DB;

    $instance = $DB->get_record(
        'completionlink',
        ['id' => $coursemodule->instance],
        'id, name, intro, introformat, targetcourseid, completiontracking'
    );
    if (!$instance) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    // Populate customdata so the base activity_custom_completion::get_details()
    // can find the active rules and report them in the completion dropdown.
    $info->customdata = [
        'customcompletionrules' => [
            'completiontracking' => (int) $instance->completiontracking,
        ],
        'targetcourseid' => (int) $instance->targetcourseid,
    ];

    // Show description on the course page when the teacher has enabled it.
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('completionlink', $instance, $coursemodule->id, false);
    }

    // Direct-link: clicking the activity name on the course page opens the
    // target course in a new tab — no intermediate view page required.
    // Use out(true) for the HTML-escaped URL, matching cached_cm_info expectations.
    if (!empty($instance->targetcourseid)) {
        $fullurl = (new moodle_url('/course/view.php', ['id' => $instance->targetcourseid]))->out(true);
        $info->onclick = "window.open('$fullurl', '_blank'); return false;";
    }

    return $info;
}

// Completion.

/**
 * Return the completion rule descriptions shown in the activity completion UI.
 *
 * Called by Moodle core (4.0+). The actual logic lives in
 * classes/completion/custom_completion.php.
 *
 * @param  stdClass $coursemodule Row from mdl_course_modules.
 * @return array                  Array of description strings keyed by rule name.
 */
function completionlink_get_completion_active_rule_descriptions(stdClass $coursemodule): array {
    global $DB;

    $descriptions = [];

    $instance = $DB->get_record('completionlink', ['id' => $coursemodule->instance]);
    if (!$instance || empty($instance->completiontracking)) {
        return $descriptions;
    }

    // Not get_course(): it throws when the course is missing, and the target may have been deleted.
    $targetcourse = $DB->get_record('course', ['id' => $instance->targetcourseid], 'id, fullname');
    if (!$targetcourse) {
        return $descriptions;
    }

    $descriptions['completiontracking'] = get_string(
        'completiondetail:targetcourse',
        'mod_completionlink',
        $targetcourse->fullname
    );

    return $descriptions;
}
