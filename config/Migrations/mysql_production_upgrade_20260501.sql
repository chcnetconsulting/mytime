-- MyTime production MySQL migration, 2026-05-01
--
-- Purpose:
--   Upgrade the existing production MySQL schema to the current application structure:
--   - mandanten CRUD / bookings.mandant_id
--   - booking ownership via bookings.user_id
--   - shared booking groups via groups, users.group_id, bookings.group_id
--   - users.is_admin
--
-- Important:
--   1. Run this only after a fresh database backup.
--   2. This script is written for MySQL 8/9 and the current production dump shape.
--   3. It keeps the old bookings.kunde column for compatibility/history.
--   4. Existing bookings are assigned to the owner user cc@chcnet.at if present,
--      otherwise to the first existing user.
--   5. Existing users/bookings are assigned to the shared group HAYS-FRQ.

SET @db_name := DATABASE();

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `schema_upgrade_log` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `script_name` VARCHAR(190) NOT NULL,
  `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_schema_upgrade_log_script_name` (`script_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `schema_upgrade_log` (`script_name`)
VALUES ('mysql_production_upgrade_20260501');

-- Lightweight in-database backups. Keep these until the deployment is verified.
-- The columns are explicit so a later re-run does not fail after the live tables
-- have gained new NOT NULL columns.
CREATE TABLE IF NOT EXISTS `bookings_backup_20260501` (
  `id` BIGINT NOT NULL,
  `bookingdate` DATE NOT NULL,
  `ticket` VARCHAR(15) NOT NULL,
  `bookingpsp` VARCHAR(20) NOT NULL,
  `description` TEXT NOT NULL,
  `minutes` INT NOT NULL,
  `kunde` VARCHAR(20) NOT NULL,
  `created` DATETIME NOT NULL,
  `modified` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `bookings_backup_20260501` (
  `id`,
  `bookingdate`,
  `ticket`,
  `bookingpsp`,
  `description`,
  `minutes`,
  `kunde`,
  `created`,
  `modified`
)
SELECT
  `id`,
  `bookingdate`,
  `ticket`,
  `bookingpsp`,
  `description`,
  `minutes`,
  `kunde`,
  `created`,
  `modified`
FROM `bookings`;

CREATE TABLE IF NOT EXISTS `users_backup_20260501` (
  `id` BIGINT NOT NULL,
  `username` VARCHAR(255) NULL,
  `email` VARCHAR(255) NULL,
  `password` VARCHAR(255) NULL,
  `oidc_sub` VARCHAR(255) NULL,
  `first_name` VARCHAR(255) NULL,
  `last_name` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `users_backup_20260501` (
  `id`,
  `username`,
  `email`,
  `password`,
  `oidc_sub`,
  `first_name`,
  `last_name`
)
SELECT
  `id`,
  `username`,
  `email`,
  `password`,
  `oidc_sub`,
  `first_name`,
  `last_name`
FROM `users`;

COMMIT;

DELIMITER $$

DROP PROCEDURE IF EXISTS `mytime_add_column_if_missing`$$
CREATE PROCEDURE `mytime_add_column_if_missing`(
  IN table_name_in VARCHAR(64),
  IN column_name_in VARCHAR(64),
  IN alter_sql_in TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_in
      AND COLUMN_NAME = column_name_in
  ) THEN
    SET @mytime_sql := alter_sql_in;
    PREPARE mytime_stmt FROM @mytime_sql;
    EXECUTE mytime_stmt;
    DEALLOCATE PREPARE mytime_stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `mytime_add_index_if_missing`$$
CREATE PROCEDURE `mytime_add_index_if_missing`(
  IN table_name_in VARCHAR(64),
  IN index_name_in VARCHAR(64),
  IN alter_sql_in TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_in
      AND INDEX_NAME = index_name_in
  ) THEN
    SET @mytime_sql := alter_sql_in;
    PREPARE mytime_stmt FROM @mytime_sql;
    EXECUTE mytime_stmt;
    DEALLOCATE PREPARE mytime_stmt;
  END IF;
END$$

DROP PROCEDURE IF EXISTS `mytime_add_fk_if_missing`$$
CREATE PROCEDURE `mytime_add_fk_if_missing`(
  IN table_name_in VARCHAR(64),
  IN constraint_name_in VARCHAR(64),
  IN alter_sql_in TEXT
)
BEGIN
  IF NOT EXISTS (
    SELECT 1
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = table_name_in
      AND CONSTRAINT_NAME = constraint_name_in
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
  ) THEN
    SET @mytime_sql := alter_sql_in;
    PREPARE mytime_stmt FROM @mytime_sql;
    EXECUTE mytime_stmt;
    DEALLOCATE PREPARE mytime_stmt;
  END IF;
END$$

DELIMITER ;

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `mandanten` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mandanten_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `groups` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `created` DATETIME NULL,
  `modified` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `groups` (`name`, `created`, `modified`)
VALUES
  ('Default', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP),
  ('HAYS-FRQ', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP);

INSERT IGNORE INTO `mandanten` (`name`, `created`, `modified`)
SELECT DISTINCT TRIM(`kunde`), CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM `bookings`
WHERE `kunde` IS NOT NULL
  AND TRIM(`kunde`) <> '';

CALL `mytime_add_column_if_missing`(
  'users',
  'group_id',
  'ALTER TABLE `users` ADD COLUMN `group_id` BIGINT NULL AFTER `id`'
);

CALL `mytime_add_column_if_missing`(
  'users',
  'is_admin',
  'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `group_id`'
);

CALL `mytime_add_column_if_missing`(
  'users',
  'created',
  'ALTER TABLE `users` ADD COLUMN `created` DATETIME NULL AFTER `last_name`'
);

CALL `mytime_add_column_if_missing`(
  'users',
  'modified',
  'ALTER TABLE `users` ADD COLUMN `modified` DATETIME NULL AFTER `created`'
);

CALL `mytime_add_column_if_missing`(
  'bookings',
  'user_id',
  'ALTER TABLE `bookings` ADD COLUMN `user_id` BIGINT NULL AFTER `id`'
);

CALL `mytime_add_column_if_missing`(
  'bookings',
  'group_id',
  'ALTER TABLE `bookings` ADD COLUMN `group_id` BIGINT NULL AFTER `user_id`'
);

CALL `mytime_add_column_if_missing`(
  'bookings',
  'mandant_id',
  'ALTER TABLE `bookings` ADD COLUMN `mandant_id` BIGINT NULL AFTER `kunde`'
);

SET @shared_group_id := (
  SELECT `id`
  FROM `groups`
  WHERE `name` = 'HAYS-FRQ'
  LIMIT 1
);

SET @owner_user_id := (
  SELECT `id`
  FROM `users`
  WHERE `email` = 'cc@chcnet.at'
  ORDER BY `id`
  LIMIT 1
);

SET @owner_user_id := COALESCE(
  @owner_user_id,
  (SELECT `id` FROM `users` ORDER BY `id` LIMIT 1)
);

UPDATE `users`
SET `group_id` = @shared_group_id
WHERE `group_id` IS NULL;

UPDATE `users`
SET `is_admin` = 1
WHERE `email` = 'cc@chcnet.at';

UPDATE `bookings` `b`
JOIN `mandanten` `m`
  ON `m`.`name` = TRIM(`b`.`kunde`)
SET `b`.`mandant_id` = `m`.`id`
WHERE `b`.`mandant_id` IS NULL
  AND `b`.`kunde` IS NOT NULL
  AND TRIM(`b`.`kunde`) <> '';

UPDATE `bookings`
SET `user_id` = @owner_user_id
WHERE `user_id` IS NULL;

UPDATE `bookings`
SET `group_id` = @shared_group_id
WHERE `group_id` IS NULL;

-- Fail early before columns are made NOT NULL.
SET @missing_mandant_count := (SELECT COUNT(*) FROM `bookings` WHERE `mandant_id` IS NULL);
SET @missing_user_count := (SELECT COUNT(*) FROM `bookings` WHERE `user_id` IS NULL);
SET @missing_group_count := (
  SELECT
    (SELECT COUNT(*) FROM `users` WHERE `group_id` IS NULL) +
    (SELECT COUNT(*) FROM `bookings` WHERE `group_id` IS NULL)
);

COMMIT;

DELIMITER $$

DROP PROCEDURE IF EXISTS `mytime_assert_no_missing_required_refs`$$
CREATE PROCEDURE `mytime_assert_no_missing_required_refs`()
BEGIN
  IF @missing_mandant_count > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Migration stopped: bookings without mandant_id remain.';
  END IF;

  IF @missing_user_count > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Migration stopped: bookings without user_id remain.';
  END IF;

  IF @missing_group_count > 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Migration stopped: users/bookings without group_id remain.';
  END IF;
END$$

DELIMITER ;

CALL `mytime_assert_no_missing_required_refs`();

ALTER TABLE `users`
  MODIFY COLUMN `group_id` BIGINT NOT NULL,
  MODIFY COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE `bookings`
  MODIFY COLUMN `mandant_id` BIGINT NOT NULL,
  MODIFY COLUMN `user_id` BIGINT NOT NULL,
  MODIFY COLUMN `group_id` BIGINT NOT NULL;

CALL `mytime_add_index_if_missing`(
  'users',
  'idx_users_group_id',
  'ALTER TABLE `users` ADD INDEX `idx_users_group_id` (`group_id`)'
);

CALL `mytime_add_index_if_missing`(
  'bookings',
  'idx_bookings_mandant_id',
  'ALTER TABLE `bookings` ADD INDEX `idx_bookings_mandant_id` (`mandant_id`)'
);

CALL `mytime_add_index_if_missing`(
  'bookings',
  'idx_bookings_user_id',
  'ALTER TABLE `bookings` ADD INDEX `idx_bookings_user_id` (`user_id`)'
);

CALL `mytime_add_index_if_missing`(
  'bookings',
  'idx_bookings_group_id',
  'ALTER TABLE `bookings` ADD INDEX `idx_bookings_group_id` (`group_id`)'
);

CALL `mytime_add_fk_if_missing`(
  'users',
  'fk_users_group_id_groups_id',
  'ALTER TABLE `users` ADD CONSTRAINT `fk_users_group_id_groups_id` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
);

CALL `mytime_add_fk_if_missing`(
  'bookings',
  'fk_bookings_mandant_id_mandanten_id',
  'ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_mandant_id_mandanten_id` FOREIGN KEY (`mandant_id`) REFERENCES `mandanten` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
);

