-- AI Intake module: add abha_id column to patient_data
-- Safe to run multiple times (uses OpenEMR's #IfMissingColumn directive).
-- Apply via: Administration > Upgrade > run this file, or manually via MySQL.

-- ── Dependency: form_note (standard OpenEMR table, may be missing in some installs) ──
#IfNotTable form_note
CREATE TABLE IF NOT EXISTS `form_note` (
  `id`                bigint(20)   NOT NULL auto_increment,
  `date`              datetime     DEFAULT NULL,
  `pid`               bigint(20)   DEFAULT NULL,
  `user`              varchar(255) DEFAULT NULL,
  `groupname`         varchar(255) DEFAULT NULL,
  `authorized`        tinyint(4)   DEFAULT NULL,
  `activity`          tinyint(4)   DEFAULT NULL,
  `note_type`         varchar(255) DEFAULT NULL,
  `message`           longtext,
  `doctor`            varchar(255) DEFAULT NULL,
  `date_of_signature` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
#EndIf

#IfMissingColumn patient_data abha_id
ALTER TABLE `patient_data`
  ADD COLUMN `abha_id` VARCHAR(20) DEFAULT NULL
    COMMENT 'ABHA Health ID (format XX-XXXX-XXXX-XXXX)',
  ADD UNIQUE KEY `idx_abha_id` (`abha_id`);
#EndIf

-- ── Step 2: Consent audit log ────────────────────────────────────────────────
#IfNotTable ai_intake_consent
CREATE TABLE `ai_intake_consent` (
  `id`                BIGINT(20)  NOT NULL AUTO_INCREMENT,
  `pid`               BIGINT(20)  NOT NULL COMMENT 'FK patient_data.pid',
  `consent_given`     TINYINT(1)  NOT NULL DEFAULT 0,
  `consent_timestamp` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address`        VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'IPv4 or IPv6',
  PRIMARY KEY (`id`),
  KEY `idx_aic_pid` (`pid`)
) ENGINE=InnoDB COMMENT='AI Intake: patient informed consent audit log';
#EndIf

-- ── Step 2: Per-visit intake session tracker ─────────────────────────────────
-- Designed for forward extension: Step 3+ adds interview_data, summary, etc.
-- via #IfMissingColumn patches — no ALTER needed now.
#IfNotTable ai_intake_session
CREATE TABLE `ai_intake_session` (
  `id`         BIGINT(20)  NOT NULL AUTO_INCREMENT,
  `pid`        BIGINT(20)  NOT NULL COMMENT 'FK patient_data.pid',
  `pathway`    VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'allopathic | ayurvedic',
  `status`     VARCHAR(20) NOT NULL DEFAULT 'consent'
               COMMENT 'consent | pathway | interview | complete | abandoned',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ais_pid`    (`pid`),
  KEY `idx_ais_status` (`status`)
) ENGINE=InnoDB COMMENT='AI Intake: per-visit kiosk session tracker (extended each step)';
#EndIf

-- ── Language selection ─────────────────────────────────────────────────────────
#IfMissingColumn ai_intake_session language
ALTER TABLE `ai_intake_session`
  ADD COLUMN `language` VARCHAR(10) NOT NULL DEFAULT 'en'
    COMMENT 'Welcome step: patient selected language code (en, mr, etc.)';
#EndIf

#IfMissingColumn ai_intake_session interaction_mode
ALTER TABLE `ai_intake_session`
  ADD COLUMN `interaction_mode` VARCHAR(10) NOT NULL DEFAULT 'tap'
    COMMENT 'Mode selection step: tap or voice';
#EndIf

-- ── Step 3: Interview & Document columns ─────────────────────────────────────
#IfMissingColumn ai_intake_session interview_data
ALTER TABLE `ai_intake_session`
  ADD COLUMN `interview_data` JSON DEFAULT NULL
    COMMENT 'Step 3: [{question, answer}] from voice/text interview (MOCK ASR placeholder)';
#EndIf

#IfMissingColumn ai_intake_session document_data
ALTER TABLE `ai_intake_session`
  ADD COLUMN `document_data` JSON DEFAULT NULL
    COMMENT 'Step 3: extracted doc fields — mock OCR output, swap for real OCR later';
#EndIf

#IfMissingColumn ai_intake_session queue_number
ALTER TABLE `ai_intake_session`
  ADD COLUMN `queue_number` INT DEFAULT NULL;
#EndIf

#IfMissingColumn ai_intake_session waiting_room
ALTER TABLE `ai_intake_session`
  ADD COLUMN `waiting_room` VARCHAR(50) DEFAULT NULL;
#EndIf

-- ── Step 4: Summary, real encounter linkage, red flag ─────────────────────────
#IfMissingColumn ai_intake_session summary_text
ALTER TABLE `ai_intake_session`
  ADD COLUMN `summary_text` TEXT DEFAULT NULL
    COMMENT 'Step 4: template-based (later LLM) clinical summary shown to doctor';
#EndIf

#IfMissingColumn ai_intake_session red_flag
ALTER TABLE `ai_intake_session`
  ADD COLUMN `red_flag` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Step 4: 1 = keyword red-flag heuristic triggered, show priority alert';
#EndIf

#IfMissingColumn ai_intake_session openemr_encounter_id
ALTER TABLE `ai_intake_session`
  ADD COLUMN `openemr_encounter_id` BIGINT(20) DEFAULT NULL
    COMMENT 'Step 4: FK form_encounter.encounter — real OpenEMR encounter created by kiosk';
#EndIf

#IfMissingColumn ai_intake_session openemr_form_note_id
ALTER TABLE `ai_intake_session`
  ADD COLUMN `openemr_form_note_id` BIGINT(20) DEFAULT NULL
    COMMENT 'Step 4: FK form_note.id — real clinical note created by kiosk';
#EndIf
