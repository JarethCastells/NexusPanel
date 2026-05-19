-- Auto-generate folio_hex for pedidos
ALTER TABLE `pedidos` ADD COLUMN IF NOT EXISTS `folio_hex` VARCHAR(50) NULL;
DELIMITER $$
CREATE TRIGGER `trg_pedidos_folio`
AFTER INSERT ON `pedidos`
FOR EACH ROW
BEGIN
    UPDATE `pedidos` SET `folio_hex` = UPPER(HEX(NEW.id)) WHERE `id` = NEW.id;
END$$
DELIMITER ;
