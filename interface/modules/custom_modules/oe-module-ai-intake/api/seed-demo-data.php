<?php
/**
 * AI Intake — DEMO DATA SEED SCRIPT
 *
 * This script seeds a "Follow-Up Patient" (Patient B) into the OpenEMR 
 * patient_data table and creates a prior completed ai_intake_session 
 * for them, simulating a past visit with kidney-disease symptoms.
 * 
 * Patient A (New Patient) is not seeded here, so they can be organically 
 * registered during the live kiosk demo to test the "Register New Patient" flow.
 *
 * @package   OpenEMR
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');

header('Content-Type: text/plain');

$abha = '99-8888-7777-6666';

// ── 1. Create Patient B (Follow-up Patient)
$res = sqlQuery("SELECT pid FROM patient_data WHERE abha_id = ?", [$abha]);
if ($res) {
    echo "Patient B (Sunita Patel) already exists.\n";
    $pid = $res['pid'];
} else {
    sqlStatement(
        "INSERT INTO patient_data (fname, lname, sex, DOB, phone_cell, abha_id) VALUES (?, ?, ?, ?, ?, ?)",
        ['Sunita', 'Patel', 'Female', '1965-01-01', '9876543210', $abha]
    );
    $pid = $GLOBALS['adodb']['db']->Insert_ID();
    echo "Patient B (Sunita Patel) created with PID: $pid.\n";
}

// ── 2. Create Patient B's Prior Session
$resSession = sqlQuery("SELECT id FROM ai_intake_session WHERE patient_id = ? AND status = 'completed'", [$pid]);
if ($resSession) {
    echo "Prior session for Patient B already exists.\n";
} else {
    $interviewData = json_encode([
        ["question" => "What brings you in today?", "answer" => "swelling in legs and reduced urination"],
        ["question" => "How long have you had this issue?", "answer" => "About a month"],
        ["question" => "Any past medical history?", "answer" => "Type 2 diabetes for 8 years, hypertension"],
        ["question" => "Any current medications?", "answer" => "Metformin 500mg, Amlodipine 5mg"]
    ]);
    
    $summary = "DOCUMENTS UPLOADED\n- 📋 Report: Lab_Report_Creatinine.pdf\n\nCHIEF COMPLAINT\nswelling in legs and reduced urination\n\nHISTORY OF PRESENT ILLNESS\nPatient reports symptoms. History of Type 2 diabetes for 8 years, hypertension. Prior labs show elevated creatinine levels.\n\nCURRENT MEDICATIONS\nMetformin 500mg, Amlodipine 5mg\n\nPATHWAY: Allopathic Consultation";

    sqlStatement(
        "INSERT INTO ai_intake_session (patient_id, pathway, status, interaction_mode, language, interview_data, document_data, summary_text, created_at, updated_at) 
         VALUES (?, 'allopathic', 'completed', 'tap', 'en', ?, '[]', ?, DATE_SUB(NOW(), INTERVAL 3 MONTH), DATE_SUB(NOW(), INTERVAL 3 MONTH))",
        [$pid, $interviewData, $summary]
    );
    
    echo "Prior session for Patient B created (dated 3 months ago).\n";
}

echo "\n=======================================================\n";
echo "✅ SEEDING COMPLETE!\n";
echo "=======================================================\n";
echo "Test Persona 1 (Patient A - New):\n";
echo "- Use the 'Register New Patient' tab.\n";
echo "- Enter details manually (e.g. Anil Sharma, 58, Male)\n";
echo "- This triggers the full new-patient interview.\n\n";

echo "Test Persona 2 (Patient B - Follow-up):\n";
echo "- Use the 'Existing ABHA ID' tab.\n";
echo "- Enter ABHA ID: $abha\n";
echo "- This triggers the shorter follow-up interview with the prior data summary card.\n";
echo "=======================================================\n";
