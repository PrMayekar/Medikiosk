<?php

/**
 * AI Intake — Step 3 Migration: interview_data + document_data columns
 *
 * NOTE: Doctrine Migrations not yet integrated (db/README.md #10708).
 * Apply table.sql instead.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI Intake Step 3: add interview_data and document_data JSON columns to ai_intake_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE `ai_intake_session`
               ADD COLUMN IF NOT EXISTS `interview_data` JSON DEFAULT NULL
                 COMMENT 'Step 3: voice/text interview answers as [{question,answer}]',
               ADD COLUMN IF NOT EXISTS `document_data`  JSON DEFAULT NULL
                 COMMENT 'Step 3: mock OCR extracted fields — swap for real OCR output'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `ai_intake_session` DROP COLUMN IF EXISTS `document_data`");
        $this->addSql("ALTER TABLE `ai_intake_session` DROP COLUMN IF EXISTS `interview_data`");
    }
}
