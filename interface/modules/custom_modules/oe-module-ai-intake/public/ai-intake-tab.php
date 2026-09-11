<?php
/**
 * AI Intake — Dedicated Patient Tab
 *
 * Renders the AI Intake pre-visit summary and the button for ambient consultation
 * inside a full patient tab.
 */

require_once(__DIR__ . '/../../../../globals.php');
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Common\Session\SessionWrapperFactory;

if (!AclMain::aclCheckCore('patients', 'med')) {
    die("Access denied. Medical access required.");
}

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$pid = (isset($_GET['pid']) && $_GET['pid'] > 0) ? (int)$_GET['pid'] : (int)$session->get('pid');

if ($pid <= 0) {
    die("Invalid patient ID.");
}

// Fetch latest AI Intake session for this patient.
$sessionRow = sqlQuery(
    "SELECT id, pathway, summary_text, red_flag,
            openemr_encounter_id, openemr_form_note_id, created_at, status
       FROM ai_intake_session
      WHERE pid = ?
      ORDER BY id DESC
      LIMIT 1",
    [$pid]
);

$webroot = OEGlobalsBag::getInstance()->getWebRoot() ?? '';
$modPath = $webroot . '/interface/modules/custom_modules/oe-module-ai-intake';
$cardId  = 'ai-intake-card-' . $pid;

// Extract variables for Twig (same as card renderer)
$aiSession = null;
if ($sessionRow) {
    $aiSession = [
        'id'                   => (int) ($sessionRow['id'] ?? 0),
        'pathway'              => htmlspecialchars($sessionRow['pathway'] ?? '', ENT_QUOTES),
        'summary_text_raw'     => $sessionRow['summary_text'] ?? '',
        'red_flag'             => (bool) ($sessionRow['red_flag'] ?? false),
        'openemr_encounter_id' => (int) ($sessionRow['openemr_encounter_id'] ?? 0),
        'created_at'           => $sessionRow['created_at'] ?? ''
    ];
}

