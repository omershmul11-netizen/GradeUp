ALTER TABLE `student_subject_grades`
    ADD COLUMN `study_units` TINYINT NULL AFTER `subject_id`;

ALTER TABLE `tutoring_groups`
    ADD COLUMN `study_units` TINYINT NULL AFTER `grade_level`;

-- בשלב הנוכחי הקטלוג המובנה מופעל רק עבור מתמטיקה, כיתה י״א, 4 יח״ל.
UPDATE `tutoring_groups` tg
JOIN `subjects` s ON s.subject_id = tg.subject_id
SET tg.study_units = 4
WHERE tg.grade_level = '11'
  AND (LOWER(s.subject_name) IN ('math', 'mathematics') OR s.subject_name LIKE '%מתמט%')
  AND tg.study_units IS NULL;

UPDATE `student_subject_grades` ssg
JOIN `students` st ON st.student_id = ssg.student_id
JOIN `subjects` s ON s.subject_id = ssg.subject_id
SET ssg.study_units = 4
WHERE st.grade_level = '11'
  AND (LOWER(s.subject_name) IN ('math', 'mathematics') OR s.subject_name LIKE '%מתמט%')
  AND ssg.study_units IS NULL;
