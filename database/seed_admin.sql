SET NAMES utf8mb4;

-- Seed de exemplo para bootstrap do primeiro administrador.
-- Nao grave senha em texto puro no campo senha_hash.
-- Para definir a senha manualmente, gere um hash com:
-- php -r "echo password_hash('SUA_SENHA_FORTE', PASSWORD_DEFAULT);"
-- Depois atualize apenas o campo senha_hash no banco.
--
-- Antes de executar em um ambiente real, substitua:
-- - nome
-- - cpf
-- - email
-- - senha_hash

INSERT INTO usuarios (
    nome,
    cpf,
    email,
    telefone,
    orgao,
    unidade_setor,
    senha_hash,
    perfil,
    ativo
) VALUES (
    'Administrador do Sistema',
    '000.000.000-00',
    'admin@example.local',
    NULL,
    NULL,
    NULL,
    '$2y$12$H1dIRGDm1aVoO5I6UivpKOJ4hAyhgHohSYNW8eoMKUGeyzTqhRJ22',
    'administrador',
    1
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    perfil = 'administrador',
    ativo = 1,
    atualizado_em = CURRENT_TIMESTAMP;
