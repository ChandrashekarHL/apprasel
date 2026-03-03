-- Migration: Add new columns to ad_academic_source for ERP API sync
-- Run once. Columns are added only if they don't already exist.

USE appraisal_system;

-- Add subject_code
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS subject_code VARCHAR(30) NULL AFTER course_title;

-- Add section
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS section VARCHAR(20) NULL AFTER subject_code;

-- Add semester number
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS semester TINYINT UNSIGNED NULL AFTER section;

-- Add term (ODD / EVEN)
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS term VARCHAR(10) NULL AFTER semester;

-- Add is_cc (Course Coordinator flag)
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS is_cc TINYINT(1) NOT NULL DEFAULT 0 AFTER term;

-- Add approved flag
ALTER TABLE ad_academic_source
    ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 1 AFTER is_cc;

-- Add unique key to prevent duplicate imports from ERP
-- (Drop first if exists to avoid error on re-run)
ALTER TABLE ad_academic_source
    DROP INDEX IF EXISTS uq_faculty_subject_section_year;

ALTER TABLE ad_academic_source
    ADD UNIQUE KEY uq_faculty_subject_section_year (faculty_id, subject_code, section, academic_year);

SELECT 'Migration complete. ad_academic_source updated.' AS status;
