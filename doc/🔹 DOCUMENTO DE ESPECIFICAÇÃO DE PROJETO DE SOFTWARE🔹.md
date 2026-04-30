
## DOCUMENTAÇÃO TÉCNICA MESTRE

**Sistema:** Cadastro Emergencial  
**Finalidade:** Cadastramento de residências e famílias atingidas por incidentes ou desastres, com apoio à distribuição, controle, comprovação e prestação de contas de ajuda humanitária.  
**Tecnologia-base:** PHP moderno puro, MySQL/MariaDB, arquitetura MVC, HTML5, CSS3, JavaScript Vanilla, responsividade e segurança aplicada.  
**Ambientes previstos:** Desenvolvimento local em WampServer64 e produção em hospedagem Hostinger com phpMyAdmin.



Sistema: cadastro emergencial
Tipo de documento: Definição Geral do Sistema  
Objetivo: Definir conceitualmente o sistema, sua finalidade, escopo funcional, estrutura macro, perfis de uso, lógica operacional e diretrizes técnicas iniciais para desenvolvimento.

1. Apresentação do sistema

O sistema de cadastro emergencial tem a finalidade de fazer o cadastros das casas e famílias que foram atingidos por incidentes/desastres tendo como produto o gerenciamento dos cadastros e a operacionalização da distribuição de ajuda humanitária para as famílias cadastradas. 

2. fluxo do cadastro

* usuário (cadastrador) acessa o aplicativo do cadastro da casa afetada e das famílias que residem na casa. regra: 1 casa pode ter n famílias.   
* usuário (cadastrador) acessa o aplicativo via QR code que vai ser aberto via sistema para a ação especifica por localidade (cidade, data, tipo de evento) , registrando com os dados do usuário: nome completo, CPF, e-mail, telefone, órgão/instituição, unidade/setor, senha. 
* o usuário fica registrado no histórico da referencia do cadastro da casa e família 
* aplicativo de cadastro pode ser acesso via rede de internet ou offline (se possível) para os registros  das casas e famílias afetadas.
* aplicativo para cadastro das casas e famílias:
  1. protocolo (sequencial: número/ano/localidade/evento) obs. mudou qual quer variável , zerou a contagem da sequência.
  2. município (via listagem do arquivo csv com geolocalização e id do ibge) (automático do QR code aberta via sistema)
  3. bairro/comunidade (cadastro manual fazendo a referencia do município selecionada) obs. podendo ser editada.  
  4. foto georreferenciada com latitude e longitude e endereço, data e hora tirada da residência atingida 
  5. endereço completo (automática vinda da foto georreferenciada  )
  6. complemento do endereço (campo não obrigatório)
  7. data cadastro (automática vinda da foto georreferenciada)
  8. Tipo de evento (lista dos eventos de desastre conforme COBRADE) (automático do QR code aberta via sistema)
  9. quantidades de famílias que residem na residência 
  10. abrir os cadastros por família que residem na residência (regra: 1 casa pode ter n famílias)
  11. nome do proprietário/responsável pelo imóvel/família
  12. nome do representante do proprietário/responsável pelo imóvel/família (campo não obrigatório) se for o caso de outra pessoa representar o responsável incluindo em outro campo o cpf e rg e telefone , data de nascimento.
  13. data de nascimento
  14. telefone
  15. e-mail
  16. anexos de fotos do comprovante de residência , documentos (cpf e rg)

* sistema de gerenciamento dos cadastros e gerar qr code dos cadastros:
2. domínio: defesacivilpa.com.br
3. banco: u696029111_cedec 
4. usuário banco: u696029111_cedec
5. local em desenvolvimento (wampserve64)
6. local hospedado em produção (hostiger)
7. banco phpmyadimn
8. perfil de acesso (cadastrador, gestor, administrador)
9. sistema de cadastro via qr code (perfil cadastrador)
10. sistema de gerenciamento dos cadastros (perfil gestor)
11. sistema de gerenciamento dos cadastros e usuários (perfil administrador)
12. painel de gerenciamento situacional dos cadastros com os indicadores (gráficos, mapa, quantitativos)
13. painel dos cadastros emergências
14. painel de relatórios dos cadastros
15. painel das listagem das famílias cadastradas e as ações de cadastrar os tipos de ajuda humanitária que cada família recebeu e a quantidade de cada tipo , e a emissão de comprovante de entrega dos kit no formato de impressão de impressora termina no estilo de ticks . e uma ação de confirmação que foi entregue a ajuda humanitária com a data e hora e qual usuário entregou (o usuário logado )
16. painel das listagem da prestação de contas das famílias cadastradas por tido de ajuda humanitária  
17. painel de cadastros dos tipos de ajuda humanitária
18. alterar senha
19. cadastros de usuários

