ALTER TABLE `ad_research_summary` ADD COLUMN IF NOT EXISTS `proof_file_path` VARCHAR(255);
ALTER TABLE `ad_training_summary` ADD COLUMN IF NOT EXISTS `proof_file_path` VARCHAR(255);
ALTER TABLE `ad_consultancy_summary` ADD COLUMN IF NOT EXISTS `proof_file_path` VARCHAR(255);
ALTER TABLE `ad_administration` ADD COLUMN IF NOT EXISTS `proof_file_path` VARCHAR(255);
