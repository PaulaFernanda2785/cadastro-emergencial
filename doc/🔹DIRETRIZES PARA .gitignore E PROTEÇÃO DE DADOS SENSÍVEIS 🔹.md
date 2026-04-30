.env

/config/database.php

/storage/logs/*

/storage/cache/*

/storage/private_uploads/*

/public/storage/uploads/*

/public/assets/uploads/*

*.log

*.sql

!/database/schema.sql

!/database/seeders.sql

.DS_Store

Thumbs.db

/vendor/

/node_modules/

Arquivo .env.example

APP_NAME="Cadastro Emergencial"

APP_ENV=local

APP_DEBUG=true

APP_URL=http://localhost/cadastro-emergencial

  

DB_HOST=127.0.0.1

DB_PORT=3306

DB_DATABASE=nome_do_banco

DB_USERNAME=usuario_do_banco

DB_PASSWORD=senha_do_banco

  

SESSION_SECURE=false

CSRF_TOKEN_LIFETIME=7200

IDEMPOTENCY_WINDOW_SECONDS=5

