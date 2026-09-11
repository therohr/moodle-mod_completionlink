# Course Completion Link — mod_completionlink

A Moodle activity module that embeds a direct link to another course and automatically tracks whether the student has completed it. Completion of the linked course drives activity completion on the host course, enabling downstream restriction rules and learning-path gating without any manual grading or custom reports.

**Author:** David Rohr — [tidewatercreative.com](https://tidewatercreative.com)
**Requires:** Moodle 4.5–5.2 (also used on Moodle Workplace 5.0)
**Maturity:** Stable
**License:** GNU GPL v3 or later

---

## How it works

### Activity setup

A teacher adds a **Course Completion Link** activity to a course and selects the target course from a searchable list. On Moodle Workplace the list is automatically filtered to the teacher's tenant. The teacher can also enable the **"Require completion of target course"** completion rule on the same settings screen.

The course selector searches on demand via AJAX as the teacher types, so it scales to any number of courses on the site.

### Student experience

The activity name on the course page is a direct link that opens the target course in a new tab — no intermediate view page. If the teacher enabled "Display description on course page", the description appears inline beneath the link.

### Enrolment in the linked course

This activity does **not** enrol students in the linked course — it only links to it and tracks
completion. Students need their own way in. Common options:

| Option | Best for |
| --- | --- |
| **Course meta link** (in the linked course: Participants → Enrolment methods → Course meta link, choosing this course) | Everyone in this course should be enrolled in the linked course, and removed when they leave this course |
| **Self enrolment** in the linked course | Students choose to join, optionally with an enrolment key, dates or a capacity limit |
| **Guest access** in the linked course | Content students can browse without enrolling (note: guests cannot complete a course, so completion tracking needs a real enrolment) |
| **Moodle Workplace programs / dynamic rules** | Learning paths on Workplace, where the program allocates the courses |

The plugin helps make gaps visible:

- **Teachers** see a warning when saving the activity, and in its settings, if the linked course has no course meta link
  from this course, no open self enrolment and no guest access.
- **Students** who are not enrolled are taken to a page explaining that, with a button to the linked course's enrolment
  options when self enrolment or guest access is available — otherwise it tells them to ask their teacher or
  administrator. Students who can already open the course keep the direct link.

### Completion tracking

Completion state is evaluated in four complementary ways so that no change is ever missed:

| Trigger | Direction | How it works |
| --- | --- | --- |
| **`course_completed` observer** | Promote | When a student finishes the target course the observer queues an ad hoc task that promotes the completionlink activity to complete on the host course. |
| **`course_completion_updated` observer** | Demote | When an admin revokes a user's completion, or the reaggregation cron nulls out `timecompleted`, an ad hoc task immediately demotes the completionlink activity back to incomplete. |
| **`course_reset_ended` observer** | Demote (bulk) | When the target course is reset via Moodle's course-reset UI, an ad hoc task demotes all previously-completed users in one pass. |
| **Nightly scheduled task** | Promote + Demote | A background task reconciles all completionlink instances nightly. Catches completions missed by any of the above (e.g. bulk imports, direct DB writes on non-SaaS instances). |

### Backfill on add / edit

When the activity is first created, or the target course is changed, the plugin retroactively marks complete any enrolled students who have already finished the target course — and demotes any who were previously marked complete but no longer are.

### Custom completion rule

The plugin implements Moodle's `activity_custom_completion` API (introduced in Moodle 4.0). The completion rule **"Complete the linked course: [course name]"** is shown in the activity completion UI and in students' progress reports. The completion state is derived in real time from `mdl_course_completions` — no separate data store is maintained.

### Privacy

Course Completion Link stores no personal data. It reads `mdl_course_completions`, which is owned and managed by Moodle core (`core_completion`). The privacy provider correctly declares this and satisfies GDPR requirements out of the box.

---

## Installation

1. Download the latest release ZIP from Moodle Marketplace or the [GitHub releases](https://github.com/therohr/moodle-mod_completionlink/releases).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Upload the zip and follow the on-screen prompts.
4. Complete the database upgrade (adds the `mdl_completionlink` table).

Alternatively, extract the zip so that the `completionlink` folder sits at `{moodleroot}/mod/completionlink/` (Moodle 5.1+: `{moodleroot}/public/mod/completionlink/`), then visit **Site administration** to trigger the upgrade.

### Requirements

- Moodle 4.5 or later (Moodle Workplace 5.0 supported and tested)
- Course completion must be enabled site-wide: **Site administration → Advanced features → Enable completion tracking**
- Completion must also be enabled on each host course that uses this activity

### Migrating from mod_courselink

Course completion link was previously published as `mod_courselink` (Course link). Sites that have
`mod_courselink` activities can move them to this plugin in place, without rebuilding courses. Each
activity keeps its course module id, so activity completion, access restrictions that depend on it,
course completion criteria, section layout, permission overrides and description files carry over.

1. Take a database backup and enable maintenance mode.
2. Install this plugin alongside `mod_courselink` (Site administration → Notifications).
3. Preview the migration (makes no changes):
   ```
   php mod/completionlink/cli/migrate_from_courselink.php
   ```
   On Moodle 5.1+ the path is `public/mod/completionlink/cli/migrate_from_courselink.php`.
4. Run it:
   ```
   php mod/completionlink/cli/migrate_from_courselink.php --run
   ```
   All changes run in a single database transaction. The script finishes by listing any rows that
   still reference `mod_courselink` (for example from Moodle Workplace tables or other plugins) —
   review these before continuing. Entries in log tables are historical and are expected.
5. Uninstall `mod_courselink` (Site administration → Plugins → Plugins overview), then disable
   maintenance mode.

Not migrated: deleted `mod_courselink` activities sitting in course recycle bins (the script warns
if any exist) and backups made before the migration, which require `mod_courselink` to restore.

---

## Configuration

No site-wide admin settings are required. All configuration is per-activity:

| Setting | Description |
| --- | --- |
| **Target course** | The course whose completion is tracked. Searchable autocomplete, Workplace tenant-filtered, results fetched via AJAX (up to 100 matches per search). |
| **Require completion of target course** | When enabled (default), the activity counts as complete only when the student has completed the target course. Disable to use the activity as a display-only link. |

---

## Known limitations

- **Workplace Programs and Certifications:** If a student completes the target course via a Workplace Program or Certification pathway rather than through direct course completion, the real-time observer may not fire immediately. The nightly scheduled task will reconcile the state overnight.
- **Backup, restore, duplicate and copy:** Supported via `FEATURE_BACKUP_MOODLE2` — this covers activity Duplicate, course Import, Copy course, and backup/restore. The target course is kept as-is. When restoring onto a different site, a target course that doesn't exist there is cleared (with a warning in the restore log) and must be reselected; if a *different* course happens to have the same id on that site, the link will point at it, so check targets after cross-site restores.
- **Cross-tenant linking:** Linking to a course in a different Workplace tenant is possible but not recommended. The target course selector filters by the teacher's tenant.
- **Large course lists:** The target-course autocomplete searches via AJAX (up to 100 matches per search term), so it no longer holds the whole catalog at once — narrow your search if a course doesn't appear in the first page of results.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
