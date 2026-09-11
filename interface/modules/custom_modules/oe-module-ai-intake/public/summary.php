<?php

/**
 * AI Intake — Patient Summary Screen (Step 4)
 *
 * Displays the AI-generated (template-based) clinical summary, runs the
 * red-flag heuristic, and lets the patient confirm before POSTing to
 * api/save-summary.php (which writes the real OpenEMR encounter + note).
 *
 * On success, shows a "Thank you" confirmation screen.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once(__DIR__ . '/../lib/i18n.php');

$ks    = KioskSession::requireValid('login.php');
$token = $ks->token();
$ks->requireStep('interview');
$lang      = $ks->language();
$t         = i18nStrings($lang);
$speechLang = speechLangCode($lang);

$pathway   = $ks->pathway() ?? 'allopathic';
$apiBase   = ($GLOBALS['webroot'] ?? '')
    . '/interface/modules/custom_modules/oe-module-ai-intake/api';

// Resolve cached summary from session if already submitted (idempotent).
$cachedSummary     = $ks->intakeSessionData('summary_text');
$cachedRedFlag     = (bool) $ks->intakeSessionData('red_flag');
$alreadySubmitted  = !empty($ks->intakeSessionData('openemr_form_note_id'));

// ── Generate preview summary from session data (client-side confirm first) ──
// We call the same generation logic inline for display; save-summary.php
// re-generates it server-side (single source of truth).
$sessionId     = $ks->intakeSessionId() ?? 0;
$intakeDbRow   = $sessionId ? sqlQuery(
    "SELECT interview_data, document_data FROM ai_intake_session WHERE id = ? LIMIT 1",
    [$sessionId]
) : [];

$patientId = $ks->pid();
$patientRow = sqlQuery("SELECT fname, lname FROM patient_data WHERE pid = ?", [$patientId]);
$patientName = $patientRow ? trim($patientRow['fname'] . ' ' . $patientRow['lname']) : 'Patient';

$interviewData = json_decode($intakeDbRow['interview_data'] ?? '[]', true) ?: [];
$documentData  = json_decode($intakeDbRow['document_data']  ?? 'null', true);

/**
 * Build structured sections for display — returns array of ['title'=>, 'content'=>]
 * The patient sees only the CURRENT visit summary (shorter, cleaner).
 * The full longitudinal note is built server-side in save-summary.php.
 */
