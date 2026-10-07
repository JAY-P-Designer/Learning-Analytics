# Actualização para Learning Analytics 1.0.0

- Componente: `local_mulima_analytics`.
- Directório: `local/mulima_analytics`.
- Versão interna: `2026100205` (anterior: `2026100204`).
- Moodle mínimo: 4.0 (`2022041900`), conforme `version.php`.

## Instalação

1. Instalar o ZIP como actualização do Learning Analytics existente.
2. Abrir **Administração do site > Notificações** e concluir a actualização.
3. Limpar todas as caches do Moodle e recarregar as páginas do plugin.
4. Confirmar **1.0.0** na lista de módulos locais.
5. Abrir a lista de disciplinas e confirmar que cada opção mostra apenas o
   nome completo, sem a etiqueta do nome curto.

O ZIP contém uma única pasta `mulima_analytics`, incluindo
`lang/en/local_mulima_analytics.php`. Esta actualização não requer
desinstalar o plugin nem altera a estrutura da base de dados.

## Numeração pública

A versão apresentada pelo plugin passa de 1.5.5 para 1.0.0 a pedido do autor.
O Moodle detecta as actualizações pelo número interno (`$plugin->version`),
que aumenta para `2026100205`. Este pacote conserva todas as funcionalidades
e correcções da versão 1.5.5.

## Idiomas (1.5.5)

- Os rótulos, opções, mensagens de carregamento, erros e resultados dinâmicos
  usam o idioma activo do Moodle. Os filtros continuam em branco até à selecção.
- As listas de presença, incluindo os PDFs e o Excel, usam os mesmos textos
  traduzidos que os relatórios.
- Os nomes de disciplinas/categorias e os textos personalizados já gravados nas
  configurações não são traduzidos automaticamente.
- Testar com inglês e português nas páginas Acessos, Cobertura, Em Risco,
  Docentes, Presenças e Configurações, incluindo exportação e repetição de um
  pedido após erro.

## Compatibilidade Moodle 4+

O requisito de instalação passa a ser o Moodle 4.0 ou superior. A camada de
compatibilidade também evita recursos de sintaxe PHP posteriores ao PHP 7.3
nas classes carregadas pelo plugin, que é importante para instalações Moodle
4.0 mais antigas. Não foi alterada a lógica dos relatórios, filtros,
permissões, Privacy API ou exportações.

## Identificação e marca de água (1.5.3)

- Todas as páginas do Learning Analytics mostram no rodapé a autoria,
  contacto telefónico e endereço de email do autor.
- A edição gratuita apresenta a indicação **Learning Analytics · Versão
  gratuita** e um contacto para solicitar a versão sem marca de água.
- A versão sem marca de água é uma edição licenciada fornecida directamente
  pelo autor. Não existe um interruptor público nas configurações que remova
  a identificação da edição gratuita.
- O rodapé funciona também nas páginas de configurações, permissões, detalhe
  de disciplina e estudantes, para manter a identificação consistente em todo
  o plugin.

## Privacidade, etapa 2 (1.5.2)

- A descrição de privacidade passa a declarar os logótipos e os eventos de exportação.
- Os novos logótipos ficam associados à conta que os envia. A Privacy API permite
  identificar, exportar e eliminar esses ficheiros mediante os pedidos aprovados
  pelo Moodle, incluindo pedidos que abrangem vários utilizadores.
- A eliminação individual não afecta ficheiros de outros utilizadores, disciplinas,
  notas, inscrições, fóruns, logs ou ficheiros de `local_listas_exame`.
- Logótipos antigos sem ID do utilizador não são atribuídos automaticamente a ninguém.
  Podem ser removidos ou substituídos em **Configurações > Geral**. Um logótipo
  reenviado fica associado à conta que o envia, sem identificar retroactivamente
  o autor do envio anterior. Rever manualmente conteúdos pessoais desses ficheiros.
- Um pedido que elimine dados de todos os utilizadores no contexto do sistema remove
  todos os logótipos deste componente, incluindo os antigos sem proprietário.
- Se um logótipo em uso for eliminado, a gestão pode enviar um substituto. Em sites
  com o plugin antigo, o fallback pode voltar a apresentar o logótipo desse plugin.
- A cópia do logótipo usada nos PDFs passa a usar uma pasta temporária por pedido,
  com limpeza pelo Moodle. Ficheiros temporários de versões antigas não são apagados
  automaticamente porque não há registo que permita confirmar a sua origem.

Consulte [PRIVACY.md](PRIVACY.md) para o inventário dos dados, limites e validação.
Os testes Moodle incluídos devem ser executados numa instalação de teste antes da
submissão pública; a validação local desta versão não substitui esse ensaio.

## Preparação para distribuição pública, etapa 1 (1.5.1)

- Licença GPLv3 completa em `COPYING.txt`, com opção de usar versões posteriores.
- Cabeçalhos de licença e autoria nos ficheiros PHP, JavaScript, CSS e Mustache.
- Identificador `GPL-3.0-or-later` nos metadados dos testes de navegador.
- Documentação da licença e incremento da versão do plugin.

