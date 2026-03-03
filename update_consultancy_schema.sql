CREATE TABLE IF NOT EXISTS `ad_consultancy_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `consultancy_projects_list` TEXT,
    `patents_filed_list` TEXT,
    `innovation_workshops_list` TEXT,
    `startup_contribution` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_summary` (`faculty_id`, `academic_year`)
);
