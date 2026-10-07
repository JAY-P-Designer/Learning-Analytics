# Learning Analytics 1.4.8

## Abrir disciplinas no relatório de Docentes

- Os nomes completos na coluna **Disciplina(s)** são ligações para a disciplina
  correspondente, abertas num novo separador para preservar o relatório.
- **Ver mais disciplinas** expande a lista quando existem mais de duas, com uma
  ligação para cada disciplina. A coluna também fica disponível em telemóvel.
- O nome da disciplina em **Ver trabalhos** permite a mesma navegação.
- As ligações usam o ID da disciplina, incluindo quando há nomes iguais, e o
  endereço configurado no Moodle, incluindo instalações em subdirectórios.
- Incluem-se indicações de novo separador em português e inglês, foco de teclado
  visível e os nomes escapados para apresentação segura.

Não há novas consultas à base de dados para obter as ligações. Os filtros,
indicadores, exportações e permissões mantêm o comportamento da versão 1.4.7.
O Moodle verifica as permissões ao abrir a disciplina.

Instalar como actualização, concluir **Administração do site > Notificações** e
limpar as caches. Componente `local_learning_analytics`, versão interna
`2026092100`, Moodle mínimo 5.0.