function buildPreviewSections(string $pathway, array $interview, ?array $docs): array
{
    $find = function(string ...$hints) use ($interview): string {
        foreach ($interview as $qa) {
            $q = strtolower($qa['question'] ?? '');
            foreach ($hints as $hint) {
                if (str_contains($q, strtolower($hint))) return trim($qa['answer'] ?? '');
            }
        }
        return 'Not specified';
    };

    $sections = [];

    $cc = $find('main health concern', 'chief complaint', 'same concern', 'new concern');
    $sections[] = ['title' => '🔍 Chief Complaint', 'content' => $cc ?: 'Not specified'];

    if (strtolower($pathway) === 'ayurvedic') {
        $dur  = $find('how long have you had', 'how long', 'season', 'किती दिवसांपासून');
        $sev  = $find('affecting your daily', 'scale of 1');
        $body = $find('body build', 'body type', 'prakriti', 'शरीरयष्टी');
        $dig  = $find('digestion', 'appetite', 'भूक', 'पचन');
        $sleep  = $find('sleep', 'energy level', 'झोप');
        $stress = $find('stress', 'ताण');
        $diet   = $find('diet', 'food', 'आहार');
        $rem    = $find('ayurvedic', 'panchakarma', 'herbal', 'पंचकर्म', 'आयुर्वेदिक');
        $past   = $find('past illness', 'past medical', 'family health', 'मागील आजार');

        $hpi  = "Duration/Season: {$dur}. Severity impact: {$sev}/10.";
        $sections[] = ['title' => '📋 History of Present Illness', 'content' => $hpi];
        $sections[] = ['title' => '🌿 Prakriti Assessment', 'content' =>
            "Body build & skin: {$body}.\nDigestion & appetite: {$dig}.\nSleep & energy: {$sleep}.\nStress response: {$stress}.\nDiet habits: {$diet}.\nPrior Ayurvedic treatment: {$rem}."];
        $sections[] = ['title' => '📂 Past Medical History', 'content' => $past ?: 'None known'];
    } else {
        $dur  = $find('how long', 'duration', 'किती दिवसांपासून');
        $sev  = $find('scale of 1', 'severity');
        $agg  = $find('makes it worse', 'aggravating', 'वाढवतात');
        $rel  = $find('makes it better', 'relieving', 'आराम');
        $past = $find('past medical', 'past illness', 'मागील आजार');
        $meds = $find('medication', 'allerg', 'औषधे');

        // Also catch follow-up specific fields
        $symChange = $find('symptoms changed');
        $txResp    = $find('treatment', 'advice from your last');
        $newSx     = $find('new symptom');
        $addl      = $find('anything else', 'additional');

        $hpi = "Duration: {$dur}. Severity: {$sev}/10.\nAggravating: {$agg}. Relieving: {$rel}.";
        if ($symChange && $symChange !== 'Not specified') $hpi .= "\nSymptom change since last visit: {$symChange}.";
        if ($txResp   && $txResp   !== 'Not specified') $hpi .= "\nTreatment response: {$txResp}.";
        if ($newSx    && $newSx    !== 'Not specified') $hpi .= "\nNew symptoms: {$newSx}.";
        if ($addl     && $addl     !== 'Not specified') $hpi .= "\nAdditional notes: {$addl}.";

        $sections[] = ['title' => '📋 History of Present Illness', 'content' => $hpi];
        $sections[] = ['title' => '📂 Past Medical History', 'content' => $past ?: 'None known'];
        $sections[] = ['title' => '💊 Medications & Allergies', 'content' => $meds ?: 'None reported'];
    }

    // Documents
    if ($docs && is_array($docs)) {
        if (isset($docs['filename'])) $docs = [$docs];
        $docLines = '';
        foreach ($docs as $doc) {
            $type = $doc['document_type'] ?? 'Unknown';
            $file = $doc['filename'] ?? 'document';
            $docLines .= "{$type}: {$file}\n";
            if (!empty($doc['ocr_text'])) {
                $preview = mb_substr(trim($doc['ocr_text']), 0, 200);
                $docLines .= "  Key text: {$preview}\n";
            }
        }
        $sections[] = ['title' => '📄 Documents Uploaded', 'content' => trim($docLines)];
    }

    return $sections;
}


function buildRedFlagCheck(array $interview): bool
{
    $text = strtolower(implode(' ', array_column($interview, 'answer')));
    if (str_contains($text, 'chest pain') && str_contains($text, 'breath')) return true;
    if (str_contains($text, 'unconscious') || str_contains($text, 'fainted')) return true;
    if (str_contains($text, 'blood') && (str_contains($text, 'vomit') || str_contains($text, 'stool'))) return true;
    if ((str_contains($text, 'severe headache') || str_contains($text, 'worst headache'))
        && (str_contains($text, 'vision') || str_contains($text, 'speech') || str_contains($text, 'weakness'))) return true;
    return false;
}

$previewSections = buildPreviewSections($pathway, $interviewData, $documentData);
$isRedFlag       = $cachedRedFlag ?: buildRedFlagCheck($interviewData);
$pathwayIcon     = $pathway === 'ayurvedic' ? '🌿' : '🏥';
$pathwayLabel    = ucfirst($pathway);