Esta etapa não altera os cálculos, filtros, permissões ou dados existentes.
As próximas etapas abrangem a Privacy API, a preparação para uso internacional,
a documentação pública e a validação para submissão ao Moodle Marketplace.

## Pontuação configurável por disciplina (1.5.0)

1. Com uma conta autorizada a configurar o site, abrir **Configurações > Geral >
   Pontuação dos docentes**. Confirmar os pesos iniciais 3/2/4/1, limite 99 e
   níveis moderado 20 e alto 60. Guardar os valores pretendidos.
2. Em **Docentes**, seleccionar as categorias, disciplina e intervalo em dias.
   Abrir **Ver cálculo por disciplina** na coluna **Pontuação**. Conferir a
   quantidade, o peso e os pontos de cada critério com as acções desse docente.
3. Confirmar a contagem de uma actividade criada e de um recurso publicado.
   Repetir uma edição: não deve acrescentar pontos. O recurso conta apenas no
   seu critério. Eventos de criação eliminados pela retenção de logs não podem
   ser reconstruídos a partir deste relatório.
4. Numa disciplina partilhada, confirmar que cada docente recebe pontos pelas
   suas próprias acções. A simples existência de trabalhos ou fóruns criados
   por outras pessoas não lhe atribui pontos.
5. Confirmar o limite e os níveis configurados. O total usa a soma dos pontos
   calculados das disciplinas, antes de aplicar o limite uma única vez.
   Não corresponde à soma das pontuações de disciplina já limitadas.
6. Se usar um peso zero, confirmar que os pontos desse critério são zero mas
   as acções permanecem nas contagens e no filtro **Com actividade**.
7. Exportar o Excel e conferir a folha **Pontuação por disciplina**, juntamente
   com as folhas existentes de docentes, trabalhos e fóruns.

O formulário da pontuação tem o seu próprio botão **Guardar**. As regras
aplicam-se a todas as disciplinas e às consultas seguintes, incluindo períodos
anteriores. Não alteram as notas dos estudantes nem a meta de pontos da Cobertura.
A correcção dos eventos de criação e a eliminação da dupla contagem podem mudar
os valores relativamente à versão anterior, mesmo usando os pesos iniciais.

## Inventário dos fóruns e participação (1.4.9)

1. Em **Docentes**, seleccionar uma disciplina com um fórum sem tópicos.
   O fórum deve contar em **Fóruns existentes** e aparecer em **Ver fóruns**.
2. Abrir a lista e confirmar o nome, a disciplina, **Sem tópicos** e
   **Sem participação no período**. Confirmar também um fórum oculto.
3. Num fórum com mensagens apenas de estudantes ou de outro docente, confirmar
   que os tópicos existem mas que não é atribuída participação ao docente da linha.
4. Publicar um tópico ou uma resposta com o docente e confirmar a participação,
   os totais e a última mensagem no período seleccionado.
5. Alterar o intervalo em dias. O inventário e os tópicos existentes permanecem;
   apenas as mensagens e a participação do docente seguem o intervalo.
6. Abrir o fórum e a disciplina através das ligações. Exportar e conferir a folha
   **Fóruns**, que apresenta uma linha por docente e fórum, incluindo os vazios.

## Acesso às disciplinas a partir de Docentes (1.4.8)

1. Seleccionar o âmbito e clicar num nome da coluna **Disciplina(s)**.
2. Confirmar que abre a disciplina correcta num novo separador e que o relatório
   mantém os filtros e os resultados no separador inicial.
3. Num docente com mais de duas disciplinas, abrir **Ver mais disciplinas** e
   confirmar que todas as restantes têm uma ligação própria.
4. Abrir **Ver trabalhos** e usar a ligação no nome da disciplina.
5. Confirmar a navegação em telemóvel, deslocando a tabela horizontalmente.

As ligações usam os IDs das disciplinas e o endereço configurado no Moodle.
O acesso continua sujeito às permissões normais de cada disciplina.

## Validação do primeiro grupo: Acessos

1. Confirmar que o período e a categoria começam em branco e sem resultados.
2. Seleccionar o período, Categoria 1, Categoria 2 e Categoria 3, quando existirem.
   Cada categoria seguinte deve começar em branco.
3. Confirmar que a pesquisa e a tabela mostram apenas disciplinas directamente
   associadas ao nível escolhido. Se só existirem disciplinas dentro de categorias
   ainda por seleccionar, a área de resultados deve permanecer oculta.
4. Seleccionar explicitamente **Todas neste nível** e confirmar que passam a ser
   incluídas as disciplinas desse nível e de todas as suas subcategorias.
5. Abrir uma disciplina, consultar uma actividade, voltar e exportar o Excel.
6. Comparar as disciplinas e os valores apresentados com os dados do Moodle.

