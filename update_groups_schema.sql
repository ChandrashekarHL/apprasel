-- Phase 6: Faculty Groups Definitions Only
-- Complex ALTERs are handled in the PHP script to ensure safety/idempotency.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create Workload Groups Table
DROP TABLE IF EXISTS `ad_workload_groups`;
CREATE TABLE `ad_workload_groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_code` VARCHAR(5) NOT NULL UNIQUE, -- A, B, C, D, E
    `group_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `target_teaching` DECIMAL(5,2) DEFAULT 0,
    `target_research` DECIMAL(5,2) DEFAULT 0,
    `target_admin` DECIMAL(5,2) DEFAULT 0,
    `target_mentoring` DECIMAL(5,2) DEFAULT 0,
    `target_aav` DECIMAL(5,2) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert the 5 Standard Groups
INSERT INTO `ad_workload_groups` (`group_code`, `group_name`, `description`, `target_teaching`, `target_research`, `target_admin`, `target_mentoring`, `target_aav`) VALUES
('A', 'Teaching Intensive', 'Focus on Academic Delivery & Student Engagement', 60.00, 10.00, 10.00, 10.00, 10.00),
('B', 'Teaching & Research', 'Balanced Academic Profile', 40.00, 30.00, 10.00, 10.00, 10.00),
('C', 'Research Intensive', 'Focus on High-Impact Publications & Grants', 20.00, 60.00, 5.00, 5.00, 10.00),
('D', 'Administration & Leadership', 'Heads of Dept, Deans, Coordinators', 20.00, 10.00, 50.00, 10.00, 10.00),
('E', 'Probation / Formative', 'New Faculty or Special Development', 50.00, 10.00, 10.00, 10.00, 20.00);

SET FOREIGN_KEY_CHECKS = 1;
