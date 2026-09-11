<?php

/**
 * AI Intake — ABHA Verification API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/verify-abha.php
 * Input (JSON):  { "abha_id": "XX-XXXX-XXXX-XXXX", "otp": "123456" }
 * Output (JSON): { "success": true,  "token": "<opaque>", "is_new_patient": bool }
 *             or { "success": false, "error": "..." }
 *
 * SECURITY MODEL
 * ==============
 * The patient's pid is NEVER returned to the browser.
 * After OTP is verified, we store { pid, abha_id, is_new_patient, expires_at }
 * in a server-side kiosk session keyed by a random opaque token.
 * The frontend holds only the token; all subsequent steps (consent, voice
 * interview, etc.) exchange the token server-side to get the pid.
 * This prevents any caller who knows an ABHA ID from accessing another
 * patient's record — even with a valid OTP — because the token is single-use
 * and server-controlled.
 *
 * OTP verification is currently MOCKED — any 6-digit OTP is accepted.
 * To integrate real ABDM OTP, replace verifyOtp() below.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Patient-facing endpoint — no staff session required.
$ignoreAuth = true;

// Bootstrap OpenEMR (DB connection, autoloader, globals).
require_once(__DIR__ . '/../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/patient.inc.php');

header('Content-Type: application/json');

// Only accept POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON body.
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$abhaId = trim($body['abha_id'] ?? '');
$otp    = trim($body['otp'] ?? '');

// --- Input validation -------------------------------------------------------

if (!validateAbhaId($abhaId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid ABHA ID format. Expected XX-XXXX-XXXX-XXXX']);
    exit;
}

if (!validateOtp($otp)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'OTP must be exactly 6 digits']);
    exit;
}

// --- OTP verification -------------------------------------------------------
// OTP must be verified BEFORE we touch any patient record.
// SWAP verifyOtp() to integrate real ABDM — nothing else changes.

$otpResult = verifyOtp($abhaId, $otp);

if (!$otpResult['valid']) {
    // Wrong OTP — do NOT reveal whether the ABHA ID exists in our DB.
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $otpResult['message'] ?? 'OTP verification failed. Please try again.']);
    exit;
}

// --- Patient lookup / creation (only reached after OTP passes) -------------

$existingPatient = sqlQuery(
    "SELECT pid FROM patient_data WHERE abha_id = ? LIMIT 1",
    [$abhaId]
);

$isNewPatient = false;

if ($existingPatient) {
    $pid = (int) $existingPatient['pid'];
} else {
    // First-time ABHA login — create a minimal patient record.
    // updatePatientData() is OpenEMR's canonical path → PatientService::databaseInsert()
    $pid = updatePatientData(null, [
        'fname' => 'ABHA',
        'lname' => 'Patient',
        'sex'   => 'Unknown',
    ], true);

    if (!$pid) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create patient record']);
        exit;
    }

    // Bind the ABHA ID to the new record.
    sqlStatement(
        "UPDATE patient_data SET abha_id = ? WHERE pid = ?",
        [$abhaId, $pid]
    );

    $isNewPatient = true;
}

// --- Issue a kiosk session token -------------------------------------------
// pid is stored SERVER-SIDE only. The browser never sees it.

$token = issueKioskToken($pid, $abhaId, $isNewPatient);

echo json_encode([
    'success'        => true,
    'token'          => $token,
    'is_new_patient' => $isNewPatient,
]);

// ============================================================================
// Kiosk session helpers
// ============================================================================

/**
 * Create a short-lived server-side kiosk session.
 * Returns an opaque random token the browser can exchange for the session.
 *
 * Token TTL: 30 minutes (enough for the full kiosk flow).
 * Storage: PHP $_SESSION under a namespaced key — one token per kiosk flow.
 */
function issueKioskToken(int $pid, string $abhaId, bool $isNewPatient): string
{
    // Start (or resume) the PHP session — globals.php may have already done this.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Generate a cryptographically random token.
    $token = bin2hex(random_bytes(32)); // 64 hex chars

    // Store session keyed by token so multiple browser tabs don't collide.
    $_SESSION['ai_intake_sessions'][$token] = [
        'pid'              => $pid,
        'abha_id'          => $abhaId,
        'is_new_patient'   => $isNewPatient,
        'language'         => $_SESSION['ai_intake_lang'] ?? 'en',
        'interaction_mode' => $_SESSION['ai_intake_mode'] ?? 'tap',
        'expires_at'       => time() + 1800, // 30 minutes
        'steps_done'       => [],            // tracks completed kiosk steps
    ];

    return $token;
}

// ============================================================================
// Validation helpers
// ============================================================================

/**
 * Validate ABHA ID: must be XX-XXXX-XXXX-XXXX (14 digits, 3 hyphens).
 */
function validateAbhaId(string $id): bool
{
    return (bool) preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $id);
}

/**
 * Validate OTP: exactly 6 numeric digits.
 */
function validateOtp(string $otp): bool
{
    return (bool) preg_match('/^\d{6}$/', $otp);
}

/**
 * Verify OTP against the ABDM service.
 *
 * Currently MOCKED — any 6-digit OTP is accepted.
 * To use real ABDM, replace this function body with an HTTP call to:
 *   POST https://healthidsbx.abdm.gov.in/api/v1/auth/confirmWithAadhaarOtp
 * Signature stays the same — nothing else in this file changes.
 *
 * @param string $abhaId
 * @param string $otp
 * @return array{valid: bool, message?: string}
 */
function verifyOtp(string $abhaId, string $otp): array
{
    // --- MOCK: accept any well-formed 6-digit OTP ---
    return ['valid' => true];

    // --- REAL ABDM (uncomment + fill in) ---
    // $txnId  = $_SESSION['abha_txn_id'] ?? '';
    // $result = abdmConfirmOtp($txnId, $otp);
    // return ['valid' => $result['success'], 'message' => $result['message'] ?? ''];
}
