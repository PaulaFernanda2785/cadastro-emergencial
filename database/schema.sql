SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(180) NOT NULL,
    telefone VARCHAR(30) NULL,
    orgao VARCHAR(180) NULL,
    unidade_setor VARCHAR(180) NULL,
    militar TINYINT(1) NOT NULL DEFAULT 0,
    graduacao VARCHAR(80) NULL,
    nome_guerra VARCHAR(120) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('cadastrador', 'gestor', 'administrador') NOT NULL DEFAULT 'cadastrador',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_acesso DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_usuarios_cpf (cpf),
    UNIQUE KEY uk_usuarios_email (email),
    KEY idx_usuarios_perfil (perfil),
    KEY idx_usuarios_ativo (ativo),
    KEY idx_usuarios_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS municipios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_ibge VARCHAR(20) NOT NULL,
    nome VARCHAR(180) NOT NULL,
    uf CHAR(2) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    UNIQUE KEY uk_municipios_ibge (codigo_ibge),
    KEY idx_municipios_uf_nome (uf, nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS acoes_emergenciais (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    municipio_id BIGINT UNSIGNED NOT NULL,
    localidade VARCHAR(180) NOT NULL,
    tipo_evento VARCHAR(180) NOT NULL,
    data_evento DATE NOT NULL,
    token_publico VARCHAR(100) NOT NULL,
    status ENUM('aberta', 'encerrada', 'cancelada') NOT NULL DEFAULT 'aberta',
    criado_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_acoes_municipio FOREIGN KEY (municipio_id) REFERENCES municipios(id),
    CONSTRAINT fk_acoes_usuario FOREIGN KEY (criado_por) REFERENCES usuarios(id),
    UNIQUE KEY uk_acoes_token_publico (token_publico),
    KEY idx_acoes_status (status),
    KEY idx_acoes_municipio_status (municipio_id, status),
    KEY idx_acoes_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS residencias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    acao_id BIGINT UNSIGNED NOT NULL,
    protocolo VARCHAR(80) NOT NULL,
    municipio_id BIGINT UNSIGNED NOT NULL,
    bairro_comunidade VARCHAR(180) NOT NULL,
    endereco VARCHAR(255) NOT NULL,
    complemento VARCHAR(180) NULL,
    imovel ENUM('proprio', 'alugado', 'cedido') NULL,
    condicao_residencia ENUM('perda_total', 'perda_parcial', 'nao_atingida') NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    foto_georreferenciada VARCHAR(255) NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantidade_familias INT UNSIGNED NOT NULL DEFAULT 1,
    cadastrado_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_residencias_acao FOREIGN KEY (acao_id) REFERENCES acoes_emergenciais(id),
    CONSTRAINT fk_residencias_municipio FOREIGN KEY (municipio_id) REFERENCES municipios(id),
    CONSTRAINT fk_residencias_usuario FOREIGN KEY (cadastrado_por) REFERENCES usuarios(id),
    UNIQUE KEY uk_residencias_protocolo (protocolo),
    KEY idx_residencias_acao (acao_id),
    KEY idx_residencias_municipio (municipio_id),
    KEY idx_residencias_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS familias (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    residencia_id BIGINT UNSIGNED NOT NULL,
    responsavel_nome VARCHAR(180) NOT NULL,
    responsavel_cpf VARCHAR(14) NOT NULL,
    responsavel_rg VARCHAR(30) NULL,
    data_nascimento DATE NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(180) NULL,
    quantidade_integrantes INT UNSIGNED NOT NULL DEFAULT 1,
    possui_criancas TINYINT(1) NOT NULL DEFAULT 0,
    possui_idosos TINYINT(1) NOT NULL DEFAULT 0,
    possui_pcd TINYINT(1) NOT NULL DEFAULT 0,
    possui_gestantes TINYINT(1) NOT NULL DEFAULT 0,
    representante_nome VARCHAR(180) NULL,
    representante_cpf VARCHAR(14) NULL,
    representante_rg VARCHAR(30) NULL,
    representante_telefone VARCHAR(30) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_familias_residencia FOREIGN KEY (residencia_id) REFERENCES residencias(id),
    KEY idx_familias_cpf (responsavel_cpf),
    KEY idx_familias_representante_cpf (representante_cpf),
    KEY idx_familias_residencia (residencia_id),
    KEY idx_familias_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS documentos_anexos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    familia_id BIGINT UNSIGNED NULL,
    residencia_id BIGINT UNSIGNED NULL,
    tipo_documento VARCHAR(80) NOT NULL,
    nome_original VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    extensao VARCHAR(10) NOT NULL,
    tamanho_bytes BIGINT UNSIGNED NOT NULL,
    hash_arquivo CHAR(64) NULL,
    enviado_por BIGINT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_docs_familia FOREIGN KEY (familia_id) REFERENCES familias(id),
    CONSTRAINT fk_docs_residencia FOREIGN KEY (residencia_id) REFERENCES residencias(id),
    CONSTRAINT fk_docs_usuario FOREIGN KEY (enviado_por) REFERENCES usuarios(id),
    KEY idx_docs_familia (familia_id),
    KEY idx_docs_residencia (residencia_id),
    KEY idx_docs_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_ajuda (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    unidade_medida VARCHAR(50) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tipos_ajuda_nome (nome),
    KEY idx_tipos_ajuda_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entregas_ajuda (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    familia_id BIGINT UNSIGNED NOT NULL,
    tipo_ajuda_id BIGINT UNSIGNED NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    data_entrega DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entregue_por BIGINT UNSIGNED NOT NULL,
    comprovante_codigo VARCHAR(80) NOT NULL,
    observacao TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_entregas_familia FOREIGN KEY (familia_id) REFERENCES familias(id),
    CONSTRAINT fk_entregas_tipo FOREIGN KEY (tipo_ajuda_id) REFERENCES tipos_ajuda(id),
    CONSTRAINT fk_entregas_usuario FOREIGN KEY (entregue_por) REFERENCES usuarios(id),
    UNIQUE KEY uk_entregas_comprovante (comprovante_codigo),
    KEY idx_entregas_tipo_data (tipo_ajuda_id, data_entrega),
    KEY idx_entregas_familia (familia_id),
    KEY idx_entregas_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens_idempotencia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(128) NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    rota VARCHAR(180) NOT NULL,
    processado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_origem VARCHAR(45) NULL,
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_tokens_idempotencia_token (token),
    KEY idx_tokens_rota_data (rota, processado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs_sistema (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT UNSIGNED NULL,
    acao VARCHAR(100) NOT NULL,
    entidade VARCHAR(100) NOT NULL,
    entidade_id BIGINT UNSIGNED NULL,
    descricao TEXT NULL,
    ip_origem VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_logs_usuario_data (usuario_id, criado_em),
    KEY idx_logs_entidade (entidade, entidade_id),
    KEY idx_logs_acao_data (acao, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
