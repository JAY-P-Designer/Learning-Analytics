# Transfer from the previous component

The visible product remains **Learning Analytics**. Its technical identifier is
now **`local_mulima_analytics`**, with URLs under **`/local/mulima_analytics/`**.
The previous identifier `local_learning_analytics` collides with an unrelated
published Moodle plugin. This package does not reserve the new identifier in the
Marketplace or claim ownership of the unrelated plugin.

## Existing installation of this author's report suite

1. Keep the current installation and take the normal site/database/file backup.
2. Install the ZIP as a new local plugin, alongside `local_learning_analytics`.
3. Complete **Site administration > Notifications**.
4. Open the new **Learning Analytics > Settings > Transfer previous settings >
   Review transfer**, or `/local/mulima_analytics/migration.php` as a site administrator.
5. Review the counts and execute the transfer. Verify each report, role access,
   scoring configuration and logos in the new installation.
6. After verification, uninstall the old component through Moodle administration
   and remove its old code as Moodle instructs. Update bookmarks and other
   integrations that still point to the old URLs.

The transfer requires `moodle/site:config`, a POST request and a valid session key.
A lock prevents concurrent transfers; a completion marker makes repeated requests
safe. Database changes are transactional. The marker is written only on success.
An installation error or test failure does not mean the old component should be
uninstalled.

## What is transferred

- All saved source configuration except its internal version and migration markers.
  Values `0` and empty strings are retained. Settings already saved in the new
  component are kept, and their count is shown in the preview.
- All nine report-suite capability records: allows, prevents, prohibits and context
  overrides. Destination permissions are replaced with the source matrix; fresh
  default grants absent from the source are removed. This intentionally preserves
  restrictions as well as access. The simple role-selection UI is not rerun.
- System-context `logo_pdf` and `logo_excel` files through Moodle's Files API.
  Source files and their recorded owners are retained. If an area already has a
  configured destination logo, it is kept and that source area is skipped. Counts
  include directory metadata; they are not necessarily a count of visible images.
- Custom menu URLs for the previous component on this site. Labels, language
  suffixes, query strings and unrelated or external URLs are preserved.

The source configuration, permissions and files are not removed. No course,
student, submission, grade or forum is copied or modified: reports read the
existing Moodle records. Historical export events remain in the enabled log
stores, under their original component. Old bookmarks and external integrations
are not redirected automatically.

The tool recognises the previous report suite by its version and complete
capability signature. It does not import an installation just because the old
component name exists, and it does not migrate `local_listas_exame` or
`local_relatorio_die` installations. Those require their own migration plan.
If the old component was already uninstalled, use a prior backup to recover its
settings; the tool cannot reconstruct deleted configuration or permissions.

## Em português

Instale `local_mulima_analytics` ao lado da versão anterior, conclua Notificações
e abra **Configurações > Transferir definições anteriores > Rever transferência**
na nova instalação. Confira os elementos e execute. As permissões anteriores
substituem as novas, incluindo bloqueios; as definições e logótipos já configurados
no destino são mantidos. A instalação antiga só deve ser removida depois de
verificar o resultado. Actualize também os favoritos com o novo endereço.