* listagem de prestação de contas 
* regra: cada listagem de prestação de contas é para um tipo de ajuda humanitária 
2. cabeçalho (logo cbmpa-cedec, Corpo de Bombeiros Militar do Pará e Coordenadoria Estadual de proteção e Defesa Civil)
3. Formulário de prestação de contas
4. município 
5. responsável pela distribuição (usuário logado) 
6. telefone (usuário logado)
7. e-mail (usuário logadfo)
8. tipo de material distribuído (tipo de ajuda humanitária )
9. total de famílias
10. data da entrega (data que começou a primeira entrega da ajuda humanitária para o protocolo gerado que faz referência : /ano/localidade/evento )
11. bairros /comunidades 
12. dados sobre a distribuição: 
* n°
* Nome do beneficiário (responsável familiar ou o nome do representante que foi cadastrado no moment9o do cadastro que vai representar o responsável familiar ) 
* cpf 
* quantidade recebida (do tipo de ajuda humanitária recebida) 
* assinatura 
13. visto do responsável pela distribuição
14. ** rodapé 

Referência: Portaria Nº 194 de 17 de Maio de 2024, Art.11º 

1 Deverá ser preenchido um formulário para cada tipo de material

2 Deverá ser usada uma ficha para cada tipo de material**

15. paginação.

* o sistema vai ser desenvolvido em php moderno puro
* telas responsivas 
* estilo css moderno
* tela de login 
* sistema com total segurança contra vulnerabilidades de ataques 
* repositório github com cuidado de não expor dados sensíveis 
* ações em progresso evitando múltiplos  cliques
* obs: "Atue como um desenvolvedor PHP Sênior. Preciso de uma solução completa para evitar múltiplos cliques em botões de envio (Submit) em um sistema PHP puro. A solução deve cobrir dois pilares:
1. Camada de Interface (Frontend):
* Crie um script em JavaScript (Vanilla JS) que, ao submeter o formulário, desabilite o botão de envio (disabled = true).
* O texto do botão deve mudar para 'Processando...' e um indicador visual de carregamento deve aparecer.
* Impeça o envio do formulário se ele já estiver em processo de transmissão.
2. Camada de Segurança (Backend PHP):
* Utilize Tokens de Sessão (CSRF/Idempotência) para validar a requisição.
* Implemente uma lógica onde, ao receber o POST, o PHP verifique se aquele token já foi processado nos últimos 5 segundos.
* Se o usuário atualizar a página (F5) ou tentar reenviar o POST manualmente, o sistema deve ignorar a execução duplicada e retornar um aviso amigável.
3. Integração com Banco de Dados:
* Demonstre como estruturar o if principal que envolve a query de inserção (PDO) para garantir que o código só execute se a validação de 'clique único' for bem-sucedida.
Mantenha o código limpo, comentado e seguro contra ataques básicos de injeção."

obs. suba para o repositório do github escondendo os dados sensíveis no gitignore. protegendo o sistema contra vazamento de dados sensíveis .   

* desenvolver os documentos: 
1. 🔹01 - DOCUMENTO TÉCNICO – DEFINIÇÃO CONCEITUAL DO SISTEMA🔹
2.  🔹02 - DOCUMENTO TÉCNICO – MAPA COMPLETO DOS MÓDULOS, PÁGINAS E HIERARQUIA DE NAVEGAÇÃO🔹
3. 🔹03 - DOCUMENTO TÉCNICO – PERFIS DE USUÁRIO E MATRIZ DE PERMISSÕES DO SISTEMA🔹
4. 🔹04 - DOCUMENTO TÉCNICO – ARQUITETURA MVC COMPLETA DO SISTEMA🔹
5. 🔹06 - DOCUMENTO TÉCNICO – DICIONÁRIO DE DADOS COMPLETO DO SISTEMA🔹
6. 🔹arquivo do banco de dados🔹
7. 🔹Prompt de Comando para o GPTcotex🔹