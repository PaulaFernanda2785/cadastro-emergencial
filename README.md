# Cadastro Emergencial

Sistema web para gestão de cadastros emergenciais, ações de resposta, residências atingidas, famílias, entregas de ajuda, documentos institucionais e assinaturas eletrônicas em contexto de Proteção e Defesa Civil.

## Objetivo

O Cadastro Emergencial centraliza informações operacionais de campo para apoiar equipes de gestão, cadastradores e administração durante eventos adversos. O sistema permite abrir ações emergenciais, cadastrar residências e famílias, registrar entregas, gerar documentos e acompanhar assinaturas com controle por perfil de acesso.

## Principais módulos

- Painel situacional com indicadores operacionais e mapa georreferenciado.
- Ações emergenciais com link e QR Code personalizados.
- Cadastro de residências, famílias e documentos anexos.
- Registro de itens, confirmacao de entrega, historico e operacao em lote.
- Prestação de contas com filtros, documento gerado e fluxo de coassinatura.
- Programa Recomeçar para análise de famílias aptas ao benefício.
- DTI e documentos institucionais com assinatura principal e coautores.
- Central de assinaturas para pendências, histórico, autorização, negativa e impressão.
- Relatórios operacionais com filtros inteligentes e exportação.
- Administração de usuários, perfis e tipos de ajuda.

## Perfis de acesso

- `administrador`: acesso total aos registros, documentos, usuários e configurações.
- `gestor`: acesso operacional aos módulos de gestão, relatórios, documentos e entregas.
- `cadastrador`: acesso restrito aos cadastros e documentos vinculados ao próprio usuário e à ação emergencial aberta pelo link ou QR Code.

## Regras de segurança

- Documentos e registros respeitam escopo por perfil.
- Cadastradores não acessam dados de outras ações ou de outros usuários.
- Coassinaturas só liberam impressão quando todos os coautores autorizam.
- Negativa de coassinatura exige motivo.
- Sessão possui encerramento por inatividade.
- Uploads ficam em diretório privado fora da pasta pública.

## Tecnologia

- PHP 8+
- MySQL ou MariaDB
- Arquitetura MVC própria
- HTML, CSS e JavaScript sem dependência de build obrigatório
- Apache com `mod_rewrite`

## Estrutura

```text
app/          Código da aplicação
config/       Configurações
database/     Schema e patches SQL
public/       Entrada pública e assets
resources/    Views
storage/      Cache, logs e uploads privados
terit/        Bases territoriais
```

## Configuração local

1. Copie `.env.example` para `.env`.
2. Configure banco, URL e timezone no `.env`.
3. Importe `database/schema.sql`.
4. Crie o primeiro administrador com `database/seed_admin.sql` ou pelo fluxo administrativo existente.
5. Aponte o servidor web para `public/` ou use o front controller de raiz conforme o ambiente.

## Publicação

A pasta `deploy/` pode ser gerada localmente para hospedagem compartilhada, separando:

- `public_html/`: arquivos públicos do domínio;
- `emergencial_app/`: aplicação fora do webroot;
- `database/`: SQL de implantação.

Por segurança, `deploy/` não deve ser versionada em repositórios públicos, pois pode conter configurações e dados operacionais.

## Licença

Consulte o arquivo `LICENSE`.
