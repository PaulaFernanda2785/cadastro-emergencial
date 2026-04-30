## 1. Perfis previstos

### 1.1 Cadastrador

Usuário responsável pelo registro de campo das residências e famílias afetadas.

Permissões:

- acessar ação emergencial via QR Code;
- registrar dados pessoais de cadastrador;
- cadastrar residência;
- cadastrar famílias vinculadas;
- anexar fotos e documentos;
- visualizar apenas seus próprios cadastros, se autorizado;
- editar cadastro enquanto a ação estiver aberta, conforme regra definida.

### 1.2 Gestor

Usuário responsável pelo acompanhamento operacional dos cadastros e entregas.

Permissões:

- acessar painel situacional;
- visualizar cadastros da ação ou município autorizado;
- consultar famílias;
- registrar entrega de ajuda humanitária;
- emitir comprovante de entrega;
- gerar relatórios;
- gerar prestação de contas;
- exportar dados, se autorizado.

### 1.3 Administrador

Usuário com acesso total ao sistema.

Permissões:

- gerenciar usuários;
- gerenciar ações emergenciais;
- gerar QR Codes;
- configurar tipos de ajuda humanitária;
- visualizar todos os cadastros;
- editar regras operacionais;
- acessar logs;
- configurar relatórios;
- alterar parâmetros institucionais.

## 2. Matriz de permissões

|Módulo / Ação|Cadastrador|Gestor|Administrador|
|---|---|---|---|
|Acessar formulário via QR Code|Sim|Sim|Sim|
|Cadastrar residência|Sim|Sim|Sim|
|Cadastrar família|Sim|Sim|Sim|
|Anexar fotos/documentos|Sim|Sim|Sim|
|Visualizar painel situacional|Não|Sim|Sim|
|Visualizar todos os cadastros|Não|Sim, conforme escopo|Sim|
|Editar cadastro|Restrito|Sim|Sim|
|Excluir cadastro|Não|Não recomendado|Sim, com auditoria|
|Registrar entrega de ajuda|Não|Sim|Sim|
|Emitir comprovante de entrega|Não|Sim|Sim|
|Gerar prestação de contas|Não|Sim|Sim|
|Gerenciar tipos de ajuda|Não|Não|Sim|
|Gerenciar usuários|Não|Não|Sim|
|Gerar QR Code de ação|Não|Não|Sim|
|Acessar logs|Não|Não|Sim|

## 3. Regras de segurança por perfil

- O cadastrador não deve acessar painel administrativo.
- O gestor não deve criar administradores.
- O administrador deve ter ações auditadas.
- Toda alteração sensível deve registrar usuário, data, hora, IP e operação.
- Exclusões devem ser preferencialmente lógicas, não físicas.