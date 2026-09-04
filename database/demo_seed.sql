-- Synthetic portfolio data for the local Docker demo only.
-- Demo password for all three roles: GradeUpDemo!2026
-- The application upgrades this legacy demo value to a secure hash on first login.

SET NAMES utf8mb4;
SET character_set_client = utf8mb4;

INSERT INTO `coordinators`
    (`coordinator_id`, `first_name`, `last_name`, `email`, `phone`, `role_title`, `is_active`)
VALUES
    (9001, 'דמו', 'רכזת', 'coordinator@example.com', '050-000-0001', 'רכזת פדגוגית', 1);

INSERT INTO `teachers` (`teacher_id`, `first_name`, `last_name`, `email`) VALUES
    (9001, 'דמו', 'מורה', 'teacher@example.com'),
    (9002, 'נועה', 'לוי', 'noa.levi@example.com'),
    (9003, 'אורי', 'כהן', 'uri.cohen@example.com'),
    (9004, 'יעל', 'מזרחי', 'yael.mizrahi@example.com'),
    (9005, 'דניאל', 'רז', 'daniel.raz@example.com');

INSERT INTO `subjects` (`subject_id`, `subject_name`) VALUES
    (9001, 'מתמטיקה'),
    (9002, 'אנגלית'),
    (9003, 'מדעי המחשב'),
    (9004, 'פיזיקה');

INSERT INTO `teacher_subjects` (`id`, `teacher_id`, `subject_id`, `grade_level`) VALUES
    (9001, 9001, 9001, '10'), (9002, 9002, 9001, '10'),
    (9003, 9002, 9003, '10'), (9004, 9003, 9003, '10'),
    (9005, 9003, 9004, '11'), (9006, 9004, 9002, '10'),
    (9007, 9004, 9002, '11'), (9008, 9005, 9001, '11'),
    (9009, 9005, 9004, '12'), (9010, 9001, 9001, '11');

INSERT INTO `students`
    (`student_id`, `first_name`, `last_name`, `grade_level`, `class_name`, `email`)
VALUES
    (9001, 'דמו', 'תלמיד', '10', 'י1', 'student@example.com'),
    (9002, 'איתי', 'אברהם', '10', 'י1', 'itay.avraham@example.com'),
    (9003, 'מאיה', 'בן דוד', '10', 'י1', 'maya.bendavid@example.com'),
    (9004, 'נועם', 'גולן', '10', 'י1', 'noam.golan@example.com'),
    (9005, 'תמר', 'דהן', '10', 'י1', 'tamar.dahan@example.com'),
    (9006, 'עומר', 'הררי', '10', 'י1', 'omer.harari@example.com'),
    (9007, 'ליה', 'וייס', '10', 'י1', 'lia.weiss@example.com'),
    (9008, 'יונתן', 'זיו', '10', 'י1', 'yonatan.ziv@example.com'),
    (9009, 'אמה', 'חזן', '10', 'י2', 'emma.hazan@example.com'),
    (9010, 'רועי', 'טל', '10', 'י2', 'roi.tal@example.com'),
    (9011, 'שירה', 'ישראלי', '10', 'י2', 'shira.israeli@example.com'),
    (9012, 'אדם', 'כץ', '10', 'י2', 'adam.katz@example.com'),
    (9013, 'רוני', 'לוין', '10', 'י2', 'roni.levin@example.com'),
    (9014, 'גיא', 'ממן', '10', 'י2', 'guy.maman@example.com'),
    (9015, 'אלה', 'נבון', '10', 'י2', 'ella.navon@example.com'),
    (9016, 'עידו', 'סגל', '10', 'י2', 'ido.segal@example.com'),
    (9017, 'נויה', 'עמר', '10', 'י3', 'noya.amar@example.com'),
    (9018, 'אריאל', 'פרץ', '10', 'י3', 'ariel.peretz@example.com'),
    (9019, 'אביגיל', 'צור', '11', 'יא1', 'avigail.tzur@example.com'),
    (9020, 'אופק', 'קרן', '11', 'יא1', 'ofek.keren@example.com'),
    (9021, 'נגה', 'רום', '11', 'יא1', 'noga.rom@example.com'),
    (9022, 'יובל', 'שחר', '11', 'יא1', 'yuval.shahar@example.com'),
    (9023, 'מיכל', 'תבור', '11', 'יא1', 'michal.tavor@example.com'),
    (9024, 'עמית', 'אלון', '11', 'יא1', 'amit.alon@example.com');

