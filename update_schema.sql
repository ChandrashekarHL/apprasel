CREATE TABLE IF NOT EXISTS `ad_research_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `phd_guidance` VARCHAR(255),
    `journal_count` INT,
    `conference_count` INT,
    `conference_organized` VARCHAR(255),
    `research_funding` VARCHAR(255),
    `coe_member` VARCHAR(255),
    `gmu_bulletin` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_summary` (`faculty_id`, `academic_year`)
);
