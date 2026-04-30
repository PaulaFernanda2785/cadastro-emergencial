## 1. Tabela: usuarios

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador do usuário|
|nome|VARCHAR(180)|Sim|Nome completo|
|cpf|VARCHAR(14)|Sim|CPF do usuário|
|email|VARCHAR(180)|Sim|E-mail|
|telefone|VARCHAR(30)|Não|Telefone|
|orgao|VARCHAR(180)|Não|Órgão ou instituição|
|unidade_setor|VARCHAR(180)|Não|Unidade ou setor|
|senha_hash|VARCHAR(255)|Sim|Senha criptografada|
|perfil|ENUM|Sim|cadastrador, gestor, administrador|
|ativo|TINYINT|Sim|Status do usuário|
|ultimo_acesso|DATETIME|Não|Último login|
|criado_em|DATETIME|Sim|Data de criação|
|atualizado_em|DATETIME|Não|Data de atualização|

## 2. Tabela: municipios

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador interno|
|codigo_ibge|VARCHAR(20)|Sim|Código IBGE|
|nome|VARCHAR(180)|Sim|Nome do município|
|uf|CHAR(2)|Sim|Unidade federativa|
|latitude|DECIMAL(10,7)|Não|Latitude aproximada|
|longitude|DECIMAL(10,7)|Não|Longitude aproximada|

## 3. Tabela: acoes_emergenciais

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador da ação|
|municipio_id|BIGINT UNSIGNED FK|Sim|Município da ação|
|localidade|VARCHAR(180)|Sim|Localidade, bairro ou comunidade principal|
|tipo_evento|VARCHAR(180)|Sim|Evento conforme COBRADE|
|data_evento|DATE|Sim|Data do evento|
|token_publico|VARCHAR(100)|Sim|Token usado no QR Code|
|status|ENUM|Sim|aberta, encerrada, cancelada|
|criado_por|BIGINT UNSIGNED FK|Sim|Administrador criador|
|criado_em|DATETIME|Sim|Criação|
|atualizado_em|DATETIME|Não|Atualização|

## 4. Tabela: residencias

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador da residência|
|acao_id|BIGINT UNSIGNED FK|Sim|Ação emergencial vinculada|
|protocolo|VARCHAR(80)|Sim|Protocolo único|
|municipio_id|BIGINT UNSIGNED FK|Sim|Município|
|bairro_comunidade|VARCHAR(180)|Sim|Bairro ou comunidade|
|endereco|VARCHAR(255)|Sim|Endereço completo|
|complemento|VARCHAR(180)|Não|Complemento|
|latitude|DECIMAL(10,7)|Não|Latitude|
|longitude|DECIMAL(10,7)|Não|Longitude|
|foto_georreferenciada|VARCHAR(255)|Não|Caminho da foto|
|data_cadastro|DATETIME|Sim|Data do cadastro|
|quantidade_familias|INT|Sim|Quantidade informada|
|cadastrado_por|BIGINT UNSIGNED FK|Sim|Usuário cadastrador|
|criado_em|DATETIME|Sim|Criação|
|atualizado_em|DATETIME|Não|Atualização|

## 5. Tabela: familias

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador da família|
|residencia_id|BIGINT UNSIGNED FK|Sim|Residência vinculada|
|responsavel_nome|VARCHAR(180)|Sim|Responsável familiar|
|responsavel_cpf|VARCHAR(14)|Sim|CPF|
|responsavel_rg|VARCHAR(30)|Não|RG|
|data_nascimento|DATE|Não|Data de nascimento|
|telefone|VARCHAR(30)|Não|Telefone|
|email|VARCHAR(180)|Não|E-mail|
|representante_nome|VARCHAR(180)|Não|Representante|
|representante_cpf|VARCHAR(14)|Não|CPF representante|
|representante_rg|VARCHAR(30)|Não|RG representante|
|representante_telefone|VARCHAR(30)|Não|Telefone representante|
|criado_em|DATETIME|Sim|Criação|
|atualizado_em|DATETIME|Não|Atualização|

## 6. Tabela: documentos_anexos

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador|
|familia_id|BIGINT UNSIGNED FK|Não|Família vinculada|
|residencia_id|BIGINT UNSIGNED FK|Não|Residência vinculada|
|tipo_documento|VARCHAR(80)|Sim|CPF, RG, comprovante, foto etc.|
|caminho_arquivo|VARCHAR(255)|Sim|Caminho do arquivo|
|mime_type|VARCHAR(100)|Sim|MIME type|
|tamanho_bytes|BIGINT|Sim|Tamanho|
|enviado_por|BIGINT UNSIGNED FK|Sim|Usuário|
|criado_em|DATETIME|Sim|Upload|

## 7. Tabela: tipos_ajuda

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador|
|nome|VARCHAR(180)|Sim|Nome do material|
|unidade_medida|VARCHAR(50)|Sim|kit, unidade, cesta, pacote etc.|
|ativo|TINYINT|Sim|Status|
|criado_em|DATETIME|Sim|Criação|

## 8. Tabela: entregas_ajuda

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador|
|familia_id|BIGINT UNSIGNED FK|Sim|Família beneficiária|
|tipo_ajuda_id|BIGINT UNSIGNED FK|Sim|Tipo de material|
|quantidade|DECIMAL(10,2)|Sim|Quantidade entregue|
|data_entrega|DATETIME|Sim|Data e hora|
|entregue_por|BIGINT UNSIGNED FK|Sim|Usuário logado|
|comprovante_codigo|VARCHAR(80)|Sim|Código do comprovante|
|observacao|TEXT|Não|Observações|

## 9. Tabela: tokens_idempotencia

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador|
|token|VARCHAR(100)|Sim|Token único|
|usuario_id|BIGINT UNSIGNED FK|Não|Usuário relacionado|
|rota|VARCHAR(180)|Sim|Rota/processo|
|processado_em|DATETIME|Sim|Data de processamento|
|ip_origem|VARCHAR(45)|Não|IP|

## 10. Tabela: logs_sistema

|   |   |   |   |
|---|---|---|---|
|Campo|Tipo|Obrigatório|Descrição|
|id|BIGINT UNSIGNED PK|Sim|Identificador|
|usuario_id|BIGINT UNSIGNED FK|Não|Usuário|
|acao|VARCHAR(100)|Sim|Tipo da ação|
|entidade|VARCHAR(100)|Sim|Entidade afetada|
|entidade_id|BIGINT|Não|ID afetado|
|descricao|TEXT|Não|Descrição|
|ip_origem|VARCHAR(45)|Não|IP|
|user_agent|VARCHAR(255)|Não|Navegador|
|criado_em|DATETIME|Sim|Data|