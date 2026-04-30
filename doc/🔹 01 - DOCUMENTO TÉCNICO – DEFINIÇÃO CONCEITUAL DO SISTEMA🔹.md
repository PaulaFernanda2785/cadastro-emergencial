## 1. Apresentação

O Sistema de Cadastro Emergencial é uma aplicação web destinada ao registro, gerenciamento e acompanhamento de residências e famílias afetadas por incidentes, emergências ou desastres. A solução tem como objetivo apoiar ações de resposta da Defesa Civil, permitindo o cadastramento estruturado das casas atingidas, das famílias residentes, dos responsáveis familiares, dos representantes legais, dos documentos comprobatórios, dos registros georreferenciados e da distribuição de ajuda humanitária.

A plataforma será utilizada em situações de anormalidade, permitindo que equipes de campo realizem cadastros por meio de acesso via QR Code vinculado a uma ação específica. Cada ação poderá estar associada a município, data, localidade, tipo de evento e desastre classificado conforme COBRADE.

O sistema deverá permitir a rastreabilidade completa entre ação emergencial, cadastrador, residência, famílias, beneficiários, itens entregues, comprovantes emitidos e prestação de contas final.

## 2. Problema a ser resolvido

Em operações emergenciais, o cadastramento manual de famílias afetadas costuma gerar inconsistências, duplicidades, ausência de rastreabilidade, dificuldade de consolidação dos dados e fragilidade na prestação de contas da ajuda humanitária distribuída.

O sistema busca resolver esses pontos por meio de:

- cadastro padronizado;
- protocolo único por ação emergencial;
- registro do usuário cadastrador;
- georreferenciamento da residência afetada;
- vinculação entre casa e múltiplas famílias;
- controle de entrega de materiais;
- emissão de comprovante de entrega;
- geração de listagens de prestação de contas;
- indicadores situacionais em painel gerencial;
- relatórios por município, evento, bairro, família e tipo de ajuda humanitária.

## 3. Objetivos

### 3.1 Objetivo geral

Desenvolver um sistema web seguro, responsivo e modular para operacionalizar o cadastro emergencial de residências e famílias afetadas por desastres, bem como controlar a distribuição e a prestação de contas de ajuda humanitária.

### 3.2 Objetivos específicos

- Cadastrar ações emergenciais por município, localidade, data e tipo de evento.
- Gerar QR Code específico para cada ação emergencial.
- Permitir que cadastradores acessem o formulário de campo por QR Code.
- Registrar dados de residências afetadas.
- Registrar uma ou mais famílias por residência.
- Registrar responsável familiar e representante, quando houver.
- Anexar documentos e fotos comprobatórias.
- Capturar foto georreferenciada, latitude, longitude, endereço, data e hora.
- Controlar tipos de ajuda humanitária.
- Registrar entrega de itens por família.
- Emitir comprovante de entrega em formato simples para impressão térmica.
- Gerar listagem de prestação de contas por tipo de material.
- Gerar relatórios administrativos e operacionais.
- Disponibilizar painel situacional com gráficos, mapas e quantitativos.

## 4. Regras conceituais principais

1. Uma residência pode possuir uma ou várias famílias.
2. Cada família deve estar vinculada a uma residência cadastrada.
3. Cada cadastro deve estar vinculado a uma ação emergencial.
4. Cada ação emergencial possui município, localidade, data e tipo de evento.
5. O protocolo será sequencial por ano, localidade e evento.
6. Quando qualquer variável estrutural do protocolo for alterada, a contagem sequencial deverá reiniciar.
7. O cadastrador será registrado no histórico do cadastro da casa e da família.
8. A entrega de ajuda humanitária deverá registrar usuário logado, data, hora, tipo de item e quantidade.
9. A prestação de contas deverá ser gerada separadamente para cada tipo de material.
10. O sistema deverá impedir múltiplos envios de formulário por clique repetido ou reenvio manual.