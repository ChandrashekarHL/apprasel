-- Phase 12: Audit Trail & Timetable Inputs

-- 1. Plan Audit Trail
CREATE TABLE IF NOT EXISTS `ad_plan_audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT NOT NULL,
    `action_by` INT NOT NULL, -- User ID of who changed it
    `action_type` ENUM('Created', 'Updated', 'Submitted', 'Approved', 'Rejected') NOT NULL,
    `comment` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `ad_workload_plans`(`id`)
);

-- 2. Add Timetable Constraints to Plan
ALTER TABLE `ad_workload_plans` ADD COLUMN `timetable_constraints` TEXT AFTER `week_start_date`; 
-- JSON or Text description of "Preferred Free Slots" or "Hard Constraints"

-- 3. Add Rejection Reason
ALTER TABLE `ad_workload_plans` ADD COLUMN `rejection_reason` TEXT;
