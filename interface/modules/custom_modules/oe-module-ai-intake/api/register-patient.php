<?php

/**
 * AI Intake — New Patient Registration API
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/register-patient.php
 * Input (JSON):  { "name": "Full Name", "age": 35, "gender": "Male", "phone": "9876543210" }
 * Output (JSON): { "success": true, "token": "<opaque>" }
 *                or { "success": false, "error": "..." }
 *
 * SECURITY: pid is stored server-side only. Browser receives an opaque token.
 *
 * Creates a new OpenEMR patient record using the canonical updatePatientData()
 * wrapper (library/patient.inc.php → PatientService::databaseInsert()).
 * No ABHA ID is linked at registration — it can be linked in a later step.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Patient-facing endpoint — no staff session required.
$ignoreAuth = true;

require_once(__DIR__ . '/../../../../globals.php');
require_once($GLOBALS['srcdir'] . '/patient.inc.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON body']);
    exit;
}

// --- Input extraction -------------------------------------------------------

$fullName = trim($body['name'] ?? '');
$age      = (int) ($body['age'] ?? 0);
$gender   = trim($body['gender'] ?? '');
$phone    = trim($body['phone'] ?? '');
$abhaId   = trim($body['abha_id'] ?? '');
$otp      = trim($body['otp'] ?? '');

// --- Validation -------------------------------------------------------------

$errors = [];

if (empty($fullName)) {
    $errors[] = 'name is required';
}
if ($age <= 0 || $age > 150) {
    $errors[] = 'age must be a positive number (1–150)';
}
if (!in_array(strtolower($gender), ['male', 'female', 'other', 'unknown'], true)) {
    $errors[] = 'gender must be one of: Male, Female, Other, Unknown';
}
if (!preg_match('/^\d{7,15}$/', $phone)) {
    $errors[] = 'phone must be 7–15 digits';
}
if (!empty($abhaId) && !preg_match('/^\d{2}-\d{4}-\d{4}-\d{4}$/', $abhaId)) {
    $errors[] = 'ABHA ID must be in the format XX-XXXX-XXXX-XXXX';
}
if (!preg_match('/^\d{6}$/', $otp)) {
    $errors[] = '6-digit OTP is required';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode('; ', $errors)]);
    exit;
}

// --- Name splitting ---------------------------------------------------------
// Best-effort: last word → lname, rest → fname.

$nameParts = explode(' ', $fullName, 2);
$fname     = $nameParts[0];
$lname     = $nameParts[1] ?? '';

// --- DOB approximation ------------------------------------------------------
// We only have age — store 1 Jan of the estimated birth year.

$birthYear = (int) date('Y') - $age;
$dob       = $birthYear . '-01-01';

// --- Map gender to OpenEMR convention (Male / Female / Unknown) -------------

$sexMap = [
    'male'    => 'Male',
    'female'  => 'Female',
    'other'   => 'Unknown',
    'unknown' => 'Unknown',
];
$sex = $sexMap[strtolower($gender)] ?? 'Unknown';

// --- Create patient ---------------------------------------------------------
// updatePatientData(pid=null, data, create=true) calls PatientService::databaseInsert()
// which assigns a fresh pid, UUID, regdate, and fires all patient-creation events.

$newPid = updatePatientData(null, [
    'fname'      => $fname,
    'lname'      => $lname,
    'DOB'        => $dob,
    'sex'        => $sex,
    'phone_cell' => $phone,
], true);

if (!$newPid) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to create patient record']);
    exit;
}

// If an ABHA ID was provided, save it to the patient_data table via a direct query
// since updatePatientData doesn't natively map custom demographic columns by default.
if (!empty($abhaId)) {
    sqlStatement("UPDATE patient_data SET abha_id = ? WHERE pid = ?", [$abhaId, $newPid]);
}

// Issue a server-side kiosk session token — pid never goes to the browser.
$token = issueKioskToken((int) $newPid, $abhaId);

echo json_encode([
    'success' => true,
    'token'   => $token,
]);

// ============================================================================
// Kiosk session helper (mirrors verify-abha.php)
// ============================================================================

/**
 * Store the new patient's pid in a server-side session.
 * Returns an opaque random token the browser can carry through the kiosk flow.
 */
function issueKioskToken(int $pid, string $abhaId = ''): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $token = bin2hex(random_bytes(32));

    $_SESSION['ai_intake_sessions'][$token] = [
        'pid'              => $pid,
        'abha_id'          => $abhaId ?: null,
        'is_new_patient'   => true,
        'language'         => $_SESSION['ai_intake_lang'] ?? 'en',
        'interaction_mode' => $_SESSION['ai_intake_mode'] ?? 'tap',
        'expires_at'       => time() + 1800,
        'steps_done'       => [],
    ];

    return $token;
}