Os testes automatizados incluídos usam dados controlados. A instalação,
as permissões, a geração de XLSX pelo Moodle e o tempo de consulta dos logs
reais precisam de validação no ambiente de destino.

## Validação das restantes áreas

1. **Cobertura:** seleccionar o período e as categorias, confirmar as disciplinas,
   alterar a meta e consultar uma disciplina e as submissões das actividades.
   Confirmar que o Excel usa a categoria e a meta escolhidas.
2. **Em Risco:** testar **Plataforma em geral** e **Por disciplinas**, com o mesmo
   período, categoria e limiar de dias. O primeiro modo conta cada estudante
   uma vez e usa o último acesso à plataforma. O segundo analisa cada par
   estudante/disciplina. Confirmar que o Excel mantém o âmbito escolhido.
3. **Docentes:** seleccionar categoria, disciplina e intervalo em dias. Comparar
   os docentes, métricas e pontuações da página com os do Excel.
4. Mudar rapidamente de categoria e limpar o período. Não devem reaparecer
   resultados de uma selecção anterior.
5. **Presenças:** os botões de PDF/Excel ficam desactivados enquanto não existirem
   disciplinas no âmbito escolhido. Confirmar que a lista de disciplinas e os
   ficheiros usam o mesmo âmbito, directo ou incluindo subcategorias.

No modo Plataforma em geral, a categoria/disciplinas determina os estudantes
incluídos; o último acesso considerado é o da plataforma inteira. Por isso,
os resultados podem diferir das versões que usavam apenas acessos às disciplinas
seleccionadas. No modo Por disciplinas, o relatório pode ter várias linhas para
o mesmo estudante, uma por disciplina em risco.

## Indicadores de inactividade

Em **Em Risco**, confirmar a explicação destinada à gestão da plataforma.
No modo Plataforma em geral, cada estudante é contado uma vez. No modo Por
disciplinas, cada registo corresponde a um estudante numa disciplina.
Os cartões identificam inactividade crítica, alerta de inactividade e aviso de
inactividade. O total indica o limiar de dias seleccionado.

## Fóruns dos docentes (1.4.6)

1. Seleccionar uma disciplina com um fórum criado por outro docente ou por um
   administrador. O fórum deve constar do total de fóruns existentes.
2. Publicar uma resposta com o docente dessa disciplina e confirmar que aparece
   em **Mensagens do docente** e **Respostas**, dentro do período escolhido.
3. Iniciar um tópico com o mesmo docente. Deve contar como um tópico e uma
   mensagem, sem duplicar os pontos através dos eventos de criação do Moodle.
4. Confirmar que mensagens de estudantes ou de outros docentes não lhe são
   atribuídas. Dois cargos do mesmo docente na disciplina não duplicam valores.
5. Alterar o intervalo em dias. As participações seguem esse intervalo; o total
   de fóruns existentes continua a representar as disciplinas seleccionadas.
6. Confirmar as mesmas contagens, a última mensagem e a pontuação no Excel.

Incluem-se fóruns ocultos, por se tratar de um relatório de gestão. Excluem-se
mensagens eliminadas e fóruns em processo de eliminação. Os dados de participação
são as publicações actualmente existentes no Moodle, mesmo sem histórico de logs.
Uma disciplina sem fóruns é distinguida de um docente sem publicações no período.
Os fóruns sem participação do docente não aumentam a sua pontuação.

## Trabalhos e nível de correcção (1.4.7)

1. Abrir **Docentes** e seleccionar uma categoria/disciplina com trabalhos,
   incluindo alguns criados por administradores ou outros docentes.
2. Abrir **Ver trabalhos** na linha do docente. Confirmar os nomes, disciplinas,
   submissões recebidas, corrigidas, pendentes, percentagem e estado.
3. Comparar com as tentativas actuais no Moodle. Uma nota zero é uma correcção;
   um rascunho não é uma submissão recebida; uma nova tentativa não fica corrigida
   por existir uma nota na tentativa anterior. Uma edição posterior à correcção
   deixa a submissão novamente pendente.
4. Confirmar que 20 corrigidas em 40 submissões mostram 50%, e que sem submissões
   não aparece 100% nem erro de divisão por zero.
5. Mudar o intervalo de actividade do docente. As pendências antigas mantêm-se;
   apenas as avaliações atribuídas ao docente no período e a sua pontuação
   seguem o intervalo em dias.
6. Confirmar que uma disciplina partilhada não duplica o cartão geral. Cada
   docente vê a situação das suas disciplinas, mas recebe pontos apenas pelas
   avaliações que lhe estão atribuídas.
7. Em entregas de grupo, confirmar que uma entrega conta uma vez. Só fica
   totalmente corrigida quando todos os membros elegíveis têm avaliação.
8. Exportar Excel: a primeira folha contém os indicadores por docente e a folha
   **Trabalhos** lista cada trabalho uma vez, incluindo os sem submissões.
