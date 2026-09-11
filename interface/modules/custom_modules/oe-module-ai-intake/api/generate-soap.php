<?php
/**
 * AI Intake — Generate SOAP Note API
 *
 * Receives a raw ambient transcript via POST, uses the Groq LLM (Llama3-8b-8192)
 * to format it into a structured clinical SOAP note, and returns the result.
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || empty($body['transcript'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing transcript']);
    exit;
}

$transcript = trim($body['transcript']);
$apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');

if (empty($apiKey)) {
    // Fallback if no API key is provided
    echo json_encode([
        'success' => true,
        'soap_text' => "SUBJECTIVE:\n[No API Key provided. Raw transcript below]\n" . $transcript . "\n\nOBJECTIVE:\n\nASSESSMENT:\n\nPLAN:\n",
    ]);
    exit;
}

$prompt = "You are a senior clinical documentation assistant in a hospital. "
    . "You are provided with a raw, real-time ambient transcript of a conversation between a doctor and a patient.\n"
    . "Generate a COMPREHENSIVE, FORMAL clinical SOAP note for the doctor.\n"
    . "The note must be formatted strictly into these four sections: SUBJECTIVE, OBJECTIVE, ASSESSMENT, PLAN.\n"
    . "Do NOT invent information that is not discussed in the transcript.\n"
    . "Reply with only the SOAP note text, no preamble or commentary.\n\n"
    . "RAW TRANSCRIPT:\n" . $transcript;

$payload = json_encode([
    'model'    => 'llama3-8b-8192',
    'messages' => [
        ['role' => 'system', 'content' => 'You write structured, formal clinical SOAP notes from ambient transcripts. Reply with only the text, no preamble.'],
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
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode === 200) {
    $json = json_decode($response, true);
    $text = $json['choices'][0]['message']['content'] ?? '';
    if (!empty(trim($text))) {
        echo json_encode([
            'success' => true,
            'soap_text' => trim($text)
        ]);
        exit;
    }
}

error_log('[AI Intake] Groq API call failed (HTTP ' . $httpCode . '): ' . substr($response, 0, 200));

echo json_encode([
    'success' => false,
    'error' => 'Failed to generate SOAP note via LLM.',
    'raw_response' => substr($response, 0, 200)
]);
