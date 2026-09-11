<?php
/**
 * AI Intake — Translate API
 *
 * Uses Groq LLM to instantly translate an English clinical summary into Marathi.
 */

require_once(__DIR__ . '/../../../../globals.php');
use OpenEMR\Common\Acl\AclMain;

if (!AclMain::aclCheckCore('patients', 'med')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$textToTranslate = trim($input['text'] ?? '');

if (empty($textToTranslate)) {
    echo json_encode(['error' => 'No text provided', 'translation' => '']);
    exit;
}

$apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');
if (empty($apiKey)) {
    echo json_encode(['error' => 'No Groq API Key found', 'translation' => '']);
    exit;
}

$prompt = "Translate the following English clinical pre-visit summary into formal Marathi.\n"
        . "Maintain the exact same structure, bullet points, and clinical terminology.\n\n"
        . "TEXT TO TRANSLATE:\n" . $textToTranslate;

$payload = json_encode([
    'model'    => 'llama3-8b-8192',
    'messages' => [
        ['role' => 'system', 'content' => 'You are an expert English-to-Marathi clinical translator. Output ONLY the translated Marathi text. Do not add any conversational prefix or suffix.'],
        ['role' => 'user',   'content' => $prompt],
    ],
    'temperature' => 0.1,
    'max_tokens'  => 1200,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 20,
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
    $translatedText = $json['choices'][0]['message']['content'] ?? '';
    if (!empty(trim($translatedText))) {
        echo json_encode(['success' => true, 'translation' => trim($translatedText)]);
        exit;
    }
}

echo json_encode(['error' => 'Translation failed from LLM API']);
