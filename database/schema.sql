-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Sanitized schema for GradeUp
-- גרסת שרת: 8.0.46
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gradeup`
--

-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `assignments`
--

CREATE TABLE `assignments` (
  `assignment_id` int NOT NULL,
  `group_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `assignments`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `assignment_answers`
--

CREATE TABLE `assignment_answers` (
  `answer_id` int NOT NULL,
  `assignment_id` int NOT NULL,
  `question_id` int NOT NULL,
  `student_id` int NOT NULL,
  `selected_option` enum('a','b','c','d') NOT NULL,
  `is_correct` tinyint(1) NOT NULL,
  `feedback` text,
  `answered_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `assignment_answers`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `assignment_questions`
--

CREATE TABLE `assignment_questions` (
  `question_id` int NOT NULL,
  `assignment_id` int NOT NULL,
  `question_text` text NOT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_option` enum('a','b','c','d') NOT NULL,
  `explanation` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `assignment_questions`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `assignment_submissions`
--

CREATE TABLE `assignment_submissions` (
  `submission_id` int NOT NULL,
  `assignment_id` int NOT NULL,
  `student_id` int NOT NULL,
  `answer_text` text NOT NULL,
  `ai_feedback` text,
  `ai_score` int DEFAULT NULL,
  `teacher_feedback` text,
  `status` enum('submitted','checked_by_ai','approved_by_teacher','needs_fix') DEFAULT 'submitted',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `assignment_submissions`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `coordinators`
--

CREATE TABLE `coordinators` (
  `coordinator_id` int NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role_title` varchar(100) DEFAULT 'רכז פדגוגי',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `coordinators`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `parents`
--

CREATE TABLE `parents` (
  `parent_id` int NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `parents`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `students`
--

CREATE TABLE `students` (
  `student_id` int NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `class_name` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `students`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `student_parents`
--

CREATE TABLE `student_parents` (
  `id` int NOT NULL,
  `student_id` int NOT NULL,
  `parent_id` int NOT NULL,
  `relationship` varchar(50) DEFAULT 'parent',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `student_parents`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `student_subject_grades`
--

CREATE TABLE `student_subject_grades` (
  `grade_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `latest_grade` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `student_subject_grades`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int NOT NULL,
  `subject_name` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `subjects`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `teachers`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int NOT NULL,
  `teacher_id` int DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `teacher_subjects`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `tutoring_groups`
--

CREATE TABLE `tutoring_groups` (
  `group_id` int NOT NULL,
  `subject_id` int DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `day_of_week` varchar(20) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `tutoring_groups`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `tutoring_group_students`
--

CREATE TABLE `tutoring_group_students` (
  `id` int NOT NULL,
  `group_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `tutoring_group_students`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `tutoring_sessions`
--

CREATE TABLE `tutoring_sessions` (
  `id` int NOT NULL,
  `group_id` int NOT NULL,
  `session_date` date NOT NULL,
  `topic` varchar(255) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `tutoring_sessions`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `tutoring_session_attendance`
--

CREATE TABLE `tutoring_session_attendance` (
  `id` int NOT NULL,
  `session_id` int NOT NULL,
  `student_id` int NOT NULL,
  `status` enum('present','absent','late') NOT NULL DEFAULT 'present',
  `note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `tutoring_session_attendance`
--


-- --------------------------------------------------------

--
-- מבנה טבלה עבור טבלה `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) DEFAULT 'coordinator',
  `student_id` int DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `coordinator_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- הוצאת מידע עבור טבלה `users`
--


--
-- Indexes for dumped tables
--

--
-- אינדקסים לטבלה `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`assignment_id`);

--
-- אינדקסים לטבלה `assignment_answers`
--
ALTER TABLE `assignment_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD UNIQUE KEY `unique_student_question` (`student_id`,`question_id`);

--
-- אינדקסים לטבלה `assignment_questions`
--
ALTER TABLE `assignment_questions`
  ADD PRIMARY KEY (`question_id`);

--
-- אינדקסים לטבלה `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD UNIQUE KEY `unique_assignment_student` (`assignment_id`,`student_id`);

--
-- אינדקסים לטבלה `coordinators`
--
ALTER TABLE `coordinators`
  ADD PRIMARY KEY (`coordinator_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- אינדקסים לטבלה `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- אינדקסים לטבלה `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- אינדקסים לטבלה `student_parents`
--
ALTER TABLE `student_parents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_parent` (`student_id`,`parent_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- אינדקסים לטבלה `student_subject_grades`
--
ALTER TABLE `student_subject_grades`
  ADD PRIMARY KEY (`grade_id`);

--
-- אינדקסים לטבלה `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`);

--
-- אינדקסים לטבלה `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`);

--
-- אינדקסים לטבלה `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `tutoring_groups`
--
ALTER TABLE `tutoring_groups`
  ADD PRIMARY KEY (`group_id`);

--
-- אינדקסים לטבלה `tutoring_group_students`
--
ALTER TABLE `tutoring_group_students`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `tutoring_sessions`
--
ALTER TABLE `tutoring_sessions`
  ADD PRIMARY KEY (`id`);

--
-- אינדקסים לטבלה `tutoring_session_attendance`
--
ALTER TABLE `tutoring_session_attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_student` (`session_id`,`student_id`);

--
-- אינדקסים לטבלה `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_coordinator` (`coordinator_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `assignment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_answers`
--
ALTER TABLE `assignment_answers`
  MODIFY `answer_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_questions`
--
ALTER TABLE `assignment_questions`
  MODIFY `question_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_submissions`
--
ALTER TABLE `assignment_submissions`
  MODIFY `submission_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coordinators`
--
ALTER TABLE `coordinators`
  MODIFY `coordinator_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_parents`
--
ALTER TABLE `student_parents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_subject_grades`
--
ALTER TABLE `student_subject_grades`
  MODIFY `grade_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tutoring_groups`
--
ALTER TABLE `tutoring_groups`
  MODIFY `group_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tutoring_group_students`
--
ALTER TABLE `tutoring_group_students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tutoring_sessions`
--
ALTER TABLE `tutoring_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tutoring_session_attendance`
--
ALTER TABLE `tutoring_session_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- הגבלות לטבלאות שהוצאו
--

--
-- הגבלות לטבלה `student_parents`
--
ALTER TABLE `student_parents`
  ADD CONSTRAINT `student_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE;

--
-- הגבלות לטבלה `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_coordinator` FOREIGN KEY (`coordinator_id`) REFERENCES `coordinators` (`coordinator_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
