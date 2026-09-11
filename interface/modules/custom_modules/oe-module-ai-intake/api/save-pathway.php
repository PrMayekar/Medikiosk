<?php

/**
 * AI Intake — Save Pathway API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-pathway.php
 * Input (JSON):  { "token": "<opaque>", "pathway": "allopathic" | "ayurvedic" }
 * Output (JSON): { "success": true }
 *             or { "success": false, "error": "..." }
 *
 * Validates that the consent step was completed first, then records the
 * patient's treatment pathway choice in ai_intake_session.
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

// --- Validate token ---------------------------------------------------------
$ks = KioskSession::requireValidApi($body);

// --- Enforce step order: consent must come before pathway -------------------
$ks->requireStep('consent', isApi: true);

// --- Input validation -------------------------------------------------------
$allowed = ['allopathic', 'ayurvedic'];
$pathway  = strtolower(trim($body['pathway'] ?? ''));

if (!in_array($pathway, $allowed, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error'   => 'pathway must be one of: ' . implode(', ', $allowed),
    ]);
    exit;
}

// --- Persist pathway choice -------------------------------------------------
$sessionId = $ks->intakeSessionId();
if (!$sessionId) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No intake session found. Complete consent first.']);
    exit;
}

$result = sqlStatement(
    "UPDATE ai_intake_session
        SET pathway    = ?,
            status     = 'pathway',
            updated_at = NOW()
      WHERE id = ?",
    [$pathway, $sessionId]
);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save pathway choice']);
    exit;
}

// --- Update kiosk session ---------------------------------------------------
$ks->set('pathway', $pathway);
$ks->markStep('pathway');

echo json_encode(['success' => true]);
