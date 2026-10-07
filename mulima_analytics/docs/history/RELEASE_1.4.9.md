# Learning Analytics 1.4.9

## Fóruns existentes e participação do docente

- **Fóruns existentes** conta as actividades de fórum nas disciplinas do docente,
  mesmo sem tópicos, ocultas ou criadas por outras pessoas. Excluem-se actividades
  em eliminação e registos sem uma actividade correspondente na disciplina.
- **Ver fóruns** apresenta o nome, a disciplina, os tópicos existentes, a indicação
  de actividade oculta e o estado de participação de cada fórum.
- Um fórum vazio aparece como **Sem tópicos** e **Sem participação no período**.
  Um fórum com mensagens apenas de outras pessoas continua sem participação do
  docente da linha. Só tópicos e respostas publicados por esse docente contam.
- O número de tópicos existentes abrange todo o conteúdo actual. As mensagens,
  a participação e a última publicação do docente seguem o intervalo seleccionado.
- A contagem total e o detalhe são obtidos da mesma lista de fóruns. Vários cargos
  do mesmo docente numa disciplina não duplicam os valores.
- Os nomes do fórum e da disciplina abrem as páginas correspondentes num novo
  separador, mantendo o relatório. As permissões de acesso são as normais do Moodle.
- O Excel inclui uma folha **Fóruns**, com uma linha por docente e fórum. O mesmo
  fórum pode aparecer para diferentes docentes, com a participação de cada um.

Os restantes indicadores e filtros mantêm as suas regras. A existência de fóruns,
por si só, não aumenta a pontuação de actividade do docente.

## Validação e instalação

Os testes locais executam as consultas reais com SQLite e verificam o mapeamento
do Excel. O navegador verifica a lista, os estados, as ligações e a apresentação
em português e inglês. A instalação, os dados reais e o XLSX gerado pelo Moodle
devem ser confirmados no ambiente de destino.

Instalar como actualização, concluir **Administração do site > Notificações** e
limpar as caches. Não há alteração da estrutura da base de dados.

Componente `local_learning_analytics`; versão interna `2026092101`; Moodle 5.0+.
