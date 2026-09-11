<?php

/**
 * AI Intake — Consent Screen (Step 2)
 *
 * Displays informed consent text, an audio listen button, and an
 * "I Agree & Continue" button (disabled until the patient ticks the checkbox).
 * On agree → POST api/save-consent.php → redirect to pathway-selection.php.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once(__DIR__ . '/../lib/i18n.php');

// Validate token — exits with 403 page on failure.
$ks           = KioskSession::requireValid('login.php');
$token        = $ks->token();
$isNewPatient = $ks->isNewPatient();
$lang         = $ks->language();
$t            = i18nStrings($lang);
$speechLang   = speechLangCode($lang);

// Mark that login step is done (idempotent).
$ks->markStep('login');

$apiBase = ($GLOBALS['webroot'] ?? '') . '/interface/modules/custom_modules/oe-module-ai-intake/api';

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['consent_title']) ?> — AI Intake</title>
    <meta name="description" content="Please read and agree to the informed consent before your consultation.">
    <style>
        /* ── Design tokens (same as login.php) ──────────────────────────────── */
        :root {
            --brand-primary:  #2c9cd4;
            --brand-accent:   #00d4ff;
            --brand-dark:     #1a6fa0;
            --brand-gradient: linear-gradient(135deg, #1a6fa0 0%, #2c9cd4 50%, #00d4ff 100%);
            --surface:        #ffffff;
            --surface-glass:  rgba(255,255,255,0.93);
            --text-primary:   #1a2a3a;
            --text-secondary: #4a6070;
            --text-muted:     #8899a6;
            --border:         #d8e8f0;
            --border-focus:   #2c9cd4;
            --success:        #2ea055;
            --radius-card:    20px;
            --radius-input:   10px;
            --shadow-card:    0 8px 40px rgba(44,156,212,.18), 0 2px 8px rgba(0,0,0,.06);
            --shadow-btn:     0 4px 16px rgba(44,156,212,.35);
            --transition:     0.22s cubic-bezier(.4,0,.2,1);
            --font:           'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
            font-family: var(--font);
            background: var(--brand-gradient);
            color: var(--text-primary);
        }
        body::before {
            content: ''; position: fixed;
            width: 500px; height: 500px; border-radius: 50%;
            top: -120px; left: -120px;
            background: #00d4ff; opacity: .1;
            animation: blob 18s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes blob {
            0%,100% { transform: scale(1) translate(0,0); }
            50%     { transform: scale(1.1) translate(30px,-20px); }
        }

        .page-wrap {
            min-height: 100vh;
            display: flex; flex-direction: column;
            align-items: center; justify-content: flex-start;
            padding: 32px 16px 48px;
        }

        /* Progress bar */
        .progress {
            width: 100%; max-width: 600px;
            margin-bottom: 28px;
        }
        .progress-label {
            display: flex; justify-content: space-between;
            color: rgba(255,255,255,.8); font-size: .78rem;
            font-weight: 600; letter-spacing: .04em;
            text-transform: uppercase; margin-bottom: 8px;
        }
        .progress-track {
            height: 4px; background: rgba(255,255,255,.25);
            border-radius: 2px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; width: 33%;
            background: #fff; border-radius: 2px;
            transition: width .6s ease;
        }

        /* Card */
        .card {
            background: var(--surface-glass);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.6);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            width: 100%; max-width: 600px;
            overflow: hidden;
        }

        /* Card header */
        .card-header {
            padding: 28px 36px 0;
            display: flex; align-items: flex-start; gap: 16px;
        }
        .header-icon {
            flex-shrink: 0;
            width: 48px; height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg,#1a6fa0,#2c9cd4);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
        }
        .header-text h1 {
            font-size: 1.25rem; font-weight: 700;
            color: var(--text-primary); margin-bottom: 4px;
        }
        .header-text p { font-size: .85rem; color: var(--text-muted); }

        .welcome-tag {
            display: inline-block; margin: 20px 36px 0;
            background: <?= $isNewPatient ? '#e8f5e9' : '#e3f2fd' ?>;
            color: <?= $isNewPatient ? '#2e7d32' : '#1565c0' ?>;
            border-radius: 6px; padding: 5px 12px;
            font-size: .8rem; font-weight: 600;
        }

        /* Divider */
        .divider { height: 1px; background: var(--border); margin: 20px 36px 0; }

        /* Consent body */
        .consent-body { padding: 24px 36px; }

        /* Audio listen row */
        .listen-row {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 20px;
            padding: 12px 16px;
            background: rgba(44,156,212,.06);
            border: 1px solid rgba(44,156,212,.18);
            border-radius: var(--radius-input);
        }
        .listen-btn {
            flex-shrink: 0;
            width: 40px; height: 40px;
            border: none; border-radius: 50%;
            background: var(--brand-primary);
            color: #fff; font-size: 1.1rem;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: transform var(--transition), box-shadow var(--transition);
            box-shadow: 0 2px 8px rgba(44,156,212,.4);
        }
        .listen-btn:hover { transform: scale(1.08); box-shadow: 0 4px 14px rgba(44,156,212,.5); }
        .listen-btn:active { transform: scale(.95); }
        .listen-text { font-size: .85rem; color: var(--text-secondary); line-height: 1.4; }
        .listen-text strong { color: var(--text-primary); }

        /* Consent text box */
        .consent-scroll {
            height: 260px;
            overflow-y: auto;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-input);
            padding: 20px 22px;
            background: #f8fcff;
            font-size: .9rem;
            line-height: 1.7;
            color: var(--text-secondary);
            scroll-behavior: smooth;
        }
        .consent-scroll::-webkit-scrollbar { width: 6px; }
        .consent-scroll::-webkit-scrollbar-track { background: transparent; }
        .consent-scroll::-webkit-scrollbar-thumb { background: #c0d8e8; border-radius: 3px; }

        .consent-scroll h2 {
            font-size: 1rem; font-weight: 700;
            color: var(--text-primary); margin-bottom: 10px;
        }
        .consent-scroll h3 {
            font-size: .88rem; font-weight: 700;
            color: var(--text-primary); margin: 16px 0 6px;
        }
        .consent-scroll p { margin-bottom: 10px; }
        .consent-scroll ul { padding-left: 20px; margin-bottom: 10px; }
        .consent-scroll ul li { margin-bottom: 5px; }

        /* Scroll indicator */
        .scroll-hint {
            text-align: center; font-size: .75rem;
            color: var(--text-muted); margin-top: 6px;
            transition: opacity var(--transition);
        }
        .scroll-hint.hidden { opacity: 0; }

        /* Checkbox row */
        .agree-row {
            display: flex; align-items: flex-start; gap: 12px;
            margin-top: 22px;
            padding: 16px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-input);
            cursor: pointer;
            transition: border-color var(--transition), background var(--transition);
        }
        .agree-row:hover { border-color: var(--brand-primary); background: rgba(44,156,212,.03); }
        .agree-row input[type=checkbox] {
            width: 20px; height: 20px; margin-top: 2px; flex-shrink: 0;
            accent-color: var(--brand-primary); cursor: pointer;
        }
        .agree-row label {
            font-size: .9rem; color: var(--text-primary);
            line-height: 1.5; cursor: pointer; font-weight: 500;
        }
        .agree-row label span { color: var(--text-muted); font-weight: 400; }

        /* Alert */
        .alert {
            border-radius: var(--radius-input); padding: 12px 16px;
            font-size: .88rem; margin-top: 16px;
            display: none; align-items: center; gap: 10px;
        }
        .alert.visible { display: flex; }
        .alert-error { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6c6; }

        /* Buttons */
        .card-footer-actions { padding: 0 36px 28px; }
        .btn {
            width: 100%; padding: 15px;
            border: none; border-radius: var(--radius-input);
            font-size: 1rem; font-weight: 700;
            cursor: pointer; position: relative; overflow: hidden;
            transition: opacity var(--transition), transform var(--transition), box-shadow var(--transition);
        }
        .btn:active:not(:disabled) { transform: scale(.98); }
        .btn:disabled { opacity: .45; cursor: not-allowed; }

        .btn-primary {
            background: var(--brand-gradient);
            color: #fff; box-shadow: var(--shadow-btn);
            margin-bottom: 10px;
        }
        .btn-primary:not(:disabled):hover { box-shadow: 0 6px 24px rgba(44,156,212,.5); }

        .btn .spinner {
            display: none; width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.35);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
            position: absolute; right: 20px;
            top: 50%; transform: translateY(-50%);
        }
        .btn.loading .spinner { display: block; }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        .back-link {
            display: block; text-align: center;
            font-size: .83rem; color: var(--brand-primary);
            text-decoration: none; margin-top: 6px;
        }
        .back-link:hover { text-decoration: underline; }

        @media (max-width: 520px) {
            .card-header, .welcome-tag, .divider,
            .consent-body, .card-footer-actions { padding-left: 20px; padding-right: 20px; }
            .welcome-tag { margin-left: 20px; }
            .divider { margin: 20px 20px 0; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Progress indicator -->
    <div class="progress" role="progressbar" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100" aria-label="Step 1 of 3">
        <div class="progress-label">
            <span>Step 1 of 3 — Consent</span>
            <span>33%</span>
        </div>
        <div class="progress-track"><div class="progress-fill"></div></div>
    </div>

    <main class="card" role="main">

        <!-- Header -->
        <div class="card-header">
            <div class="header-icon" aria-hidden="true">📋</div>
            <div class="header-text">
                <h1><?= htmlspecialchars($t['consent_heading']) ?></h1>
                <p><?= htmlspecialchars($t['consent_intro']) ?></p>
            </div>
        </div>

        <span class="welcome-tag">
            <?= $isNewPatient ? '👋 ' . ($lang === 'mr' ? 'स्वागत! नवीन रुग्ण' : 'Welcome! New Patient') : '👋 ' . ($lang === 'mr' ? 'पुन्हा स्वागत! परतणारे रुग्ण' : 'Welcome back! Returning Patient') ?>
        </span>

        <div class="divider"></div>

        <div class="consent-body">

            <!-- Audio listen button -->
            <div class="listen-row" role="complementary" aria-label="Audio option">
                <button id="btn-listen" class="listen-btn" type="button"
                        aria-label="Listen to consent text"
                        onclick="listenConsent()"><?= htmlspecialchars($t['btn_listen_consent']) ?></button>
                <div class="listen-text">
                    <strong><?= $lang === 'mr' ? 'ऐकायचे आहे का?' : 'Prefer to listen?' ?></strong><br>
                    <?= $lang === 'mr' ? 'सहमती मजकूर ऐकण्यासाठी स्पीकर बटण दाबा.' : 'Press the speaker button to hear this consent read aloud.' ?>
                </div>
            </div>

            <!-- Scrollable consent text -->
            <div id="consent-scroll" class="consent-scroll" tabindex="0"
                 role="document" aria-label="Consent document">
                <?php if ($lang === 'mr'): ?>
                <h2>एआय-सहाय्यित क्लिनिकल इनटेकसाठी माहितीपूर्ण संमती</h2>
                <p>पुढे जाण्यापूर्वी, कृपया खालील माहिती काळजीपूर्वक वाचा. हा दस्तऐवज स्पष्ट करतो की या सुविधेतील आजच्या भेटीदरम्यान तुमची आरोग्य माहिती कशी गोळा केली जाईल, तिच्यावर प्रक्रिया केली जाईल आणि कशी वापरली जाईल.</p>

                <h3>१. आम्ही काय गोळा करतो</h3>
                <p>या इनटेक सत्रादरम्यान, आम्ही खालील गोष्टी गोळा करू शकतो:</p>
                <ul>
                    <li>इनटेक मुलाखतीदरम्यान तुमचे <strong>व्हॉइस रेकॉर्डिंग</strong>, ज्याचे मजकुरात रूपांतर करून तुमच्या डॉक्टरांसाठी क्लिनिकल सारांश तयार केला जाईल.</li>
                    <li>कोणतीही <strong>कागदपत्रे</strong> किंवा छायाचित्रे जी तुम्ही अपलोड करणे निवडता (उदा., मागील प्रिस्क्रिप्शन, लॅब रिपोर्ट, वैद्यकीय नोंदी).</li>
                    <li>लक्षणे, वैद्यकीय इतिहास आणि सध्याच्या औषधांसह आरोग्य-संबंधित प्रश्नांची तुमची उत्तरे.</li>
                </ul>

                <h3>२. आम्ही त्याचा कसा वापर करतो</h3>
                <p>गोळा केलेली माहिती <strong>केवळ</strong> खालील कारणांसाठी वापरली जाते:</p>
                <ul>
                    <li>तुमच्या उपचार करणाऱ्या डॉक्टरांसाठी पूर्व-सल्लामसलत क्लिनिकल सारांश तयार करणे.</li>
                    <li>रुग्णालयाच्या OpenEMR सिस्टममध्ये तुमची रुग्ण नोंद तयार करणे किंवा अद्यतनित करणे.</li>
                    <li>भविष्यातील भेटींमध्ये तुमची काळजी सातत्य सुधारणे (जर तुम्ही स्वतंत्रपणे संमती दिली तर).</li>
                </ul>

                <h3>३. एआय प्रक्रिया</h3>
                <p>संबंधित क्लिनिकल माहिती काढण्यासाठी तुमचा आवाज आणि इनटेक प्रतिसादांवर एआय प्रणालीद्वारे प्रक्रिया केली जाऊ शकते. हा एआय-व्युत्पन्न सारांश तुमच्या डॉक्टरांद्वारे तपासला जातो आणि तो क्लिनिकल तपासणीला <strong>बदली म्हणून काम करत नाही</strong>. सर्व एआय आउटपुट केवळ सल्लागार म्हणून मानले जातात.</p>

                <h3>४. डेटा संचयन आणि सुरक्षा</h3>
                <p>तुमचा डेटा या सुविधेच्या इलेक्ट्रॉनिक मेडिकल रेकॉर्ड सिस्टीम (OpenEMR) मध्ये सुरक्षितपणे संग्रहित केला जातो आणि मानक एन्क्रिप्शनद्वारे संरक्षित केला जातो. तुमच्या व्हॉइस रेकॉर्डिंगवर रिअल टाइममध्ये प्रक्रिया केली जाते आणि तुम्ही स्वतंत्र स्पष्ट संमती दिल्याशिवाय वर्तमान सत्राच्या पलीकडे <strong>जतन केले जात नाही</strong>.</p>

                <h3>५. तुमचे अधिकार</h3>
                <ul>
                    <li>तुम्हाला कोणत्याही वेळी या एआय-सहाय्यित इनटेकला <strong>नकार देण्याचा</strong> आणि कर्मचाऱ्यांसोबत पारंपारिक सल्लामसलत करण्याची विनंती करण्याचा अधिकार आहे.</li>
                    <li>तुम्ही कोणत्याही वेळी तुमच्या आरोग्य माहितीमध्ये प्रवेश, दुरुस्ती किंवा हटविण्याची विनंती करू शकता.</li>
                    <li>या इनटेकला नकार दिल्यास तुम्हाला मिळणाऱ्या काळजीच्या गुणवत्तेवर कोणताही परिणाम होणार नाही.</li>
                </ul>

                <h3>६. लागू कायदा</h3>
                <p>ही संमती <strong>डिजिटल वैयक्तिक डेटा संरक्षण कायदा, २०२३ (DPDP कायदा)</strong> आणि भारतातील लागू आरोग्य डेटा नियमांद्वारे शासित आहे. तुमचा डेटा कायद्यानुसार आवश्यक असल्याशिवाय, तुमच्या स्पष्ट संमतीशिवाय तृतीय पक्षांसह सामायिक केला जाणार नाही.</p>

                <p style="margin-top:16px;font-size:.82rem;color:var(--text-muted);">
                    या संमतीबद्दल तुमचे काही प्रश्न असल्यास, कृपया पुढे जाण्यापूर्वी रिसेप्शन डेस्कवरील कर्मचाऱ्याशी बोला.
                </p>
                <?php else: ?>
                <h2>Informed Consent for AI-Assisted Clinical Intake</h2>

                <p>Before we proceed with your consultation, please read the following information carefully.
                   This document explains how your health information will be collected, processed, and used
                   during today's visit at this facility.</p>

                <h3>1. What We Collect</h3>
                <p>During this intake session, we may collect:</p>
                <ul>
                    <li>Your <strong>voice recording</strong> during the intake interview, which will be transcribed
                        into text to generate a clinical summary for your doctor.</li>
                    <li>Any <strong>documents</strong> or photographs you choose to upload (e.g., previous prescriptions,
                        lab reports, medical records).</li>
                    <li>Your responses to health-related questions, including symptoms, medical history, and current medications.</li>
                </ul>

                <h3>2. How We Use It</h3>
                <p>The information collected is used <strong>only</strong> to:</p>
                <ul>
                    <li>Generate a pre-consultation clinical summary for your treating physician.</li>
                    <li>Create or update your patient record in the hospital's OpenEMR system.</li>
                    <li>Improve your care continuity across future visits (if you consent separately).</li>
                </ul>

                <h3>3. AI Processing</h3>
                <p>Your voice and intake responses may be processed by an AI system to extract relevant clinical information.
                   This AI-generated summary is reviewed by your doctor and <strong>does not replace</strong> a clinical examination.
                   All AI outputs are treated as advisory only.</p>

                <h3>4. Data Storage &amp; Security</h3>
                <p>Your data is stored securely within this facility's electronic medical record system (OpenEMR),
                   protected by industry-standard encryption. Your voice recordings are processed in real time and
                   <strong>are not retained</strong> beyond the current session unless you provide separate explicit consent.</p>

                <h3>5. Your Rights</h3>
                <ul>
                    <li>You have the right to <strong>refuse</strong> this AI-assisted intake at any time and request a
                        traditional consultation with staff.</li>
                    <li>You may request access to, correction of, or deletion of your health information at any time.</li>
                    <li>Refusing this intake will not affect the quality of care you receive.</li>
                </ul>

                <h3>6. Applicable Law</h3>
                <p>This consent is governed by the <strong>Digital Personal Data Protection Act, 2023 (DPDP Act)</strong>
                   and applicable health data regulations in India. Your data will not be shared with third parties
                   without your explicit consent, except as required by law.</p>

                <p style="margin-top:16px;font-size:.82rem;color:var(--text-muted);">
                    If you have any questions about this consent, please speak with a staff member at the reception desk
                    before proceeding.
                </p>
                <?php endif; ?>
            </div>
            <p class="scroll-hint" id="scroll-hint" aria-live="polite">↓ Scroll down to read the full document</p>

            <!-- Error alert -->
            <div id="alert" class="alert alert-error" role="alert">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span id="alert-msg"></span>
            </div>

            <!-- Checkbox -->
            <div class="agree-row" onclick="toggleCheck()">
                <input type="checkbox" id="consent-check" aria-describedby="consent-check-label"
                       onchange="onCheckChange()" onclick="event.stopPropagation()">
                <label id="consent-check-label" for="consent-check">
                    <?= htmlspecialchars($t['consent_checkbox']) ?>
                </label>
            </div>

        </div><!-- .consent-body -->

        <!-- Actions -->
        <div class="card-footer-actions">
            <button id="btn-agree" class="btn btn-primary" type="button"
                    disabled onclick="submitConsent()">
                <?= htmlspecialchars($t['btn_agree']) ?>
                <span class="spinner" aria-hidden="true"></span>
            </button>
            <a class="back-link" href="login.php"><?= htmlspecialchars($t['btn_back']) ?></a>
        </div>

    </main>
</div>

<script>
var TOKEN       = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var API_BASE    = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
var SPEECH_LANG = '<?= htmlspecialchars($speechLang, ENT_QUOTES) ?>';
var CONSENT_TTS_TEXT = '<?= htmlspecialchars($t['tts_consent_text'], ENT_QUOTES) ?>';
var ERR_NETWORK = '<?= htmlspecialchars($t['err_network'], ENT_QUOTES) ?>';

/* ── Real TTS: read consent aloud ──────────────────────────────────────── */
var ttsChunks = [];
var currentChunkIdx = 0;
var isPlaying = false;

function listenConsent() {
    if (!('speechSynthesis' in window)) { showAlert('Text-to-speech is not supported in this browser.'); return; }
    var btn = document.getElementById('btn-listen');
    
    if (isPlaying) {
        window.speechSynthesis.cancel();
        isPlaying = false;
        btn.textContent = '🔊';
        return;
    }
    
    window.speechSynthesis.cancel();
    // Chunk long text by sentences to prevent Chrome TTS stalling
    var text = CONSENT_TTS_TEXT;
    ttsChunks = text.match(/[^.!?]+[.!?]+/g) || [text];
    currentChunkIdx = 0;
    isPlaying = true;
    btn.textContent = '⏸'; 
    btn.setAttribute('aria-label','Stop');
    
    playNextChunk();
}

function playNextChunk() {
    if (!isPlaying) return;
    if (currentChunkIdx >= ttsChunks.length) {
        isPlaying = false;
        document.getElementById('btn-listen').textContent = '🔊';
        return;
    }
    
    var utter = new SpeechSynthesisUtterance(ttsChunks[currentChunkIdx].trim());
    utter.lang = SPEECH_LANG;
    utter.rate = 0.95;
    
    var voices = window.speechSynthesis.getVoices();
    var match  = voices.find(function(v) { return v.lang.startsWith(SPEECH_LANG.split('-')[0]); });
    if (match) utter.voice = match;
    
    utter.onend = function() {
        currentChunkIdx++;
        playNextChunk();
    };
    utter.onerror = function(e) {
        if (e.error === 'interrupted' || e.error === 'canceled') return;
        console.warn('TTS error on chunk', e);
        currentChunkIdx++;
        playNextChunk();
    };
    
    // Workaround for Chrome bug: speech gets stuck if not resumed
    window.speechSynthesis.resume();
    window.speechSynthesis.speak(utter);
}

if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = function() { window.speechSynthesis.getVoices(); };
}

/* ── Scroll hint ─────────────────────────────────────────────────────────── */
(function () {
    var el   = document.getElementById('consent-scroll');
    var hint = document.getElementById('scroll-hint');
    el.addEventListener('scroll', function () {
        var atBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 10;
        hint.classList.toggle('hidden', atBottom);
    });
})();

/* ── Checkbox ────────────────────────────────────────────────────────────── */
function toggleCheck() {
    var cb = document.getElementById('consent-check');
    cb.checked = !cb.checked;
    onCheckChange();
}
function onCheckChange() {
    var checked = document.getElementById('consent-check').checked;
    document.getElementById('btn-agree').disabled = !checked;
}

/* ── Submit consent ─────────────────────────────────────────────────────── */
function submitConsent() {
    clearAlert();
    var btn = document.getElementById('btn-agree');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch(API_BASE + '/save-consent.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ token: TOKEN, consent_given: true }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.classList.remove('loading');
        if (d.success) {
            window.location.href = 'pathway-selection.php?token=' + encodeURIComponent(TOKEN);
        } else {
            btn.disabled = false;
            showAlert(d.error || ERR_NETWORK);
        }
    })
    .catch(function() {
        btn.classList.remove('loading');
        btn.disabled = false;
        showAlert(ERR_NETWORK);
    });
}

function showAlert(msg) {
    var el = document.getElementById('alert');
    document.getElementById('alert-msg').textContent = msg;
    el.classList.add('visible');
}
function clearAlert() {
    document.getElementById('alert').classList.remove('visible');
}
</script>
</body>
</html>