INSERT INTO `parents` (`parent_id`, `first_name`, `last_name`, `email`, `phone`) VALUES
    (9101, 'טל', 'תלמיד', 'parent01@example.com', '050-100-0001'),
    (9102, 'שרון', 'אברהם', 'parent02@example.com', '050-100-0002'),
    (9103, 'מיכל', 'בן דוד', 'parent03@example.com', '050-100-0003'),
    (9104, 'רונית', 'גולן', 'parent04@example.com', '050-100-0004'),
    (9105, 'אייל', 'דהן', 'parent05@example.com', '050-100-0005'),
    (9106, 'דנה', 'הררי', 'parent06@example.com', '050-100-0006'),
    (9107, 'גיל', 'וייס', 'parent07@example.com', '050-100-0007'),
    (9108, 'נועה', 'זיו', 'parent08@example.com', '050-100-0008'),
    (9109, 'ליאור', 'חזן', 'parent09@example.com', '050-100-0009'),
    (9110, 'יעל', 'טל', 'parent10@example.com', '050-100-0010'),
    (9111, 'אורן', 'ישראלי', 'parent11@example.com', '050-100-0011'),
    (9112, 'הילה', 'כץ', 'parent12@example.com', '050-100-0012'),
    (9113, 'רותם', 'לוין', 'parent13@example.com', '050-100-0013'),
    (9114, 'ענת', 'ממן', 'parent14@example.com', '050-100-0014'),
    (9115, 'אבי', 'נבון', 'parent15@example.com', '050-100-0015'),
    (9116, 'קרן', 'סגל', 'parent16@example.com', '050-100-0016');

INSERT INTO `student_parents` (`id`, `student_id`, `parent_id`, `relationship`) VALUES
    (9101, 9001, 9101, 'parent'), (9102, 9002, 9102, 'parent'),
    (9103, 9003, 9103, 'parent'), (9104, 9004, 9104, 'parent'),
    (9105, 9005, 9105, 'parent'), (9106, 9006, 9106, 'parent'),
    (9107, 9007, 9107, 'parent'), (9108, 9008, 9108, 'parent'),
    (9109, 9009, 9109, 'parent'), (9110, 9010, 9110, 'parent'),
    (9111, 9011, 9111, 'parent'), (9112, 9012, 9112, 'parent'),
    (9113, 9013, 9113, 'parent'), (9114, 9014, 9114, 'parent'),
    (9115, 9015, 9115, 'parent'), (9116, 9016, 9116, 'parent');

INSERT INTO `student_subject_grades` (`grade_id`, `student_id`, `subject_id`, `study_units`, `latest_grade`) VALUES
    (9001, 9001, 9001, 4, 68), (9002, 9002, 9001, 4, 66),
    (9003, 9003, 9001, 4, 64), (9004, 9004, 9001, 4, 69),
    (9005, 9005, 9001, 4, 63), (9006, 9006, 9001, 4, 67),
    (9007, 9007, 9001, 4, 65), (9008, 9008, 9001, 4, 70),
    (9101, 9001, 9003, NULL, 61), (9102, 9002, 9003, NULL, 62),
    (9103, 9003, 9003, NULL, 63), (9104, 9004, 9003, NULL, 64),
    (9105, 9005, 9003, NULL, 65), (9106, 9006, 9003, NULL, 66),
    (9107, 9007, 9003, NULL, 67), (9108, 9008, 9003, NULL, 68),
    (9109, 9009, 9003, NULL, 69), (9110, 9010, 9003, NULL, 70),
    (9111, 9011, 9003, NULL, 62), (9112, 9012, 9003, NULL, 64),
    (9113, 9013, 9003, NULL, 66), (9114, 9014, 9003, NULL, 68),
    (9115, 9015, 9003, NULL, 69), (9116, 9016, 9003, NULL, 70),
    (9201, 9019, 9002, NULL, 64), (9202, 9020, 9002, NULL, 67),
    (9203, 9021, 9002, NULL, 69), (9204, 9022, 9002, NULL, 65),
    (9205, 9023, 9002, NULL, 68), (9206, 9024, 9002, NULL, 63),
    (9301, 9019, 9001, 4, 64), (9302, 9020, 9001, 4, 67),
    (9303, 9021, 9001, 4, 69), (9304, 9022, 9001, 4, 65),
    (9305, 9023, 9001, 4, 68), (9306, 9024, 9001, 4, 63);

