<?php
/**
 * AI Intake — DEMO DATA CLEANUP SCRIPT
 *
 * This script deletes the seeded Patient B and any associated
 * ai_intake_session rows. It also deletes any newly registered patients
 * during the demo (e.g. Patient A) if their name is Anil Sharma.
 *
 * @package   OpenEMR
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');

header('Content-Type: text/plain');

$abha = '99-8888-7777-6666';

// Delete sessions for the ABHA seeded patient (Patient B)
$resB = sqlQuery("SELECT pid FROM patient_data WHERE abha_id = ?", [$abha]);
if ($resB) {
    $pidB = $resB['pid'];
    sqlStatement("DELETE FROM ai_intake_session WHERE patient_id = ?", [$pidB]);
    sqlStatement("DELETE FROM patient_data WHERE pid = ?", [$pidB]);
    echo "Deleted Patient B (Sunita Patel) and their sessions.\n";
} else {
    echo "Patient B not found.\n";
}

// Optional: Delete Patient A if they were created during the demo
$resA = sqlQuery("SELECT pid FROM patient_data WHERE fname = 'Anil' AND lname = 'Sharma'");
if ($resA) {
    $pidA = $resA['pid'];
    sqlStatement("DELETE FROM ai_intake_session WHERE patient_id = ?", [$pidA]);
    sqlStatement("DELETE FROM patient_data WHERE pid = ?", [$pidA]);
    echo "Deleted Patient A (Anil Sharma) and their sessions.\n";
} else {
    echo "Patient A (Anil Sharma) not found.\n";
}

echo "\n✅ DEMO DATA CLEANUP COMPLETE!\n";
