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
 * One-off migration of mod_courselink activities to mod_completionlink.
 *
 * mod_completionlink is mod_courselink renamed. This script moves every existing
 * courselink activity to the new module in place: course_modules rows keep their ids,
 * so module contexts, activity completion, access restrictions, course completion
 * criteria, section layout and logs stay attached. Only the rows that name the old
 * module or component are rewritten.
 *
 * Dry run by default; pass --run to make changes. All changes run in one database
 * transaction. Put the site in maintenance mode and take a database backup first.
 *
 * @package   mod_completionlink
 * @copyright 2026 David Rohr (tidewatercreative.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$options, $unrecognised] = cli_get_params(['run' => false, 'help' => false], ['h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Migrate mod_courselink activities to mod_completionlink, keeping course module ids.

Options:
--run         Perform the migration. Without it, only report what would change.
-h, --help    Print this help.

Before running:
  1. Install mod_completionlink (Site administration > Notifications).
  2. Enable maintenance mode and back up the database.

Example:
\$ php mod/completionlink/cli/migrate_from_courselink.php          (dry run)
\$ php mod/completionlink/cli/migrate_from_courselink.php --run

Afterwards, uninstall mod_courselink from Site administration > Plugins.");
    exit(0);
}

$dbman = $DB->get_manager();

$oldmodule = $DB->get_record('modules', ['name' => 'courselink']);
$newmodule = $DB->get_record('modules', ['name' => 'completionlink']);
if (!$oldmodule || !$dbman->table_exists('courselink')) {
    cli_error('mod_courselink is not installed on this site; nothing to migrate.');
}
if (!$newmodule || !$dbman->table_exists('completionlink')) {
    cli_error('mod_completionlink is not installed. Install it from Site administration > Notifications first.');
}

$instances = $DB->get_records('courselink', null, 'id');
$cms = $DB->get_records('course_modules', ['module' => $oldmodule->id], 'id', 'id, course, instance');
$capsql = $DB->sql_like('capability', ':cap');
$capparams = ['cap' => 'mod/courselink:%'];

$orphancms = array_filter($cms, static fn($cm) => !isset($instances[$cm->instance]));
$plan = [
    'courselink instances'                 => count($instances),
    'course modules'                       => count($cms) - count($orphancms),
    'course modules with missing instance' => count($orphancms),
    'description files'                    => $DB->count_records('files', ['component' => 'mod_courselink']),
    'role capability rows'                 => $DB->count_records_select('role_capabilities', $capsql, $capparams),
    'calendar events'                      => $DB->count_records('event', ['modulename' => 'courselink']),
    'grade items'                          => $DB->count_records('grade_items', ['itemmodule' => 'courselink']),
    'course completion criteria'           => $DB->count_records('course_completion_criteria', ['module' => 'courselink']),
    'course completion defaults'           => $DB->count_records('course_completion_defaults', ['module' => $oldmodule->id]),
    'recent activity entries'              => $dbman->table_exists('block_recent_activity')
        ? $DB->count_records('block_recent_activity', ['modname' => 'courselink']) : 0,
    'queued ad hoc tasks (deleted)'        => $DB->count_records('task_adhoc', ['component' => 'mod_courselink']),
];

cli_heading('mod_courselink -> mod_completionlink migration');
foreach ($plan as $label => $count) {
    cli_writeln(str_pad($label, 40) . $count);
}
if ($dbman->table_exists('tool_recyclebin_course')) {
    $binned = $DB->count_records('tool_recyclebin_course', ['module' => $oldmodule->id]);
    if ($binned) {
        cli_writeln("\nWarning: {$binned} deleted courselink activities are in course recycle bins. They are not " .
            "migrated and cannot be restored once mod_courselink is uninstalled.");
    }
}
foreach ($orphancms as $cm) {
    cli_writeln("Warning: course module {$cm->id} (course {$cm->course}) points to missing courselink instance " .
        "{$cm->instance}; it will be left unchanged.");
}

if (!$options['run']) {
    cli_writeln("\nDry run only - nothing was changed. Re-run with --run to migrate.");
    exit(0);
}

$transaction = $DB->start_delegated_transaction();

// Copy instances, keeping a map from old to new instance ids.
$instancemap = [];
foreach ($instances as $instance) {
    $oldid = $instance->id;
    unset($instance->id);
    $instancemap[$oldid] = $DB->insert_record('completionlink', $instance);
}

// Point course modules at the new module. Their ids, and therefore contexts, are unchanged.
$courseids = [];
foreach ($cms as $cm) {
    if (!isset($instancemap[$cm->instance])) {
        continue;
    }
    $DB->update_record('course_modules', (object) [
        'id'       => $cm->id,
        'module'   => $newmodule->id,
        'instance' => $instancemap[$cm->instance],
    ]);
    $courseids[$cm->course] = true;
}

// Files: the component is part of the pathname hash, so it must be recalculated.
$files = $DB->get_records(
    'files',
    ['component' => 'mod_courselink'],
    'id',
    'id, contextid, filearea, itemid, filepath, filename'
);
foreach ($files as $file) {
    $hash = file_storage::get_pathname_hash(
        $file->contextid,
        'mod_completionlink',
        $file->filearea,
        $file->itemid,
        $file->filepath,
        $file->filename
    );
    if ($DB->record_exists('files', ['pathnamehash' => $hash])) {
        throw new coding_exception("File {$file->id} already exists under mod_completionlink; aborting.");
    }
    $DB->update_record('files', (object) ['id' => $file->id, 'component' => 'mod_completionlink', 'pathnamehash' => $hash]);
}

// Role capabilities: rename overrides; where the new capability already has a row for the same
// role and context (system defaults added on install), keep the old permission and drop the old row.
foreach ($DB->get_records_select('role_capabilities', $capsql, $capparams) as $rc) {
    $newcap = 'mod/completionlink:' . substr($rc->capability, strlen('mod/courselink:'));
    if (!$DB->record_exists('capabilities', ['name' => $newcap])) {
        cli_writeln("Warning: {$newcap} does not exist; role_capabilities row {$rc->id} left unchanged.");
        continue;
    }
    $existing = $DB->get_record(
        'role_capabilities',
        ['roleid' => $rc->roleid, 'contextid' => $rc->contextid, 'capability' => $newcap]
    );
    if ($existing) {
        if ((int) $existing->permission !== (int) $rc->permission) {
            $DB->update_record(
                'role_capabilities',
                (object) ['id' => $existing->id, 'permission' => $rc->permission, 'timemodified' => time()]
            );
        }
        $DB->delete_records('role_capabilities', ['id' => $rc->id]);
    } else {
        $DB->update_record('role_capabilities', (object) ['id' => $rc->id, 'capability' => $newcap]);
    }
}

// Rows that name the module, with instance ids remapped where present.
foreach ($DB->get_records('event', ['modulename' => 'courselink']) as $event) {
    $DB->update_record('event', (object) [
        'id'         => $event->id,
        'modulename' => 'completionlink',
        'instance'   => $instancemap[$event->instance] ?? $event->instance,
        'component'  => $event->component === 'mod_courselink' ? 'mod_completionlink' : $event->component,
    ]);
}
foreach ($DB->get_records('grade_items', ['itemmodule' => 'courselink']) as $item) {
    $DB->update_record('grade_items', (object) [
        'id'           => $item->id,
        'itemmodule'   => 'completionlink',
        'iteminstance' => $instancemap[$item->iteminstance] ?? $item->iteminstance,
    ]);
}
// Criteria reference the course module id in moduleinstance, which is unchanged.
$DB->set_field('course_completion_criteria', 'module', 'completionlink', ['module' => 'courselink']);
if ($dbman->table_exists('block_recent_activity')) {
    $DB->set_field('block_recent_activity', 'modname', 'completionlink', ['modname' => 'courselink']);
}
foreach ($DB->get_records('course_completion_defaults', ['module' => $oldmodule->id]) as $default) {
    if ($DB->record_exists('course_completion_defaults', ['course' => $default->course, 'module' => $newmodule->id])) {
        $DB->delete_records('course_completion_defaults', ['id' => $default->id]);
    } else {
        $DB->set_field('course_completion_defaults', 'module', $newmodule->id, ['id' => $default->id]);
    }
}

// Queued tasks reference mod_courselink classes; the nightly check_completion task reconciles.
$DB->delete_records('task_adhoc', ['component' => 'mod_courselink']);

$transaction->allow_commit();

foreach (array_keys($courseids) as $courseid) {
    rebuild_course_cache($courseid, true);
}
purge_all_caches();

cli_writeln("\nMigrated " . count($instancemap) . ' instance(s) across ' . count($courseids) . ' course(s).');

// Report anything still naming the old plugin, e.g. tables from Moodle Workplace or other plugins.
// Plugin registrations (capabilities, config, modules, scheduled tasks...) are removed on uninstall.
$registrations = ['capabilities', 'config_plugins', 'modules', 'events_handlers', 'external_functions',
    'task_scheduled', 'message_providers', 'upgrade_log', 'log_display', 'courselink', 'completionlink'];
$leftovers = [];
foreach ($DB->get_tables(false) as $table) {
    if (in_array($table, $registrations, true)) {
        continue;
    }
    foreach ($DB->get_columns($table, false) as $column) {
        if ($column->meta_type !== 'C') {
            continue;
        }
        if (in_array($column->name, ['component', 'modulename', 'itemmodule', 'modname', 'module'], true)) {
            $count = $DB->count_records_select($table, "{$column->name} IN ('courselink', 'mod_courselink')");
        } else if ($column->name === 'capability') {
            $count = $DB->count_records_select($table, $DB->sql_like($column->name, ':cap'), $capparams);
        } else {
            continue;
        }
        if ($count) {
            $leftovers[] = "{$table}.{$column->name}: {$count}";
        }
    }
}
if ($leftovers) {
    cli_writeln("\nRows still referencing mod_courselink (review; log tables are historical and expected):");
    foreach ($leftovers as $line) {
        cli_writeln('  ' . $line);
    }
} else {
    cli_writeln("\nNo remaining references to mod_courselink outside plugin registrations.");
}
cli_writeln("\nNext: uninstall mod_courselink from Site administration > Plugins, then disable maintenance mode.");
