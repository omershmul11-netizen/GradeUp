-- Synthetic data for the local Docker demo only.
-- Demo password for all three roles: GradeUpDemo!2026
-- The application upgrades this legacy demo value to a secure hash on first login.

INSERT INTO `coordinators`
    (`coordinator_id`, `first_name`, `last_name`, `email`, `phone`, `role_title`, `is_active`)
VALUES
    (9001, 'Demo', 'Coordinator', 'coordinator@example.com', NULL, 'רכז פדגוגי', 1);

INSERT INTO `teachers`
    (`teacher_id`, `first_name`, `last_name`, `email`)
VALUES
    (9001, 'Demo', 'Teacher', 'teacher@example.com');

INSERT INTO `students`
    (`student_id`, `first_name`, `last_name`, `grade_level`, `class_name`, `email`)
VALUES
    (9001, 'Demo', 'Student', 'י', 'י1', 'student@example.com');

INSERT INTO `subjects`
    (`subject_id`, `subject_name`)
VALUES
    (9001, 'מתמטיקה');

INSERT INTO `teacher_subjects`
    (`id`, `teacher_id`, `subject_id`, `grade_level`)
VALUES
    (9001, 9001, 9001, 'י');

INSERT INTO `student_subject_grades`
    (`grade_id`, `student_id`, `subject_id`, `latest_grade`)
VALUES
    (9001, 9001, 9001, 72);

INSERT INTO `users`
    (`user_id`, `username`, `email`, `password`, `role`, `student_id`, `teacher_id`, `coordinator_id`)
VALUES
    (9001, 'demo.admin', 'coordinator@example.com', 'GradeUpDemo!2026', 'coordinator', NULL, NULL, 9001),
    (9002, 'demo.teacher', 'teacher@example.com', 'GradeUpDemo!2026', 'teacher', NULL, 9001, NULL),
    (9003, 'demo.student', 'student@example.com', 'GradeUpDemo!2026', 'student', 9001, NULL, NULL);
