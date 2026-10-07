# Learning Analytics 1.4.6

## Fóruns e participação dos docentes

A área **Docentes** passa a distinguir:

- **Fóruns existentes:** todos os fóruns nas disciplinas seleccionadas em que o
  utilizador é docente, incluindo fóruns criados por outras pessoas e fóruns
  ocultos. Este total não depende da data de criação do fórum.
- **Fóruns com participação:** fóruns distintos em que o docente publicou no
  intervalo escolhido. Várias mensagens no mesmo fórum contam como um fórum.
- **Mensagens do docente:** total das suas publicações, separado em tópicos
  iniciados e respostas. Mensagens de estudantes ou de outros docentes não lhe
  são atribuídas.
- **Última mensagem no período:** data da publicação mais recente no intervalo,
  consultável na própria célula da tabela.

As contagens de participação vêm das mensagens actualmente existentes no Moodle,
independentemente da retenção de logs ou do autor do fórum. Incluem respostas a
tópicos de outras pessoas e excluem mensagens eliminadas, actividades em processo
de eliminação e publicações fora do intervalo. Mantém-se o âmbito escolhido nos
filtros de período de execução, categorias e disciplina.

Cada mensagem do docente acrescenta um ponto de fórum. A existência de um fórum
não atribui pontos e os eventos de criação de tópicos/mensagens deixam de voltar
a somar como actividades criadas. Os restantes critérios e pesos mantêm-se.

A página distingue **Sem fóruns nestas disciplinas** de **Sem publicações no
período**. A ausência de logs de acesso aparece como **Sem registo de acesso**.
Os mesmos indicadores são incluídos em colunas próprias no Excel. A apresentação
e os novos textos estão disponíveis em português e inglês.

## Validação

- PHP 8.2: sintaxe dos ficheiros alterados.
- 72 verificações com SQL real sobre SQLite e dados controlados: âmbito das
  categorias, docentes com vários cargos, autoria, respostas, datas-limite,
  mensagens eliminadas, participação sem logs, pontuação e colunas do Excel.
- Chromium: seis cenários específicos dos fóruns em português/inglês e em
  computador/telemóvel, além dos testes existentes das áreas adicionais.

Os testes de Excel verificam os dados e a correspondência das colunas através de
um adaptador de escrita; não substituem a geração do XLSX pela biblioteca do
Moodle. A instalação e a confirmação com os dados reais devem ser feitas no site.

## Instalação

Instalar como actualização do Learning Analytics, concluir **Administração do site
> Notificações** e limpar as caches. Mantêm-se as configurações e os filtros em
branco até à selecção. Não é necessário desinstalar o plugin.

Componente: `local_learning_analytics`. Versão interna: `2026091800`.
Moodle mínimo: 5.0. Não há alterações à estrutura da base de dados.
