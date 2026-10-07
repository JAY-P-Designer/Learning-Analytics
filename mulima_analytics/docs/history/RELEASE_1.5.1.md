# Learning Analytics 1.5.1

## Public distribution preparation: step 1

This release documents the plugin licence and authorship before the remaining
Moodle Marketplace submission work.

- Adds the complete, unmodified GNU General Public License version 3 text in
  `COPYING.txt`. The plugin is licensed under GPL version 3 or any later version.
- Adds licence and copyright headers to all PHP files, browser scripts, the
  compiled JavaScript module, the stylesheet and the Mustache template.
- Records the author already identified in the plugin README:
  Joaquim Pascoal Mulima Junior. Existing source comments and credits are retained.
- Adds `GPL-3.0-or-later` to the browser test package metadata.
- Updates the licence documentation and release metadata.

Report calculations, filters, permissions and database structures are unchanged.
The JavaScript build retains the same executable content as version 1.5.0.

## Validation

The release checks verify PHP syntax, licence header coverage, preservation of
executable source, the complete licence text and the installable ZIP structure.
This documentation release does not add new functional compatibility claims.

## Remaining publication work

1. Correct and review the Privacy API implementation and data declarations.
2. Replace institution-specific defaults and complete translatable interface text.
3. Prepare English documentation, public repository/support links and screenshots
   using fictional data.
4. Validate installation, upgrades and report behaviour on the Moodle versions
   and database engines to be advertised, then submit for Marketplace review.

This release completes the licensing preparation step. It has not been submitted
to or approved by Moodle Marketplace.

Component: `local_learning_analytics`.
Release: `1.5.1`.
Internal version: `2026100200`.
