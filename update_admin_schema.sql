CREATE TABLE IF NOT EXISTS `ad_administration` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `marketing_activities` TEXT,
    `student_affairs_involvement` TEXT,
    `career_advice_placements` TEXT,
    `innovation_entrepreneurship` TEXT,
    `exam_evaluation_duties` TEXT,
    `university_docs` TEXT,
    `iqac_work` TEXT,
    `student_proctoring` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_admin` (`faculty_id`, `academic_year`)
);

CREATE TABLE IF NOT EXISTS `ad_appraisal_final_verdict` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `reviewer_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `total_marks` DECIMAL(5, 2),
    `comments` TEXT,
    `recommendation_candidate` TEXT,
    `recommendation_management` TEXT,
    `signature` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE `ad_appraisal_reviews` 
MODIFY COLUMN `section_name` ENUM('Academic', 'Research', 'Training', 'Consultancy', 'Administration', 'Overall') NOT NULL;
