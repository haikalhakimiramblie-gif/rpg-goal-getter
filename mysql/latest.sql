-- Check and add status column
SET @status_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goals' AND COLUMN_NAME = 'status');
SET @sql = IF(@status_exists = 0, 'ALTER TABLE goals ADD COLUMN status VARCHAR(20) DEFAULT "active"', 'SELECT "status exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;

-- Check and add total_hp_deducted column
SET @thd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goals' AND COLUMN_NAME = 'total_hp_deducted');
SET @sql = IF(@thd_exists = 0, 'ALTER TABLE goals ADD COLUMN total_hp_deducted INT DEFAULT 0', 'SELECT "total_hp_deducted exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;

-- Check and add total_quests_failed column  
SET @tqf_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_stats' AND COLUMN_NAME = 'total_quests_failed');
SET @sql = IF(@tqf_exists = 0, 'ALTER TABLE user_stats ADD COLUMN total_quests_failed INT DEFAULT 0', 'SELECT "total_quests_failed exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;

-- Check and add pending_attacks column
SET @pa_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goals' AND COLUMN_NAME = 'pending_attacks');
SET @sql = IF(@pa_exists = 0, 'ALTER TABLE goals ADD COLUMN pending_attacks INT DEFAULT 0', 'SELECT "pending_attacks exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;