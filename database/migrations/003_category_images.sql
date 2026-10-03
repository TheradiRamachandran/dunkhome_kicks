USE dunkhome_kicks;

SET @category_image_column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'categories'
      AND COLUMN_NAME = 'image'
);

SET @category_image_migration = IF(
    @category_image_column_exists = 0,
    'ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL AFTER description',
    'SELECT 1'
);

PREPARE category_image_statement FROM @category_image_migration;
EXECUTE category_image_statement;
DEALLOCATE PREPARE category_image_statement;