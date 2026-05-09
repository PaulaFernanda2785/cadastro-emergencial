SET NAMES utf8mb4;
SET time_zone = '-03:00';

ALTER TABLE entregas_ajuda
    ADD COLUMN status_operacional ENUM('registrado', 'entregue') NOT NULL DEFAULT 'entregue' AFTER quantidade,
    ADD COLUMN registrado_em DATETIME NULL AFTER status_operacional,
    ADD COLUMN entregue_em DATETIME NULL AFTER data_entrega,
    ADD KEY idx_entregas_status_operacional (status_operacional);

UPDATE entregas_ajuda
SET status_operacional = 'entregue',
    registrado_em = COALESCE(registrado_em, criado_em, data_entrega),
    entregue_em = COALESCE(entregue_em, data_entrega)
WHERE deleted_at IS NULL;
