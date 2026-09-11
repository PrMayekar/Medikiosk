<?php

/**
 * AI Intake — Save Document API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-document.php
 * Input (JSON): { "token": "<opaque>", "document_data": object|null }
 * Output:       { "success": true } | { "success": false, "error": "..." }
 *
 * Persists mock OCR-extracted document data into ai_intake_session.document_data.
 * Passing null (or omitting document_data) is valid — represents "Skip".
 *
 * MOCK OCR / REAL FILE UPLOAD NOTE
 * =================================
 * Currently this endpoint accepts a pre-built JSON object (the mock OCR result
 * constructed client-side). To upgrade to real file upload + OCR:
 *
 *   Step A — Change the request to multipart/form-data:
 *     Accept $_FILES['document'] as a real uploaded file.
 *     Validate MIME type (image/jpeg, image/png, application/pdf).
 *     Save temporarily to sys_get_temp_dir().
 *
 *   Step B — Call your OCR service:
 *     $extracted = callOcrService($tmpPath);   // ← insert real API call here
 *     // callOcrService() returns the same shape as the mock JSON below.
 *
 *   Step C — Everything below this comment stays identical.
 *     $document_data = $extracted;
 *
 * The database column, session update, and response format do not change.
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
$ks->requireStep('interview', isApi: true);

// --- Extract document data (null = patient skipped upload) ------------------
$documentData = $body['document_data'] ?? null; // null is explicitly valid (skip)

// If provided, validate it is an array/object (not a scalar).
if ($documentData !== null && !is_array($documentData)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'document_data must be an object or null']);
    exit;
}

// --- Persist to ai_intake_session -------------------------------------------
$sessionId = $ks->intakeSessionId();
if (!$sessionId) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No intake session found. Complete previous steps first.']);
    exit;
}

$encodedData = $documentData !== null
    ? json_encode($documentData, JSON_UNESCAPED_UNICODE)
    : null;

$result = sqlStatement(
    "UPDATE ai_intake_session
        SET document_data = ?,
            status        = 'complete',
            updated_at    = NOW()
      WHERE id = ?",
    [$encodedData, $sessionId]
);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save document data']);
    exit;
}

// --- Update kiosk session ---------------------------------------------------
$ks->set('document_data', $documentData);
$ks->markStep('document');

echo json_encode(['success' => true]);
