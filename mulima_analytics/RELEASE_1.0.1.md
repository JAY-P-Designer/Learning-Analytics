# Learning Analytics 1.0.1

- New technical component: `local_mulima_analytics`.
- Directory: `local/mulima_analytics`.
- Internal upgrade version: `2026100206`.
- Minimum declared Moodle version: 4.0 (`2022041900`).
- Visible product name remains Learning Analytics.

Component references, namespaces, language filenames, capabilities, callbacks,
AMD calls, template names, report URLs, AJAX and export endpoints use the new
identifier. Legacy global helpers have also been namespaced to avoid conflicts
while the old component remains installed.

A site-administrator transfer screen previews configuration, permission, logo
and custom-menu counts. It preserves granular access restrictions, existing
new configuration and logo areas, and the old installation. It rejects unrelated
components sharing the previous identifier, uses a session key and lock, rolls
back database changes on failure, and does not run again after completion.

Use [MIGRATION.md](MIGRATION.md) for an existing installation. Install as a separate
component and transfer before uninstalling the old one. Future releases of the
new component can be installed as normal updates.

Current installation instructions are available in English and Portuguese.
Earlier release notes are retained in `docs/history` and are not instructions
for installing this package. This release resolves the technical naming change;
Marketplace edition classification, full Moodle integration tests, repository
publication and submission review are still separate work.
