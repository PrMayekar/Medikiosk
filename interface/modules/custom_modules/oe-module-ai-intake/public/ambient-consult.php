<?php
/**
 * AI Intake — Doctor-Side Ambient Consultation (Full Screen)
 */
require_once(__DIR__ . '/../../../../globals.php');
use OpenEMR\Common\Acl\AclMain;

// Must be a logged in user with medical access
if (!AclMain::aclCheckCore('patients', 'med')) {
    die("Access denied. You must be a logged-in medical provider.");
}

$pid = (int)($_GET['pid'] ?? 0);
$sessionId = (int)($_GET['session'] ?? 0);

if (!$pid || !$sessionId) {
    die("Invalid Patient ID or Session ID.");
}

$patientRow = sqlQuery("SELECT CONCAT(fname, ' ', lname) as full_name, DOB, sex FROM patient_data WHERE pid = ?", [$pid]);
$patientName = $patientRow['full_name'] ?? 'Unknown Patient';

$apiBase = ($GLOBALS['webroot'] ?? '') . '/interface/modules/custom_modules/oe-module-ai-intake/api';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ambient Consultation - <?= htmlspecialchars($patientName) ?></title>
    <style>
        :root {
            --brand-primary: #2c9cd4;
            --brand-dark: #1a6fa0;
            --text-primary: #1a2a3a;
            --text-secondary: #4a6070;
            --bg: #f4f7f6;
            --surface: #ffffff;
            --border: #d8e8f0;
            --radius: 12px;
            --font: 'Segoe UI', system-ui, sans-serif;
        }
        body { margin:0; padding:0; font-family:var(--font); background:var(--bg); color:var(--text-primary); }
        .header { background:var(--surface); border-bottom:1px solid var(--border); padding:20px 40px; display:flex; justify-content:space-between; align-items:center; }
        .patient-info h1 { margin:0; font-size:1.4rem; color:var(--brand-dark); }
        .patient-info p { margin:4px 0 0; color:var(--text-secondary); font-size:0.9rem; font-weight:600; }
        .lang-toggle select { padding:6px 12px; border-radius:6px; border:1px solid var(--border); font-size:0.9rem; }
        .container { max-width:1000px; margin:40px auto; padding:0 20px; display:flex; gap:30px; }
        
        .panel { flex:1; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px; box-shadow:0 4px 12px rgba(0,0,0,0.03); display:flex; flex-direction:column; }
        .panel h2 { margin-top:0; font-size:1.1rem; border-bottom:2px solid var(--brand-primary); padding-bottom:10px; display:inline-block; margin-bottom:20px; }
        
        .transcript-box { flex:1; min-height:300px; background:#f8fcff; border:1px solid #cce4f0; border-radius:8px; padding:16px; font-size:0.95rem; line-height:1.6; overflow-y:auto; margin-bottom:20px; color:#333; }
        .interim { color: #888; }
        
        .btn { padding:12px 24px; border:none; border-radius:8px; font-weight:700; cursor:pointer; font-size:0.95rem; display:inline-flex; align-items:center; justify-content:center; gap:8px; transition:0.2s; }
        .btn-start { background:#2e7d32; color:#fff; }
        .btn-start:hover { background:#1b5e20; }
        .btn-stop { background:#c62828; color:#fff; display:none; }
        .btn-stop:hover { background:#b71c1c; }
        .btn-save { background:var(--brand-primary); color:#fff; width:100%; margin-top:16px; }
        .btn-save:hover { background:var(--brand-dark); }
        .btn:disabled { opacity:0.6; cursor:not-allowed; }
        
        .soap-textarea { width:100%; min-height:400px; box-sizing:border-box; padding:16px; border:1px solid #cce4f0; border-radius:8px; font-family:inherit; font-size:0.9rem; line-height:1.6; resize:vertical; }
        .soap-textarea:focus { outline:none; border-color:var(--brand-primary); box-shadow:0 0 0 3px rgba(44,156,212,0.1); }
        
        #generating-overlay { display:none; position:absolute; top:0; left:0; right:0; bottom:0; background:rgba(255,255,255,0.8); align-items:center; justify-content:center; font-weight:700; color:var(--brand-primary); border-radius:var(--radius); }
        .status-msg { margin-top: 10px; font-size: 0.85rem; font-weight: 600; text-align:center; }
        
        /* Recording indicator */
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.5;} }
        .rec-dot { display:inline-block; width:10px; height:10px; background:#fff; border-radius:50%; animation:pulse 1.2s infinite; }
    </style>
</head>
<body>

<div class="header">
    <div class="patient-info">
        <h1>🎙 Ambient Consultation</h1>
        <p>Patient: <?= htmlspecialchars($patientName) ?> (ID: <?= $pid ?>)</p>
    </div>
    <div class="lang-toggle">
        <select id="lang-select" onchange="updateUiLang()">
            <option value="en">English (Default)</option>
            <option value="mr">मराठी (Marathi)</option>
            <option value="hi">हिंदी (Hindi)</option>
        </select>
    </div>
</div>

<div class="container">
    <!-- Transcription Panel -->
    <div class="panel" style="position:relative;">
        <h2 id="lbl-live-trans">Live Transcription</h2>
        <div class="transcript-box" id="transcript-box">
            <span id="final-text"></span>
            <span id="interim-text" class="interim"></span>
        </div>
        
        <button id="btn-start" class="btn btn-start" onclick="startRecording()">
            ▶ <span id="lbl-start">Start Listening</span>
        </button>
        <button id="btn-stop" class="btn btn-stop" onclick="stopRecording()">
            <span class="rec-dot"></span> <span id="lbl-stop">Stop & Generate SOAP</span>
        </button>
        
        <div id="generating-overlay">
            Generating SOAP Note via LLM...
        </div>
    </div>

    <!-- SOAP Editor Panel -->
    <div class="panel">
        <h2 id="lbl-soap-note">Generated SOAP Note</h2>
        <textarea id="soap-textarea" class="soap-textarea" placeholder="Your clinical note will appear here..."></textarea>
        <button id="btn-save" class="btn btn-save" onclick="saveSoapNote()">
            💾 <span id="lbl-save">Save to Patient Chart</span>
        </button>
        <div id="save-status" class="status-msg" style="color:#2e7d32; display:none;">✓ Saved to OpenEMR</div>
        <div id="save-error" class="status-msg" style="color:#c62828; display:none;"></div>
    </div>
</div>

<script>
const API_BASE = '<?= $apiBase ?>';
const SESSION_ID = <?= $sessionId ?>;
const PID = <?= $pid ?>;

let recognition;
let isRecording = false;
let finalTranscript = '';

// Language mappings
const langStrings = {
    'en': { live: 'Live Transcription', start: 'Start Listening', stop: 'Stop & Generate SOAP', soap: 'Generated SOAP Note', save: 'Save to Patient Chart', gen: 'Generating SOAP Note via LLM...' },
    'mr': { live: 'थेट लिप्यंतरण', start: 'ऐकणे सुरू करा', stop: 'थांबवा आणि SOAP तयार करा', soap: 'तयार केलेली SOAP नोंद', save: 'रुग्ण रेकॉर्डमध्ये जतन करा', gen: 'SOAP नोंद तयार करत आहे...' },
    'hi': { live: 'लाइव ट्रांसक्रिप्शन', start: 'सुनना शुरू करें', stop: 'रोकें और SOAP जनरेट करें', soap: 'जेनरेट किया गया SOAP नोट', save: 'मरीज़ के चार्ट में सेव करें', gen: 'SOAP नोट जेनरेट हो रहा है...' }
};

function updateUiLang() {
    const l = document.getElementById('lang-select').value;
    document.getElementById('lbl-live-trans').textContent = langStrings[l].live;
    document.getElementById('lbl-start').textContent = langStrings[l].start;
    document.getElementById('lbl-stop').textContent = langStrings[l].stop;
    document.getElementById('lbl-soap-note').textContent = langStrings[l].soap;
    document.getElementById('lbl-save').textContent = langStrings[l].save;
    document.getElementById('generating-overlay').textContent = langStrings[l].gen;
    
    // Update recognition language if recording is not active
    if (!isRecording && recognition) {
        recognition.lang = l === 'en' ? 'en-IN' : (l === 'mr' ? 'mr-IN' : 'hi-IN');
    }
}

function initSpeech() {
    if (!('webkitSpeechRecognition' in window)) {
        alert("Web Speech API is not supported by this browser. Please use Google Chrome.");
        return;
    }
    recognition = new webkitSpeechRecognition();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = 'en-IN'; // default

    recognition.onresult = function(event) {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                finalTranscript += event.results[i][0].transcript + ' ';
            } else {
                interim += event.results[i][0].transcript;
            }
        }
        document.getElementById('final-text').textContent = finalTranscript;
        document.getElementById('interim-text').textContent = interim;
        
        // Auto-scroll
        const box = document.getElementById('transcript-box');
        box.scrollTop = box.scrollHeight;
    };
    
    recognition.onerror = function(event) {
        console.error('Speech recognition error', event.error);
        if (event.error === 'not-allowed') {
            alert('Microphone access denied.');
            stopRecording();
        }
    };
    
    // Automatically restart if it stops while we still want it recording
    recognition.onend = function() {
        if (isRecording) {
            recognition.start();
        }
    };
}

function startRecording() {
    if (!recognition) initSpeech();
    finalTranscript = document.getElementById('final-text').textContent = '';
    document.getElementById('interim-text').textContent = '';
    document.getElementById('soap-textarea').value = '';
    
    try {
        recognition.start();
        isRecording = true;
        document.getElementById('btn-start').style.display = 'none';
        document.getElementById('btn-stop').style.display = 'inline-flex';
    } catch(e) {
        console.error(e);
    }
}

function stopRecording() {
    isRecording = false;
    if (recognition) recognition.stop();
    document.getElementById('btn-start').style.display = 'inline-flex';
    document.getElementById('btn-stop').style.display = 'none';
    
    // If we have text, generate SOAP
    const fullText = finalTranscript.trim();
    if (fullText.length > 5) {
        generateSoap(fullText);
    }
}

async function generateSoap(transcript) {
    document.getElementById('generating-overlay').style.display = 'flex';
    try {
        const res = await fetch(API_BASE + '/generate-soap.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transcript: transcript })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('soap-textarea').value = data.soap_text;
        } else {
            alert("Error generating SOAP: " + data.error);
        }
    } catch (e) {
        alert("Network error generating SOAP.");
    } finally {
        document.getElementById('generating-overlay').style.display = 'none';
    }
}

async function saveSoapNote() {
    const text = document.getElementById('soap-textarea').value.trim();
    const btn = document.getElementById('btn-save');
    const err = document.getElementById('save-error');
    const ok = document.getElementById('save-status');
    
    if (!text) { err.textContent = "Cannot save an empty note."; err.style.display = 'block'; return; }
    
    btn.disabled = true;
    err.style.display = 'none';
    ok.style.display = 'none';
    
    try {
        const res = await fetch(API_BASE + '/save-consultation-note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ session_id: SESSION_ID, patient_id: PID, soap_note_text: text })
        });
        const data = await res.json();
        if (data.success) {
            ok.style.display = 'block';
        } else {
            err.textContent = "Error: " + data.error;
            err.style.display = 'block';
        }
    } catch (e) {
        err.textContent = "Network error while saving.";
        err.style.display = 'block';
    } finally {
        btn.disabled = false;
    }
}

// Init
window.addEventListener('DOMContentLoaded', () => {
    initSpeech();
});
</script>

</body>
</html>
