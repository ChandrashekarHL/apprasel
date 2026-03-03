USE appraisal_system;
ALTER TABLE `ad_daily_ai_activity` 
ADD COLUMN `user_response` TEXT NULL AFTER `activity_text`,
ADD COLUMN `ai_feedback` TEXT NULL AFTER `user_response`,
ADD COLUMN `interaction_log` TEXT NULL AFTER `ai_feedback`;
