-- Adiciona e-mail do representante familiar.
-- Execute este arquivo uma vez no banco do servidor compartilhado.

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'familias'
      AND COLUMN_NAME = 'representante_email'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE familias ADD COLUMN representante_email VARCHAR(180) NULL AFTER representante_telefone',
    'SELECT "Coluna representante_email ja existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