// ── OpenEMR Page Headers ──
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xla("AI Intake"); ?></title>
    <?php include_once("{$GLOBALS['srcdir']}/header.inc.php"); ?>
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .aic-tab-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        /* Bring in styles from the twig file for the card layout */
        .aic-section-title {
            font-size: 1rem; font-weight: 700; color: #4a6070; text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 2px solid #e0e6ed; padding-bottom: 6px; margin-bottom: 16px;
        }
        .aic-btn {
            display: inline-block; padding: 6px 12px; font-size: .86rem; font-weight: 600;
            border-radius: 4px; border: none; cursor: pointer; text-decoration: none; text-align: center;
        }
        .aic-btn-primary { background: #2c9cd4; color: #fff; }
        .aic-btn-primary:hover { background: #1a6fa0; text-decoration: none; color: #fff; }
        
        .aic-empty-state {
            text-align: center; padding: 40px 20px; color: #6c757d; font-size: 1.1rem;
        }
        
        /* Simple badge style for pathway */
        .aic-badge {
            display: inline-block; padding: 3px 8px; font-size: 0.75rem; font-weight: 700;
            border-radius: 4px; color: #fff; text-transform: uppercase;
        }
        .bg-ayur { background-color: #f57f17; }
        .bg-allo { background-color: #0277bd; }
        
        .translate-btn {
            background: #f8f9fa; border: 1px solid #ced4da; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; cursor: pointer; color: #495057; font-weight: 600;
        }
        .translate-btn:hover { background: #e9ecef; }
    </style>
</head>
<body class="body_top">
    <div class="aic-tab-container">
        <h4 style="color:#2c9cd4; margin-bottom: 20px;">
            <i class="fa fa-microphone"></i> AI Intake Dashboard
        </h4>
        
        <?php if (!$aiSession): ?>
            <div class="aic-empty-state">
                <i class="fa fa-clipboard" style="font-size:3rem; margin-bottom:15px; color:#dee2e6;"></i><br>
                <?php echo xla("No AI Intake session found for this patient."); ?><br>
                <small><?php echo xla("The patient needs to complete the kiosk intake flow first."); ?></small>
            </div>
        <?php else: ?>
            
            <div style="margin-bottom: 15px;">
                <span class="aic-badge <?php echo ($aiSession['pathway'] === 'ayurvedic') ? 'bg-ayur' : 'bg-allo'; ?>">
                    <?php echo htmlspecialchars(ucfirst($aiSession['pathway'])); ?>
                </span>
                <span style="font-size: 0.85rem; color:#6c757d; margin-left:10px;">
                    <i class="fa fa-clock"></i> <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($aiSession['created_at']))); ?>
                </span>
                <?php if ($aiSession['openemr_encounter_id']): ?>
                    <span style="font-size: 0.85rem; color:#2e7d32; font-weight:bold; margin-left:10px; background:#e8f5e9; padding:2px 6px; border-radius:4px;">
                        ✓ Enc #<?php echo $aiSession['openemr_encounter_id']; ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="aic-section-title" style="display:flex; justify-content:space-between; align-items:center;">
                <span>Pre-Visit Intake Summary</span>
                <button class="translate-btn" id="btn-translate" onclick="toggleTranslation()">🌐 View in Marathi</button>
            </div>
            <div id="summary-container" style="background:#f8fcff; border:1px solid #cce4f0; border-radius:8px; padding:16px; margin-bottom:20px; font-family:monospace; white-space:pre-wrap; font-size:0.9rem; line-height:1.6; color:#333;">
<?php echo htmlspecialchars($aiSession['summary_text_raw']); ?>
            </div>
            <div id="summary-container-mr" style="display:none; background:#fef8e7; border:1px solid #ffe599; border-radius:8px; padding:16px; margin-bottom:20px; font-family:monospace; white-space:pre-wrap; font-size:1rem; line-height:1.7; color:#333;"></div>
            
            <div style="margin-bottom: 30px;">
                <a href="<?php echo $webroot; ?>/interface/patient_file/encounter/trend_form.php?formname=note" target="_blank" style="font-size:0.85rem; font-weight:600; text-decoration:none;">
                    ▶ <i class="fa fa-pencil-alt"></i> Edit Pre-Visit Note in Chart
                </a>
            </div>
            
            <div class="aic-section-title">🎙 Full-Screen Ambient Consultation</div>
            <div style="text-align:center; padding: 30px 20px; background:#fff; border:1px dashed #ced4da; border-radius:8px;">
                <p style="font-size:0.95rem; color:#4a6070; margin-bottom: 20px;">
                    Start a live ambient listening session to automatically transcribe the consultation and generate a SOAP note using LLM.
                </p>
                <a href="<?php echo $modPath; ?>/public/ambient-consult.php?pid=<?php echo $pid; ?>&session=<?php echo $aiSession['id']; ?>" target="_blank" class="aic-btn aic-btn-primary" style="font-size:1.05rem; padding: 12px 24px;">
                    ▶ Start Ambient Consultation
                </a>
            </div>

        <?php endif; ?>
    </div>

<script>
var originalText = <?php echo json_encode($aiSession['summary_text_raw'] ?? ''); ?>;
var translatedText = '';
var isMarathi = false;

function toggleTranslation() {
    var btn = document.getElementById('btn-translate');
    var enContainer = document.getElementById('summary-container');
    var mrContainer = document.getElementById('summary-container-mr');
    
    if (isMarathi) {
        // Switch back to English
        isMarathi = false;
        enContainer.style.display = 'block';
        mrContainer.style.display = 'none';
        btn.innerHTML = '🌐 View in Marathi';
        return;
    }
    
    // Switch to Marathi
    isMarathi = true;
    enContainer.style.display = 'none';
    mrContainer.style.display = 'block';
    btn.innerHTML = '🌐 View in English';
    
    if (translatedText !== '') {
        mrContainer.textContent = translatedText;
        return;
    }
    
    // Fetch translation
    mrContainer.innerHTML = '<span style="color:#6c757d;">Translating to Marathi using AI...</span>';
    
    fetch('<?php echo $modPath; ?>/api/translate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text: originalText })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            translatedText = d.translation;
            mrContainer.textContent = translatedText;
        } else {
            mrContainer.innerHTML = '<span style="color:#dc3545;">Translation failed. Please try again later.</span>';
            translatedText = '';
        }
    })
    .catch(e => {
        mrContainer.innerHTML = '<span style="color:#dc3545;">Translation network error.</span>';
    });
}
</script>
</body>
</html>