CALL `mytime_add_fk_if_missing`(
  'bookings',
  'fk_bookings_user_id_users_id',
  'ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_user_id_users_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
);

CALL `mytime_add_fk_if_missing`(
  'bookings',
  'fk_bookings_group_id_groups_id',
  'ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_group_id_groups_id` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE'
);

CREATE TABLE IF NOT EXISTS `cake_migrations` (
  `version` BIGINT NOT NULL,
  `migration_name` VARCHAR(100) NULL,
  `plugin` VARCHAR(100) NULL,
  `start_time` TIMESTAMP NULL,
  `end_time` TIMESTAMP NULL,
  `breakpoint` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL `mytime_add_column_if_missing`(
  'cake_migrations',
  'plugin',
  'ALTER TABLE `cake_migrations` ADD COLUMN `plugin` VARCHAR(100) NULL AFTER `migration_name`'
);

INSERT IGNORE INTO `cake_migrations` (`version`, `migration_name`, `plugin`, `start_time`, `end_time`, `breakpoint`)
VALUES
  (20260428193000, 'CreateUsers', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
  (20260428193100, 'CreateBookings', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
  (20260428210000, 'CreateMandantenAndLinkBookings', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
  (20260429030000, 'AddUserIdToBookings', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
  (20260429032000, 'AddGroupsForSharedBookings', NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0);

DROP PROCEDURE IF EXISTS `mytime_assert_no_missing_required_refs`;
DROP PROCEDURE IF EXISTS `mytime_add_fk_if_missing`;
DROP PROCEDURE IF EXISTS `mytime_add_index_if_missing`;
DROP PROCEDURE IF EXISTS `mytime_add_column_if_missing`;

SELECT
  (SELECT COUNT(*) FROM `bookings`) AS `bookings_count`,
  (SELECT COUNT(*) FROM `users`) AS `users_count`,
  (SELECT COUNT(*) FROM `mandanten`) AS `mandanten_count`,
  (SELECT COUNT(*) FROM `groups`) AS `groups_count`,
  (SELECT COUNT(*) FROM `bookings` WHERE `mandant_id` IS NULL) AS `bookings_without_mandant`,
  (SELECT COUNT(*) FROM `bookings` WHERE `user_id` IS NULL) AS `bookings_without_user`,
  (SELECT COUNT(*) FROM `bookings` WHERE `group_id` IS NULL) AS `bookings_without_group`,
  (SELECT COUNT(*) FROM `users` WHERE `group_id` IS NULL) AS `users_without_group`;
