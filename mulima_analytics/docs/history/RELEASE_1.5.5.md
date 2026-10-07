# Learning Analytics 1.5.5

## Correcção dos idiomas

Os textos da interface que estavam fixos em português passam a usar o idioma
activo do Moodle. A correcção abrange Painel, Acessos, Cobertura, Em Risco,
Docentes, Presenças, configurações, permissões e páginas de detalhe.

- Os filtros hierárquicos, a pesquisa de disciplinas e os estados de
  carregamento, erro, repetição e ausência de resultados usam os ficheiros
  de idioma, incluindo os textos produzidos por JavaScript.
- As tabelas, diálogos, legendas e notificações usam as mesmas traduções.
- As listas de presença usam títulos, cabeçalhos, épocas, assinaturas por
  omissão e rodapés traduzidos no Excel, PDF e pré-visualização.
- Os nomes guardados de disciplinas, categorias e pessoas, bem como os textos
  personalizados nas configurações da instituição, continuam a ser conteúdo
  da instituição e não são traduzidos automaticamente.
- O rodapé mostra o nome do autor uma vez e o telefone abre o WhatsApp.

## Actualização

- Componente: `local_learning_analytics`.
- Versão interna: `2026100204`.
- Moodle mínimo: 4.0 (`2022041900`).
- Não há alteração da estrutura da base de dados.

Instalar o ZIP como actualização, concluir **Administração do site >
Notificações** e limpar as caches do Moodle. Recarregar as páginas do plugin
depois de trocar o idioma. Não é necessário desinstalar a versão existente.

## Validação local

- Análise da sintaxe de todos os ficheiros PHP em PHP 7.4.
- 1 245 verificações dos idiomas: chaves, campos de substituição e sete
  modelos PHP renderizados em inglês e português.
- 89 cenários no navegador, com respostas Moodle simuladas: idiomas,
  filtros, pedidos concorrentes, erros, exportação, ecrãs móveis, fóruns,
  trabalhos e pontuação.
- 284 verificações dos textos produzidos pelos modelos de exportação de
  presenças em Excel e PDF, nas três épocas e nos dois idiomas.
- Regressões locais de privacidade, âmbito das categorias, métricas,
  fóruns, trabalhos, pontuação e configuração passaram.

Os ensaios locais usam dados controlados. A instalação, as permissões e os
ficheiros gerados pelas bibliotecas do Moodle devem também ser conferidos
na instalação de destino.
