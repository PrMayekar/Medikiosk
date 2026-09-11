<?php

/**
 * AI Intake — Save Consent API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-consent.php
 * Input (JSON):  { "token": "<opaque>", "consent_given": true }
 * Output (JSON): { "success": true }
 *             or { "success": false, "error": "..." }
 *
 * Stores consent in ai_intake_consent (audit log) and creates the
 * ai_intake_session row that tracks this kiosk visit from here onward.
 * The patient's pid is resolved from the server-side kiosk session — it is
 * never sent by the browser.
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

// --- Validate token (exits with JSON 401 on failure) -----------------------
$ks = KioskSession::requireValidApi($body);
$pid = $ks->pid();

// --- Input validation -------------------------------------------------------
$consentGiven = isset($body['consent_given']) ? (bool) $body['consent_given'] : null;
if ($consentGiven === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'consent_given is required']);
    exit;
}

// We do not allow declining consent through the kiosk — the patient must
// speak to a staff member. But we log it either way for audit purposes.

// --- Persist consent record -------------------------------------------------
$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
// Trim to first IP if X-Forwarded-For contains a list.
$ipAddress = trim(explode(',', $ipAddress)[0]);
// Clamp to column length (IPv6 max = 45 chars).
$ipAddress = substr($ipAddress, 0, 45);

$consentId = sqlInsert(
    "INSERT INTO ai_intake_consent (pid, consent_given, consent_timestamp, ip_address)
     VALUES (?, ?, NOW(), ?)",
    [$pid, $consentGiven ? 1 : 0, $ipAddress]
);

if (!$consentId) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save consent record']);
    exit;
}

// --- Create intake session row (if not already created) ---------------------
$existingSessionId = $ks->intakeSessionId();
if ($existingSessionId) {
    // Already created (e.g. patient hit back and re-consented) — just update status.
    sqlStatement(
        "UPDATE ai_intake_session SET status = 'consent', updated_at = NOW() WHERE id = ?",
        [$existingSessionId]
    );
    $sessionId = $existingSessionId;
} else {
    $sessionId = sqlInsert(
        "INSERT INTO ai_intake_session (pid, pathway, status, language, interaction_mode, created_at, updated_at)
         VALUES (?, '', 'consent', ?, ?, NOW(), NOW())",
        [$pid, $ks->language(), $ks->interactionMode()]
    );
    if (!$sessionId) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create intake session']);
        exit;
    }
}

// --- Update kiosk session ---------------------------------------------------
$ks->set('consent_given', $consentGiven);
$ks->set('intake_session_id', (int) $sessionId);
$ks->markStep('consent');

echo json_encode(['success' => true]);
