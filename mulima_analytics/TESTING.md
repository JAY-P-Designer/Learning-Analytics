# Testing Learning Analytics 1.0.1

## Checks completed for this package

Standalone PHP 7.4 fixtures execute production report SQL against SQLite and
use doubles for the required Moodle APIs. Browser suites execute the delivered
inline scripts and course picker in Chromium with simulated endpoint responses.
The language fixtures are rendered from the production PHP page bodies.

| Check | Result | Scope and limit |
| --- | --- | --- |
| Legacy migration | 106 assertions passed | Settings, permission matrix, file ownership, isolation, menu rewrites, lock/authentication checks, retry and rollback; uses API doubles |
| Report SQL and scoring | All eight existing runners passed | Category scope, risk cohorts, teacher metrics, forum inventory, assignments, exports and score settings; some runners include earlier checks |
| Privacy | 71 assertions passed | Provider SQL, approved users/contexts and logo isolation with API doubles |
| Localisation | 1292 checks passed | EN/PT language parity, seven report/settings page bodies and three migration states |
| Attendance export text | 284 assertions passed | XLSX/PDF output calls for three examination sessions in both languages; captures writer calls rather than final real Moodle binaries |
| Browser interactions | 89 scenarios passed | Accesses, additional tabs, empty categories, forum participation, assignments, scoring and localisation, desktop/mobile fixtures |

The package structure, English language filename, component references and PHP/JS
syntax are checked separately. These checks do not constitute Moodle installer
validation or a Marketplace review.

## Repeat standalone checks

Run each PHP fixture in a separate process with PDO SQLite and mbstring available:

```sh
php tests/regression/access_scope.php
php tests/regression/report_metrics.php
php tests/regression/forum_participation.php
php tests/regression/forum_export.php
php tests/regression/forum_inventory.php
php tests/regression/assignment_progress.php
php tests/regression/teacher_scoring.php
php tests/regression/privacy.php
php tests/regression/legacy_migration.php
LANGUAGE_FIXTURE_DIR=/tmp/la-language-fixtures php tests/regression/localization.php
```

Browser dependencies are declared in `tests/browser/package.json`. Install them
in that directory, then run the package's test script with Playwright Chromium
available. Set `LANGUAGE_FIXTURE_DIR` to the fixtures generated above, and
`CHROMIUM_EXECUTABLE_PATH` when using a separately installed Chromium. Store
screenshots outside the release directory with `TEST_ARTIFACT_DIR`.

## Moodle integration still required before submission

The included `advanced_testcase` tests use the real Moodle database and Files API.
They have not been executed in the standalone runtime used for this package.
Run them in disposable Moodle installations, starting with the minimum supported
Moodle 4.0 and versions proposed for the Marketplace listing:

```sh
vendor/bin/phpunit local/mulima_analytics/tests
```

Also complete installer validation, Moodle plugin CI/prechecks, manual AJAX and
export checks under the site's theme, and one migration from an actual previous
installation. Check a permitted role and a denied role, logos and scoring before
uninstalling the previous component. Test the rollback path on disposable data,
not on a live site. Verify the enabled Moodle log store's privacy/retention
behaviour separately. Use fictional people and courses in public screenshots.

Minimum-version metadata is a declared requirement, not proof that every later
Moodle release is compatible. Do not list a Moodle release as tested solely
because its version number passes the installer requirement.
