<?php

/**
 * AI Intake — Step 2 Migration: Consent & Session Tables
 *
 * Creates ai_intake_consent and ai_intake_session tables.
 * NOTE: Doctrine Migrations is NOT yet fully integrated (see db/README.md #10708).
 * Apply table.sql in the module directory instead.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI Intake Step 2: add ai_intake_consent and ai_intake_session tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `ai_intake_consent` (
              `id`                BIGINT(20)  NOT NULL AUTO_INCREMENT,
              `pid`               BIGINT(20)  NOT NULL COMMENT 'FK patient_data.pid',
              `consent_given`     TINYINT(1)  NOT NULL DEFAULT 0,
              `consent_timestamp` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `ip_address`        VARCHAR(45) NOT NULL DEFAULT '',
              PRIMARY KEY (`id`),
              KEY `idx_aic_pid` (`pid`)
            ) ENGINE=InnoDB COMMENT='AI Intake: patient informed consent audit log'
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `ai_intake_session` (
              `id`         BIGINT(20)  NOT NULL AUTO_INCREMENT,
              `pid`        BIGINT(20)  NOT NULL COMMENT 'FK patient_data.pid',
              `pathway`    VARCHAR(20) NOT NULL DEFAULT '',
              `status`     VARCHAR(20) NOT NULL DEFAULT 'consent',
              `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_ais_pid`    (`pid`),
              KEY `idx_ais_status` (`status`)
            ) ENGINE=InnoDB COMMENT='AI Intake: per-visit kiosk session tracker'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE IF EXISTS `ai_intake_session`");
        $this->addSql("DROP TABLE IF EXISTS `ai_intake_consent`");
    }
}
