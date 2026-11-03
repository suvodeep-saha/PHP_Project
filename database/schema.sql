-- =======================================================
-- Online Assignment Submission System
-- Database Schema Definition & Initial Seed Data
-- =======================================================

CREATE DATABASE IF NOT EXISTS `online_assignment_system`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `online_assignment_system`;

-- -------------------------------------------------------
-- 1. Table structure for table: `users`
-- -------------------------------------------------------
DROP TABLE IF EXISTS `submissions`;
DROP TABLE IF EXISTS `assignments`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `phone` VARCHAR(20) DEFAULT NULL,
  `bio` TEXT DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 2. Table structure for table: `assignments`
-- -------------------------------------------------------
CREATE TABLE `assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `teacher_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `deadline` DATETIME NOT NULL,
  `file` VARCHAR(255) DEFAULT NULL,
  `max_marks` INT UNSIGNED NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_assignments_subject` (`subject`),
  INDEX `idx_assignments_deadline` (`deadline`),
  INDEX `idx_assignments_teacher` (`teacher_id`),
  CONSTRAINT `fk_assignments_teacher`
    FOREIGN KEY (`teacher_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 3. Table structure for table: `submissions`
-- -------------------------------------------------------
CREATE TABLE `submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT UNSIGNED NOT NULL,
  `assignment_id` INT UNSIGNED NOT NULL,
  `file` VARCHAR(255) NOT NULL,
  `student_notes` TEXT DEFAULT NULL,
  `submit_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `marks` DECIMAL(5,2) DEFAULT NULL,
  `feedback` TEXT DEFAULT NULL,
  `status` ENUM('Pending', 'Reviewed', 'Completed') NOT NULL DEFAULT 'Pending',
  `evaluated_at` DATETIME DEFAULT NULL,
  `evaluated_by` INT UNSIGNED DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_submissions_status` (`status`),
  INDEX `idx_submissions_student` (`student_id`),
  INDEX `idx_submissions_assignment` (`assignment_id`),
  UNIQUE KEY `unique_student_assignment` (`student_id`, `assignment_id`),
  CONSTRAINT `fk_submissions_student`
    FOREIGN KEY (`student_id`)
    REFERENCES `users` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_assignment`
    FOREIGN KEY (`assignment_id`)
    REFERENCES `assignments` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_submissions_evaluator`
    FOREIGN KEY (`evaluated_by`)
    REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------
-- 4. Initial Seed Data
-- Passwords:
-- admin@assignments.edu       -> admin123
-- prof.sharma@assignments.edu -> teacher123
-- dr.roy@assignments.edu      -> teacher123
-- suvo@student.edu            -> student123
-- priya@student.edu           -> student123
-- rahul@student.edu           -> student123
-- -------------------------------------------------------

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `bio`, `status`) VALUES
(1, 'System Administrator', 'admin@assignments.edu', '$2y$12$.O2JCXn.FIZzuIUnhBKT9.SPR48mq5I.h.gyouZOGEeeCy28RSYxK', 'admin', '+91 9876543210', 'Department Administrator overseeing the academic portal.', 'active'),
(2, 'Prof. Ramesh Sharma', 'prof.sharma@assignments.edu', '$2y$12$Lv.WLyISDIzm5furlZcMhOethXDSwqYU35x5BqVFnekVsOO1Kt/L.', 'teacher', '+91 9831012345', 'Senior Professor of Computer Science & Engineering. Teaching Web Technologies & DBMS.', 'active'),
(3, 'Dr. Ananya Roy', 'dr.roy@assignments.edu', '$2y$12$Lv.WLyISDIzm5furlZcMhOethXDSwqYU35x5BqVFnekVsOO1Kt/L.', 'teacher', '+91 9831098765', 'Associate Professor in IT Department. Specializes in Algorithms and Cloud Computing.', 'active'),
(4, 'Suvodeep Mukherjee', 'suvo@student.edu', '$2y$12$DeieJMZIl4GJuisR6L8N2eRRLHsjRD2Du3KVXsHketjHLH9UWaerW', 'student', '+91 9812345678', 'Final year B.Tech Computer Science student. Roll No: CS-2024-042.', 'active'),
(5, 'Priya Sen', 'priya@student.edu', '$2y$12$DeieJMZIl4GJuisR6L8N2eRRLHsjRD2Du3KVXsHketjHLH9UWaerW', 'student', '+91 9823456789', 'Final year B.Tech Computer Science student. Roll No: CS-2024-055.', 'active'),
(6, 'Rahul Verma', 'rahul@student.edu', '$2y$12$DeieJMZIl4GJuisR6L8N2eRRLHsjRD2Du3KVXsHketjHLH9UWaerW', 'student', '+91 9834567890', 'Final year B.Tech IT student. Roll No: IT-2024-019.', 'active');

-- Sample Assignments
INSERT INTO `assignments` (`id`, `teacher_id`, `title`, `subject`, `description`, `deadline`, `file`, `max_marks`) VALUES
(1, 2, 'PHP Session & Database Integration Lab', 'Web Technologies', 'Implement a secure login system using Core PHP PDO and MySQL. Ensure password hashing with bcrypt, session timeout after 30 minutes, and complete prevention of SQL Injection and XSS attacks. Submit full project source and report in PDF format.', DATE_ADD(NOW(), INTERVAL 7 DAY), 'sample_web_tech_assign1.pdf', 100),
(2, 2, 'Database Normalization & E-R Modeling', 'Database Management Systems', 'Design a 3NF normalized schema for a Hospital Management System. Include Entity-Relationship diagram, DDL script with foreign keys, and 5 complex analytical queries using JOINs and Aggregate functions.', DATE_ADD(NOW(), INTERVAL 14 DAY), 'sample_dbms_assign2.pdf', 100),
(3, 3, 'Dynamic Programming & Graph Traversal', 'Design & Analysis of Algorithms', 'Implement Bellman-Ford shortest path algorithm and Floyd-Warshall all-pairs shortest path in C++/Java. Compare asymptotic time and space complexities with experimental runtime graphs.', DATE_ADD(NOW(), INTERVAL 3 DAY), NULL, 50);

-- Sample Submissions demonstrating the 3 workflow statuses:
-- 1. Completed (evaluated, marks finalized, completed status)
-- 2. Reviewed (evaluated, feedback given)
-- 3. Pending (submitted by student, awaiting teacher review)
INSERT INTO `submissions` (`id`, `student_id`, `assignment_id`, `file`, `student_notes`, `submit_date`, `marks`, `feedback`, `status`, `evaluated_at`, `evaluated_by`) VALUES
(1, 4, 1, 'sub_1_student_4_web_tech.pdf', 'Completed all required modules including PDO prepared statements and CSRF token verification.', DATE_SUB(NOW(), INTERVAL 2 DAY), 94.50, 'Excellent work! Clean coding practices, proper session handling, and well-structured database schema.', 'Completed', DATE_SUB(NOW(), INTERVAL 1 DAY), 2),
(2, 5, 1, 'sub_1_student_5_web_tech.pdf', 'Implemented login and registration. Tested with sample database.', DATE_SUB(NOW(), INTERVAL 1 DAY), 86.00, 'Good implementation. Add comments to your SQL queries for better readability.', 'Reviewed', DATE_SUB(NOW(), INTERVAL 4 HOUR), 2),
(3, 6, 1, 'sub_1_student_6_web_tech.pdf', 'Submitted lab assignment with complete source code and execution screenshots.', NOW(), NULL, NULL, 'Pending', NULL, NULL);

