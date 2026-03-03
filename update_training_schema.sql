CREATE TABLE IF NOT EXISTS `ad_training_summary` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `training_courses_taught` TEXT,
    `training_undergone` TEXT,
    `fdp_undergone` TEXT,
    `fdp_conducted` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_summary` (`faculty_id`, `academic_year`)
);
