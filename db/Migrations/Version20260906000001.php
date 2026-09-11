<?php

/**
 * AI Intake Module — ABHA ID Migration
 *
 * Adds abha_id column to patient_data table.
 * NOTE: Doctrine Migrations is NOT yet fully integrated into OpenEMR (see db/README.md
 * and issue #10708). This migration exists as a forward-looking reference.
 * For now, apply table.sql in this module directory instead.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Core\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260906000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI Intake: add abha_id column (VARCHAR 20, UNIQUE) to patient_data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE `patient_data`
               ADD COLUMN `abha_id` VARCHAR(20) DEFAULT NULL
                 COMMENT 'ABHA Health ID (format XX-XXXX-XXXX-XXXX)',
               ADD UNIQUE KEY `idx_abha_id` (`abha_id`)"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `patient_data` DROP KEY `idx_abha_id`");
        $this->addSql("ALTER TABLE `patient_data` DROP COLUMN `abha_id`");
    }
}
