CREATE TABLE IF NOT EXISTS `ad_faculty_timetable` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `room_no` varchar(50) DEFAULT NULL,
  `task_type` varchar(100) DEFAULT 'Teaching',
  PRIMARY KEY (`id`)
);

INSERT INTO `ad_faculty_timetable` (`faculty_id`, `day_of_week`, `start_time`, `end_time`, `course_name`, `room_no`, `task_type`) VALUES 
(1, 'Monday', '09:00:00', '10:00:00', 'Data Structures', 'LH-101', 'Teaching'),
(1, 'Monday', '11:00:00', '12:00:00', 'Algorithms', 'LH-102', 'Teaching'),
(1, 'Tuesday', '14:00:00', '16:00:00', 'Research Lab', 'RL-2', 'Research'),
(1, 'Wednesday', '10:00:00', '11:00:00', 'Mentoring Hour', 'OFF-1', 'Mentoring');
