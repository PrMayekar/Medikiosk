<?php
/**
 * AI Intake — Unified Voice App (SPA)
 *
 * 100% hands-free conversational interface for Consent, Pathway, and Interview.
 * Runs entirely on the client side with fetch() calls to update the backend,
 * ensuring Chrome's SpeechSynthesis user-interaction token is never dropped.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once(__DIR__ . '/../lib/i18n.php');

$isTest = ($_GET['test'] ?? '') === 'qa';

if ($isTest) {
    $token = 'test-token-123';
    $lang = 'en';
    $t = i18nStrings($lang);
    $speechLang = speechLangCode($lang);
    $currentStep = 'interview';
} else {
    $ks    = KioskSession::requireValid('login.php');
    $token = $ks->token();
    $lang  = $ks->language();
    $t     = i18nStrings($lang);
    $speechLang = speechLangCode($lang);
    
    // Check if we are resuming
    $currentStep = 'consent';
    if ($ks->hasStep('consent')) $currentStep = 'pathway';
    if ($ks->hasStep('pathway')) $currentStep = 'interview';
}

$apiBase    = ($GLOBALS['webroot'] ?? '') . '/interface/modules/custom_modules/oe-module-ai-intake/api';

// Define questions to inject into JS
$questionSets = [
    'allopathic' => [
        'en' => [
            ['id' => 'chief_complaint', 'q' => 'What is your main health concern today?'],
            ['id' => 'duration',        'q' => 'How long have you been experiencing this?'],
            ['id' => 'severity',        'q' => 'On a scale of 1 to 10, how severe is it right now?'],
            ['id' => 'aggravating',     'q' => 'What makes it worse?'],
            ['id' => 'relieving',       'q' => 'What makes it better, or provides relief?'],
            ['id' => 'past_history',    'q' => 'Do you have any past medical conditions we should know about?'],
            ['id' => 'meds_allergies',  'q' => 'Are you currently taking any medications, or do you have any known allergies?']
        ],
        'mr' => [
            ['id' => 'chief_complaint', 'q' => 'आज तुमची मुख्य आरोग्य समस्या काय आहे?'],
            ['id' => 'duration',        'q' => 'तुम्हाला हे किती दिवसांपासून होत आहे?'],
            ['id' => 'severity',        'q' => '१ ते १० च्या प्रमाणात, सध्या हे किती तीव्र आहे?'],
            ['id' => 'aggravating',     'q' => 'कोणत्या गोष्टी त्रास वाढवतात?'],
            ['id' => 'relieving',       'q' => 'कोणत्या गोष्टींनी आराम मिळतो?'],
            ['id' => 'past_history',    'q' => 'तुम्हाला कोणतेही जुने आजार आहेत का?'],
            ['id' => 'meds_allergies',  'q' => 'तुम्ही सध्या कोणती औषधे घेत आहात किंवा कोणत्या गोष्टींची ॲलर्जी आहे का?']
        ]
    ],
    'ayurvedic' => [
        'en' => [
            ['id' => 'chief_complaint', 'q' => 'What is your main health concern today?'],
            ['id' => 'duration',        'q' => 'How long have you been experiencing this?'],
            ['id' => 'severity',        'q' => 'On a scale of 1 to 10, how severe is it?'],
            ['id' => 'aggravating',     'q' => 'What makes it worse?'],
            ['id' => 'relieving',       'q' => 'What makes it better?'],
            ['id' => 'body_type',       'q' => 'How would you describe your body type — cold and dry, hot and intense, or heavy and slow?'],
            ['id' => 'appetite',        'q' => 'How is your appetite and digestion usually?'],
            ['id' => 'sleep',           'q' => 'How would you describe your sleep?'],
            ['id' => 'stress_response', 'q' => 'How do you generally respond to stress?'],
            ['id' => 'past_remedies',   'q' => 'Have you tried any Ayurvedic treatments before?']
        ],
        'mr' => [
            ['id' => 'chief_complaint', 'q' => 'आज तुमची मुख्य आरोग्य समस्या काय आहे?'],
            ['id' => 'duration',        'q' => 'तुम्हाला हे किती दिवसांपासून होत आहे?'],
            ['id' => 'severity',        'q' => '१ ते १० च्या प्रमाणात, सध्या हे किती तीव्र आहे?'],
            ['id' => 'aggravating',     'q' => 'कोणत्या गोष्टी त्रास वाढवतात?'],
            ['id' => 'relieving',       'q' => 'कोणत्या गोष्टींनी आराम मिळतो?'],
            ['id' => 'body_type',       'q' => 'तुमची शरीरप्रकृती कशी आहे — थंड आणि कोरडी, गरम आणि तीव्र, की जड आणि संथ?'],
            ['id' => 'appetite',        'q' => 'तुमची भूक आणि पचन साधारणपणे कसे असते?'],
            ['id' => 'sleep',           'q' => 'तुमची झोप कशी असते?'],
            ['id' => 'stress_response', 'q' => 'तुम्ही ताणतणावाला कसा प्रतिसाद देता?'],
            ['id' => 'past_remedies',   'q' => 'तुम्ही यापूर्वी कोणतेही आयुर्वेदिक उपचार घेतले आहेत का?']
        ]
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voice Intake — AI Kiosk</title>
    <style>
        :root {
            --brand-primary:  #2c9cd4;
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --surface:        #ffffff;
            --text-primary:   #1a2a3a;
            --text-muted:     #8899a6;
            --transition:     0.3s cubic-bezier(.4,0,.2,1);
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { height:100%; font-family:system-ui,-apple-system,sans-serif; background:var(--brand-gradient); color:var(--text-primary); overflow:hidden; }
        body::before { content:''; position:fixed; width:600px; height:600px; border-radius:50%; top:-150px; left:-150px; background:#00d4ff; opacity:.15; animation:blob 20s infinite; pointer-events:none; }
        @keyframes blob { 0%,100%{transform:translate(0,0)} 50%{transform:translate(40px,-30px)} }

        .page-wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px; text-align:center; }
        
        /* Orb / Mic Visual */
        .orb-container { position:relative; margin-bottom:40px; }
        .orb {
            width:160px; height:160px; border-radius:50%;
            background:var(--brand-gradient); display:flex; align-items:center; justify-content:center;
            font-size:4rem; color:#fff; box-shadow:0 10px 40px rgba(44,156,212,.4);
            transition:var(--transition); position:relative; z-index:2; cursor:pointer;
        }
        .orb-ring {
            position:absolute; inset:-20px; border-radius:50%; border:2px solid rgba(255,255,255,.3);
            z-index:1; opacity:0; transition:opacity .3s;
        }
        
        /* States */
        body.state-init .orb { animation:float 3s infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        
        body.state-speaking .orb { transform:scale(1.05); box-shadow:0 15px 50px rgba(44,156,212,.6); }
        body.state-speaking .orb-ring { opacity:1; animation:ripple 2s linear infinite; }
        
        body.state-listening .orb { background:linear-gradient(135deg,#c62828,#e53935); box-shadow:0 15px 50px rgba(229,57,53,.6); transform:scale(1.15); }
        body.state-listening .orb-ring { opacity:1; border-color:rgba(229,57,53,.4); animation:ripple-fast 1s linear infinite; }

        body.state-processing .orb { background:#ffb300; box-shadow:0 15px 50px rgba(255,179,0,.6); }
        body.state-processing .orb::after { content:''; position:absolute; inset:5px; border-radius:50%; border:4px solid transparent; border-top-color:#fff; animation:spin 1s linear infinite; }

        @keyframes ripple { 0%{transform:scale(1);opacity:.5} 100%{transform:scale(1.5);opacity:0} }
        @keyframes ripple-fast { 0%{transform:scale(1);opacity:.8} 100%{transform:scale(1.4);opacity:0} }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Typography */
        .status-text { font-size:1rem; font-weight:700; color:rgba(255,255,255,.7); text-transform:uppercase; letter-spacing:2px; margin-bottom:12px; }
        .main-text { font-size:2.2rem; font-weight:700; color:#fff; max-width:800px; line-height:1.3; min-height:85px; text-shadow:0 2px 10px rgba(0,0,0,.1); word-break:break-word; }
        .sub-text { font-size:1.4rem; font-weight:600; color:rgba(255,255,255,.6); margin-top:16px; min-height:40px; }

        .hidden { display:none !important; }
    </style>
</head>
<body class="state-init">

<div class="page-wrap">
    <div class="orb-container">
        <div class="orb-ring"></div>
        <div class="orb" id="orb" onclick="startApp()">🎤</div>
    </div>
    
    <div class="status-text" id="status">Tap to Begin</div>
    <div class="main-text" id="main-text">Ready when you are.</div>
    <div class="sub-text" id="sub-text"></div>
</div>

<script>
/* ─── Config ───────────────────────────────────────────────────────────── */
const TOKEN      = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
const API_BASE   = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
const LANG       = '<?= htmlspecialchars($lang, ENT_QUOTES) ?>';
const SPEECH_LANG = '<?= htmlspecialchars($speechLang, ENT_QUOTES) ?>';
const ALL_QUESTIONS = <?= json_encode($questionSets) ?>;

const IS_MR = (LANG === 'mr');
const STRINGS = {
    consent_prompt: IS_MR 
        ? "माहिती आणि संमती: आम्ही तुमचे बोलणे रेकॉर्ड करू जेणेकरून डॉक्टरांसाठी सारांश तयार करता येईल. तुम्ही सहमत आहात का? हो किंवा नाही सांगा."
        : "Informed Consent: We will record your voice to generate a clinical summary for your doctor. Do you agree? Say yes or no.",
    pathway_prompt: IS_MR
        ? "तुम्हाला ॲलोपॅथिक उपचार हवे आहेत की आयुर्वेदिक? ॲलोपॅथिक किंवा आयुर्वेदिक असे सांगा."
        : "Would you prefer an Allopathic or Ayurvedic consultation? Say Allopathic or Ayurvedic.",
    got_it: IS_MR ? "ठीक आहे." : "Got it.",
    not_catch: IS_MR ? "मला समजले नाही. कृपया पुन्हा सांगा." : "I didn't quite catch that. Could you repeat?",
    thanks: IS_MR ? "धन्यवाद! तुमची माहिती सबमिट केली जात आहे." : "Thank you! I have all your answers. Submitting now.",
    err_mic: IS_MR ? "मायक्रोफोन त्रुटी." : "Microphone error."
};

let currentPhase = '<?= htmlspecialchars($currentStep) ?>'; // 'consent', 'pathway', 'interview'
let selectedPathway = 'allopathic';
let currentQIndex = 0;
let interviewAnswers = [];
let retryCount = 0;

/* ─── State Machine Core ───────────────────────────────────────────────── */
function setState(stateStr, mainText, subText, orbIcon) {
    document.body.className = 'state-' + stateStr;
    document.getElementById('status').textContent = stateStr.toUpperCase();
    if (mainText !== undefined) document.getElementById('main-text').textContent = mainText;
    if (subText !== undefined) document.getElementById('sub-text').textContent = subText;
    if (orbIcon !== undefined) document.getElementById('orb').textContent = orbIcon;
}

function startApp() {
    if (document.body.classList.contains('state-init')) {
        // Unlock audio context
        speak("", function() {
            runPhase();
        });
    }
}

function runPhase() {
    retryCount = 0;
    if (currentPhase === 'consent') {
        promptAndListen(STRINGS.consent_prompt, handleConsent);
    } else if (currentPhase === 'pathway') {
        promptAndListen(STRINGS.pathway_prompt, handlePathway);
    } else if (currentPhase === 'interview') {
        let qList = ALL_QUESTIONS[selectedPathway][LANG] || ALL_QUESTIONS[selectedPathway]['en'];
        if (currentQIndex < qList.length) {
            promptAndListen(qList[currentQIndex].q, handleInterview);
        } else {
            finishInterview();
        }
    }
}

/* ─── Voice Handlers (TTS -> STT) ──────────────────────────────────────── */
function speak(text, onDone) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    if (!text) { if(onDone) onDone(); return; }
    
    setState('speaking', text, '', '🔊');
    
    var utter = new SpeechSynthesisUtterance(text);
    utter.lang = SPEECH_LANG;
    utter.rate = 0.95;
    var voices = window.speechSynthesis.getVoices();
    var match  = voices.find(v => v.lang.startsWith(SPEECH_LANG.split('-')[0]));
    if (match) utter.voice = match;
    
    utter.onend = function() {
        // Small delay before mic opens
        setTimeout(onDone, 300);
    };
    utter.onerror = function() { setTimeout(onDone, 300); };
    window.speechSynthesis.speak(utter);
}

function listen(onResult, onSilence) {
    if (!window.SpeechRecognition && !window.webkitSpeechRecognition) {
        alert("Speech API not supported in this browser.");
        return;
    }
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var recog = new SR();
    recog.lang = SPEECH_LANG;
    recog.continuous = false; // Auto-stop on silence
    recog.interimResults = true;
    recog.maxAlternatives = 1;
    
    let finalTranscript = '';
    
    setState('listening', document.getElementById('main-text').textContent, 'Listening...', '🔴');
    
    recog.onresult = function(e) {
        let interim = '';
        finalTranscript = '';
        for (let i = e.resultIndex; i < e.results.length; i++) {
            let t = e.results[i][0].transcript;
            if (e.results[i].isFinal) finalTranscript += t + ' ';
            else interim += t;
        }
        document.getElementById('sub-text').textContent = (finalTranscript + interim).trim();
    };
    
    recog.onend = function() {
        let txt = finalTranscript.trim() || document.getElementById('sub-text').textContent.trim();
        if (txt) {
            onResult(txt);
        } else {
            onSilence();
        }
    };
    
    recog.onerror = function(e) {
        if (e.error === 'no-speech') {
            // Handled by onend with empty transcript
        } else {
            setState('init', STRINGS.err_mic + " (" + e.error + ")", "Tap to retry", "🎤");
        }
    };
    
    try { recog.start(); } catch(e) {}
}

function promptAndListen(text, resultHandler) {
    speak(text, function() {
        listen(
            function(transcript) {
                // Success
                resultHandler(transcript.trim());
            },
            function() {
                // Silence
                retryCount++;
                if (retryCount < 3) {
                    speak(STRINGS.not_catch, function() {
                        promptAndListen(text, resultHandler);
                    });
                } else {
                    setState('init', "Conversation paused.", "Tap to resume", "▶️");
                }
            }
        );
    });
}

/* ─── Intent Parsers & API Calls ───────────────────────────────────────── */
function apiCall(endpoint, data, onSuccess) {
    setState('processing', 'Saving...', '', '⏳');
    fetch(API_BASE + endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) onSuccess();
        else setState('init', "Network Error", "Tap to retry", "▶️");
    }).catch(e => {
        setState('init', "Network Error", "Tap to retry", "▶️");
    });
}

function handleConsent(transcript) {
    let t = transcript.toLowerCase();
    if (t.includes('yes') || t.includes('agree') || t.includes('accept') || t.includes('हो') || t.includes('मान्य')) {
        apiCall('/save-consent.php', { token: TOKEN, consent_given: true }, function() {
            currentPhase = 'pathway';
            speak(STRINGS.got_it, runPhase);
        });
    } else if (t.includes('no') || t.includes('disagree') || t.includes('नाही')) {
        setState('init', "Consent Denied.", "Please see reception.", "❌");
    } else {
        // Didn't understand
        speak(STRINGS.not_catch, runPhase);
    }
}

function handlePathway(transcript) {
    let t = transcript.toLowerCase();
    if (t.includes('allo') || t.includes('english') || t.includes('regular') || t.includes('ॲलो')) {
        selectedPathway = 'allopathic';
        apiCall('/save-pathway.php', { token: TOKEN, pathway: 'allopathic' }, function() {
            currentPhase = 'interview';
            speak(STRINGS.got_it, runPhase);
        });
    } else if (t.includes('ayur') || t.includes('आयु')) {
        selectedPathway = 'ayurvedic';
        apiCall('/save-pathway.php', { token: TOKEN, pathway: 'ayurvedic' }, function() {
            currentPhase = 'interview';
            speak(STRINGS.got_it, runPhase);
        });
    } else {
        speak(STRINGS.not_catch, runPhase);
    }
}

function handleInterview(transcript) {
    let qList = ALL_QUESTIONS[selectedPathway][LANG] || ALL_QUESTIONS[selectedPathway]['en'];
    let qObj = qList[currentQIndex];
    interviewAnswers.push({
        question: qObj.q, // Using key expected by API (question, answer)
        answer: transcript
    });
    
    currentQIndex++;
    if (currentQIndex < qList.length) {
        speak(STRINGS.got_it, runPhase);
    } else {
        runPhase(); // will trigger finishInterview
    }
}

function finishInterview() {
    speak(STRINGS.thanks, function() {
        if (TOKEN === 'test-token-123') {
            setState('init', "Test complete!", "Answers are logged in console.", "✅");
            console.log("Collected Answers:", interviewAnswers);
            return;
        }

        setState('processing', 'Finalizing...', '', '⏳');
        fetch(API_BASE + '/save-interview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: TOKEN, answers: interviewAnswers })
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                // Done! Move to document upload (which is hands-on anyway)
                window.location.href = 'document-upload.php?token=' + encodeURIComponent(TOKEN);
            } else {
                setState('init', "Network Error", "Tap to retry", "▶️");
            }
        });
    });
}
</script>
</body>
</html>
