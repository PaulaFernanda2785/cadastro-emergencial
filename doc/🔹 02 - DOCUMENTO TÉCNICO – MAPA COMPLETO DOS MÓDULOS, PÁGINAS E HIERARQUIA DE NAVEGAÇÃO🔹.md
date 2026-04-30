## 1. Módulo Público / Acesso por QR Code

### 1.1 Página de acesso à ação emergencial

**Rota sugerida:** `/acao/{token}`  
**Perfil:** Cadastrador  
**Função:** Abrir o formulário de cadastro vinculado à ação emergencial.

Dados carregados automaticamente:

- município;
- tipo de evento;
- data da ação;
- localidade;
- token da ação;
- status da ação.

### 1.2 Cadastro inicial do cadastrador

**Rota sugerida:** `/acao/{token}/cadastrador`

Campos:

- nome completo;
- CPF;
- e-mail;
- telefone;
- órgão/instituição;
- unidade/setor;
- senha;
- confirmação de senha.

### 1.3 Formulário de cadastro da residência

**Rota sugerida:** `/cadastro/residencia/novo`

Campos principais:

- protocolo;
- município;
- bairro/comunidade;
- foto georreferenciada;
- latitude;
- longitude;
- endereço completo;
- complemento;
- data e hora do cadastro;
- tipo de evento;
- quantidade de famílias residentes.

### 1.4 Cadastro de famílias vinculadas à residência

**Rota sugerida:** `/cadastro/residencia/{id}/familias`

Campos:

- responsável familiar;
- CPF;
- RG;
- data de nascimento;
- telefone;
- e-mail;
- representante, se houver;
- CPF do representante;
- RG do representante;
- telefone do representante;
- anexos de documentos;
- fotos de comprovante de residência.

---

## 2. Módulo de Login

**Rota sugerida:** `/login`

Funções:

- autenticação de usuários;
- validação de senha com hash seguro;
- controle de sessão;
- bloqueio de acesso por perfil;
- proteção contra brute force;
- recuperação ou alteração de senha.

---

## 3. Módulo Gestor

### 3.1 Painel situacional

**Rota sugerida:** `/gestor/dashboard`

Indicadores:

- total de residências cadastradas;
- total de famílias cadastradas;
- total de pessoas beneficiárias;
- total por bairro/comunidade;
- total por tipo de evento;
- total por ação emergencial;
- total de materiais distribuídos;
- pendências de entrega;
- mapa dos cadastros georreferenciados.

### 3.2 Painel de cadastros emergenciais

**Rota sugerida:** `/gestor/cadastros`

Funções:

- listar cadastros;
- filtrar por município, ação, bairro, evento e data;
- visualizar residência;
- visualizar famílias;
- exportar relatório;
- imprimir ficha de cadastro.

### 3.3 Painel de famílias cadastradas

**Rota sugerida:** `/gestor/familias`

Funções:

- listar famílias;
- filtrar por ação, bairro, responsável e CPF;
- registrar ajuda humanitária recebida;
- emitir comprovante de entrega;
- confirmar entrega;
- visualizar histórico.

### 3.4 Painel de prestação de contas

**Rota sugerida:** `/gestor/prestacao-contas`

Funções:

- gerar listagem por tipo de ajuda;
- totalizar famílias atendidas;
- totalizar quantidade distribuída;
- gerar documento para assinatura;
- imprimir ou exportar PDF.

### 3.5 Painel de relatórios

**Rota sugerida:** `/gestor/relatorios`

Relatórios previstos:

- relatório geral por ação emergencial;
- relatório por município;
- relatório por bairro/comunidade;
- relatório por tipo de evento;
- relatório de famílias cadastradas;
- relatório de ajuda humanitária entregue;
- relatório de pendências;
- relatório de prestação de contas.

---

## 4. Módulo Administrador

### 4.1 Gerenciamento de ações emergenciais

**Rota sugerida:** `/admin/acoes`

Funções:

- cadastrar ação;
- editar ação;
- ativar/inativar ação;
- gerar QR Code;
- visualizar link público;
- configurar município, evento e localidade.

### 4.2 Gerenciamento de usuários

**Rota sugerida:** `/admin/usuarios`

Funções:

- cadastrar usuários;
- editar usuários;
- definir perfil;
- bloquear/desbloquear acesso;
- resetar senha;
- auditar atividade.

### 4.3 Tipos de ajuda humanitária

**Rota sugerida:** `/admin/ajudas`

Funções:

- cadastrar tipo de material;
- editar tipo;
- ativar/inativar tipo;
- definir unidade de medida;
- controlar uso em entregas.

### 4.4 Configurações institucionais

**Rota sugerida:** `/admin/configuracoes`

Funções:

- logos institucionais;
- cabeçalho dos relatórios;
- rodapé;
- parâmetros de protocolo;
- configurações de segurança.