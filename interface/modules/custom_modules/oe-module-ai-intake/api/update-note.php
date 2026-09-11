<?php

/**
 * AI Intake — Update Note API (Doctor-side)
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/update-note.php
 * Input (JSON): { "session_id": int, "summary_text": string }
 * Output:       { "success": true } | { "success": false, "error": "..." }
 *
 * Called by the doctor from the AI Intake card on the patient demographics page.
 * Requires an active OpenEMR staff session (doctor must be logged in).
 * Updates BOTH form_note.message (the real OpenEMR note) AND ai_intake_session.summary_text.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Doctor is logged in — use standard OpenEMR auth (NOT $ignoreAuth = true).
require_once(__DIR__ . '/../../../../globals.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json');

// --- Auth: must be a logged-in user with patient/med access ----------------
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

// --- Validate inputs -------------------------------------------------------
$sessionId   = isset($body['session_id'])  ? (int) $body['session_id']         : 0;
$summaryText = isset($body['summary_text']) ? trim((string) $body['summary_text']) : '';

if ($sessionId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid session_id']);
    exit;
}
if ($summaryText === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'summary_text cannot be empty']);
    exit;
}

// --- Fetch intake session --------------------------------------------------
$intakeSession = sqlQuery(
    "SELECT id, pid, openemr_form_note_id
       FROM ai_intake_session
      WHERE id = ?
      LIMIT 1",
    [$sessionId]
);

if (empty($intakeSession)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Intake session not found']);
    exit;
}

// Guard: doctor must have access to this specific patient.
$pid = (int) $intakeSession['pid'];
if (!$pid) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'Patient not linked to this session']);
    exit;
}

// --- Update form_note (real OpenEMR note) ----------------------------------
$formNoteId = (int) ($intakeSession['openemr_form_note_id'] ?? 0);
if ($formNoteId > 0) {
    sqlStatement(
        "UPDATE form_note SET message = ?, date = NOW() WHERE id = ?",
        [$summaryText, $formNoteId]
    );
}

// --- Update ai_intake_session.summary_text ---------------------------------
sqlStatement(
    "UPDATE ai_intake_session SET summary_text = ?, updated_at = NOW() WHERE id = ?",
    [$summaryText, $sessionId]
);

echo json_encode(['success' => true]);
