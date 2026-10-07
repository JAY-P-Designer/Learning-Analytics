# Learning Analytics 1.5.0

## Pontuação dos docentes

Em **Configurações > Geral > Pontuação dos docentes**, a gestão do site pode
definir os pontos por acção, o limite máximo e os níveis moderado e alto.
O formulário valida os valores e guarda as regras em conjunto. Tem o seu
próprio botão **Guardar**, separado das restantes configurações.

| Critério | Contagem no intervalo seleccionado | Peso inicial |
| --- | --- | ---: |
| Actividades criadas | Actividades existentes criadas pelo próprio docente, excluindo recursos | 3 |
| Recursos publicados | Recursos existentes criados pelo próprio docente | 2 |
| Avaliações pelo docente | Avaliações válidas de trabalhos atribuídas ao próprio docente | 4 |
| Mensagens em fóruns | Tópicos e respostas publicados pelo próprio docente | 1 |

Por omissão, a pontuação máxima é 99, o nível moderado começa em 20 e o alto
em 60. Pesos: inteiros de 0 a 100. Limite: inteiro de 2 a 100000. Os níveis
respeitam `1 ≤ moderado < alto ≤ limite`. Um peso zero mantém as contagens
de actividade, atribuindo zero pontos ao critério.

Cada disciplina tem um detalhe com **quantidade × peso = pontos**, os pontos
calculados e a pontuação final limitada. O total do docente soma os pontos
calculados das disciplinas seleccionadas e aplica o limite uma única vez.
**Ver cálculo por disciplina** abre esse detalhe na coluna **Pontuação**,
com ligações para as disciplinas e suporte de teclado e telemóvel.

As regras guardadas aplicam-se às consultas seguintes, incluindo os intervalos
passados. Não se trata de um histórico de pontuações congeladas. A pontuação de
actividade não altera as notas dos estudantes nem a meta de avaliação usada
em Painel e Cobertura.

## Correcção da contagem de criação

A criação passa a usar o evento do núcleo do Moodle `course_module_created`
e o identificador da actividade. Cada actividade é contada uma vez; eventos
duplicados e edições repetidas não acrescentam pontos. Um recurso é contado
apenas no critério de recursos, sem duplicar a sua pontuação como actividade.

Contam actividades existentes, incluindo ocultas, mas excluindo as que estão
em processo de eliminação. A autoria e a data da criação dependem dos registos
de eventos ainda disponíveis no Moodle. Um acesso à disciplina, um trabalho
existente ou um fórum sem publicação do docente não atribui pontos por si só.
Os valores podem diferir da versão anterior devido a estas correcções.

## Exportação e validação

O Excel mantém as folhas de docentes, trabalhos e fóruns e acrescenta
**Pontuação por disciplina**, com quantidades, pesos, pontos, limite, nível
e pontuação final. As regras de correcção dos trabalhos e de participação
nos fóruns mantêm-se.

Testes locais com consultas reais sobre SQLite verificaram contagens, autoria,
âmbito, limites, pesos zero, validação, permissões de gravação e mapeamento para
Excel. Os testes no navegador verificaram o detalhe em português e inglês,
telemóvel, teclado, filtros e os detalhes existentes de trabalhos e fóruns.
A configuração foi renderizada a partir do PHP real e verificada nos dois idiomas.

Para reproduzir as regressões incluídas, num ambiente com PHP e SQLite:

```sh
php tests/regression/teacher_scoring.php
php tests/regression/forum_inventory.php
php tests/regression/assignment_progress.php
```

Os testes de interface estão em `tests/browser`, com Playwright. Estes testes
não substituem a validação dos dados reais, da instalação e do XLSX produzido
pelo Moodle no ambiente de destino.

Instalar como actualização, concluir **Administração do site > Notificações**
e limpar as caches. Não há alteração da estrutura da base de dados.

Componente `local_learning_analytics`; versão interna `2026092102`; Moodle 5.0+.
