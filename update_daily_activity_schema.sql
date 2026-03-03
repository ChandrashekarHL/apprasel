CREATE TABLE IF NOT EXISTS `ad_daily_ai_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `activity_date` date NOT NULL,
  `activity_text` text DEFAULT NULL,
  `status` enum('Assigned','Completed','Missed') DEFAULT 'Assigned',
  `briefing_json` text DEFAULT NULL,
  `briefing_html` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `ad_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text,
  `duration_minutes` int(11) DEFAULT 0,
  `proof_file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
