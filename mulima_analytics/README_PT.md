# Learning Analytics 1.0.1

Relatórios de acompanhamento no Moodle: Painel, Acessos, Cobertura, Em Risco,
Docentes e Presenças. Cada área mantém a sua própria lógica de contagem.

- Componente: `local_mulima_analytics`.
- Pasta: `local/mulima_analytics`.
- Versão interna: `2026100206`.
- Moodle mínimo declarado: 4.0 (`2022041900`).
- Autor: Joaquim Pascoal Mulima Junior.
- Apoio: joaquimmulima.ab@gmail.com; [WhatsApp](https://wa.me/258842008122).

## Instalar

Instale o ZIP em **Administração do site > Plugins > Instalar plugins** e conclua
**Notificações**. Em instalação manual, a pasta final deve conter
`local/mulima_analytics/version.php`. Abra **Administração do site > Relatórios >
Learning Analytics**.

Se já usa o nosso componente `local_learning_analytics`, mantenha-o instalado
até concluir a transferência. O novo componente é instalado ao lado do antigo.
Abra **Learning Analytics > Configurações > Transferir definições anteriores >
Rever transferência**, verifique os elementos e execute a transferência. Depois
verifique os relatórios, permissões e logótipos, e só então desinstale o componente
anterior. Consulte [MIGRATION.md](MIGRATION.md).

As actualizações futuras de `local_mulima_analytics` serão feitas normalmente
sobre este componente. Conclua Notificações e limpe as caches depois de actualizar.

## Configurações

A ligação **Configurações** permite gerir pontuação dos docentes, dados da
instituição, logótipo, listas e permissões. Os filtros seguem a árvore real de
categorias e mostram o nome completo da disciplina. As selecções em branco não
incluem automaticamente disciplinas de todas as categorias descendentes.

Esta edição mantém a autoria, o contacto e a oferta de uma edição sem marca de
água. A sua classificação gratuita ou paga no Marketplace ainda precisa de ser
resolvida antes da submissão. Não existe um servidor de licenças ou assinatura
criptográfica de pacotes implementados.

Licença GNU GPL v3 ou posterior: [COPYING.txt](COPYING.txt). Os testes locais
não substituem a validação numa instalação real do Moodle nem a revisão do
Marketplace. Consulte [TESTING.md](TESTING.md), [PRIVACY.md](PRIVACY.md) e
[RELEASE_1.0.1.md](RELEASE_1.0.1.md). As notas em `docs/history` são históricas.
