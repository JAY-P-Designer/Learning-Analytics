# Learning Analytics: data and privacy

This document describes release 1.5.2 (`local_mulima_analytics`). It is intended
for site administrators and reviewers of the plugin's Privacy API implementation.

## Data inventory

| Data | Purpose and storage | Privacy responsibility |
| --- | --- | --- |
| User names, email addresses, identifiers, enrolments and roles | Read from Moodle to identify people in authorised reports; no separate plugin database copies | The Moodle components owning the original records |
| Course access, inactivity, assignment submissions and grades, forum participation and teacher activity | Read from Moodle; indicators and scores are calculated for the selected report | The source Moodle components and log stores |
| Uploaded institution logos | Moodle Files API, system context, component `local_mulima_analytics`, areas `logo_pdf` and `logo_excel`; the current settings form uploads `logo_pdf` | This plugin's Privacy API provider |
| Logo metadata | Uploader ID, file name, file contents and standard Files API metadata, including timestamps | This plugin exports owned files and metadata, and deletes through the Files API |
| Report export audit events | Moodle event/log system: requesting user, time, usual event metadata including IP where recorded, report type and endpoint name | Enabled Moodle log stores; their export, deletion and retention settings apply |
| Institution names, report footers, scoring rules and role permissions | Site-wide configuration, not a per-user profile or report archive | Site administrators manage these settings; use organisational content, not personal records |
| Generated XLSX/PDF reports | Generated for the authorised user's download; this plugin does not maintain a stored report archive | The recipient and institution manage downloaded copies |
| Temporary PDF logo copy | A Moodle request directory, scheduled for removal by Moodle's shutdown handler | Moodle temporary-file lifecycle |
| Collapsed navigation preference | Browser local storage key `die_col`; no user ID or report rows are stored in this key | The browser; clear site storage to remove it |

There are no plugin-owned database tables and no plugin telemetry service.
The plugin does not automatically send report data to an external analytics service.
Normal Moodle infrastructure, server logs, backups and downloaded files remain
subject to the site's own administration and retention policies.

## Privacy API behaviour

The provider implements metadata, user-data request and bulk user-list interfaces.
It declares a `core_files` link and a `logstore` plugin-type link.

- Context and user discovery considers only this component's logo areas in the
  system context. Directory records are included because they may also contain
  an uploader ID.
- A user export includes only that user's attributed logo files and their file
  metadata, within an approved system context. It never exports a complete shared
  logo area on behalf of one user.
- Individual and bulk user deletion remove only files attributed to positive,
  approved user IDs. Other users' logos and unrelated areas are preserved.
- Deletion for all users in the approved system context removes both plugin logo
  areas, including unattributed legacy files. This can remove the logo in use.
- Core users, courses, enrolments, submissions, grades, forum posts and logs are
  not deleted by this provider. Their owners handle their own privacy requests.
- A removed active logo can be replaced in the report settings. Existing fallback
  branding from `local_listas_exame` may become visible again.

The provider makes no direct database deletions of file records. The Moodle Files
API manages deletion and shared physical file content. Moodle backups and the
file pool's normal cleanup lifecycle are not bypassed.

## Existing installations

Before 1.5.2 the upload handler did not explicitly set `files.userid`. Such logos
can have a null or zero uploader ID. An upgrade cannot reliably reconstruct the
uploader, and does not assign those logos to an arbitrary administrator.

Unattributed logos are not included in individual exports or individual/bulk-user
deletion requests. Administrators should review their content and remove or
replace them in **Settings > General** when needed. Re-uploading records the new
uploader, not the original historical uploader. Retained legacy content must be
reviewed manually if it contains personal information.

Older installations can read branding/configuration from `local_listas_exame`.
This provider never exports or deletes that separate component's files. Review
them through the owning plugin or its administration. Temporary `logo_` files
created by older releases cannot safely be attributed from their names alone;
this upgrade does not scan or delete arbitrary operating-system temporary files.

## Validation

The optional transfer from the author's previous component copies only its
configuration, capability overrides and system-context logo areas. File ownership
is retained where recorded; null or zero legacy owners are not assigned to the
administrator running the transfer. The previous component remains responsible
for its retained original files until it is uninstalled. The transfer completion
marker contains a timestamp and aggregate counts, not a user profile or report
archive. Existing report data and historical export events remain with Moodle.

The standalone regression runner exercises actual provider SQL with controlled
SQLite fixtures and test doubles for Moodle contexts, file storage and export
writers. It checks ownership, both logo areas, approved contexts, empty requests,
legacy files and isolation from other components. It is not a full Moodle test.

Run the included integration tests in an isolated Moodle 4.0+ test installation:

```sh
vendor/bin/phpunit local/mulima_analytics/tests/privacy_provider_test.php
```

Before public submission, also complete one real export/deletion request with
disposable test accounts and logos, check the generated archive, confirm a second
account's logo is preserved and export an attendance PDF with a logo. Verify the
enabled log store's privacy and retention settings separately. Do not use real
personal data for publication screenshots or destructive validation.

## References

- [Moodle Privacy API](https://moodledev.io/docs/apis/subsystems/privacy)
- [Moodle Privacy API FAQ: logs and other subsystems](https://moodledev.io/docs/apis/subsystems/privacy/faq)
- [Moodle Files API privacy provider](https://github.com/moodle/moodle/blob/MOODLE_400_STABLE/files/classes/privacy/provider.php)

This implementation is a technical part of publication preparation; installation
validation and Moodle Marketplace review remain separate stages.
