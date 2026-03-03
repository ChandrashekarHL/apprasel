-- Add Users defined in SRS Module 1
-- Passwords are set to 'admin123' for Governance roles, 'password123' for Faculty

INSERT INTO `ad_faculty_users` 
(`username`, `password`, `full_name`, `email`, `department`, `designation`, `role`, `teaching_role`, `group_id`, `date_joined`) 
VALUES 
-- 1. Faculty (Standard)
('faculty_ug', 'password123', 'Prof. Arjun Singh', 'arjun@gmu.edu', 'Computer Science', 'Assistant Professor', 'Faculty', 'UG', 1, '2023-01-01'),

-- 2. Faculty (Research Focused)
('faculty_res', 'password123', 'Dr. Sarah Khan', 'sarah@gmu.edu', 'Data Science', 'Professor', 'Faculty', 'PhD', 3, '2022-06-15'),

-- 3. Head of Department (HoD)
('hod_cse', 'admin123', 'Dr. Rajesh Kumar', 'hod.cse@gmu.edu', 'Computer Science', 'Head of Department', 'Reviewer', 'Mixed', 4, '2015-05-20'),

-- 4. Dean (Academic Affairs)
('dean_academics', 'admin123', 'Dr. Emily Blunt', 'dean.acad@gmu.edu', 'Dean Office', 'Dean of Academics', 'Admin', 'Mixed', 4, '2010-08-01'),

-- 5. Registrar
('registrar', 'admin123', 'Mr. Robert Vance', 'registrar@gmu.edu', 'Administration', 'Registrar', 'Admin', 'Mixed', 4, '2012-11-10'),

-- 6. Vice Chancellor (VC)
('vc_gmu', 'admin123', 'Prof. Charles Xavier', 'vc@gmu.edu', 'VC Secretariat', 'Vice Chancellor', 'Admin', 'Mixed', 4, '2008-01-01');
