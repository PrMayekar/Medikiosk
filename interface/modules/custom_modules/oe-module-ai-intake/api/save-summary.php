<?php

/**
 * AI Intake — Save Summary API (Step 4 — REAL OpenEMR integration)
 *
 * POST /interface/modules/custom_modules/oe-module-ai-intake/api/save-summary.php
 * Input (JSON): { "token": "<opaque>" }
 * Output:       { "success": true, "encounter_id": int, "form_note_id": int }
 *               | { "success": false, "error": "..." }
 *
 * This is the endpoint that writes real data into OpenEMR.
 * It:
 *   1. Generates a template-based clinical summary from interview/document data
 *   2. Runs a keyword-based red-flag heuristic
 *   3. Creates (or reuses) a real form_encounter row + forms registry entry
 *   4. Creates a real form_note row + forms registry entry
 *   5. Updates ai_intake_session: status = 'completed', stores encounter/note IDs
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * REAL INTEGRATION NOTES
 * ─────────────────────────────────────────────────────────────────────────────
 * Encounter:  form_encounter table + forms table (standard OpenEMR encounter pattern)
 * Note:       form_note table + forms table (same pattern as interface/forms/note/save.php)
 * Functions:  sqlQuery(), sqlInsert(), sqlStatement() from library/sqlconf.php
 *             addForm() from library/forms.inc.php
 *
 * Why we don't call todaysEncounterCheck():
 *   That function reads $_SESSION['authUserID'] (staff session) for facility + username.
 *   This endpoint uses $ignoreAuth = true (no staff session). We replicate the same
 *   INSERT SQL directly, with provider_id = 0 (anonymous/kiosk — allowed by schema).
 *
 * MOCK AI SUMMARY NOTE
 * ─────────────────────────────────────────────────────────────────────────────
 * generateSummaryText() uses string templates. To swap in a real LLM:
 *   Replace the function body with an API call to Gemini/GPT.
 *   The caller and DB write logic below do not change.
 *
 * MOCK RED-FLAG NOTE
 * ─────────────────────────────────────────────────────────────────────────────
 * detectRedFlag() uses keyword co-occurrence. To swap in real clinical NLP:
 *   Replace the function body with a call to your NLP service.
 *   The caller and DB write logic below do not change.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');

require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once($GLOBALS['srcdir'] . '/forms.inc.php');   // addForm()

use OpenEMR\Common\Database\QueryUtils;

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

// ── 1. Validate token + step order ─────────────────────────────────────────
$ks = KioskSession::requireValidApi($body);
// Allow if at least 'interview' is done (document upload is optional).
$ks->requireStep('interview', isApi: true);

$pid       = $ks->pid();
$sessionId = $ks->intakeSessionId();
$pathway   = $ks->pathway() ?? 'allopathic';

if (!$sessionId) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No intake session found']);
    exit;
}

// ── 2. Load ai_intake_session row ──────────────────────────────────────────
$intakeRow = sqlQuery(
    "SELECT id, pathway, interview_data, document_data,
            openemr_encounter_id, openemr_form_note_id
       FROM ai_intake_session WHERE id = ? LIMIT 1",
    [$sessionId]
);

if (empty($intakeRow)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Intake session row not found in DB']);
    exit;
}

// Already submitted? Return existing IDs (idempotent).
if (!empty($intakeRow['openemr_form_note_id'])) {
    echo json_encode([
        'success'      => true,
        'encounter_id' => (int) $intakeRow['openemr_encounter_id'],
        'form_note_id' => (int) $intakeRow['openemr_form_note_id'],
        'idempotent'   => true,
    ]);
    exit;
}

$interviewData = json_decode($intakeRow['interview_data'] ?? '[]', true) ?: [];
$documentData  = json_decode($intakeRow['document_data']  ?? 'null', true);

// ── 3a. Load ALL previous completed sessions (for longitudinal summary) ─────
$prevSessions = [];
$prevResult = sqlStatement(
    "SELECT interview_data, document_data, summary_text, created_at, pathway
       FROM ai_intake_session
      WHERE pid = ? AND status = 'completed'
      ORDER BY created_at DESC LIMIT 10",
    [$pid]
);
while ($r = sqlFetchArray($prevResult)) {
    $prevSessions[] = $r;
}

// ── 3b. Get patient info for the header ──────────────────────────────────────
$patientRow = sqlQuery(
    "SELECT CONCAT(fname, ' ', lname) as full_name, DOB, sex FROM patient_data WHERE pid = ? LIMIT 1",
    [$pid]
);
$patientName = $patientRow['full_name'] ?? 'Unknown';
$patientDob  = $patientRow['DOB'] ?? '';

// ── 3. Generate summary text and structured data ──────────────────────────────
$aiOutput = generateSummaryText($pathway, $interviewData, $documentData, $prevSessions, $patientName, $patientDob);
$summaryText = is_array($aiOutput) ? ($aiOutput['summary_text'] ?? '') : $aiOutput;

// Helper function to insert into lists without duplicates
function insertIntoLists($pid, $type, $items) {
    if (!is_array($items) || empty($items)) return;
    
    foreach ($items as $item) {
        $title = trim((string)$item);
        if (empty($title)) continue;
        
        // Check for duplicate active entry
        $existing = sqlQuery(
            "SELECT id FROM lists WHERE pid = ? AND type = ? AND title = ? AND activity = 1",
            [$pid, $type, $title]
        );
        if (empty($existing)) {
            // Note: form_note usually has user='ai_intake_kiosk'. Using the same here.
            sqlInsert(
                "INSERT INTO lists (date, pid, type, title, activity, user, groupname) VALUES (NOW(), ?, ?, ?, 1, 'ai_intake_kiosk', 'Default')",
                [$pid, $type, $title]
            );
        }
    }
}

// Map the extracted structured data into the clinical dashboard columns
if (is_array($aiOutput)) {
    insertIntoLists($pid, 'medication', $aiOutput['medications'] ?? []);
    insertIntoLists($pid, 'allergy', $aiOutput['allergies'] ?? []);
    insertIntoLists($pid, 'medical_problem', $aiOutput['medical_problems'] ?? []);
}

// ── 4. Red-flag detection (MOCK heuristic — see comment at top) ─────────────
$redFlag = detectRedFlag($interviewData) ? 1 : 0;

// ── 5. Create / reuse today's encounter ────────────────────────────────────
// We replicate the SQL from todaysEncounterCheck() directly (no staff session).

$today = date('Y-m-d');

// Reuse an existing encounter for this patient today, if one exists.
$existingRow = sqlQuery(
    "SELECT encounter FROM form_encounter WHERE pid = ? AND DATE(date) = ? ORDER BY encounter DESC LIMIT 1",
    [$pid, $today]
);
$encounterId = (int) ($existingRow['encounter'] ?? 0);

if (!$encounterId) {
    // No encounter today — create one.
    $encounterId = QueryUtils::generateId();
    $chiefComplaint = extractAnswer($interviewData, 'chief_complaint')
        ?: 'AI Kiosk Intake';
    $reason = $redFlag ? "[RED FLAG] $chiefComplaint" : $chiefComplaint;

    // Look up a real facility (use the first active facility, not a hard-coded ID).
    $facilityRow = sqlQuery(
        "SELECT name, id FROM facility WHERE inactive = 0 ORDER BY id ASC LIMIT 1"
    );
    $facilityName = $facilityRow['name'] ?? '';
    $facilityId   = (int) ($facilityRow['id'] ?? 0);

    $formEncounterId = sqlInsert(
        "INSERT INTO form_encounter SET
           date             = ?,
           reason           = ?,
           facility         = ?,
           facility_id      = ?,
           billing_facility = ?,
           provider_id      = 0,
           pid              = ?,
           encounter        = ?,
           pc_catid         = 5,
           pos_code         = 11",
        [
            "$today 00:00:00",
            $reason,
            $facilityName,
            $facilityId,
            $facilityId,
            $pid,
            $encounterId,
        ]
    );

    // Register the encounter in the forms table (required by OpenEMR chart view).
    addForm(
        $encounterId,
        'New Patient Encounter',
        $formEncounterId,
        'newpatient',
        $pid,
        '0',         // authorized = 0 (kiosk — no staff sign-off yet)
        'NOW()',
        'ai_intake_kiosk'
    );
}

// ── 6. Create form_note (real OpenEMR clinical note) ───────────────────────
// Pattern from interface/forms/note/save.php:
//   $newid = formSubmit('form_note', $_POST, $formId, $userauthorized);
//   addForm($encounter, "Work/School Note", $newid, "note", $pid, $userauthorized);
//
// We insert directly (formSubmit() reads $_SESSION['pid'] which we can't set
// safely in a $ignoreAuth context).

$formNoteId = sqlInsert(
    "INSERT INTO form_note SET
       date              = NOW(),
       pid               = ?,
       user              = 'ai_intake_kiosk',
       groupname         = 'Default',
       authorized        = 0,
       activity          = 1,
       note_type         = 'AI Intake Summary',
       message           = ?",
    [$pid, $summaryText]
);

// Register the note in the forms table so it appears in the Encounter → Forms list.
addForm(
    $encounterId,
    'AI Intake Summary',
    $formNoteId,
    'note',
    $pid,
    '0',
    'NOW()',
    'ai_intake_kiosk'
);

// ── 7. Queue Generation & Update ai_intake_session ────────────────────────
$qRow = sqlQuery("SELECT MAX(queue_number) as m FROM ai_intake_session WHERE DATE(created_at) = CURDATE()");
$queueNumber = ((int) ($qRow['m'] ?? 0)) + 1;
$waitingRoom = ($queueNumber % 2 === 1) ? 'Room 1' : 'Room 2';

sqlStatement(
    "UPDATE ai_intake_session
        SET summary_text          = ?,
            red_flag              = ?,
            openemr_encounter_id  = ?,
            openemr_form_note_id  = ?,
            queue_number          = ?,
            waiting_room          = ?,
            status                = 'completed',
            updated_at            = NOW()
      WHERE id = ?",
    [$summaryText, $redFlag, $encounterId, $formNoteId, $queueNumber, $waitingRoom, $sessionId]
);

// Update kiosk session cache.
$ks->set('summary_text', $summaryText);
$ks->set('red_flag', $redFlag);
$ks->set('openemr_encounter_id', $encounterId);
$ks->set('openemr_form_note_id', $formNoteId);
$ks->markStep('summary');

echo json_encode([
    'success'      => true,
    'encounter_id' => $encounterId,
    'form_note_id' => $formNoteId,
    'red_flag'     => (bool) $redFlag,
    'summary_text' => $summaryText,
    'queue_number' => $queueNumber,
    'waiting_room' => $waitingRoom
]);

// ── Helper: extract a specific answer by question ID ───────────────────────
function extractAnswer(array $interviewData, string $questionId): string
{
    $hintMap = [
        // Shared / Allopathic
        'chief_complaint'   => ['main health concern', 'chief complaint', 'same concern', 'मुख्य आरोग्य'],
        'duration'          => ['how long', 'duration', 'किती दिवसांपासून'],
        'duration_season'   => ['how long have you had', 'season', 'ऋतू'],
        'severity'          => ['scale of 1', 'severity', 'rate', 'तीव्र', 'affecting your daily'],
        'aggravating'       => ['makes it worse', 'aggravating', 'वाढवतात'],
        'relieving'         => ['makes it better', 'relieving', 'आराम'],
        'past_history'      => ['past medical', 'past illness', 'मागील आजार', 'family health', 'past_history'],
        'meds_allergies'    => ['medication', 'allerg', 'औषधे'],
        // Ayurvedic-specific
        'prakriti_body'     => ['body build', 'body type', 'skin', 'prakriti', 'शरीरयष्टी'],
        'digestion'         => ['digestion', 'appetite', 'भूक', 'पचन'],
        'sleep_energy'      => ['sleep', 'energy level', 'झोप'],
        'stress_response'   => ['stress', 'ताण'],
        'diet_habits'       => ['diet', 'food intol', 'craving', 'आहार'],
        'ayurvedic_history' => ['ayurvedic', 'panchakarma', 'herbal', 'पंचकर्म', 'आयुर्वेदिक'],
        // Follow-up
        'followup_same_or_new' => ['same concern', 'same issue', 'new concern'],
        'symptoms_change'      => ['symptoms changed', 'symptom', 'लक्षण'],
        'treatment_response'   => ['treatment', 'advice', 'उपचार'],
        'new_symptoms'         => ['new symptom', 'नवीन लक्षण'],
        'new_meds'             => ['new medic', 'new_meds', 'नवीन औषधे'],
        'current_severity'     => ['condition today', 'compared to last'],
        'additional_concerns'  => ['anything else', 'additional'],
        // Legacy aliases
        'body_type'   => ['body type', 'prakriti'],
        'appetite'    => ['appetite', 'digestion'],
        'sleep'       => ['sleep'],
        'past_remedies' => ['ayurvedic', 'home remed'],
    ];

    $hints = $hintMap[$questionId] ?? [];
    foreach ($interviewData as $qa) {
        $q = strtolower($qa['question'] ?? '');
        foreach ($hints as $hint) {
            if (str_contains($q, strtolower($hint))) {
                return trim($qa['answer'] ?? '');
            }
        }
    }
    return '';
}


// ── REAL AI SUMMARY GENERATOR (Groq API with template fallback) ─────────────
// Set GROQ_API_KEY in your .env file to activate real LLM summaries.
// Now includes all previous visit data for a longitudinal cumulative summary.
function generateSummaryText(
    string $pathway,
    array  $interviewData,
    ?array $documentData,
    array  $prevSessions = [],
    string $patientName = '',
    string $patientDob  = ''
): array|string {
    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');

    // Build current visit Q&A block
    $currentVisitNumber = count($prevSessions) + 1;
    $qaLines = '';
    foreach ($interviewData as $qa) {
        $q = trim($qa['question'] ?? '');
        $a = trim($qa['answer'] ?? '');
        if ($q && $a) {
            $qaLines .= "Q: $q\nA: $a\n\n";
        }
    }

    // Build OCR document block
    $ocrLines = '';
    if ($documentData && is_array($documentData)) {
        $docs = isset($documentData['filename']) ? [$documentData] : $documentData;
        foreach ($docs as $doc) {
            $type = $doc['document_type'] ?? 'Document';
            $file = $doc['filename']      ?? '';
            $text = trim($doc['ocr_text'] ?? '');
            $ocrLines .= "  Document: $type" . ($file ? " ($file)" : '') . "\n";
            if ($text) {
                $ocrLines .= "  Extracted text (first 600 chars):\n  " . wordwrap(substr($text, 0, 600), 80, "\n  ") . "\n\n";
            }
        }
    }

    // Build previous visits block for longitudinal context
    $prevVisitLines = '';
    foreach (array_reverse($prevSessions) as $idx => $prev) {
        $visitNum = $idx + 1;
        $visitDate = date('d-M-Y', strtotime($prev['created_at'] ?? 'now'));
        $prevPathway = ucfirst($prev['pathway'] ?? 'allopathic');
        if (!empty($prev['summary_text'])) {
            $prevVisitLines .= "VISIT #$visitNum ($visitDate — $prevPathway):\n" . trim($prev['summary_text']) . "\n\n";
        } else {
            // Build a mini summary from raw interview data
            $prevInterview = json_decode($prev['interview_data'] ?? '[]', true) ?: [];
            $miniSummary = '';
            foreach ($prevInterview as $pqa) {
                $miniSummary .= 'Q: ' . ($pqa['question'] ?? '') . '\nA: ' . ($pqa['answer'] ?? '') . "\n";
            }
            if ($miniSummary) {
                $prevVisitLines .= "VISIT #$visitNum ($visitDate — $prevPathway) [raw Q&A]:\n$miniSummary\n";
            }
        }
    }

        if (!empty($apiKey)) {
        $prompt = "You are a senior clinical documentation assistant in an Indian hospital. "
            . "A patient (" . ($patientName ?: 'Patient') . ") has completed a self-reported intake interview for Visit #$currentVisitNumber.\n"
            . "Generate a COMPREHENSIVE, FORMAL clinical note for the doctor.\n"
            . "The note must:\n"
            . "  1. Clearly document THIS visit's complaint, history, and findings.\n"
            . "  2. Include a PATIENT HISTORY TIMELINE section summarising all previous visits chronologically.\n"
            . "  3. For Ayurvedic pathway, include a PRAKRITI ASSESSMENT section.\n"
            . "  4. Include DOCUMENTS REVIEWED with key findings from any OCR-extracted reports.\n"
            . "  5. Be precise, clinical, and formatted with clearly labelled sections.\n"
            . "  6. Do NOT invent information not present in the answers.\n\n"
            . "CRITICAL INSTRUCTION: You MUST output ONLY a valid JSON object. Do NOT wrap it in markdown code blocks (e.g. no ```json). "
            . "The JSON object must have exactly these keys:\n"
            . "  \"summary_text\": (string) The full clinical note you generated.\n"
            . "  \"medications\": (array of strings) A list of medication names the patient is taking.\n"
            . "  \"allergies\": (array of strings) A list of allergies the patient has.\n"
            . "  \"medical_problems\": (array of strings) A list of active medical problems or chief complaints.\n\n"
            . "PATHWAY: " . ucfirst($pathway) . " | VISIT: #$currentVisitNumber\n\n"
            . "CURRENT VISIT RESPONSES:\n$qaLines"
            . ($ocrLines ? "DOCUMENTS SUBMITTED (OCR text):\n$ocrLines" : '')
            . ($prevVisitLines ? "PREVIOUS VISIT HISTORY:\n$prevVisitLines" : '');

        $payload = json_encode([
            'model'    => 'llama3-8b-8192',
            'messages' => [
                ['role' => 'system', 'content' => 'You write structured, formal clinical pre-visit summaries for Indian hospital doctors. You MUST respond with ONLY a raw JSON object. No markdown, no prefixes.'],
                ['role' => 'user',   'content' => $prompt],
            ],
            'temperature' => 0.25,
            'max_tokens'  => 1200,
        ]);

        $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $httpCode === 200) {
            $jsonResponse = json_decode($response, true);
            $content = $jsonResponse['choices'][0]['message']['content'] ?? '';
            
            // Clean up any potential markdown blocks the LLM might have incorrectly added
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = substr($content, 7);
            }
            if (str_ends_with($content, '```')) {
                $content = substr($content, 0, -3);
            }
            $content = trim($content);

            $parsed = json_decode($content, true);

            if ($parsed && isset($parsed['summary_text'])) {
                // Return the full array instead of just the string so we can process it in the main script
                $parsed['summary_text'] = trim($parsed['summary_text'])
                    . "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                    . "PATHWAY: " . ucfirst($pathway) . " Consultation | Visit #$currentVisitNumber\n"
                    . "Generated by: AI Intake Kiosk (Groq llama3-8b-8192)\n"
                    . "Generated at: " . date('d-M-Y H:i:s') . "\n";
                return $parsed;
            }
        }
        error_log('[AI Intake] Groq API call failed or invalid JSON (HTTP ' . $httpCode . '): ' . substr($response, 0, 200));
    }

    // ── Fallback: structured template with longitudinal history ─────────────
    $fallbackText = generateTemplateSummary($pathway, $interviewData, $documentData, $prevSessions, $currentVisitNumber, $patientName);
    return ['summary_text' => $fallbackText, 'medications' => [], 'allergies' => [], 'medical_problems' => []];
}

function generateTemplateSummary(
    string $pathway,
    array  $interviewData,
    ?array $documentData,
    array  $prevSessions = [],
    int    $visitNumber = 1,
    string $patientName = ''
): string {
    $date = date('d-M-Y');
    $sep  = str_repeat('─', 48);

    $cc       = extractAnswer($interviewData, 'chief_complaint') ?: 'Not specified';
    $duration = extractAnswer($interviewData, 'duration')        ?: extractAnswer($interviewData, 'duration_season') ?: 'Not specified';
    $severity = extractAnswer($interviewData, 'severity')        ?: 'Not specified';
    $agg      = extractAnswer($interviewData, 'aggravating')     ?: 'None reported';
    $rel      = extractAnswer($interviewData, 'relieving')       ?: 'None reported';
    $past     = extractAnswer($interviewData, 'past_history')    ?: 'None known';
    $meds     = extractAnswer($interviewData, 'meds_allergies')  ?: 'None reported';

    $s = "AI INTAKE CLINICAL NOTE\n";
    $s .= "$sep\n";
    $s .= "Patient: " . ($patientName ?: 'N/A') . "   |   Date: $date   |   Visit #$visitNumber\n";
    $s .= "Pathway: " . ucfirst($pathway) . " Consultation\n";
    $s .= "$sep\n\n";

    $s .= "CURRENT VISIT\n";
    $s .= "$sep\n";
    $s .= "CHIEF COMPLAINT\n{$cc}\n\n";
    $s .= "HISTORY OF PRESENT ILLNESS\n";
    $s .= "Duration: {$duration}. Severity: {$severity}/10.\n";
    $s .= "Aggravating factors: {$agg}.\nRelieving factors: {$rel}.\n\n";
    $s .= "PAST MEDICAL HISTORY\n{$past}\n\n";
    $s .= "CURRENT MEDICATIONS & ALLERGIES\n{$meds}\n\n";

    if (strtolower($pathway) === 'ayurvedic') {
        $body   = extractAnswer($interviewData, 'prakriti_body')    ?: extractAnswer($interviewData, 'body_type') ?: 'Not specified';
        $dig    = extractAnswer($interviewData, 'digestion')        ?: extractAnswer($interviewData, 'appetite')  ?: 'Not specified';
        $sleep  = extractAnswer($interviewData, 'sleep_energy')     ?: extractAnswer($interviewData, 'sleep')     ?: 'Not specified';
        $stress = extractAnswer($interviewData, 'stress_response')  ?: 'Not specified';
        $diet   = extractAnswer($interviewData, 'diet_habits')      ?: 'Not specified';
        $rem    = extractAnswer($interviewData, 'ayurvedic_history') ?: extractAnswer($interviewData, 'past_remedies') ?: 'None';
        $season = extractAnswer($interviewData, 'duration_season')  ?: '';

        $s .= "PRAKRITI ASSESSMENT (Ayurvedic)\n";
        $s .= "Body build & skin: {$body}.\n";
        $s .= "Digestion & appetite: {$dig}.\n";
        $s .= "Sleep & energy: {$sleep}.\n";
        $s .= "Stress response: {$stress}.\n";
        $s .= "Diet & food habits: {$diet}.\n";
        if ($season) $s .= "Seasonal pattern: {$season}.\n";
        $s .= "Prior Ayurvedic treatment: {$rem}.\n\n";
    }

    // Documents reviewed
    if ($documentData && is_array($documentData)) {
        $s .= "DOCUMENTS REVIEWED\n";
        $docs = isset($documentData['filename']) ? [$documentData] : $documentData;
        foreach ($docs as $doc) {
            $type = $doc['document_type'] ?? 'Unknown';
            $file = $doc['filename']      ?? 'document';
            $text = trim($doc['ocr_text'] ?? '');
            $s .= "  [{$type}] {$file}\n";
            if ($text) {
                $preview = wordwrap(substr($text, 0, 300), 72, "\n    ");
                $s .= "    Extracted text: {$preview}\n";
            }
        }
        $s .= "\n";
    }

    // Previous visits section
    if (!empty($prevSessions)) {
        $s .= "$sep\n";
        $s .= "PREVIOUS VISIT HISTORY\n";
        $s .= "$sep\n";
        foreach (array_reverse($prevSessions) as $idx => $prev) {
            $visitNum  = $idx + 1;
            $visitDate = date('d-M-Y', strtotime($prev['created_at'] ?? 'now'));
            $pPathway  = ucfirst($prev['pathway'] ?? 'Allopathic');
            $s .= "\nVisit #$visitNum — $visitDate ($pPathway)\n";
            if (!empty($prev['summary_text'])) {
                // Show first 600 chars of each past summary
                $excerpt = wordwrap(substr(trim($prev['summary_text']), 0, 600), 72, "\n");
                $s .= $excerpt . (strlen($prev['summary_text']) > 600 ? "\n…[see full note]" : '') . "\n";
            } else {
                $prevInterview = json_decode($prev['interview_data'] ?? '[]', true) ?: [];
                $cc = extractAnswer($prevInterview, 'chief_complaint') ?: 'Unknown';
                $s .= "Chief complaint: $cc\n";
            }
        }
        $s .= "\n";
    }

    $s .= "$sep\n";
    $s .= "Generated by: AI Intake Kiosk | Generated at: " . date('d-M-Y H:i:s') . "\n";

    return $s;
}

// ── RED-FLAG HEURISTIC ────────────────────────────────────────────────────────
function detectRedFlag(array $interviewData): bool
{
    $allText = strtolower(implode(' ', array_column($interviewData, 'answer')));

    // Chest pain + breathing difficulty → possible cardiac event
    if (str_contains($allText, 'chest pain') &&
        (str_contains($allText, 'breath') || str_contains($allText, 'breathe'))) {
        return true;
    }
    // Loss of consciousness
    if (str_contains($allText, 'unconscious') ||
        str_contains($allText, 'fainted') ||
        str_contains($allText, 'loss of consciousness') ||
        str_contains($allText, 'blacked out')) {
        return true;
    }
    // Blood in vomit, stool, or urine
    if (str_contains($allText, 'blood') && (
        str_contains($allText, 'vomit') ||
        str_contains($allText, 'stool') ||
        str_contains($allText, 'urine'))) {
        return true;
    }
    // Severe headache + neurological symptoms
    if ((str_contains($allText, 'severe headache') || str_contains($allText, 'worst headache')) && (
        str_contains($allText, 'vision') ||
        str_contains($allText, 'speech') ||
        str_contains($allText, 'weakness') ||
        str_contains($allText, 'numb'))) {
        return true;
    }
    return false;
}

