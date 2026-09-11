<?php

/**
 * AI Intake — Patient Login & Registration (Step 1)
 *
 * Tap mode: Traditional forms.
 * Voice mode: Full ASR via Web Speech API (webkitSpeechRecognition) with live transcript.
 *
 * Both ABHA login and new-patient registration support voice input.
 * OTP is always entered by the user (spoken or typed) — never auto-filled.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/i18n.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$lang       = $_SESSION['ai_intake_lang']  ?? 'en';
$mode       = $_SESSION['ai_intake_mode']  ?? 'tap';
$t          = i18nStrings($lang);
$speechLang = speechLangCode($lang);
$webRoot    = $GLOBALS['webroot'] ?? '';
$apiBase    = $webRoot . '/interface/modules/custom_modules/oe-module-ai-intake/api';

// If already logged in, skip straight to consent
$token = $_GET['token'] ?? '';
if (!empty($token) && isset($_SESSION['ai_intake_sessions'][$token])) {
    header('Location: consent.php?token=' . urlencode($token));
    exit;
}

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['login_title']) ?> — AI Intake</title>
    <style>
        :root {
            --brand-primary:  #2c9cd4;
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --surface:        rgba(255,255,255,0.96);
            --text-primary:   #1a2a3a;
            --text-secondary: #4a6070;
            --text-muted:     #8899a6;
            --border:         #d8e8f0;
            --border-focus:   #2c9cd4;
            --error:          #e53935;
            --success:        #2ea055;
            --input-bg:       #f4fafd;
            --radius-card:    24px;
            --radius-input:   12px;
            --transition:     0.2s cubic-bezier(0.4,0,0.2,1);
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { height:100%; font-family:'Segoe UI',system-ui,sans-serif; background:var(--brand-gradient); color:var(--text-primary); }
        body::before {
            content:''; position:fixed; width:600px; height:600px; border-radius:50%;
            top:-150px; left:-150px; background:#00d4ff; opacity:.15;
            animation:blob 20s ease-in-out infinite; pointer-events:none;
        }
        @keyframes blob { 0%,100%{transform:scale(1) translate(0,0)} 50%{transform:scale(1.1) translate(40px,-30px)} }

        .page-wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px 16px; }

        .card { background:var(--surface); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.8); border-radius:var(--radius-card); box-shadow:0 10px 50px rgba(0,0,0,0.15); width:100%; max-width:520px; overflow:hidden; }

        /* Tabs */
        .tab-bar { display:grid; grid-template-columns:1fr 1fr; background:rgba(44,156,212,0.08); border-bottom:1px solid var(--border); }
        .tab-btn { padding:18px; border:none; background:transparent; font-size:1rem; font-weight:700; color:var(--text-secondary); cursor:pointer; transition:var(--transition); }
        .tab-btn.active { color:var(--brand-primary); box-shadow:inset 0 -3px 0 var(--brand-primary); background:#fff; }
        .tab-btn:disabled { opacity:.4; cursor:not-allowed; }

        .panel { display:none; padding:32px 36px; }
        .panel.active { display:block; }

        /* Form */
        .form-group { margin-bottom:20px; position:relative; }
        .form-group label { display:flex; align-items:center; justify-content:space-between; font-size:.84rem; font-weight:700; color:var(--text-secondary); margin-bottom:8px; text-transform:uppercase; letter-spacing:.05em; }
        .form-group input,.form-group select { width:100%; padding:14px 18px; border:2px solid var(--border); border-radius:var(--radius-input); font-size:1.05rem; background:var(--input-bg); color:var(--text-primary); transition:var(--transition); outline:none; }
        .form-group input:focus,.form-group select:focus { border-color:var(--border-focus); background:#fff; box-shadow:0 0 0 4px rgba(44,156,212,0.1); }
        .form-group input.error { border-color:var(--error); }

        .btn-speaker { background:transparent; border:none; font-size:1.1rem; cursor:pointer; opacity:.6; transition:var(--transition); padding:4px; border-radius:50%; }
        .btn-speaker:hover { opacity:1; transform:scale(1.1); background:#e3f2fd; }

        /* Buttons */
        .btn { width:100%; padding:16px; border:none; border-radius:var(--radius-input); font-size:1.05rem; font-weight:700; cursor:pointer; transition:var(--transition); display:flex; justify-content:center; align-items:center; gap:8px; }
        .btn-primary { background:var(--brand-gradient); color:#fff; box-shadow:0 6px 20px rgba(44,156,212,.3); }
        .btn-primary:hover { box-shadow:0 8px 28px rgba(44,156,212,.45); }
        .btn:disabled { opacity:.5; cursor:not-allowed; }
        .spinner { display:none; width:18px; height:18px; border:2.5px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; flex-shrink:0; }
        .btn.loading .spinner { display:block; }
        @keyframes spin { to { transform:rotate(360deg); } }

        .alert { padding:12px 16px; border-radius:12px; font-size:.88rem; font-weight:600; margin-bottom:20px; display:none; align-items:center; gap:10px; }
        .alert.visible { display:flex; }
        .alert-error { background:#fdecea; color:#b71c1c; border:1px solid #f5c6c6; }

        .otp-section { display:none; border-top:1px solid var(--border); padding-top:20px; margin-top:20px; animation:slideDown .3s ease; }
        .otp-section.visible { display:block; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }

        /* ── Voice Mode UI ──────────────────────────────────────────── */
        body.mode-voice .tap-only { display:none !important; }
        body.mode-voice .voice-only { display:flex !important; }
        .voice-only { display:none; flex-direction:column; align-items:center; text-align:center; padding:20px 0; }

        .voice-prompt-text { font-size:1.3rem; font-weight:700; color:var(--text-primary); margin-bottom:20px; min-height:36px; line-height:1.35; }

        /* Live transcript display */
        .voice-live-text {
            min-height:48px; font-size:1.6rem; font-weight:700; color:var(--brand-primary);
            letter-spacing:.04em; margin-bottom:8px; transition:all .15s ease;
            word-break:break-word; padding:0 12px;
        }
        .voice-interim { color:#aaa; font-style:italic; }

        /* Mic button */
        .voice-mic-btn {
            width:130px; height:130px; border-radius:50%;
            background:var(--brand-gradient); border:none; color:#fff;
            font-size:3.6rem; cursor:pointer;
            box-shadow:0 10px 40px rgba(44,156,212,.4);
            display:flex; align-items:center; justify-content:center;
            margin:24px auto; transition:all .3s cubic-bezier(.4,0,.2,1);
            position:relative; outline:none;
        }
        .voice-mic-btn::before {
            content:''; position:absolute; inset:-12px; border-radius:50%;
            background:rgba(44,156,212,.2); z-index:-1;
            opacity:0; transition:opacity .3s;
        }
        .voice-mic-btn.recording { transform:scale(1.08); background:linear-gradient(135deg,#c62828,#e53935); }
        .voice-mic-btn.recording::before { opacity:1; animation:pulse 1.2s infinite; }
        .voice-mic-btn:disabled { opacity:.5; cursor:not-allowed; }
        @keyframes pulse { 0%{transform:scale(.9);opacity:.8} 100%{transform:scale(1.35);opacity:0} }

        /* State label below mic */
        .voice-state-label { font-size:.9rem; font-weight:700; color:var(--text-muted); margin-top:4px; transition:color .2s; }
        .voice-state-label.active { color:#e53935; }
        .voice-state-label.done   { color:var(--success); }
    </style>
</head>
<body class="mode-<?= htmlspecialchars($mode) ?>">

<div class="page-wrap">
    <main class="card" aria-label="Patient check-in">

        <div class="tab-bar">
            <button id="tab-abha"     class="tab-btn active" onclick="switchTab('abha')"><?= htmlspecialchars($t['tab_abha']) ?></button>
            <button id="tab-register" class="tab-btn"        onclick="switchTab('register')"><?= htmlspecialchars($t['tab_register']) ?></button>
        </div>

        <!-- ── ABHA Login ─────────────────────────────────────── -->
        <div id="panel-abha" class="panel active" role="tabpanel">
            <div id="abha-alert" class="alert alert-error" role="alert"><span id="abha-alert-msg"></span></div>

            <!-- Voice UI -->
            <div class="voice-only" id="v-abha-section">
                <div class="voice-prompt-text" id="v-abha-prompt"><?= htmlspecialchars($t['tts_say_abha']) ?></div>
                <div class="voice-live-text"   id="v-abha-live"></div>
                <button class="voice-mic-btn"  id="v-abha-mic"
                        onclick="voiceCapture('abha_id', 'v-abha')"
                        aria-label="<?= htmlspecialchars($t['voice_idle']) ?>">🎤</button>
                <div class="voice-state-label" id="v-abha-state"><?= htmlspecialchars($t['voice_idle']) ?></div>

                <div id="v-abha-otp-section" style="display:none; width:100%; margin-top:20px;">
                    <div class="voice-prompt-text" id="v-abha-otp-prompt"><?= htmlspecialchars($t['tts_say_otp']) ?></div>
                    <div class="voice-live-text"   id="v-abha-otp-live"></div>
                    <button class="voice-mic-btn"  id="v-abha-otp-mic"
                            onclick="voiceCapture('otp_abha', 'v-abha-otp')"
                            aria-label="<?= htmlspecialchars($t['voice_idle']) ?>">🎤</button>
                    <div class="voice-state-label" id="v-abha-otp-state"><?= htmlspecialchars($t['voice_idle']) ?></div>
                </div>
            </div>

            <!-- Tap UI -->
            <form id="form-abha" class="tap-only" novalidate onsubmit="return false;">
                <div class="form-group">
                    <label for="abha-input">
                        <?= htmlspecialchars($t['abha_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['abha_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <input type="text" id="abha-input" placeholder="<?= htmlspecialchars($t['abha_placeholder']) ?>" maxlength="19" inputmode="numeric" autocomplete="off">
                </div>
                <div id="otp-section-abha" class="otp-section visible" style="display:block;">
                    <div class="form-group">
                        <label for="otp-input-abha">
                            Enter any 6-digit code
                            <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['otp_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                        </label>
                        <input type="text" id="otp-input-abha" placeholder="<?= htmlspecialchars($t['otp_placeholder']) ?>" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    <button id="btn-verify-abha" type="button" class="btn btn-primary" onclick="verifyAbha()">
                        <?= htmlspecialchars($t['btn_verify']) ?> <span class="spinner" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

        <!-- ── New Patient Registration ───────────────────────── -->
        <div id="panel-register" class="panel" role="tabpanel">
            <div id="reg-alert" class="alert alert-error" role="alert"><span id="reg-alert-msg"></span></div>

            <!-- Voice UI -->
            <div class="voice-only" id="v-reg-section">
                <div class="voice-prompt-text" id="v-reg-prompt"><?= htmlspecialchars($t['tts_say_name']) ?></div>
                <div class="voice-live-text"   id="v-reg-live"></div>
                <button class="voice-mic-btn"  id="v-reg-mic"
                        onclick="voiceRegNext()"
                        aria-label="<?= htmlspecialchars($t['voice_idle']) ?>">🎤</button>
                <div class="voice-state-label" id="v-reg-state"><?= htmlspecialchars($t['voice_idle']) ?></div>
            </div>

            <!-- Tap UI -->
            <form id="form-register" class="tap-only" novalidate onsubmit="return false;">
                <div class="form-group">
                    <label for="reg-name">
                        <?= htmlspecialchars($t['name_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['name_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <input type="text" id="reg-name" placeholder="<?= htmlspecialchars($t['name_placeholder']) ?>" autocomplete="name">
                </div>
                <div class="form-group">
                    <label for="reg-age">
                        <?= htmlspecialchars($t['age_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['age_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <input type="number" id="reg-age" placeholder="<?= htmlspecialchars($t['age_placeholder']) ?>" min="1" max="150">
                </div>
                <div class="form-group">
                    <label for="reg-gender">
                        <?= htmlspecialchars($t['gender_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['gender_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <select id="reg-gender">
                        <option value="" disabled selected><?= htmlspecialchars($t['gender_select']) ?></option>
                        <option value="Male"><?= htmlspecialchars($t['gender_male']) ?></option>
                        <option value="Female"><?= htmlspecialchars($t['gender_female']) ?></option>
                        <option value="Other"><?= htmlspecialchars($t['gender_other']) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="reg-phone">
                        <?= htmlspecialchars($t['phone_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['phone_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <input type="tel" id="reg-phone" placeholder="<?= htmlspecialchars($t['phone_placeholder']) ?>" inputmode="tel">
                </div>
                <div class="form-group">
                    <label for="reg-abha">
                        <?= htmlspecialchars($t['abha_optional_label']) ?>
                        <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['abha_optional_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                    </label>
                    <input type="text" id="reg-abha" placeholder="<?= htmlspecialchars($t['abha_placeholder']) ?>" maxlength="19" inputmode="numeric">
                </div>
                <div id="otp-section-reg" class="otp-section visible" style="display:block;">
                    <div class="form-group">
                        <label for="otp-input-reg">
                            Enter any 6-digit code
                            <button type="button" class="btn-speaker" onclick="speak('<?= htmlspecialchars($t['otp_label'], ENT_QUOTES) ?>')" aria-label="Listen">🔊</button>
                        </label>
                        <input type="text" id="otp-input-reg" placeholder="<?= htmlspecialchars($t['otp_placeholder']) ?>" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                    </div>
                    <button id="btn-verify-reg" type="button" class="btn btn-primary" onclick="registerPatient()">
                        <?= htmlspecialchars($t['btn_register']) ?> <span class="spinner" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </div>

    </main>
</div>

<script>
/* ─── Config from PHP ─────────────────────────────────────────────────── */
var API_BASE    = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
var SPEECH_LANG = '<?= htmlspecialchars($speechLang, ENT_QUOTES) ?>';
var STRINGS     = {
    voice_idle:       '<?= htmlspecialchars($t['voice_idle'],        ENT_QUOTES) ?>',
    voice_recording:  '<?= htmlspecialchars($t['voice_recording'],   ENT_QUOTES) ?>',
    voice_processing: '<?= htmlspecialchars($t['voice_processing'],  ENT_QUOTES) ?>',
    voice_done:       '<?= htmlspecialchars($t['voice_done'],        ENT_QUOTES) ?>',
    tts_say_abha:     '<?= htmlspecialchars($t['tts_say_abha'],      ENT_QUOTES) ?>',
    tts_say_otp:      '<?= htmlspecialchars($t['tts_say_otp'],       ENT_QUOTES) ?>',
    tts_say_name:     '<?= htmlspecialchars($t['tts_say_name'],      ENT_QUOTES) ?>',
    tts_say_age:      '<?= htmlspecialchars($t['tts_say_age'],       ENT_QUOTES) ?>',
    tts_say_gender:   '<?= htmlspecialchars($t['tts_say_gender'],    ENT_QUOTES) ?>',
    tts_say_phone:    '<?= htmlspecialchars($t['tts_say_phone'],     ENT_QUOTES) ?>',
    tts_say_reg_otp:  '<?= htmlspecialchars($t['tts_say_reg_otp'],  ENT_QUOTES) ?>',
    tts_got_it:       '<?= htmlspecialchars($t['tts_got_it'],        ENT_QUOTES) ?>',
    tts_otp_sent:     '<?= htmlspecialchars($t['tts_otp_sent'],      ENT_QUOTES) ?>',
    tts_verifying:    '<?= htmlspecialchars($t['tts_verifying'],     ENT_QUOTES) ?>',
    err_invalid_abha: '<?= htmlspecialchars($t['err_invalid_abha'],  ENT_QUOTES) ?>',
    err_fill_all:     '<?= htmlspecialchars($t['err_fill_all'],      ENT_QUOTES) ?>',
    err_otp_6dig:     '<?= htmlspecialchars($t['err_otp_6dig'],      ENT_QUOTES) ?>',
    err_network:      '<?= htmlspecialchars($t['err_network'],       ENT_QUOTES) ?>',
    err_mic_denied:   '<?= htmlspecialchars($t['err_mic_denied'],    ENT_QUOTES) ?>',
    err_mic_notsupported: '<?= htmlspecialchars($t['err_mic_notsupported'], ENT_QUOTES) ?>',
    err_empty_speech: '<?= htmlspecialchars($t['err_empty_speech'],  ENT_QUOTES) ?>',
};

/* ─── TTS ─────────────────────────────────────────────────────────────── */
function speak(text) {
    if (!('speechSynthesis' in window)) return;
    window.speechSynthesis.cancel();
    var utter = new SpeechSynthesisUtterance(text);
    utter.lang = SPEECH_LANG;
    // Try to find a voice for the language
    var voices = window.speechSynthesis.getVoices();
    var match  = voices.find(v => v.lang.startsWith(SPEECH_LANG.split('-')[0]));
    if (match) utter.voice = match;
    window.speechSynthesis.speak(utter);
}

// Voices load async — cache once ready
if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = function() { window.speechSynthesis.getVoices(); };
}

/* ─── Input formatting ─────────────────────────────────────────────────── */
document.getElementById('abha-input').addEventListener('input', formatAbha);
document.getElementById('reg-abha').addEventListener('input', formatAbha);
function formatAbha(e) {
    var d = e.target.value.replace(/\D/g,'').slice(0,14), f='';
    if (d.length > 0)  f  = d.slice(0,2);
    if (d.length > 2)  f += '-' + d.slice(2,6);
    if (d.length > 6)  f += '-' + d.slice(6,10);
    if (d.length > 10) f += '-' + d.slice(10,14);
    e.target.value = f;
}
document.getElementById('otp-input-abha').addEventListener('input', function(e){ e.target.value = e.target.value.replace(/\D/g,'').slice(0,6); });
document.getElementById('otp-input-reg').addEventListener('input',  function(e){ e.target.value = e.target.value.replace(/\D/g,'').slice(0,6); });

/* ─── Tab switching ────────────────────────────────────────────────────── */
var currentTab = 'abha';
function switchTab(which) {
    if (isRecording) return; // block tab change during recording
    currentTab = which;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + which).classList.add('active');
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel-' + which).classList.add('active');
    document.querySelectorAll('.alert').forEach(a => a.classList.remove('visible'));
    if (isVoiceMode()) {
        var prompt = which === 'abha' ? STRINGS.tts_say_abha : STRINGS.tts_say_name;
        speak(prompt);
    }
}

/* ─── Alert helpers ────────────────────────────────────────────────────── */
function showAlert(prefix, msg) {
    var el = document.getElementById(prefix + '-alert');
    document.getElementById(prefix + '-alert-msg').textContent = msg;
    el.classList.add('visible');
}
function hideAlert(prefix) { document.getElementById(prefix + '-alert').classList.remove('visible'); }
function setLoading(btn, on) { btn.disabled = on; on ? btn.classList.add('loading') : btn.classList.remove('loading'); }
function isVoiceMode() { return document.body.classList.contains('mode-voice'); }

/* ─── Tap flow: send OTP ───────────────────────────────────────────────── */
function sendOtp(flow) {
    hideAlert(flow === 'abha' ? 'abha' : 'reg');
    if (flow === 'abha') {
        var val = document.getElementById('abha-input').value.trim();
        if (!/^\d{2}-\d{4}-\d{4}-\d{4}$/.test(val)) return showAlert('abha', STRINGS.err_invalid_abha);
    } else {
        var name   = document.getElementById('reg-name').value.trim();
        var age    = document.getElementById('reg-age').value;
        var gender = document.getElementById('reg-gender').value;
        var phone  = document.getElementById('reg-phone').value.trim();
        if (!name || !age || !gender || !phone) return showAlert('reg', STRINGS.err_fill_all);
    }
    var sectionId = flow === 'abha' ? 'otp-section-abha' : 'otp-section-reg';
    document.getElementById(sectionId).classList.add('visible');
    speak(flow === 'abha' ? STRINGS.tts_say_otp : STRINGS.tts_say_reg_otp);
}

/* ─── Tap flow: verify ABHA ────────────────────────────────────────────── */
function verifyAbha() {
    hideAlert('abha');
    var abha = document.getElementById('abha-input').value.trim();
    if (!/^\d{2}-\d{4}-\d{4}-\d{4}$/.test(abha)) return showAlert('abha', STRINGS.err_invalid_abha);
    var otp  = document.getElementById('otp-input-abha').value.trim();
    if (otp.length !== 6) return showAlert('abha', STRINGS.err_otp_6dig);
    var btn = document.getElementById('btn-verify-abha');
    setLoading(btn, true);
    fetch(API_BASE + '/verify-abha.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ abha_id: abha, otp: otp })
    }).then(r => r.json()).then(data => {
        if (data.success) { 
            var dest = isVoiceMode() ? 'voice-app.php' : 'consent.php';
            window.location.href = dest + '?token=' + encodeURIComponent(data.token); 
        }
        else { setLoading(btn, false); showAlert('abha', data.error || STRINGS.err_network); }
    }).catch(() => { setLoading(btn, false); showAlert('abha', STRINGS.err_network); });
}

/* ─── Tap flow: register new patient ───────────────────────────────────── */
function registerPatient() {
    hideAlert('reg');
    var name   = document.getElementById('reg-name').value.trim();
    var age    = document.getElementById('reg-age').value;
    var gender = document.getElementById('reg-gender').value;
    var phone  = document.getElementById('reg-phone').value.trim();
    var abha   = document.getElementById('reg-abha').value.trim();
    if (!name || !age || !gender || !phone) return showAlert('reg', STRINGS.err_fill_all);
    var otp    = document.getElementById('otp-input-reg').value.trim();
    if (otp.length !== 6) return showAlert('reg', STRINGS.err_otp_6dig);
    var btn = document.getElementById('btn-verify-reg');
    setLoading(btn, true);
    fetch(API_BASE + '/register-patient.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ name, age: parseInt(age), gender, phone, abha_id: abha, otp })
    }).then(r => r.json()).then(data => {
        if (data.success) { 
            var dest = isVoiceMode() ? 'voice-app.php' : 'consent.php';
            window.location.href = dest + '?token=' + encodeURIComponent(data.token); 
        }
        else { setLoading(btn, false); showAlert('reg', data.error || STRINGS.err_network); }
    }).catch(() => { setLoading(btn, false); showAlert('reg', STRINGS.err_network); });
}

/* ═══════════════════════════════════════════════════════════════════════
   REAL ASR — Web Speech API
   ═══════════════════════════════════════════════════════════════════════ */
var isRecording = false;
var activeRecognition = null;
var accumulatedTranscript = '';
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

function startASR(liveEl, stateEl, micBtn, onFinal, onError) {
    if (!SpeechRecognition) {
        onError('Speech recognition not supported. Please use Google Chrome.');
        return;
    }
    // Tap to stop if already recording
    if (isRecording) {
        if (activeRecognition) { try { activeRecognition.stop(); } catch(e) {} }
        return;
    }

    // CRITICAL: cancel any TTS first, or mic will pick up the speaker's voice
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();

    isRecording = true;
    accumulatedTranscript = '';

    activeRecognition = new SpeechRecognition();
    activeRecognition.lang            = SPEECH_LANG;
    activeRecognition.interimResults  = true;
    activeRecognition.maxAlternatives = 1;
    activeRecognition.continuous      = false; // reliable onend fires on silence

    micBtn.classList.add('recording');
    micBtn.disabled = false; // keep tappable so user can stop
    stateEl.textContent = '🔴 Recording... (tap to stop)';
    stateEl.className   = 'voice-state-label active';
    if (liveEl) liveEl.textContent = '🎙 Listening...';

    var handled = false;

    activeRecognition.onstart = function() {
        if (liveEl) liveEl.textContent = '🎙 Listening...';
    };

    activeRecognition.onresult = function(event) {
        var interim = '';
        for (var i = event.resultIndex; i < event.results.length; i++) {
            var t = event.results[i][0].transcript;
            if (event.results[i].isFinal) accumulatedTranscript += t + ' ';
            else interim += t;
        }
        var display = accumulatedTranscript.trim();
        if (liveEl) {
            liveEl.innerHTML = display
                ? '<span>' + escHtml(display) + '</span><span class="voice-interim"> ' + escHtml(interim) + '</span>'
                : '<span class="voice-interim">' + escHtml(interim) + '</span>';
        }
    };

    activeRecognition.onend = function() {
        isRecording = false;
        micBtn.classList.remove('recording');
        if (handled) return;
        handled = true;
        
        stateEl.textContent = STRINGS.voice_processing;
        var finalText = liveEl.textContent.replace(/\s+/g, ' ').trim();
        if (!finalText) {
            stateEl.textContent = STRINGS.voice_idle;
            stateEl.className   = 'voice-state-label';
            liveEl.textContent  = '';
            onError(STRINGS.err_empty_speech + " (Check mic permissions/HTTPS)");
            return;
        }
        stateEl.textContent = STRINGS.voice_done;
        stateEl.className   = 'voice-state-label done';
        onFinal(finalText);
    };

    activeRecognition.onerror = function(event) {
        if (handled) return;
        handled = true;
        isRecording = false;
        micBtn.classList.remove('recording');
        stateEl.textContent = STRINGS.voice_idle;
        stateEl.className   = 'voice-state-label';
        
        if (event.error === 'not-allowed') onError(STRINGS.err_mic_denied);
        else onError(STRINGS.err_empty_speech + " (" + event.error + ")");
    };

    try { activeRecognition.start(); }
    catch(e) { 
        isRecording = false; 
        if (!handled) { handled = true; onError(STRINGS.err_mic_notsupported); }
    }
}

/* ─── Voice: ABHA ID capture ───────────────────────────────────────────── */
function voiceCapture(field, prefix) {
    if (isRecording) {
        if (activeRecognition) activeRecognition.stop();
        return;
    }
    
    var liveEl  = document.getElementById(prefix + '-live');
    var stateEl = document.getElementById(prefix + '-state');
    var micBtn  = document.getElementById(prefix + '-mic');

    speak(STRINGS.voice_recording);

    if (field === 'abha_id') {
        startASR(liveEl, stateEl, micBtn,
            function(text) {
                // Clean up digits from spoken text like "nine nine eight eight..."
                var digits = text.replace(/\D/g,'');
                var formatted = digits.length === 14
                    ? digits.replace(/(\d{2})(\d{4})(\d{4})(\d{4})/,'$1-$2-$3-$4')
                    : text;
                capturedAbhaId = formatted;
                liveEl.textContent = formatted;
                // Show OTP step
                document.getElementById('v-abha-mic').style.display        = 'none';
                document.getElementById('v-abha-prompt').style.display     = 'none';
                document.getElementById('v-abha-otp-section').style.display = 'block';
                speak(STRINGS.tts_otp_sent + ' ' + STRINGS.tts_say_otp);
            },
            function(err) { showAlert('abha', err); }
        );

    } else if (field === 'otp_abha') {
        startASR(liveEl, stateEl, micBtn,
            function(text) {
                var otp = text.replace(/\D/g,'').slice(0,6);
                liveEl.textContent = otp;
                if (!otp || otp.length !== 6) { showAlert('abha', STRINGS.err_otp_6dig); return; }
                speak(STRINGS.tts_verifying);
                // Submit to backend
                fetch(API_BASE + '/verify-abha.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ abha_id: capturedAbhaId, otp: otp })
                }).then(r => r.json()).then(data => {
                    if (data.success) { 
                        var dest = isVoiceMode() ? 'voice-app.php' : 'consent.php';
                        window.location.href = dest + '?token=' + encodeURIComponent(data.token); 
                    }
                    else showAlert('abha', data.error || STRINGS.err_network);
                }).catch(() => showAlert('abha', STRINGS.err_network));
            },
            function(err) { showAlert('abha', err); }
        );
    }
}

var capturedAbhaId = '';

/* ─── Voice: Registration step machine ────────────────────────────────── */
var vRegStep = 0;
var vRegData = { name:'', age:0, gender:'', phone:'' };
var REG_PROMPTS = [
    STRINGS.tts_say_name, STRINGS.tts_say_age,
    STRINGS.tts_say_gender, STRINGS.tts_say_phone, STRINGS.tts_say_reg_otp
];

function voiceRegNext() {
    if (isRecording) {
        if (activeRecognition) activeRecognition.stop();
        return;
    }
    var liveEl  = document.getElementById('v-reg-live');
    var stateEl = document.getElementById('v-reg-state');
    var micBtn  = document.getElementById('v-reg-mic');
    var prompt  = document.getElementById('v-reg-prompt');

    startASR(liveEl, stateEl, micBtn,
        function(text) {
            speak(STRINGS.tts_got_it);
            if (vRegStep === 0) {
                vRegData.name = text;
                liveEl.textContent = text;
                vRegStep++;
                prompt.textContent = STRINGS.tts_say_age;
                setTimeout(() => speak(STRINGS.tts_say_age), 600);
            } else if (vRegStep === 1) {
                vRegData.age = parseInt(text.replace(/\D/g,'')) || 0;
                liveEl.textContent = vRegData.age + ' yrs';
                vRegStep++;
                prompt.textContent = STRINGS.tts_say_gender;
                setTimeout(() => speak(STRINGS.tts_say_gender), 600);
            } else if (vRegStep === 2) {
                // Normalise gender from spoken text
                var g = text.toLowerCase();
                if (g.includes('male') || g.includes('पुरुष')) vRegData.gender = 'Male';
                else if (g.includes('female') || g.includes('स्त्री')) vRegData.gender = 'Female';
                else vRegData.gender = 'Other';
                liveEl.textContent = vRegData.gender;
                vRegStep++;
                prompt.textContent = STRINGS.tts_say_phone;
                setTimeout(() => speak(STRINGS.tts_say_phone), 600);
            } else if (vRegStep === 3) {
                vRegData.phone = text.replace(/\D/g,'');
                liveEl.textContent = vRegData.phone;
                vRegStep++;
                prompt.textContent = STRINGS.tts_say_reg_otp;
                speak(STRINGS.tts_otp_sent + ' ' + STRINGS.tts_say_reg_otp);
            } else if (vRegStep === 4) {
                var otp = text.replace(/\D/g,'').slice(0,6);
                liveEl.textContent = 'OTP: ' + otp;
                if (!otp || otp.length !== 6) { showAlert('reg', STRINGS.err_otp_6dig); return; }
                speak(STRINGS.tts_verifying);
                stateEl.textContent = STRINGS.voice_processing;
                fetch(API_BASE + '/register-patient.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ name: vRegData.name, age: vRegData.age, gender: vRegData.gender, phone: vRegData.phone, abha_id:'', otp: otp })
                }).then(r => r.json()).then(data => {
                    if (data.success) { 
                        var dest = isVoiceMode() ? 'voice-app.php' : 'consent.php';
                        window.location.href = dest + '?token=' + encodeURIComponent(data.token); 
                    }
                    else showAlert('reg', data.error || STRINGS.err_network);
                }).catch(() => showAlert('reg', STRINGS.err_network));
            }
        },
        function(err) { showAlert('reg', err); }
    );
}

/* ─── Utility ───────────────────────────────────────────────────────────── */
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ─── Auto-read on voice mode load ─────────────────────────────────────── */
if (isVoiceMode()) {
    // Small delay to let browser TTS engine initialise
    setTimeout(() => speak(STRINGS.tts_say_abha), 700);
}
</script>
</body>
</html>