INSERT INTO `tutoring_groups`
    (`group_id`, `subject_id`, `grade_level`, `study_units`, `teacher_id`, `day_of_week`, `start_time`, `end_time`, `status`)
VALUES
    (9101, 9001, '10', 4, 9001, 'Sunday', '16:00:00', '17:00:00', 'approved'),
    (9102, 9002, '11', NULL, 9004, 'Tuesday', '17:00:00', '18:00:00', 'approved'),
    (9103, 9001, '11', 4, 9001, 'Wednesday', '18:00:00', '19:00:00', 'approved');

INSERT INTO `tutoring_group_students` (`id`, `group_id`, `student_id`) VALUES
    (9101, 9101, 9001), (9102, 9101, 9002), (9103, 9101, 9003),
    (9104, 9101, 9004), (9105, 9101, 9005), (9106, 9101, 9006),
    (9107, 9101, 9007), (9108, 9101, 9008),
    (9109, 9102, 9019), (9110, 9102, 9020), (9111, 9102, 9021),
    (9112, 9102, 9022), (9113, 9102, 9023), (9114, 9102, 9024);

INSERT INTO `assignments`
    (`assignment_id`, `group_id`, `title`, `description`, `due_date`, `created_by`)
VALUES
    (9201, 9101, 'תרגול משוואות ריבועיות', 'פתרו את שלוש השאלות ובדקו את דרך הפתרון.', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'demo.teacher');

INSERT INTO `assignment_questions`
    (`question_id`, `assignment_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `explanation`)
VALUES
    (9301, 9201, 'מהם פתרונות המשוואה x²-5x+6=0?', '2 ו-3', '1 ו-6', '-2 ו--3', 'אין פתרון', 'a', 'פירוק לגורמים: (x-2)(x-3)=0.'),
    (9302, 9201, 'מהו הדיסקרימיננט של x²+4x+4=0?', '0', '4', '8', '16', 'a', 'b²-4ac שווה 16-16, ולכן 0.'),
    (9303, 9201, 'לכמה פתרונות ממשיים יש משוואה עם דיסקרימיננט שלילי?', 'אפס', 'אחד', 'שניים', 'אינסוף', 'a', 'דיסקרימיננט שלילי אינו נותן שורשים ממשיים.');

INSERT INTO `assignment_submissions`
    (`submission_id`, `assignment_id`, `student_id`, `answer_text`, `ai_feedback`, `ai_score`, `teacher_feedback`, `status`)
VALUES
    (9401, 9201, 9002, 'עניתי על כל שאלות הבחירה.', 'עבודה מדויקת והסברים טובים.', 100, 'כל הכבוד!', 'approved_by_teacher');

INSERT INTO `assignment_answers`
    (`answer_id`, `assignment_id`, `question_id`, `student_id`, `selected_option`, `is_correct`, `feedback`)
VALUES
    (9401, 9201, 9301, 9002, 'a', 1, 'נכון'),
    (9402, 9201, 9302, 9002, 'a', 1, 'נכון'),
    (9403, 9201, 9303, 9002, 'a', 1, 'נכון');

INSERT INTO `tutoring_sessions` (`id`, `group_id`, `session_date`, `topic`, `notes`) VALUES
    (9501, 9101, DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'פירוק לגורמים', 'חזרה ותרגול בקבוצות קטנות'),
    (9502, 9102, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'זמני עבר באנגלית', 'תרגול דיבור וכתיבה');

INSERT INTO `tutoring_session_attendance` (`id`, `session_id`, `student_id`, `status`, `note`) VALUES
    (9501, 9501, 9001, 'present', ''), (9502, 9501, 9002, 'present', ''),
    (9503, 9501, 9003, 'late', 'איחור של 10 דקות'), (9504, 9501, 9004, 'present', ''),
    (9505, 9501, 9005, 'absent', 'חולה'), (9506, 9501, 9006, 'present', ''),
    (9507, 9501, 9007, 'present', ''), (9508, 9501, 9008, 'present', '');

INSERT INTO `users`
    (`user_id`, `username`, `email`, `password`, `role`, `student_id`, `teacher_id`, `coordinator_id`)
VALUES
    (9001, 'demo.admin', 'coordinator@example.com', 'GradeUpDemo!2026', 'coordinator', NULL, NULL, 9001),
    (9002, 'demo.teacher', 'teacher@example.com', 'GradeUpDemo!2026', 'teacher', NULL, 9001, NULL),
    (9003, 'demo.student', 'student@example.com', 'GradeUpDemo!2026', 'student', 9001, NULL, NULL);
