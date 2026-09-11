<?php

/**
 * AI Intake — Save Consultation SOAP Note (Step 5, Doctor-side)
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-consultation-note.php
 * Input (JSON): { "session_id": int, "patient_id": int, "soap_note_text": string }
 * Output:       { "success": true, "form_note_id": int }
 *               | { "success": false, "error": "..." }
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * REUSES EXACTLY THE SAME OpenEMR note-write mechanism as Step 4 (save-summary.php):
 *
 *   Table:    form_note  (same as pre-visit summary)
 *   Function: sqlInsert() → addForm()  (same pair used in save-summary.php)
 *   Encounter: REUSES the encounter created in Step 4 (openemr_encounter_id
 *             from ai_intake_session) — no new encounter is created.
 *
 * Distinction from the pre-visit note:
 *   note_type = 'AI Consultation SOAP Note'
 *              (vs 'AI Intake Summary' used for the pre-visit note)
 *   user      = the logged-in doctor's OpenEMR username (from $_SESSION)
 *   authorized = 1 (doctor is authenticated — form_note shows as authorised)
 *
 * MOCK ASR NOTE
 * ─────────────────────────────────────────────────────────────────────────────
 * The soap_note_text is currently a hardcoded template generated client-side.
 * To upgrade to real ASR + LLM:
 *   - Send the audio blob to an ASR endpoint to get a transcript
 *   - Pass the transcript to a SOAP-generation LLM endpoint
 *   - POST the resulting SOAP text to this endpoint
 *   This endpoint does not change — it only stores whatever text is sent.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Doctor is logged in — standard OpenEMR auth (NOT $ignoreAuth = true).
require_once(__DIR__ . '/../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/forms.inc.php');   // addForm()

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Session\SessionWrapperFactory;

header('Content-Type: application/json');

// --- Auth: must be a logged-in staff user with patient/med access -----------
if (!AclMain::aclCheckCore('patients', 'med')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// --- Validate inputs --------------------------------------------------------
$sessionId    = isset($body['session_id'])    ? (int) $body['session_id']             : 0;
$patientId    = isset($body['patient_id'])    ? (int) $body['patient_id']             : 0;
$soapNoteText = isset($body['soap_note_text']) ? trim((string) $body['soap_note_text']) : '';

if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid session_id']);
    exit;
}
if ($patientId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid patient_id']);
    exit;
}
if ($soapNoteText === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'soap_note_text cannot be empty']);
    exit;
}

// --- Fetch intake session to get the existing OpenEMR encounter ID ----------
$intakeSession = sqlQuery(
    "SELECT id, pid, openemr_encounter_id
       FROM ai_intake_session
      WHERE id = ? AND pid = ?
      LIMIT 1",
    [$sessionId, $patientId]
);

if (empty($intakeSession)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Intake session not found for this patient']);
    exit;
}

$pid         = (int) $intakeSession['pid'];
$encounterId = (int) ($intakeSession['openemr_encounter_id'] ?? 0);

if (!$encounterId) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'error'   => 'No encounter found for this session. Complete the patient intake flow first.',
    ]);
    exit;
}

// --- Get logged-in doctor's username for the note record -------------------
$session  = SessionWrapperFactory::getInstance()->getActiveSession();
$username = $session->get('authUser') ?? 'physician';

// --- Create form_note row (SAME mechanism as save-summary.php Step 4) ------
//
// Exact pattern from interface/forms/note/save.php:
//   $newid = formSubmit('form_note', $_POST, $formId, $userauthorized);
//   addForm($encounter, "Work/School Note", $newid, "note", $pid, $userauthorized);
//
// We insert directly because formSubmit() reads $_SESSION['pid'] (patient context),
// which may differ from the doctor's session. Direct sqlInsert is the same result.
//
// note_type distinguishes this from the pre-visit summary ('AI Intake Summary'):
//   'AI Consultation SOAP Note' — generated during the consultation

$formNoteId = sqlInsert(
    "INSERT INTO form_note SET
       date       = NOW(),
       pid        = ?,
       user       = ?,
       groupname  = 'Default',
       authorized = 1,
       activity   = 1,
       note_type  = 'AI Consultation SOAP Note',
       message    = ?",
    [$pid, $username, $soapNoteText]
);

if (!$formNoteId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to insert form_note row']);
    exit;
}

// --- Register the note in the forms table (same addForm call as Step 4) ----
// This makes the note visible in the patient chart under Encounters → Forms.
addForm(
    $encounterId,
    'AI Consultation SOAP Note',   // form_name shown in the Encounter Forms list
    $formNoteId,
    'note',                         // formdir — reuses the existing 'note' form type
    $pid,
    '1',                            // authorized = 1 (doctor-approved)
    'NOW()',
    $username
);

echo json_encode([
    'success'      => true,
    'form_note_id' => (int) $formNoteId,
    'encounter_id' => $encounterId,
]);
