# Learning Analytics 1.4.1: primeiro grupo, Acessos

## Referência e diagnóstico

Referência fornecida: `local_relatorio_die_moodle5_beta5.zip`.
Base de actualização: Learning Analytics 1.4.0.

Num navegador Chromium, a beta5 carregou correctamente as disciplinas do
período e da subcategoria. Na 1.4.0 foram reproduzidas duas falhas:

1. A montagem da árvore de categorias estava fora da função que declarava
   `AX` e `SK`, causando `ReferenceError: AX is not defined`.
2. Um `select` oculto, contendo apenas uma opção vazia, recebia IDs que não
   existiam nas suas opções. O navegador descartava esses valores. Os pedidos
   seguintes acabavam por consultar o período inteiro.

## Correcção

- Acessos inicializa os seus filtros dentro da mesma função e guarda o ID num
  campo oculto adequado. Não depende da árvore global usada pelos outros grupos.
- Cada nível lista apenas as categorias filhas do nível anterior. Seleccionar
  uma categoria actualiza a lista de disciplinas e o relatório para toda a sua
  árvore. Não há um limite artificial de três níveis.
- Folhas sem subcategorias não geram um selector vazio adicional.
- O estilo usa a grelha e as classes de filtros existentes no plugin, com
  rótulos associados aos controlos e disposição adaptável a ecrãs pequenos.
- Pedidos substituídos são cancelados e as suas respostas ignoradas. Um curto
  intervalo de 180 ms evita iniciar relatórios desnecessários durante a selecção.
- Erros HTTP, respostas inválidas e sessão expirada deixam de parecer resultados
  vazios. Existe uma acção para repetir o pedido e um limite de espera de 60 s.
- O picker, a panorâmica e a exportação partilham o mesmo cálculo de disciplinas.
  Categorias de outro período ou IDs inexistentes são rejeitados.
- As contagens das categorias são feitas em lote. No cenário testado, a consulta
  de filhas de uma categoria usou quatro consultas, independentemente do número
  dos seus descendentes.
- O bloqueio da sessão é libertado depois dos controlos de acesso, para uma
  consulta longa não reter os novos pedidos da mesma sessão. A implementação
  segue a [documentação de sessões do Moodle](https://docs.moodle.org/dev/Session_locks).
- Os critérios de contagem de visualizações e utilizadores de Acessos mantêm-se.
  Não foram alteradas as regras de Cobertura, Em Risco, Docentes ou Presenças.

## Verificação executada

**15 cenários num navegador Chromium real**, executando o JavaScript da página
e o componente de pesquisa efectivamente incluídos no pacote, com respostas
Moodle simuladas: inicialização, hierarquia e descendentes, pedidos duplicados,
categorias vazias, respostas fora de ordem, limpeza e mudança de período,
erros e repetição, respostas HTML, pesquisa, detalhe, regresso, parâmetros de
exportação e dimensões dos filtros em computador e telemóvel.

**17 verificações SQL**, executando o código PHP de selecção de categorias e
disciplinas com PDO SQLite em memória: inclusão de disciplinas directas e
descendentes, exclusão de categorias vizinhas e outros períodos, folhas,
contagens, número de consultas e rejeição de IDs inválidos.

Verificados também a sintaxe PHP e o empacotamento do componente Moodle.

Estes testes não equivalem a uma instalação completa no Moodle de destino.
Não foram usados os dados, contas ou logs reais da instituição. Não foi medido
o tempo de execução nesse servidor. Os testes das exportações validam a
selecção e os parâmetros; a criação do ficheiro XLSX pelo Moodle fica para a
validação no ambiente de destino.

## Reexecutar os testes

SQL, com PHP e PDO SQLite:

```sh
php tests/regression/access_scope.php
```

Navegador, com Node.js e npm:

```sh
cd tests/browser
npm install
npx playwright install chromium
npm test
```

Opcionalmente, `CHROMIUM_EXECUTABLE_PATH` indica um Chromium existente e
`TEST_ARTIFACT_DIR` indica a pasta das capturas dos filtros. Os testes usam
exclusivamente dados de teste interceptados; não acedem ao servidor institucional.
