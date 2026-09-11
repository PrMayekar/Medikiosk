<?php

/**
 * AI Intake — Step 4 Migration: summary_text, red_flag, openemr encounter/note link columns
 *
 * NOTE: Doctrine Migrations not yet integrated. Apply table.sql instead.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI Intake Step 4: add summary_text, red_flag, openemr_encounter_id, openemr_form_note_id to ai_intake_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE `ai_intake_session`
               ADD COLUMN IF NOT EXISTS `summary_text`           TEXT          DEFAULT NULL,
               ADD COLUMN IF NOT EXISTS `red_flag`               TINYINT(1)    NOT NULL DEFAULT 0,
               ADD COLUMN IF NOT EXISTS `openemr_encounter_id`   BIGINT(20)    DEFAULT NULL,
               ADD COLUMN IF NOT EXISTS `openemr_form_note_id`   BIGINT(20)    DEFAULT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE `ai_intake_session`
               DROP COLUMN IF EXISTS `openemr_form_note_id`,
               DROP COLUMN IF EXISTS `openemr_encounter_id`,
               DROP COLUMN IF EXISTS `red_flag`,
               DROP COLUMN IF EXISTS `summary_text`"
        );
    }
}
