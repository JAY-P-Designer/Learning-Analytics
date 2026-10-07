# Learning Analytics 1.4.7

## Trabalhos na área Docentes

- Todos os trabalhos existentes nas disciplinas do docente são incluídos,
  independentemente de quem os criou. Incluem-se trabalhos ocultos; excluem-se
  actividades em processo de eliminação.
- Cada linha apresenta trabalhos existentes, submissões recebidas, corrigidas e
  por corrigir. **Ver trabalhos** abre a lista com o estado de cada actividade.
- O nível é **corrigidas / recebidas × 100**. Os estados são **Por iniciar**,
  **Em curso** e **Concluído**. **Sem submissões** e **Sem trabalhos nestas
  disciplinas** são apresentados separadamente, sem percentagens inventadas.
- O cartão geral conta cada trabalho uma vez, mesmo que a disciplina tenha
  vários docentes. A percentagem agregada usa o total de submissões, não a média
  das percentagens dos trabalhos.

## Critérios de contagem

São consideradas as tentativas actuais com estado de submissão enviada. Os
rascunhos e as tentativas anteriores não aumentam o denominador. Uma avaliação
deve corresponder à mesma tentativa e ser posterior à última alteração da
submissão. A nota zero é válida; uma escala ainda sem valor não é uma correcção.
Quando há fluxo de avaliação, uma avaliação ainda em correcção permanece pendente;
uma avaliação terminada pode aguardar revisão ou publicação.

As entregas de grupo contam uma vez, mesmo existindo registos individuais por
membro. Uma entrega só fica totalmente corrigida quando todos os membros elegíveis
têm avaliação; avaliações parciais deixam o trabalho **Em curso**. A associação a
grupos segue os membros actualmente inscritos, os papéis de estudante, o
agrupamento do trabalho e os grupos habilitados para participação.

O inventário e as pendências abrangem todas as submissões actualmente existentes
no âmbito escolhido. O intervalo de 7/30/90/365 dias limita apenas a actividade
pessoal do docente. A pontuação usa as avaliações válidas que lhe estão atribuídas
nos registos actuais do Moodle, incluindo trabalhos sem submissão online; esses
trabalhos não recebem uma percentagem baseada em submissões inexistentes.
O relatório não reconstrói um histórico de avaliadores substituídos por edições
posteriores. As restantes áreas mantêm as suas próprias regras de cálculo.

## Exportação e apresentação

A folha de docentes inclui todos os novos indicadores. A folha **Trabalhos**
apresenta nomes, disciplinas, tipo de entrega, contagens, percentagem e estado,
com uma linha por trabalho. Mantêm-se os indicadores dos fóruns.
Os textos estão disponíveis em português e inglês. A tabela pode ser deslocada
horizontalmente em telemóveis, mantendo as explicações fora da zona de deslocação.

## Verificação e actualização

Verificação local com PHP 8.2, SQL sobre SQLite, adaptador de escrita Excel e
Chromium. Os testes incluem novas tentativas, nota zero, avaliações desactualizadas,
rascunhos, grupos, duplicação de inscrições/cargos, intervalos, exportação e
apresentação em português/inglês. A geração efectiva do XLSX e os dados reais devem
ser confirmados no Moodle de destino.

Instalar como actualização, concluir **Administração do site > Notificações** e
limpar as caches. Não é necessário desinstalar nem alterar a base de dados.

Componente: `local_learning_analytics`. Versão interna: `2026091801`.
Moodle mínimo: 5.0.
