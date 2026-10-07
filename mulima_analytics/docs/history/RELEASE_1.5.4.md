# Learning Analytics 1.5.4

## Compatibilidade com Moodle 4+

- O requisito mínimo passa de Moodle 5.0 (`2025041400`) para Moodle 4.0
  (`2022041900`).
- O componente continua a ser `local_learning_analytics` e o directório
  continua a ser `local/learning_analytics`.
- A versão interna é `2026100203`; não há migração de base de dados.
- A lógica dos relatórios, filtros hierárquicos, permissões, pontuação,
  participação em fóruns, trabalhos, presenças e exportações é preservada.

## Compatibilidade de PHP

As classes carregadas durante a navegação e os pedidos AJAX deixam de usar
`readonly`, `mixed`, `never` e promoção de propriedades no construtor. As
funções anónimas substituem as arrow functions usadas nas exportações. Assim,
o código do plugin pode ser analisado por ambientes Moodle 4.x mais antigos,
sem alterar o resultado dos cálculos.

## Instalação

1. Instalar o ZIP como actualização do `local_learning_analytics` existente,
   ou como nova instalação no Moodle 4.x.
2. Abrir **Administração do site → Notificações** e concluir a actualização.
3. Limpar as caches do Moodle e fazer um recarregamento completo do navegador.
4. Confirmar **Learning Analytics 1.5.4** em **Administração do site →
   Plugins → Plugins locais**.

Não desinstale a versão anterior: a actualização preserva as configurações,
permissões e logótipos já guardados.
