-- Database Setup for Self-Appraisal Module

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `ad_appraisal_reviews`;
DROP TABLE IF EXISTS `ad_appraisal_files`;
DROP TABLE IF EXISTS `ad_appraisal_consultancy`;
DROP TABLE IF EXISTS `ad_appraisal_training`;
DROP TABLE IF EXISTS `ad_appraisal_research`;
DROP TABLE IF EXISTS `ad_appraisal_academic_defs`;
DROP TABLE IF EXISTS `ad_academic_source`;
DROP TABLE IF EXISTS `ad_faculty_users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Mock Users Table
CREATE TABLE `ad_faculty_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL, -- In production, use password_hash
    `full_name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `designation` VARCHAR(100) NOT NULL,
    `date_joined` DATE,
    `email` VARCHAR(100)
);

-- Insert Mock Faculty
INSERT INTO `ad_faculty_users` (`username`, `password`, `full_name`, `department`, `designation`, `date_joined`, `email`) VALUES
('faculty1', 'password123', 'Dr. John Doe', 'Computer Science', 'Professor', '2020-01-15', 'john.doe@example.com'),
('faculty2', 'password123', 'Prof. Jane Smith', 'Electronics', 'Associate Professor', '2019-06-10', 'jane.smith@example.com'),
('reviewer1', 'admin123', 'Dr. Alan Turing', 'Dean Office', 'Dean', '2015-03-01', 'dean@example.com');


-- 2. Mock Source Table for Academic Data (Read-Only for Faculty)
-- Dropping old table to recreate with new schema if it exists (for demo purposes)
DROP TABLE IF EXISTS `ad_academic_source`;
CREATE TABLE `ad_academic_source` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `program_semester` VARCHAR(100) NOT NULL,
    `course_title` VARCHAR(150) NOT NULL,
    `avg_student_feedback` DECIMAL(4, 2), -- e.g. 8.5
    `percentage_result` DECIMAL(5, 2),
    `class_avg_grade` VARCHAR(5), -- e.g. "A"
    `avg_attainment_level` ENUM('1', '2', '3') NOT NULL COMMENT '1=High, 2=Medium, 3=Low',
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);

-- Insert Mock Academic Data (Updated Format)
INSERT INTO `ad_academic_source` (`faculty_id`, `academic_year`, `program_semester`, `course_title`, `avg_student_feedback`, `percentage_result`, `class_avg_grade`, `avg_attainment_level`) VALUES
(1, '2024-2025', 'B.Tech CS Sem 3', 'Data Structures', 8.5, 92.5, 'A', '1'),
(1, '2024-2025', 'B.Tech CS Sem 5', 'Algorithms', 9.0, 88.0, 'A+', '1'),
(1, '2024-2025', 'M.Tech CS Sem 1', 'Advanced AI', 7.8, 85.0, 'B+', '2');


-- 3. Academic Manual Questions (Form Submission)
CREATE TABLE IF NOT EXISTS `ad_appraisal_academic_defs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `weekly_load` ENUM('Yes', 'No') NOT NULL,
    `teaching_diary` ENUM('Yes', 'No') NOT NULL,
    `student_register` ENUM('Yes', 'No') NOT NULL,
    `eval_ontime` ENUM('Yes', 'No') NOT NULL,
    `marks_entry_ontime` ENUM('Yes', 'No') NOT NULL,
    `regular_classes` ENUM('Yes', 'No') NOT NULL,
    `syllabus_coverage` ENUM('Yes', 'No') NOT NULL,
    `attainment_calc` ENUM('Yes', 'No') NOT NULL,
    `materials_developed` ENUM('Yes', 'No') NOT NULL,
    `proof_file_path` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);


-- 4. Research Section Table
CREATE TABLE IF NOT EXISTS `ad_appraisal_research` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `publication_type` ENUM('Journal', 'Conference', 'Book Chapter', 'Patent') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `journal_name` VARCHAR(255),
    `publication_date` DATE,
    `impact_factor` DECIMAL(5, 2),
    `citation_count` INT DEFAULT 0,
    `status` ENUM('Submitted', 'Accepted', 'Published') DEFAULT 'Published',
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);

-- 5. Training & Competency Table
CREATE TABLE IF NOT EXISTS `ad_appraisal_training` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `program_type` ENUM('FDP', 'Workshop', 'Seminar', 'Course') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `organized_by` VARCHAR(255),
    `duration_days` INT,
    `start_date` DATE,
    `end_date` DATE,
    `outcome` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);

-- 6. Consultancy & Innovation Table
CREATE TABLE IF NOT EXISTS `ad_appraisal_consultancy` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `project_type` ENUM('Consultancy', 'Funded Project', 'Product Dev', 'Start-up') NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `funding_agency` VARCHAR(255),
    `amount_sanctioned` DECIMAL(15, 2),
    `status` ENUM('Ongoing', 'Completed', 'Proposed') NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);

-- 7. Attachments Tracking Table
CREATE TABLE IF NOT EXISTS `ad_appraisal_files` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `section_name` ENUM('Academic', 'Research', 'Training', 'Consultancy') NOT NULL,
    `record_id` INT NOT NULL, -- ID from the respective table
    `file_path` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. Reviews and Grading Table
CREATE TABLE IF NOT EXISTS `ad_appraisal_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `reviewer_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `section_name` ENUM('Academic', 'Research', 'Training', 'Consultancy', 'Overall') NOT NULL,
    `score_awarded` DECIMAL(5, 2),
    `max_score` DECIMAL(5, 2),
    `remarks` TEXT,
    `review_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
