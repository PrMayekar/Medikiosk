<?php

/**
 * AI Intake — Welcome Screen (Step 0)
 *
 * Patient-facing language selection screen. The very first page of the flow.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['language'])) {
    $_SESSION['ai_intake_lang'] = $_POST['language'];
    header('Location: mode-select.php');
    exit;
}

$languages = [
    ['code' => 'en', 'label' => 'English',   'native' => 'English',   'functional' => true],
    ['code' => 'mr', 'label' => 'Marathi',   'native' => 'मराठी',      'functional' => true],
    ['code' => 'hi', 'label' => 'Hindi',     'native' => 'हिंदी',       'functional' => false],
    ['code' => 'ta', 'label' => 'Tamil',     'native' => 'தமிழ்',      'functional' => false],
    ['code' => 'te', 'label' => 'Telugu',    'native' => 'తెలుగు',     'functional' => false],
    ['code' => 'bn', 'label' => 'Bengali',   'native' => 'বাংলা',      'functional' => false],
    ['code' => 'gu', 'label' => 'Gujarati',  'native' => 'ગુજરાતી',    'functional' => false],
    ['code' => 'kn', 'label' => 'Kannada',   'native' => 'ಕನ್ನಡ',      'functional' => false],
    ['code' => 'ml', 'label' => 'Malayalam', 'native' => 'മലയാളം',    'functional' => false],
    ['code' => 'pa', 'label' => 'Punjabi',   'native' => 'ਪੰਜਾਬੀ',     'functional' => false],
];

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome — AI Intake Kiosk</title>
    <meta name="description" content="AI-assisted patient intake kiosk. Select your language to begin.">
    <style>
        :root {
            --brand-gradient: linear-gradient(135deg, #1a6fa0 0%, #2c9cd4 50%, #00d4ff 100%);
            --text-primary: #1a2a3a;
            --text-muted: #8899a6;
            --surface: rgba(255,255,255,0.96);
            --transition: 0.2s cubic-bezier(0.4,0,0.2,1);
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body {
            min-height:100%;
            font-family:'Segoe UI',system-ui,-apple-system,sans-serif;
            background:var(--brand-gradient); color:var(--text-primary);
        }
        body::before {
            content:''; position:fixed; width:600px; height:600px; border-radius:50%;
            top:-150px; left:-150px; background:#00d4ff; opacity:.15;
            animation:blob 20s ease-in-out infinite; pointer-events:none;
        }
        @keyframes blob {
            0%,100% { transform:scale(1) translate(0,0); }
            50%     { transform:scale(1.1) translate(40px,-30px); }
        }
        .page-wrap {
            min-height:100vh; display:flex; flex-direction:column;
            align-items:center; justify-content:center; padding:40px 16px;
        }
        .container {
            width:100%; max-width:600px;
            background:var(--surface); backdrop-filter:blur(20px);
            border:1px solid rgba(255,255,255,0.8); border-radius:24px;
            box-shadow:0 10px 50px rgba(0,0,0,0.15); padding:48px 32px; text-align:center;
        }
        .icon-wrap {
            font-size:3.5rem; margin-bottom:12px; display:inline-block;
            animation:float 4s ease-in-out infinite;
        }
        @keyframes float {
            0%,100% { transform:translateY(0); }
            50%     { transform:translateY(-8px); }
        }
        h1 { font-size:2rem; font-weight:700; margin-bottom:8px; }
        .subheading { font-size:1rem; color:var(--text-muted); margin-bottom:32px; }
        .lang-grid {
            display:grid; grid-template-columns:repeat(2,1fr); gap:14px;
        }
        .lang-btn {
            background:#fff; border:2px solid #d8e8f0; border-radius:16px;
            padding:20px 16px; cursor:pointer; text-align:center;
            transition:var(--transition); display:flex; flex-direction:column;
            align-items:center; justify-content:center; outline:none; position:relative;
        }
        .lang-btn:hover { border-color:#2c9cd4; transform:translateY(-3px); box-shadow:0 8px 20px rgba(44,156,212,0.18); }
        .lang-btn:active { transform:scale(0.97); }
        .lang-btn.functional::after {
            content:'✓'; position:absolute; top:8px; right:10px;
            font-size:.7rem; color:#2ea055; font-weight:700;
        }
        .native-text { font-size:1.5rem; font-weight:700; color:#1a6fa0; margin-bottom:4px; }
        .label-text  { font-size:.8rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:.05em; }
        #toast {
            position:fixed; bottom:30px; left:50%;
            transform:translateX(-50%) translateY(100px);
            background:#323232; color:#fff; padding:12px 24px; border-radius:50px;
            font-size:.9rem; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,0.15);
            opacity:0; transition:all .3s cubic-bezier(.4,0,.2,1); pointer-events:none; z-index:1000;
        }
        #toast.show { transform:translateX(-50%) translateY(0); opacity:1; }
        @media(max-width:480px) {
            .container { padding:32px 24px; }
            .lang-grid { grid-template-columns:1fr; gap:10px; }
            .lang-btn  { padding:14px 12px; flex-direction:row; justify-content:space-between; }
            .native-text { font-size:1.2rem; margin-bottom:0; }
        }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="container" role="main">
        <div class="icon-wrap" aria-hidden="true">🙏</div>
        <h1>Namaste • नमस्कार</h1>
        <p class="subheading">Select your language / आपली भाषा निवडा</p>

        <form id="lang-form" method="POST" action="welcome.php">
            <input type="hidden" name="language" id="lang-input" value="">
            <div class="lang-grid" role="listbox" aria-label="Language selection">
                <?php foreach ($languages as $l): ?>
                <button type="button"
                        class="lang-btn <?= $l['functional'] ? 'functional' : '' ?>"
                        role="option"
                        aria-label="<?= htmlspecialchars($l['label']) ?>"
                        onclick="selectLanguage('<?= $l['code'] ?>', <?= $l['functional'] ? 'true' : 'false' ?>)">
                    <span class="native-text"><?= htmlspecialchars($l['native']) ?></span>
                    <span class="label-text"><?= htmlspecialchars($l['label']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<div id="toast" role="status" aria-live="polite">Language coming soon. Continuing in English.</div>

<script>
function selectLanguage(code, isFunctional) {
    var form  = document.getElementById('lang-form');
    document.getElementById('lang-input').value = code;

    if (!isFunctional) {
        var toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(function() { form.submit(); }, 2000);
    } else {
        form.submit();
    }
}
</script>
</body>
</html>
