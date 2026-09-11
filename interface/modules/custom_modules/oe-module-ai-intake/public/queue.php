<?php

/**
 * AI Intake — Final Queue Screen (Step 5)
 *
 * Displays the patient's queue number and waiting room assignment.
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
$lang  = $ks->language();
$t     = i18nStrings($lang);

$qNum = (int)($_GET['q'] ?? 1);
$pathway = $ks->pathway() ?? 'allopathic';

// Default routing
if ($pathway === 'ayurvedic') {
    $doctors = ['Vaidya Joshi', 'Vaidya Kulkarni'];
    $rooms = ['Ayurvedic Room A', 'Ayurvedic Room B'];
} else {
    $doctors = ['Dr. Mehta', 'Dr. Sharma', 'Dr. Patel'];
    $rooms = ['Room 1', 'Room 2', 'Room 3'];
}

// Predictable assignment based on queue number
$assignedDoctorIndex = $qNum % count($doctors);
$assignedRoomIndex = $qNum % count($rooms);

$assignedDoctor = $doctors[$assignedDoctorIndex];
$assignedRoom = $rooms[$assignedRoomIndex];

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['queue_title']) ?> — AI Intake</title>
    <style>
        :root {
            --brand-primary:  #2c9cd4;
            --brand-gradient: linear-gradient(135deg,#1a6fa0 0%,#2c9cd4 50%,#00d4ff 100%);
            --surface-glass:  rgba(255,255,255,.93);
            --text-primary:   #1a2a3a;
            --text-secondary: #4a6070;
            --radius-card:    24px;
            --shadow-card:    0 12px 48px rgba(44,156,212,.2);
            --font:           'Segoe UI',system-ui,-apple-system,sans-serif;
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { min-height:100%; font-family:var(--font); background:var(--brand-gradient); color:var(--text-primary); }
        .page-wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:20px; }
        
        .card {
            width:100%; max-width:500px;
            background:var(--surface-glass); backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,.6);
            border-radius:var(--radius-card); box-shadow:var(--shadow-card);
            text-align:center; padding:48px 32px;
        }
        .icon { font-size:4rem; margin-bottom:16px; animation:bounce 2s infinite ease-in-out; }
        @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
        
        .title { font-size:1.5rem; font-weight:700; margin-bottom:8px; color:var(--text-primary); }
        .subtitle { font-size:1.05rem; color:var(--text-secondary); margin-bottom:32px; }
        
        .queue-box {
            background:#f0f8ff; border:2px dashed #b3d4fc; border-radius:16px;
            padding:32px 20px; margin-bottom:32px;
        }
        .q-label { font-size:0.9rem; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:0.05em; }
        .q-number { font-size:4.5rem; font-weight:800; color:#1565c0; line-height:1; margin:8px 0; }
        .q-room { font-size:1.1rem; font-weight:600; color:#2e7d32; background:#e8f5e9; display:inline-block; padding:6px 16px; border-radius:50px; margin-top:8px; }
        
        .btn-home {
            display:inline-block; padding:14px 32px; background:transparent; color:var(--brand-primary);
            border:2px solid var(--brand-primary); border-radius:12px; font-weight:700; text-decoration:none;
            transition:all 0.2s; margin-top:16px;
        }
        .btn-home:hover { background:var(--brand-primary); color:#fff; }
        
        .doctor-assigned { font-size:1.15rem; font-weight:700; color:#424242; margin-top:16px; }
        .btn-req-doc {
            background:transparent; color:#555; border:none; text-decoration:underline;
            cursor:pointer; font-size:.85rem; margin-top:8px;
        }
        
        .doc-panel {
            display:none; margin-top:20px; background:#f9f9f9; padding:16px; border-radius:12px; border:1px solid #ddd;
        }
        .doc-panel.open { display:block; }
        .doc-list { display:flex; flex-direction:column; gap:8px; margin-top:12px; }
        .doc-option {
            background:#fff; border:1px solid #ccc; padding:10px; border-radius:8px; cursor:pointer; font-weight:600;
        }
        .doc-option:hover { border-color:var(--brand-primary); background:#f0f7fc; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="card" role="main">
        <div class="icon" aria-hidden="true"><?= htmlspecialchars($t['queue_icon']) ?></div>
        <h1 class="title"><?= htmlspecialchars($t['queue_heading']) ?></h1>
        <p class="subtitle"><?= htmlspecialchars($t['queue_subheading']) ?></p>
        
        <div class="queue-box">
            <div class="q-label"><?= htmlspecialchars($t['queue_number_label']) ?></div>
            <div class="q-number">#<?= htmlspecialchars($qNum) ?></div>
            <div class="q-room" id="room-display"><?= htmlspecialchars($t['queue_wait_label']) ?> <?= htmlspecialchars($assignedRoom) ?></div>
            <div class="doctor-assigned">Assigned to: <span id="doc-display"><?= htmlspecialchars($assignedDoctor) ?></span></div>
        </div>
        
        <button class="btn-req-doc" onclick="toggleDocPanel()">🙋 Request a specific doctor</button>
        
        <div class="doc-panel" id="doc-panel">
            <div style="font-size:0.9rem; font-weight:700; color:#333;">Select a provider (Wait times may vary)</div>
            <div class="doc-list">
                <?php foreach($doctors as $idx => $doc): ?>
                    <div class="doc-option" onclick="selectDoctor('<?= htmlspecialchars($doc) ?>', '<?= htmlspecialchars($rooms[$idx % count($rooms)]) ?>')">
                        <?= htmlspecialchars($doc) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <br>
        <a href="welcome.php" class="btn-home"><?= htmlspecialchars($t['btn_home']) ?></a>
    </div>
</div>
<script>
    function toggleDocPanel() {
        document.getElementById('doc-panel').classList.toggle('open');
    }
    function selectDoctor(docName, roomName) {
        document.getElementById('doc-display').innerText = docName;
        document.getElementById('room-display').innerText = '<?= htmlspecialchars($t['queue_wait_label']) ?> ' + roomName;
        document.getElementById('doc-panel').classList.remove('open');
    }
</script>
</body>
</html>
