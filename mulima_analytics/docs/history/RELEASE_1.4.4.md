# Learning Analytics 1.4.4

## Comportamento dos filtros

- O período e as categorias começam em branco, sem resultados.
- Escolher um período ou categoria disponibiliza as suas categorias filhas,
  sem seleccionar automaticamente a opção de todas as subcategorias.
- Só aparecem resultados automáticos quando existem disciplinas directamente
  associadas ao nível escolhido. Sem essas disciplinas, a área permanece vazia.
- A opção **Todas neste nível** permite consultar explicitamente o nível escolhido
  e todas as suas subcategorias.
- Limpar ou mudar uma selecção remove os níveis dependentes e os resultados
  anteriores. Respostas atrasadas não podem repor a selecção anterior.
- Esta regra aplica-se a Acessos, Cobertura, Em Risco, Docentes e Presenças,
  incluindo as respectivas exportações.
- Mantém-se a apresentação apenas do nome completo das disciplinas.

## Verificação

Os testes de navegador usam as páginas entregues com respostas Moodle simuladas.
Cobrem campos em branco, categorias sem disciplinas directas, disciplinas em
níveis intermédios, selecção explícita de subcategorias, exportações, pesquisa,
mudanças rápidas de categoria e recuperação de erros.

Os testes SQL executam as consultas de âmbito e das métricas de risco/docentes
sobre SQLite com dados controlados. A instalação e os ficheiros PDF/Excel finais
devem ser confirmados no Moodle de destino.

Componente: `local_learning_analytics`. Versão interna: `2026091707`.
Instalar como actualização e limpar as caches do Moodle.
