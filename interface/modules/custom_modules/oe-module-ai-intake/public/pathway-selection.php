<?php

/**
 * AI Intake — Treatment Pathway Selection (Step 2)
 *
 * Patient picks Allopathic or Ayurvedic consultation.
 * Enforces that consent step was completed first.
 * On selection → POST api/save-pathway.php → redirect to interview.php (Step 3).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once(__DIR__ . '/../lib/i18n.php');

// Validate token.
$ks    = KioskSession::requireValid('login.php');
$token = $ks->token();
$lang  = $ks->language();
$t     = i18nStrings($lang);

// Enforce step order — must have completed consent first.
$ks->requireStep('consent');

$apiBase = ($GLOBALS['webroot'] ?? '') . '/interface/modules/custom_modules/oe-module-ai-intake/api';

?><!DOCTYPE html><html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['pathway_title']) ?> — AI Intake</title>
    <meta name="description" content="Select your preferred treatment pathway: Allopathic or Ayurvedic.">
    <style>
        :root {
            --brand-primary:  #2c9cd4;
            --brand-accent:   #00d4ff;
            --brand-dark:     #1a6fa0;
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --surface-glass:  rgba(255,255,255,.93);
            --text-primary:   #1a2a3a;
            --text-secondary: #4a6070;
            --text-muted:     #8899a6;
            --border:         #d8e8f0;
            --radius-card:    20px;
            --radius-input:   12px;
            --shadow-card:    0 8px 40px rgba(44,156,212,.18),0 2px 8px rgba(0,0,0,.06);
            --transition:     .22s cubic-bezier(.4,0,.2,1);
            --font:           'Segoe UI', system-ui,-apple-system,sans-serif;
        }
        *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            min-height: 100%; font-family: var(--font);
            background: var(--brand-gradient); color: var(--text-primary);
        }
        body::before {
            content: ''; position: fixed;
            width: 500px; height: 500px; border-radius: 50%;
            top: -120px; left: -120px; background: #00d4ff; opacity: .1;
            animation: blob 18s ease-in-out infinite; pointer-events: none;
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

        /* Progress */
        .progress { width: 100%; max-width: 600px; margin-bottom: 28px; }
        .progress-label {
            display: flex; justify-content: space-between;
            color: rgba(255,255,255,.8); font-size: .78rem;
            font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px;
        }
        .progress-track { height: 4px; background: rgba(255,255,255,.25); border-radius: 2px; overflow: hidden; }
        .progress-fill  { height: 100%; width: 66%; background: #fff; border-radius: 2px; transition: width .6s; }

        /* Card shell */
        .card {
            background: var(--surface-glass);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,.6);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
            width: 100%; max-width: 600px;
            padding: 36px;
        }

        .card-title { font-size: 1.25rem; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; }
        .card-sub   { font-size: .9rem; color: var(--text-muted); margin-bottom: 32px; }

        /* Pathway cards grid */
        .pathways {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 28px;
        }
        @media (max-width: 480px) { .pathways { grid-template-columns: 1fr; } }

        .pathway-card {
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 28px 20px;
            cursor: pointer;
            text-align: center;
            background: #fff;
            transition: border-color var(--transition),
                        transform var(--transition),
                        box-shadow var(--transition),
                        background var(--transition);
            user-select: none;
            position: relative;
            overflow: hidden;
        }
        .pathway-card::before {
            content: '';
            position: absolute; inset: 0;
            background: var(--brand-gradient);
            opacity: 0;
            transition: opacity var(--transition);
        }
        .pathway-card:hover {
            border-color: var(--brand-primary);
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(44,156,212,.2);
        }
        .pathway-card:hover::before { opacity: .04; }
        .pathway-card:active { transform: translateY(-1px); }

        .pathway-card.selected {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(44,156,212,.2), 0 12px 32px rgba(44,156,212,.2);
        }
        .pathway-card.selected::before { opacity: .06; }

        .pathway-card .check {
            position: absolute; top: 12px; right: 12px;
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--brand-primary); color: #fff;
            font-size: .8rem; display: none;
            align-items: center; justify-content: center;
        }
        .pathway-card.selected .check { display: flex; }

        .pathway-icon {
            font-size: 3rem; margin-bottom: 14px;
            position: relative; z-index: 1;
            display: block;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.1));
        }
        .pathway-name {
            font-size: 1.05rem; font-weight: 700;
            color: var(--text-primary); margin-bottom: 8px;
            position: relative; z-index: 1;
        }
        .pathway-desc {
            font-size: .8rem; color: var(--text-muted);
            line-height: 1.5; position: relative; z-index: 1;
        }
        .pathway-tag {
            display: inline-block; margin-top: 10px;
            border-radius: 6px; padding: 3px 10px;
            font-size: .72rem; font-weight: 600;
            position: relative; z-index: 1;
        }

        /* Allopathic = blue tint tag; Ayurvedic = green tint tag */
        .tag-allo { background: #e3f2fd; color: #1565c0; }
        .tag-ayu  { background: #e8f5e9; color: #2e7d32; }

        /* Alert */
        .alert { border-radius: 10px; padding: 12px 16px; font-size: .88rem;
                 margin-bottom: 18px; display: none; align-items: center; gap: 10px; }
        .alert.visible { display: flex; }
        .alert-error { background: #fdecea; color: #b71c1c; border: 1px solid #f5c6c6; }

        /* Confirm button */
        .btn {
            width: 100%; padding: 15px; border: none;
            border-radius: var(--radius-input); font-size: 1rem; font-weight: 700;
            cursor: pointer; position: relative; overflow: hidden;
            transition: opacity var(--transition), transform var(--transition), box-shadow var(--transition);
        }
        .btn:active:not(:disabled) { transform: scale(.98); }
        .btn:disabled { opacity: .4; cursor: not-allowed; }
        .btn-primary {
            background: var(--brand-gradient); color: #fff;
            box-shadow: 0 4px 16px rgba(44,156,212,.35);
        }
        .btn-primary:not(:disabled):hover { box-shadow: 0 6px 24px rgba(44,156,212,.5); }
        .btn .spinner {
            display: none; width: 18px; height: 18px;
            border: 3px solid rgba(255,255,255,.35); border-top-color: #fff;
            border-radius: 50%; animation: spin .7s linear infinite;
            position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
        }
        .btn.loading .spinner { display: block; }
        @keyframes spin { to { transform: translateY(-50%) rotate(360deg); } }

        .back-link {
            display: block; text-align: center; margin-top: 14px;
            font-size: .83rem; color: var(--brand-primary); text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Progress -->
    <div class="progress" role="progressbar" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100" aria-label="Step 2 of 3">
        <div class="progress-label">
            <span>Step 2 of 3 — Treatment Pathway</span>
            <span>66%</span>
        </div>
        <div class="progress-track"><div class="progress-fill"></div></div>
    </div>

    <main class="card" role="main">

        <h1 class="card-title"><?= htmlspecialchars($t['pathway_heading']) ?></h1>

        <!-- Error alert -->
        <div id="alert" class="alert alert-error" role="alert">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            <span id="alert-msg"></span>
        </div>

        <!-- Two pathway cards -->
        <div class="pathways" role="radiogroup" aria-label="Consultation type">

            <div id="card-allo" class="pathway-card" role="radio" aria-checked="false"
                 tabindex="0" onclick="selectPathway('allopathic')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')selectPathway('allopathic')">
                <div class="check" aria-hidden="true">✓</div>
                <span class="pathway-icon" aria-hidden="true">🏥</span>
                <div class="pathway-name"><?= htmlspecialchars($t['pathway_allo_title']) ?></div>
                <div class="pathway-desc"><?= htmlspecialchars($t['pathway_allo_sub']) ?></div>
            </div>

            <div id="card-ayu" class="pathway-card" role="radio" aria-checked="false"
                 tabindex="0" onclick="selectPathway('ayurvedic')"
                 onkeydown="if(event.key==='Enter'||event.key===' ')selectPathway('ayurvedic')">
                <div class="check" aria-hidden="true">✓</div>
                <span class="pathway-icon" aria-hidden="true">🌿</span>
                <div class="pathway-name"><?= htmlspecialchars($t['pathway_ayu_title']) ?></div>
                <div class="pathway-desc"><?= htmlspecialchars($t['pathway_ayu_sub']) ?></div>
            </div>

        </div>

        <!-- Confirm button -->
        <button id="btn-confirm" class="btn btn-primary" type="button"
                disabled onclick="confirmPathway()">
            <?= htmlspecialchars($lang === 'mr' ? 'पुष्टी करा व पुढे जा' : 'Confirm & Continue') ?>
            <span class="spinner" aria-hidden="true"></span>
        </button>

        <a class="back-link" href="mode-select.php?token=<?= urlencode($token) ?>"><?= htmlspecialchars($t['btn_back']) ?></a>

    </main>
</div>

<script>
var TOKEN      = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var API_BASE   = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
var selected   = null;

function selectPathway(pathway) {
    selected = pathway;

    // Update card states.
    ['allo', 'ayu'].forEach(function(k) {
        var card = document.getElementById('card-' + k);
        var isSelected = (k === pathway.slice(0, 3));
        card.classList.toggle('selected', isSelected);
        card.setAttribute('aria-checked', isSelected ? 'true' : 'false');
    });

    document.getElementById('btn-confirm').disabled = false;
    clearAlert();
}

function confirmPathway() {
    if (!selected) { showAlert('Please select a consultation type first.'); return; }
    clearAlert();

    var btn = document.getElementById('btn-confirm');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch(API_BASE + '/save-pathway.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ token: TOKEN, pathway: selected }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.classList.remove('loading');
        if (d.success) {
            window.location.href = 'interview.php?token=' + encodeURIComponent(TOKEN);
        } else {
            btn.disabled = false;
            showAlert(d.error || '<?= htmlspecialchars($t['err_network'], ENT_QUOTES) ?>');
        }
    })
    .catch(function() {
        btn.classList.remove('loading');
        btn.disabled = false;
        showAlert('<?= htmlspecialchars($t['err_network'], ENT_QUOTES) ?>');
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
