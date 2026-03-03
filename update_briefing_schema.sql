ALTER TABLE `ad_daily_ai_activity` 
ADD COLUMN `briefing_content` TEXT NULL AFTER `status`,
ADD COLUMN `briefing_seen` TINYINT(1) DEFAULT 0 AFTER `briefing_content`;
