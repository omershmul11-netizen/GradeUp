ALTER TABLE `assignments`
    ADD COLUMN `assignment_type` VARCHAR(30) NOT NULL DEFAULT 'legacy_mcq' AFTER `created_by`,
    ADD COLUMN `worksheet_pdf_path` VARCHAR(500) NULL AFTER `assignment_type`,
    ADD COLUMN `worksheet_preview_path` VARCHAR(500) NULL AFTER `worksheet_pdf_path`,
    ADD COLUMN `curriculum_topic_id` VARCHAR(80) NULL AFTER `worksheet_preview_path`,
    ADD COLUMN `curriculum_subtopic_id` VARCHAR(80) NULL AFTER `curriculum_topic_id`;

