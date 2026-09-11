<?php

/**
 * AI Intake — Mode Selection (Tap vs Voice)
 *
 * Patient-facing screen after language selection.
 * Sets interaction mode for the entire kiosk session.
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

$lang   = $_SESSION['ai_intake_lang'] ?? 'en';
$t      = i18nStrings($lang);
$token  = $_GET['token'] ?? ($_POST['token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mode'])) {
    $mode = $_POST['mode'];
    if (in_array($mode, ['tap', 'voice'], true)) {
        $_SESSION['ai_intake_mode'] = $mode;
        if (!empty($token) && isset($_SESSION['ai_intake_sessions'][$token])) {
            $_SESSION['ai_intake_sessions'][$token]['interaction_mode'] = $mode;
            header('Location: login.php?token=' . urlencode($token));
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

$speechLang = speechLangCode($lang);

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['mode_title']) ?> — AI Intake</title>
    <style>
        :root {
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --text-primary:#1a2a3a; --text-muted:#8899a6;
            --surface:rgba(255,255,255,0.96); --transition:0.2s cubic-bezier(0.4,0,0.2,1);
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { height:100%; font-family:'Segoe UI',system-ui,-apple-system,sans-serif; background:var(--brand-gradient); color:var(--text-primary); }
        body::before {
            content:''; position:fixed; width:600px; height:600px; border-radius:50%;
            top:-150px; left:-150px; background:#00d4ff; opacity:.15;
            animation:blob 20s ease-in-out infinite; pointer-events:none;
        }
        @keyframes blob { 0%,100%{transform:scale(1) translate(0,0)} 50%{transform:scale(1.1) translate(40px,-30px)} }

        .page-wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px 16px; }
        .container { width:100%; max-width:720px; background:var(--surface); backdrop-filter:blur(20px); border:1px solid rgba(255,255,255,0.8); border-radius:24px; box-shadow:0 10px 50px rgba(0,0,0,0.15); padding:52px 44px; text-align:center; }
        h1 { font-size:2rem; font-weight:700; margin-bottom:40px; }
        .mode-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .mode-card {
            background:#fff; border:3px solid #d8e8f0; border-radius:20px;
            padding:44px 24px; cursor:pointer; text-align:center;
            transition:var(--transition); display:flex; flex-direction:column;
            align-items:center; justify-content:center; outline:none;
        }
        .mode-card:hover,.mode-card:focus { border-color:#2c9cd4; transform:translateY(-5px); box-shadow:0 14px 28px rgba(44,156,212,0.18); }
        .mode-card:active { transform:scale(0.97); }
        .icon-circle {
            width:96px; height:96px; background:#f4fafd; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:2.8rem; color:#1a6fa0; margin-bottom:20px; transition:var(--transition);
        }
        .mode-card:hover .icon-circle { background:#2c9cd4; color:#fff; box-shadow:0 8px 20px rgba(44,156,212,0.3); }
        .mode-title { font-size:1.4rem; font-weight:700; margin-bottom:6px; }
        .mode-sub   { font-size:.88rem; color:var(--text-muted); }
        @media(max-width:600px) {
            .mode-grid { grid-template-columns:1fr; }
            .container { padding:32px 24px; }
            .mode-card { padding:32px 20px; flex-direction:row; text-align:left; justify-content:flex-start; gap:24px; }
            .icon-circle { width:64px; height:64px; font-size:2rem; margin-bottom:0; flex-shrink:0; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="container" role="main">
        <h1><?= htmlspecialchars($t['mode_title']) ?></h1>

        <form id="mode-form" method="POST" action="mode-select.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <input type="hidden" name="mode"  id="mode-input" value="">

            <div class="mode-grid">
                <button type="button" class="mode-card" id="card-tap" onclick="selectMode('tap')" aria-label="<?= htmlspecialchars($t['mode_tap_label']) ?>">
                    <div class="icon-circle" aria-hidden="true">👆</div>
                    <div>
                        <div class="mode-title"><?= htmlspecialchars($t['mode_tap_label']) ?></div>
                        <div class="mode-sub"><?= htmlspecialchars($t['mode_tap_sub']) ?></div>
                    </div>
                </button>

                <button type="button" class="mode-card" id="card-voice" onclick="selectMode('voice')" aria-label="<?= htmlspecialchars($t['mode_voice_label']) ?>">
                    <div class="icon-circle" aria-hidden="true">🎤</div>
                    <div>
                        <div class="mode-title"><?= htmlspecialchars($t['mode_voice_label']) ?></div>
                        <div class="mode-sub"><?= htmlspecialchars($t['mode_voice_sub']) ?></div>
                    </div>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
var SPEECH_LANG = '<?= htmlspecialchars($speechLang, ENT_QUOTES) ?>';

function selectMode(mode) {
    if (mode === 'voice') {
        // Pre-check mic permission so the user isn't surprised later
        navigator.mediaDevices && navigator.mediaDevices.getUserMedia({ audio: true })
            .then(function(stream) {
                stream.getTracks().forEach(t => t.stop());
                submitMode(mode);
            })
            .catch(function() {
                // Permission denied / not supported — still allow tap mode selection
                submitMode(mode);
            });
    } else {
        submitMode(mode);
    }
}

function submitMode(mode) {
    document.getElementById('mode-input').value = mode;
    document.getElementById('mode-form').submit();
}
</script>
</body>
</html>
