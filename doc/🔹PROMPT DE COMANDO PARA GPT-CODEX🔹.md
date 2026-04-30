Atue como um Desenvolvedor PHP Sênior, Arquiteto de Software e Especialista em Segurança de Aplicações Web.

Preciso que você desenvolva o Sistema de Cadastro Emergencial conforme a documentação técnica do projeto.

## Contexto do sistema

O sistema deverá ser uma aplicação web em PHP moderno puro, sem framework, estruturada em arquitetura MVC customizada, com banco de dados MySQL/MariaDB, interface responsiva em HTML5, CSS3 e JavaScript Vanilla.

A finalidade do sistema é cadastrar residências e famílias atingidas por incidentes ou desastres, vinculando esses cadastros a ações emergenciais específicas acessadas por QR Code. O sistema também deverá controlar a distribuição de ajuda humanitária, emitir comprovantes de entrega e gerar prestação de contas por tipo de material distribuído.

## Ambientes

- Desenvolvimento local: WampServer64.
- Servidor local: Apache 2.4+, PHP 8.3+, MySQL 8+.
- Produção: Hostinger com PHP 8.4 e phpMyAdmin.
- Editor: Visual Studio Code.
- Banco de produção: usar variáveis de ambiente, nunca credenciais fixas no código.

## Requisitos obrigatórios

1. Usar arquitetura MVC organizada.
2. Usar PDO com prepared statements.
3. Usar controle de sessão seguro.
4. Usar password_hash e password_verify.
5. Criar autenticação com perfis: cadastrador, gestor e administrador.
6. Criar controle de permissões por middleware.
7. Criar módulo de ações emergenciais com geração de token público para QR Code.
8. Criar módulo de cadastro de residência.
9. Criar módulo de cadastro de famílias vinculadas à residência.
10. Criar módulo de tipos de ajuda humanitária.
11. Criar módulo de entrega de ajuda humanitária por família.
12. Emitir comprovante simples de entrega em formato adequado para impressão térmica.
13. Gerar prestação de contas por tipo de material.
14. Criar painel situacional com indicadores.
15. Criar relatório de cadastros e entregas.
16. Implementar proteção CSRF.
17. Implementar token de idempotência para evitar múltiplos cliques e reenvios duplicados.
18. Criar validações server-side.
19. Criar validações frontend sem depender exclusivamente delas.
20. Criar proteção de uploads.
21. Criar logs de operações sensíveis.
22. Criar .gitignore seguro.
23. Não expor credenciais, senhas, tokens ou dados sensíveis no GitHub.

## Requisito especial: prevenção de múltiplos cliques

Implemente dois pilares:

### Frontend

- JavaScript Vanilla para capturar submit.
- Desabilitar botão após primeiro envio.
- Alterar texto do botão para “Processando...”.
- Exibir indicador visual de carregamento.
- Impedir novo submit se o formulário já estiver em processamento.

### Backend

- Gerar token CSRF e token de idempotência.
- No POST, validar se o token é válido.
- Verificar se o token já foi processado nos últimos 5 segundos.
- Se já foi processado, ignorar nova execução e retornar aviso amigável.
- Envolver a query principal de INSERT em uma condição que só execute se a validação de clique único for bem-sucedida.

## Estrutura esperada

Crie a estrutura:

app/Controllers

app/Models

app/Services

app/Repositories

app/Core

app/Helpers

config

public/assets/css

public/assets/js

resources/views

database

storage/logs

storage/private_uploads

## Primeira entrega esperada

1. Criar estrutura de pastas.
2. Criar `public/index.php`.
3. Criar `Router.php`.
4. Criar `Database.php` usando PDO.
5. Criar `.env.example`.
6. Criar `.gitignore` seguro.
7. Criar `schema.sql`.
8. Criar tela de login.
9. Criar autenticação básica.
10. Criar middleware de perfil.

Desenvolva o código de forma limpa, comentada, segura, padronizada e pronta para evolução modular.

# OBSERVAÇÕES CRÍTICAS E MELHORIAS RECOMENDADAS

## 1. Banco de dados e credenciais

A documentação original cita domínio, banco e usuário de banco. Esses dados não devem ser mantidos dentro do código, README público ou repositório GitHub. Devem ser armazenados apenas em `.env` no ambiente real.

## 2. Cadastro offline

O cadastro offline é desejável, mas deve ser tratado como fase posterior. Para execução profissional, recomenda-se dividir em:

- Fase 1: sistema web online responsivo;
- Fase 2: PWA com cache local;
- Fase 3: sincronização offline com resolução de conflitos.

Implementar offline desde o início aumenta a complexidade e o risco de inconsistência dos dados.

## 3. Foto georreferenciada

A foto pode conter metadados EXIF, mas nem todo dispositivo preserva esses dados. O sistema deve também capturar latitude e longitude via navegador com autorização do usuário, não depender exclusivamente da imagem.

## 4. Prestação de contas

A prestação de contas deve seguir o princípio de uma ficha por tipo de material, conforme referência citada na especificação. A modelagem proposta permite gerar esse relatório filtrando `tipo_ajuda_id`.

## 5. Proteção de dados pessoais

O sistema tratará CPF, RG, endereço, telefone, documentos e dados de vulnerabilidade social. Portanto, deve aplicar controle rigoroso de acesso, logs, criptografia de senhas, política de retenção de anexos e cuidado com exportações.