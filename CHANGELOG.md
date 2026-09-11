# Changelog

All notable changes to mod_completionlink will be documented in this file.

## [1.1.1] - 2026-09-11

### Fixed
- The "not enrolled" page no longer shows the activity name twice.

## [1.1.0] - 2026-09-11

Initial public release of Course completion link.

### Features
- Activity that links to another course. Students who can open that course go straight to it in a new tab.
- Custom completion rule "Complete the linked course": the activity is marked complete when the student
  completes the linked course.
- Completion is kept in sync automatically: on course completion, when completion is revoked, when the
  linked course is reset, and by a nightly reconciliation task.
- Existing completions are backfilled when the activity is added, edited, duplicated or restored.
- Searchable target-course selector that scales to large catalogs and is tenant-aware on Moodle Workplace.
- Enrolment guidance: the plugin never enrols anyone, but teachers are warned when students have no way into
  the linked course, and students who are not enrolled see the course's enrolment options or who to contact.
- Backup/restore, Duplicate, Import and Copy course support.
- Group and grouping support.
- Privacy API implementation: the plugin stores no personal data.
- Supports Moodle 4.5 to 5.2.

### Migrating from mod_courselink
This plugin was previously distributed as `mod_courselink` (Course link). Sites with existing `mod_courselink`
activities can move them in place with `cli/migrate_from_courselink.php` — see "Migrating from mod_courselink"
in the README.