?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Summary — AI Intake Kiosk</title>
    <meta name="description" content="Review your AI-generated intake summary before confirming.">
    <style>
        :root {
            --brand-primary:  #2c9cd4;
            --brand-dark:     #1a6fa0;
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --surface-glass:  rgba(255,255,255,.93);
            --text-primary:   #1a2a3a;
            --text-secondary: #4a6070;
            --text-muted:     #8899a6;
            --border:         #d8e8f0;
            --radius-card:    20px;
            --radius-inner:   12px;
            --shadow-card:    0 8px 40px rgba(44,156,212,.18),0 2px 8px rgba(0,0,0,.06);
            --transition:     .22s cubic-bezier(.4,0,.2,1);
            --font:           'Segoe UI',system-ui,-apple-system,sans-serif;
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { min-height:100%; font-family:var(--font); background:var(--brand-gradient); color:var(--text-primary); }
        body::before {
            content:''; position:fixed; width:500px; height:500px; border-radius:50%;
            top:-120px; left:-120px; background:#00d4ff; opacity:.1;
            animation:blob 18s ease-in-out infinite; pointer-events:none;
        }
        @keyframes blob { 0%,100%{transform:scale(1) translate(0,0)} 50%{transform:scale(1.1) translate(30px,-20px)} }

        .page-wrap { min-height:100vh; display:flex; flex-direction:column;
                     align-items:center; padding:28px 16px 48px; }

        /* Progress */
        .topbar { width:100%; max-width:680px; margin-bottom:20px; }
        .step-label { color:rgba(255,255,255,.8); font-size:.78rem; font-weight:600;
                      text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; display:block; }
        .progress-track { height:5px; background:rgba(255,255,255,.25); border-radius:3px; overflow:hidden; }
        .progress-fill  { height:100%; width:100%; background:#fff; border-radius:3px; }

        /* Card */
        .card {
            width:100%; max-width:680px;
            background:var(--surface-glass); backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,.6);
            border-radius:var(--radius-card); box-shadow:var(--shadow-card);
        }
        .card-header {
            padding:28px 32px; border-bottom:1px solid var(--border);
            display:flex; align-items:center; gap:14px;
        }
        .header-icon { width:48px; height:48px; border-radius:12px; flex-shrink:0;
                       background:var(--brand-gradient); display:flex;
                       align-items:center; justify-content:center; font-size:1.3rem; }
        .header-text h1 { font-size:1.2rem; font-weight:700; color:var(--text-primary); margin-bottom:4px; }
        .header-text p  { font-size:.85rem; color:var(--text-muted); }
        .card-body { padding:28px 32px; }

        /* Red-flag banner */
        .redflag-banner {
            display:flex; align-items:center; gap:12px;
            background:#ffebee; border:1.5px solid #ef9a9a; border-radius:12px;
            padding:14px 18px; margin-bottom:20px;
            animation:pulseRed 2s ease-in-out infinite;
        }
        @keyframes pulseRed { 0%,100%{box-shadow:0 0 0 0 rgba(198,40,40,.0)} 50%{box-shadow:0 0 0 6px rgba(198,40,40,.12)} }
        .redflag-icon { font-size:1.5rem; flex-shrink:0; }
        .redflag-text h2 { font-size:.95rem; font-weight:700; color:#c62828; margin-bottom:3px; }
        .redflag-text p  { font-size:.82rem; color:#c62828; opacity:.85; }

        /* Pathway pill */
        .pathway-pill {
            display:inline-flex; align-items:center; gap:6px;
            border-radius:50px; padding:5px 14px;
            font-size:.8rem; font-weight:700; margin-bottom:20px;
        }
        .pill-allo { background:#e3f2fd; color:#1565c0; }
        .pill-ayu  { background:#e8f5e9; color:#2e7d32; }

        /* Summary sections */
        .summary-sections { display:flex; flex-direction:column; gap:0; margin-bottom:24px; }
        .summary-section {
            border:none; border-radius:0; margin-bottom:20px;
            border-bottom:1px solid var(--border); padding-bottom:16px;
        }
        .summary-section:last-child { border-bottom:none; margin-bottom:0; padding-bottom:0; }
        
        .section-title {
            background:transparent; padding:0 0 0 10px; font-size:.85rem; font-weight:700;
            color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em;
            border-bottom:none; border-left:3px solid var(--brand-primary); margin-bottom:10px;
        }
        .section-content {
            padding:0 0 0 13px; font-size:.92rem; color:var(--text-primary);
            line-height:1.6; white-space:pre-wrap; 
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        }
        .ayurvedic-note { background:#e8f5e9; border-radius:10px; padding:10px 16px;
                          font-size:.8rem; color:#2e7d32; margin-bottom:16px; }



        /* Error alert */
        .alert { border-radius:10px; padding:12px 16px; font-size:.87rem;
                 display:none; align-items:center; gap:8px; margin-bottom:16px; }
        .alert.visible { display:flex; }
        .alert-error { background:#fdecea; color:#b71c1c; border:1px solid #f5c6c6; }

        /* Buttons */
        .btn-row { display:flex; gap:12px; }
        .btn {
            flex:1; padding:15px; border:none; border-radius:var(--radius-inner);
            font-size:.95rem; font-weight:700; cursor:pointer; position:relative; overflow:hidden;
            transition:opacity var(--transition), transform var(--transition), box-shadow var(--transition);
        }
        .btn:active:not(:disabled) { transform:scale(.98); }
        .btn:disabled { opacity:.4; cursor:not-allowed; }
        .btn-primary {
            background:var(--brand-gradient); color:#fff;
            box-shadow:0 4px 16px rgba(44,156,212,.35);
        }
        .btn-primary:not(:disabled):hover { box-shadow:0 6px 24px rgba(44,156,212,.5); }
        .btn .spinner {
            display:none; width:16px; height:16px;
            border:2.5px solid rgba(255,255,255,.35); border-top-color:#fff;
            border-radius:50%; animation:spin .7s linear infinite;
            position:absolute; right:16px; top:50%; transform:translateY(-50%);
        }
        .btn.loading .spinner { display:block; }
        @keyframes spin { to { transform:translateY(-50%) rotate(360deg); } }

        .back-link { display:block; text-align:center; margin-top:12px;
                     font-size:.82rem; color:var(--brand-primary); text-decoration:none; }
        .back-link:hover { text-decoration:underline; }

        /* ── Thank-you screen ───────────────────────────────────────────────── */
        #thankyou-screen {
            display:none; text-align:center; padding:48px 36px;
        }
        #thankyou-screen.visible { display:block; }
        .ty-icon { font-size:4rem; margin-bottom:20px; }
        .ty-title { font-size:1.35rem; font-weight:700; color:var(--text-primary); margin-bottom:12px; }
        .ty-sub   { font-size:.9rem; color:var(--text-muted); line-height:1.65; }
        .ty-enc   { display:inline-block; margin-top:18px; background:#e8f5e9; color:#2e7d32;
                    border-radius:8px; padding:8px 18px; font-size:.85rem; font-weight:700; }
        .ty-back  { display:block; text-align:center; margin-top:24px; font-size:.83rem;
                    color:var(--brand-primary); text-decoration:none; font-weight:600; }
        .ty-back:hover { text-decoration:underline; }

        @media(max-width:480px) { .card-header,.card-body { padding-left:20px; padding-right:20px; } }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Progress -->
    <div class="topbar">
        <span class="step-label">Final Step — Review &amp; Confirm Your Summary</span>
        <div class="progress-track"><div class="progress-fill"></div></div>
    </div>

    <div class="card" role="main">

        <!-- Main summary view -->
        <div id="summary-view">

            <div class="card-header" style="flex-direction:column; align-items:flex-start; gap:10px;">
                <div style="display:flex; justify-content:space-between; width:100%; align-items:center;">
                    <div class="header-text">
                        <h1 style="font-size:1.4rem;">Clinical Note</h1>
                        <p style="font-weight:600; color:var(--text-primary);"><?= htmlspecialchars($patientName) ?> &bull; <?= date('d M Y') ?></p>
                    </div>
                    <!-- Pathway pill -->
                    <span class="pathway-pill <?= $pathway === 'ayurvedic' ? 'pill-ayu' : 'pill-allo' ?>" style="margin-bottom:0;">
                        <?= htmlspecialchars($pathwayIcon) ?>
                        <?= htmlspecialchars($pathwayLabel) ?>
                    </span>
                </div>
            </div>

            <div class="card-body">

                <!-- Red-flag banner (shown only if triggered) -->
                <?php if ($isRedFlag): ?>
                <div class="redflag-banner" role="alert" aria-live="assertive">
                    <div class="redflag-icon" aria-hidden="true">🚨</div>
                    <div class="redflag-text">
                        <h2>Priority Alert: Possible Clinical Red Flag Detected</h2>
                        <p>Please inform the front desk immediately. A healthcare provider will attend to you shortly.</p>
                    </div>
                </div>
                <?php endif; ?>


                <?php if ($pathway === 'ayurvedic'): ?>
                <div class="ayurvedic-note">🌿 This is an <strong>Ayurvedic consultation</strong>. The Prakriti (constitution) section below helps guide your Ayurvedic doctor's treatment approach.</div>
                <?php endif; ?>

                <!-- Summary sections -->
                <div class="summary-sections" aria-label="Your intake summary">
                <?php foreach ($previewSections as $sec): ?>
                    <div class="summary-section">
                        <div class="section-title"><?= htmlspecialchars($sec['title']) ?></div>
                        <div class="section-content"><?= nl2br(htmlspecialchars($sec['content'])) ?></div>
                    </div>
                <?php endforeach; ?>
                </div>


                <!-- Error alert -->
                <div id="alert" class="alert alert-error" role="alert">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <span id="alert-msg"></span>
                </div>

                <!-- Action buttons -->
                <div class="btn-row">
                    <button id="btn-confirm" class="btn btn-primary" type="button"
                            onclick="submitSummary()"
                            <?= $alreadySubmitted ? 'disabled' : '' ?>>
                        <?= $alreadySubmitted ? '✓ Already Submitted' : 'Confirm &amp; Submit →' ?>
                        <span class="spinner" aria-hidden="true"></span>
                    </button>
                </div>

                <?php if (!$alreadySubmitted): ?>
                <a class="back-link" href="document-upload.php?token=<?= urlencode($token) ?>">← Back to Document Upload</a>
                <?php endif; ?>

            </div><!-- .card-body -->
        </div><!-- #summary-view -->

        <!-- Thank-you confirmation (shown after successful submit) -->
        <div id="thankyou-screen">
            <div class="ty-icon" aria-hidden="true">🎉</div>
            <div class="ty-title">Thank You!</div>
            <p class="ty-sub">
                Your information has been submitted successfully.<br>
                Your doctor has been notified and will see your intake summary shortly.
            </p>
            <?php if ($isRedFlag): ?>
            <p class="ty-sub" style="margin-top:12px;color:#c62828;font-weight:600;">
                🚨 A priority alert has been flagged. Please stay seated — a nurse will attend to you immediately.
            </p>
            <?php endif; ?>
            <div class="ty-enc" id="ty-encounter-badge" style="display:none;">
                ✓ Encounter created in OpenEMR
            </div>
            <a class="ty-back" href="login.php">← Start a new check-in</a>
        </div>

    </div><!-- .card -->
</div>

<script>
var TOKEN    = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var API_BASE = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';

function submitSummary() {
    clearAlert();
    var btn = document.getElementById('btn-confirm');
    btn.disabled = true;
    btn.classList.add('loading');

    fetch(API_BASE + '/save-summary.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ token: TOKEN }),
    })
    .then(function(r) {
        return r.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error("Server returned HTML/Invalid JSON: " + text.substring(0, 150) + "...");
            }
        });
    })
    .then(function(d) {
        btn.classList.remove('loading');
        if (d.success) {
            window.location.href = 'queue.php?token=' + encodeURIComponent(TOKEN) + 
                                   '&q=' + encodeURIComponent(d.queue_number) + 
                                   '&w=' + encodeURIComponent(d.waiting_room);
        } else {
            btn.disabled = false;
            showAlert(d.error || 'Submission failed. Please try again.');
        }
    })
    .catch(function(e) {
        btn.classList.remove('loading');
        btn.disabled = false;
        showAlert('Error: ' + e.message);
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
