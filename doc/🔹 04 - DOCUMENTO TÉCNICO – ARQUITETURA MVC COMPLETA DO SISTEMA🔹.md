## 1. Visão geral da arquitetura

O sistema deverá ser desenvolvido em PHP moderno puro, com arquitetura MVC customizada, separando responsabilidades em camadas de apresentação, controle, regra de negócio, persistência e infraestrutura.

## 2. Estrutura sugerida de diretórios

cadastro-emergencial/

│ │ └── UsuarioController.php

│ │

│ ├── Models/

│ │ ├── Usuario.php

│ │ ├── AcaoEmergencial.php

│ │ ├── Residencia.php

│ │ ├── Familia.php

│ │ ├── DocumentoAnexo.php

│ │ ├── TipoAjuda.php

│ │ ├── EntregaAjuda.php

│ │ └── LogSistema.php

│ │

│ ├── Services/

│ │ ├── AuthService.php

│ │ ├── ProtocoloService.php

│ │ ├── QrCodeService.php

│ │ ├── GeolocalizacaoService.php

│ │ ├── CadastroService.php

│ │ ├── EntregaService.php

│ │ ├── PrestacaoContasService.php

│ │ ├── RelatorioService.php

│ │ ├── UploadService.php

│ │ └── IdempotenciaService.php

│ │

│ ├── Repositories/

│ │ ├── UsuarioRepository.php

│ │ ├── AcaoEmergencialRepository.php

│ │ ├── ResidenciaRepository.php

│ │ ├── FamiliaRepository.php

│ │ ├── TipoAjudaRepository.php

│ │ ├── EntregaRepository.php

│ │ └── LogRepository.php

│ │

│ ├── Core/

│ │ ├── Router.php

│ │ ├── Controller.php

│ │ ├── Database.php

│ │ ├── View.php

│ │ ├── Middleware.php

│ │ ├── Csrf.php

│ │ ├── Session.php

│ │ └── Validator.php

│ │

│ └── Helpers/

│ ├── functions.php

│ ├── auth.php

│ ├── response.php

│ └── formatters.php

│

├── config/

│ ├── app.php

│ ├── database.php

│ └── security.php

│

├── public/

│ ├── index.php

│ ├── .htaccess

│ ├── assets/

│ │ ├── css/

│ │ ├── js/

│ │ ├── images/

│ │ └── uploads/

│ └── storage/

│

├── resources/

│ └── views/

│ ├── layouts/

│ ├── auth/

│ ├── dashboard/

│ ├── acoes/

│ ├── residencias/

│ ├── familias/

│ ├── entregas/

│ ├── relatorios/

│ └── usuarios/

│

├── database/

│ ├── schema.sql

│ └── seeders.sql

│

├── storage/

│ ├── logs/

│ ├── cache/

│ └── private_uploads/

│

├── .env.example

├── .gitignore

└── README.md

## 3. Fluxo MVC

1. Usuário acessa uma rota.
2. `public/index.php` recebe a requisição.
3. `Router.php` identifica controller e método.
4. Middleware valida autenticação, perfil e CSRF.
5. Controller recebe os dados e aciona Service.
6. Service aplica regras de negócio.
7. Repository executa operações no banco via PDO.
8. Controller retorna View ou JSON.
9. Logs são registrados em operações relevantes.

## 4. Camadas de segurança obrigatórias

- Uso de PDO com prepared statements.
- Senhas com `password_hash()` e `password_verify()`.
- CSRF token em formulários.
- Token de idempotência para evitar múltiplos envios.
- Validação server-side de todos os campos.
- Sanitização de saída HTML.
- Controle de acesso por perfil.
- Upload com validação de MIME type, extensão e tamanho.
- Armazenamento de arquivos sensíveis fora da pasta pública, quando possível.
- `.env` fora do versionamento.
- `.gitignore` protegendo credenciais e uploads.