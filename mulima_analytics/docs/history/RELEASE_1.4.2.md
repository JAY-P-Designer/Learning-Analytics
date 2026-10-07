# Learning Analytics 1.4.2

Correcção de Cobertura, Em Risco e Docentes a partir da versão 1.4.1,
na qual o funcionamento de Acessos já tinha sido validado pelo utilizador.

## Filtros nas três áreas

- O identificador da categoria é guardado num campo oculto próprio, evitando
  a perda do valor que ocorria ao atribuí-lo a um select sem a opção correspondente.
- Cada nível apresenta as categorias filhas do anterior. A selecção abrange as
  disciplinas directamente nessa categoria e nas suas subcategorias, sem limite
  artificial de profundidade. Categorias vizinhas ficam excluídas.
- A pesquisa de disciplinas de Em Risco e Docentes respeita a mesma selecção.
  Uma disciplina de outra categoria é também rejeitada no servidor.
- Os controlos usam a mesma estrutura de filtros, com disposição adaptada a
  computador e telemóvel, rótulos associados e mensagens de erro com repetição.
- A mudança de selecção cancela pedidos anteriores e ignora respostas antigas.
  Um intervalo de 180 ms reduz o arranque de relatórios durante mudanças rápidas.
- As listas de categorias usam contagens em lote. As consultas pesadas libertam
  o bloqueio de sessão depois da autenticação, permissões e validação da sesskey,
  conforme a [documentação do Moodle](https://docs.moodle.org/dev/Session_locks).

## Comportamento próprio de cada relatório

| Área | Critérios e correcções |
| --- | --- |
| Cobertura | Mantidos os cálculos de itens avaliáveis, Plano de Avaliação, pontuação, submissões e correcções pendentes. A meta continua a reclassificar os resultados sem repetir a consulta. Página e Excel usam a mesma árvore de categorias. |
| Em Risco | Mantidos o limiar de dias e os níveis de risco. Plataforma em geral conta cada estudante uma vez e usa `user.lastaccess`, incluindo acessos fora das disciplinas seleccionadas. Por disciplinas mantém cada par estudante/disciplina e usa o mais recente entre os logs disponíveis e `user_lastaccess`. O Excel recebe e aplica o âmbito escolhido. |
| Docentes | Mantidos os critérios do relatório em ecrã, os perfis que este já considerava e os pesos 3/2/4/1, com limite de pontuação 99. O Excel passa a utilizar exactamente esse mesmo cálculo em lote. O intervalo e a disciplina seleccionada são aplicados em ambos. O rótulo de 365 dias passa a indicar Últimos 365 dias, de acordo com o cálculo existente. |

A categoria e a disciplina limitam a população de estudantes analisada em
Plataforma em geral, mesmo quando o último acesso foi a outra parte do Moodle.
Isto corrige o caso de estudantes activos na plataforma serem considerados
inactivos apenas por não terem entrado recentemente na categoria seleccionada.
No modo Por disciplinas, os totais representam registos estudante/disciplina;
o texto de distribuição identifica essa unidade.

As páginas, os endpoints e a exportação de Acessos não foram modificados.
O auxiliar de selecção de categorias passou a aceitar uma disciplina opcional
para uso nas novas áreas, mantendo o seu comportamento anterior por omissão.
Painel, Presenças, configurações e layout geral não foram alterados.

## Testes executados

- **31 cenários Chromium** para as três áreas: inicialização, categorias em
  cascata, folhas, disciplinas directas e descendentes, selecção Todos, ausência
  de resultados, respostas atrasadas, cancelamento, erros HTTP e repetição,
  parâmetros de exportação, pesquisa, critérios específicos e dimensões dos
  controlos em computador e telemóvel.
- **15 cenários Chromium de regressão de Acessos**, todos aprovados.
- **36 verificações PHP/SQL** com PDO SQLite: âmbito de categorias e disciplinas,
  selecções inválidas, contagens, deduplicação, último acesso à plataforma,
  diferença entre os dois âmbitos de risco, limiar de dias, papéis dos docentes,
  intervalo de actividade e cálculo de pontuação.
- Verificação de sintaxe PHP, nome do componente, nomes dos ficheiros de idioma
  e integridade do ZIP. Comparação dos ficheiros confirmou que os cálculos de
  Cobertura e as páginas/endpoints de Acessos foram preservados.

Os testes de navegador executam o código incluído no pacote com respostas
simuladas do Moodle. Os testes SQL executam os auxiliares de produção com dados
controlados. Não substituem uma instalação no Moodle de destino: a geração
final do ficheiro XLSX pelas bibliotecas do Moodle, as permissões e o desempenho
com os logs reais devem ser validados nesse ambiente. Não foi efectuado acesso
às contas ou dados reais da instituição nem uma medição de tempo nesse servidor.

## Reexecutar

```sh
php tests/regression/report_metrics.php
cd tests/browser
npm install
npx playwright install chromium
npm test
```

Requer PHP com PDO SQLite e Node.js para os testes locais. O plugin instalado
continua a usar as dependências e a base de dados do próprio Moodle.
As instruções de actualização e validação estão em `UPGRADING.md`.
