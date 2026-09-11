<?php

/**
 * AI Intake — Save Interview API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-interview.php
 * Input (JSON): { "token": "<opaque>", "answers": [{"question": "...", "answer": "..."}, ...] }
 * Output:       { "success": true } | { "success": false, "error": "..." }
 *
 * Persists the Q&A answer array from the voice/text interview into the
 * ai_intake_session.interview_data JSON column.
 *
 * MOCK ASR NOTE
 * =============
 * The "voice" answers are currently submitted as typed text from the interview UI.
 * When real ASR is integrated, the frontend will send the same JSON structure —
 * this endpoint does not change. The ASR transcription happens client-side
 * (or in a separate transcribe endpoint) before calling this one.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');

header('Content-Type: application/json');

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

// --- Validate token + step order -------------------------------------------
$ks = KioskSession::requireValidApi($body);
$ks->requireStep('pathway', isApi: true);

// --- Validate answers payload -----------------------------------------------
$answers = $body['answers'] ?? null;

if (!is_array($answers) || count($answers) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'answers must be a non-empty array']);
    exit;
}

// Sanitise each answer: only keep expected keys, clamp lengths.
$clean = [];
foreach ($answers as $qa) {
    if (!is_array($qa)) continue;
    $clean[] = [
        'question' => substr(trim($qa['question'] ?? ''), 0, 512),
        'answer'   => substr(trim($qa['answer']   ?? ''), 0, 2000),
    ];
}

if (count($clean) === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'No valid answers found in payload']);
    exit;
}

// --- Persist to ai_intake_session -------------------------------------------
$sessionId = $ks->intakeSessionId();
if (!$sessionId) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No intake session found. Complete previous steps first.']);
    exit;
}

$result = sqlStatement(
    "UPDATE ai_intake_session
        SET interview_data = ?,
            status         = 'interview',
            updated_at     = NOW()
      WHERE id = ?",
    [json_encode($clean, JSON_UNESCAPED_UNICODE), $sessionId]
);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save interview data']);
    exit;
}

// --- Update kiosk session ---------------------------------------------------
$ks->set('interview_data', $clean);
$ks->markStep('interview');

echo json_encode(['success' => true]);
