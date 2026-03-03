-- Workload & Academic Effectiveness System Schema
-- Adds support for Weekly Plans, Activity Logs, and Logic Metrics

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Workload Templates
-- Defines the standard expected distribution for different roles (e.g., 40% Teaching, 30% Research)
DROP TABLE IF EXISTS `ad_workload_templates`;
CREATE TABLE `ad_workload_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_name` VARCHAR(50) NOT NULL UNIQUE, -- e.g., 'Professor', 'Assistant Professor', 'HoD'
    `teaching_ratio` DECIMAL(5,2) DEFAULT 0, -- Recommended % (e.g., 40.00)
    `research_ratio` DECIMAL(5,2) DEFAULT 0,
    `admin_ratio` DECIMAL(5,2) DEFAULT 0,
    `mentoring_ratio` DECIMAL(5,2) DEFAULT 0,
    `aav_ratio` DECIMAL(5,2) DEFAULT 0, -- Assessment, Valuation, etc.
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `ad_workload_templates` (`role_name`, `teaching_ratio`, `research_ratio`, `admin_ratio`, `mentoring_ratio`, `aav_ratio`) VALUES
('Professor', 40.00, 30.00, 10.00, 10.00, 10.00),
('Associate Professor', 50.00, 20.00, 10.00, 10.00, 10.00),
('Assistant Professor', 60.00, 10.00, 5.00, 10.00, 15.00),
('HoD', 20.00, 20.00, 40.00, 10.00, 10.00);

-- 2. Weekly Workload Plans
-- Stores what the faculty PLANS to do for a specific week (Strict 40h Compliance Check in UI)
DROP TABLE IF EXISTS `ad_workload_plans`;
CREATE TABLE `ad_workload_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `week_start_date` DATE NOT NULL, -- Always the Monday of the week
    `planned_teaching_hrs` DECIMAL(5,2) DEFAULT 0,
    `planned_research_hrs` DECIMAL(5,2) DEFAULT 0,
    `planned_admin_hrs` DECIMAL(5,2) DEFAULT 0,
    `planned_mentoring_hrs` DECIMAL(5,2) DEFAULT 0,
    `planned_aav_hrs` DECIMAL(5,2) DEFAULT 0,
    `status` ENUM('Draft', 'Submitted', 'Approved', 'Locked') DEFAULT 'Draft',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_week_plan` (`faculty_id`, `week_start_date`)
);

-- 3. Daily Activity Logs (The Execution Layer)
-- Replaces "Subjective One-Time Form". This is the continuous feed.
DROP TABLE IF EXISTS `ad_activity_logs`;
CREATE TABLE `ad_activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `log_date` DATE NOT NULL,
    `category` ENUM('Teaching', 'Research', 'Admin', 'Mentoring', 'AAV') NOT NULL,
    `duration_minutes` INT NOT NULL, -- Easier to sum than floats
    `description` TEXT,
    `proof_link` VARCHAR(255), -- Optional link to artifact
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`)
);

-- 4. Appraisal Intelligence Metrics (The Scores)
-- Calculated by the Engine, stored here for Dashboards
DROP TABLE IF EXISTS `ad_appraisal_metrics`;
CREATE TABLE `ad_appraisal_metrics` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` INT NOT NULL,
    `academic_year` VARCHAR(20) NOT NULL, -- e.g., '2024-2025'
    `metric_key` ENUM('TUI', 'WFR', 'ACS', 'RRF', 'FAEI') NOT NULL,
    `score_value` DECIMAL(10,2) NOT NULL,
    `last_calculated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`faculty_id`) REFERENCES `ad_faculty_users`(`id`),
    UNIQUE KEY `unique_metric` (`faculty_id`, `academic_year`, `metric_key`)
);

SET FOREIGN_KEY_CHECKS = 1;
