# Learning Analytics 1.5.2

## Public distribution preparation: step 2

Replaces the incorrect no-personal-data declaration with a Privacy API provider.

- Describes uploaded logos in the Files API and report export events in Moodle logs.
- Finds logo owners and their system context, exports only the requesting user's
  files and metadata, and supports individual, bulk-user and whole-context deletion.
- Restricts file operations to this component's `logo_pdf` and `logo_excel` areas.
  Other users' files, other components and the Moodle source records are preserved
  during individual requests.
- Records the current user's ID on new logo uploads. Existing unattributed logos
  are not assigned to an assumed owner; the upgrade guide explains their handling.
- Uses Moodle's request temporary directory for the PDF logo copy, so its cleanup
  is registered with Moodle rather than leaving an unmanaged temporary file.
- Adds English and Portuguese metadata strings, `PRIVACY.md`, privacy regression
  checks and Moodle integration tests.

No database migration is required. The report filters, calculations and scoring
rules retain their existing behaviour.

## Validation scope

Local validation passed 71 privacy assertions with controlled SQLite fixtures and
PHP syntax checks for all 62 PHP files. It also checks translation keys and the
installable ZIP structure. The included Moodle
integration tests require an installed Moodle test environment; they have not been
run against the destination Moodle server as part of this release.

## Remaining publication work

1. Replace institution-specific defaults and complete translatable interface text.
2. Prepare English public documentation, repository/support links and screenshots
   using fictional data.
3. Validate installation, upgrades, privacy requests and report behaviour on the
   Moodle versions and database engines to be advertised, then submit for review.

Component: `local_learning_analytics`.
Release: `1.5.2`.
Internal version: `2026100201`.
