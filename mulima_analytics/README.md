# Learning Analytics

Learning Analytics provides six integrated Moodle reports for course activity,
assessment coverage, students who need attention, teacher participation and
attendance. Reports use the Moodle data already stored on the site and share
hierarchical category filters. Each report retains its own counting rules.

- **Component:** `local_mulima_analytics`
- **Directory:** `local/mulima_analytics`
- **Release:** 1.0.1; internal version `2026100206`
- **Minimum declared Moodle version:** 4.0 (`2022041900`)
- **Languages:** English and Portuguese
- **Author:** Joaquim Pascoal Mulima Junior
- **Support:** joaquimmulima.ab@gmail.com; [WhatsApp](https://wa.me/258842008122)

[Portuguese instructions](README_PT.md) · [Migration](MIGRATION.md) ·
[Release notes](RELEASE_1.0.1.md) · [Privacy](PRIVACY.md) · [Testing](TESTING.md)

## Installation

Install the ZIP through **Site administration > Plugins > Install plugins**, or
extract the `mulima_analytics` directory into Moodle's `local` directory. The final
path must be `local/mulima_analytics/version.php`. Complete **Site administration
> Notifications**, then open **Site administration > Reports > Learning Analytics**.

For existing installations of the author's `local_learning_analytics`, follow
[MIGRATION.md](MIGRATION.md) first. This is a different component, not an in-place
upgrade. Do not rename the old directory or uninstall it before transferring
its settings, permissions and logos.

Future releases of `local_mulima_analytics` can be installed as updates to this
component. After updating, complete Notifications and purge Moodle caches.

## Reports

| Report | Purpose | Page |
| --- | --- | --- |
| Dashboard | Course assessment overview and drill-down | `index.php` |
| Accesses | Activity views and participating students | `acessos.php` |
| Coverage | Assessment points, grading progress and pending submissions | `cobertura.php` |
| At risk | Inactivity in courses or across the platform; platform mode counts unique students | `risco.php` |
| Teachers | Activities, resources, submissions, forum participation and configurable scoring | `docentes.php` |
| Attendance | Attendance lists and institution settings, XLSX/PDF exports | `presenca.php` |

Choose an execution period, then the appropriate child categories. Additional
category selectors follow the actual tree. Blank child selections do not silently
collect all descendant courses. A selected leaf category includes its own courses.
Course filters display full course names. Reports start empty until a category
scope is chosen, except for courses stored directly in the selected period.

Teacher forum counts include existing forums without discussions and forums
created by other people. Participation counts the teacher's own posts. Assignment
progress uses submitted work and corrected/pending counts; it is separate from
recent grading actions by a particular teacher. Teacher scores use configurable
weights and show their calculation by course.

## Settings and access

Use the report's **Settings** link for general settings, teacher scoring,
institution details, logos and permissions. Site administrators can open
**Transfer previous settings** when a compatible previous installation is found.

Each report has a separate system capability. Exporting also requires
`local/mulima_analytics:exportdata`; attendance settings require
`local/mulima_analytics:manageattendance`. The Manager archetype has initial
access. Review role permissions before giving access to reports with personal
data. Site configuration and migration require `moodle/site:config`.

## Licensing and edition

Copyright (C) 2026 Joaquim Pascoal Mulima Junior. This plugin is licensed under
GNU GPL version 3 or later (`GPL-3.0-or-later`). The complete licence is included
in [COPYING.txt](COPYING.txt). You may use, study, modify and redistribute it under
that licence. It is supplied without warranty.

This package retains the author's attribution and contact offer for a separate
watermark-free edition. Marketplace free/paid classification must be resolved
before submitting this edition; this ZIP is not evidence of Marketplace approval.
There is no implemented licence server or cryptographic build signing.

## Validation status

Local regression fixtures test report SQL and browser interactions with simulated
Moodle responses. Native Moodle integration tests are also included, but must be
run on an isolated Moodle installation. Declaring Moodle 4.0 as the minimum does
not establish compatibility with every later Moodle release. See [TESTING.md](TESTING.md).

Historical release notes are retained in `docs/history`. Their old component
names, release numbers and installation instructions describe the earlier
packages; use the current instructions above for this release.
