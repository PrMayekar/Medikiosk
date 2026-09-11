<?php

/**
 * AI Intake — Document Upload Screen (Step 3)
 *
 * Patient can optionally upload a prescription or lab report.
 * Upload triggers a 2-second mock "Processing…" state, then displays
 * hard-coded OCR extracted fields.
 *
 * OCR NOTE
 * =============
 * The extracted fields shown after upload are hardcoded.
 * To wire real OCR, swap the JS section marked "── REAL OCR SWAP POINT ──"
 * with a real fetch() to an OCR microservice. The save-document.php
 * endpoint and this page's continue/skip logic do not change.
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
$ks->requireStep('consent');
$ks->requireStep('interview');
$lang            = $ks->language();
$t               = i18nStrings($lang);
$speechLang      = speechLangCode($lang);

$apiBase = ($GLOBALS['webroot'] ?? '')
    . '/interface/modules/custom_modules/oe-module-ai-intake/api';
$interactionMode = $ks->interactionMode() ?? 'tap';

?><!DOCTYPE html><html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['doc_title']) ?> — AI Intake</title>
    <meta name="description" content="Optionally upload a prescription or lab report to help your doctor.">
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
            --success:        #2ea055;
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

        .page-wrap {
            min-height:100vh; display:flex; flex-direction:column;
            align-items:center; padding:28px 16px 48px;
        }

        /* Progress */
        .topbar { width:100%; max-width:600px; margin-bottom:20px; }
        .step-label { color:rgba(255,255,255,.8); font-size:.78rem; font-weight:600;
                      text-transform:uppercase; letter-spacing:.04em; margin-bottom:8px; display:block; }
        .progress-track { height:5px; background:rgba(255,255,255,.25); border-radius:3px; overflow:hidden; }
        .progress-fill  { height:100%; width:88%; background:#fff; border-radius:3px; }

        /* Card */
        .card {
            width:100%; max-width:600px;
            background:var(--surface-glass); backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,.6);
            border-radius:var(--radius-card); box-shadow:var(--shadow-card); overflow:hidden;
        }
        .card-header {
            padding:28px 32px 0;
            display:flex; align-items:flex-start; gap:14px;
        }
        .header-icon {
            width:48px; height:48px; border-radius:12px; flex-shrink:0;
            background:var(--brand-gradient);
            display:flex; align-items:center; justify-content:center; font-size:1.3rem;
        }
        .header-text h1 { font-size:1.2rem; font-weight:700; color:var(--text-primary); margin-bottom:4px; }
        .header-text p  { font-size:.85rem; color:var(--text-muted); }
        .optional-badge {
            display:inline-block; margin:18px 32px 0;
            background:#fff8e1; color:#f57f17; border:1px solid #ffe082;
            border-radius:6px; padding:4px 12px; font-size:.78rem; font-weight:600;
        }
        .divider { height:1px; background:var(--border); margin:18px 32px 0; }

        /* Body */
        .card-body { padding:24px 32px; }

        /* Dropzone */
        .dropzone {
            border:2px dashed var(--border); border-radius:var(--radius-inner);
            padding:40px 24px; text-align:center; cursor:pointer;
            transition:border-color var(--transition), background var(--transition);
            background:#f8fcff; position:relative;
        }
        .dropzone:hover, .dropzone.drag-over {
            border-color:var(--brand-primary); background:rgba(44,156,212,.04);
        }
        .dropzone input[type=file] {
            position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%;
        }
        .dropzone-icon { font-size:2.8rem; margin-bottom:12px; display:block; }
        .dropzone-title { font-size:1rem; font-weight:700; color:var(--text-primary); margin-bottom:6px; }
        .dropzone-sub   { font-size:.82rem; color:var(--text-muted); }
        .dropzone-types {
            margin-top:12px; font-size:.75rem; color:var(--text-muted);
            display:flex; gap:8px; justify-content:center; flex-wrap:wrap;
        }
        .type-chip {
            background:#e8f4fd; color:var(--brand-primary);
            border-radius:4px; padding:2px 8px; font-weight:600;
        }

        /* Processing state */
        .processing {
            display:none; text-align:center; padding:32px;
        }
        .processing.visible { display:block; }
        .proc-spinner {
            width:48px; height:48px; border:4px solid rgba(44,156,212,.2);
            border-top-color:var(--brand-primary); border-radius:50%;
            animation:spin .8s linear infinite; margin:0 auto 16px;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .proc-text { font-size:.95rem; color:var(--text-secondary); font-weight:600; }
        .proc-sub  { font-size:.8rem; color:var(--text-muted); margin-top:6px; }

        /* OCR result */
        .ocr-result {
            display:none; border:1.5px solid #c8e6c9;
            border-radius:var(--radius-inner); overflow:hidden;
            animation:fadeUp .4s ease;
        }
        .ocr-result.visible { display:block; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .ocr-header {
            background:#e8f5e9; padding:12px 16px;
            display:flex; align-items:center; gap:10px;
        }
        .ocr-header-icon { font-size:1.1rem; }
        .ocr-header-text { font-size:.85rem; font-weight:700; color:#2e7d32; }
        .ocr-header-badge {
            margin-left:auto; background:#fff; color:#2e7d32;
            border:1px solid #a5d6a7; border-radius:4px; padding:2px 8px;
            font-size:.72rem; font-weight:700;
        }

        .ocr-fields { padding:16px; display:flex; flex-direction:column; gap:10px; }
        .ocr-field  { display:grid; grid-template-columns:140px 1fr; gap:8px; align-items:start; }
        .ocr-key    { font-size:.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em; }
        .ocr-val    { font-size:.9rem; color:var(--text-primary); }

        /* Alert */
        .alert { border-radius:10px; padding:12px 16px; font-size:.87rem;
                 display:none; align-items:center; gap:8px; margin-top:16px; }
        .alert.visible { display:flex; }
        .alert-error { background:#fdecea; color:#b71c1c; border:1px solid #f5c6c6; }

        /* Buttons */
        .btn-row { display:flex; gap:12px; margin-top:24px; }
        .btn {
            flex:1; padding:14px; border:none; border-radius:var(--radius-inner);
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
        .btn-ghost {
            background:transparent; color:var(--brand-primary);
            border:1.5px solid var(--brand-primary);
        }
        .btn-ghost:hover:not(:disabled) { background:rgba(44,156,212,.06); }
        .btn .spinner {
            display:none; width:16px; height:16px;
            border:2.5px solid rgba(255,255,255,.35); border-top-color:#fff;
            border-radius:50%; animation:spin .7s linear infinite;
            position:absolute; right:16px; top:50%; transform:translateY(-50%);
        }
        .btn.loading .spinner { display:block; }

        .back-link { display:block; text-align:center; margin-top:12px;
                     font-size:.82rem; color:var(--brand-primary); text-decoration:none; }
        .back-link:hover { text-decoration:underline; }

        .listen-btn {
            background:transparent; border:none; color:var(--brand-primary);
            font-size:1.1rem; cursor:pointer; padding:6px; border-radius:8px;
            transition:background var(--transition); display:inline-flex; align-items:center; justify-content:center;
            margin-left: 10px; vertical-align: middle;
        }
        .listen-btn:hover { background:rgba(44,156,212,.1); }
        .listen-btn.playing { animation:pulseBlue 1.5s infinite; }
        @keyframes pulseBlue { 0%,100%{background:transparent} 50%{background:rgba(44,156,212,.2)} }
        
        .ocr-list { display:flex; flex-direction:column; gap:12px; margin-top:16px; }

        @media(max-width:480px) {
            .card-header,.optional-badge,.divider,.card-body { padding-left:20px; padding-right:20px; }
            .optional-badge { margin-left:20px; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Progress -->
    <div class="topbar">
        <span class="step-label">Step 3 of 3 — Document Upload (optional)</span>
        <div class="progress-track"><div class="progress-fill"></div></div>
    </div>

    <main class="card" role="main">

        <div class="card-header">
            <div class="header-icon" aria-hidden="true">📄</div>
            <div class="header-text">
                <h1 style="display:inline-block; margin-right:8px;">Upload a Document</h1>
                <button id="btn-listen" class="listen-btn" onclick="playInstructions()" title="Listen">🔊</button>
                <p>Prescription, lab report, or any relevant medical record</p>
            </div>
        </div>
        <span class="optional-badge">Optional — you can skip this step</span>
        <div class="divider"></div>

        <div class="card-body">

            <!-- Dropzone (hidden when processing / after upload) -->
            <div id="dropzone" class="dropzone"
                 ondragover="onDragOver(event)" ondragleave="onDragLeave(event)" ondrop="onDrop(event)">
                <input type="file" id="file-input" accept="image/*,.pdf" multiple
                       onchange="onFileSelected(this.files)"
                       aria-label="Upload prescription or report">
                <span class="dropzone-icon" aria-hidden="true">☁️</span>
                <div class="dropzone-title">Drag &amp; drop your file here</div>
                <div class="dropzone-sub">or click to browse from your device</div>
                <div class="dropzone-types">
                    <span class="type-chip">JPG</span>
                    <span class="type-chip">PNG</span>
                    <span class="type-chip">PDF</span>
                </div>
            </div>

            <!-- Processing spinner (shown while "OCR" runs) -->
            <div id="processing" class="processing">
                <div class="proc-spinner" aria-hidden="true"></div>
                <div class="proc-text">Analysing documents…</div>
                <div class="proc-sub">Extracting key information</div>
            </div>

            <!-- OCR result -->
            <div id="ocr-result" class="ocr-result">
                <div class="ocr-header">
                    <span class="ocr-header-icon" aria-hidden="true">✅</span>
                    <span class="ocr-header-text">Documents Extracted</span>
                    <span class="ocr-header-badge">AI Processed</span>
                </div>

                <div class="ocr-fields" id="ocr-fields">
                    <!-- Fields injected by JS -->
                </div>
                
                <div style="padding:16px; border-top:1px solid #e8f5e9; text-align:center;">
                    <button class="btn btn-ghost" type="button" onclick="showDropzone()" style="padding: 10px 16px; font-size: 0.9rem;">＋ Add More Files</button>
                </div>
            </div>

            <!-- Alert -->
            <div id="alert" class="alert alert-error" role="alert">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span id="alert-msg"></span>
            </div>

            <!-- Action buttons -->
            <div class="btn-row">
                <button id="btn-skip" class="btn btn-ghost" type="button" onclick="submitDocument(null)">
                    Skip
                    <span class="spinner" aria-hidden="true"></span>
                </button>
                <button id="btn-continue" class="btn btn-primary" type="button"
                        onclick="submitDocument(extractedData)" disabled>
                    Continue →
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </div>

            <a class="back-link" href="interview.php?token=<?= urlencode($token) ?>">← Back to Interview</a>

        </div><!-- .card-body -->
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script>
// Configure pdf.js worker
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
}

var TOKEN        = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var API_BASE     = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
var extractedData = []; // array of document objects

function playInstructions() {
    var btn = document.getElementById('btn-listen');
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        var msg = new SpeechSynthesisUtterance("Upload a Document. Prescription, lab report, or any relevant medical record. You can upload multiple files. Skip this step if you don't have any.");
        msg.onstart = function() { btn.classList.add('playing'); };
        msg.onend   = function() { btn.classList.remove('playing'); }; 
        window.speechSynthesis.speak(msg);
    }
}

/* ─── PDF OCR via pdf.js — render pages to canvas, then Tesseract ─── */
async function runPdfOcr(file) {
    try {
        var arrayBuffer = await file.arrayBuffer();
        var pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
        var allText = '';
        var totalPages = Math.min(pdf.numPages, 6); // cap at 6 pages

        for (var pageNum = 1; pageNum <= totalPages; pageNum++) {
            var procEl = document.querySelector('#processing p');
            if (procEl) procEl.textContent = 'Reading page ' + pageNum + ' of ' + totalPages + ' in ' + file.name + '…';

            var page = await pdf.getPage(pageNum);
            var scale = 2.0; // higher = better OCR quality
            var viewport = page.getViewport({ scale: scale });

            var canvas = document.createElement('canvas');
            var ctx = canvas.getContext('2d');
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            await page.render({ canvasContext: ctx, viewport: viewport }).promise;

            // Try text-layer first (fast, accurate for searchable PDFs)
            var textContent = await page.getTextContent();
            var pageText = textContent.items.map(function(item) { return item.str; }).join(' ').trim();

            if (pageText.length > 50) {
                // Good quality native text — use it directly
                allText += pageText + '\n';
            } else {
                // Scanned PDF — run Tesseract on the canvas
                var imageUrl = canvas.toDataURL('image/png');
                var result = await Tesseract.recognize(imageUrl, 'eng');
                allText += (result.data.text || '') + '\n';
            }
        }
        return { ocrText: allText.trim(), isPdf: true, pageCount: pdf.numPages };
    } catch(e) {
        return { ocrText: '', isPdf: true, error: e.message };
    }
}

/* ─── Image OCR via Tesseract.js ─────────────────────────────────── */
async function runImageOcr(file) {
    try {
        var imageUrl = URL.createObjectURL(file);
        var result = await Tesseract.recognize(imageUrl, 'eng', {
            logger: function(m) {
                if (m.status === 'recognizing text') {
                    var pct = Math.round((m.progress || 0) * 100);
                    var procEl = document.querySelector('#processing p');
                    if (procEl) procEl.textContent = 'Extracting text from ' + file.name + '… ' + pct + '%';
                }
            }
        });
        URL.revokeObjectURL(imageUrl);
        return { ocrText: result.data.text || '', isPdf: false };
    } catch(e) {
        return { ocrText: '', error: e.message };
    }
}

/* ─── Document classifier — Indian medical reports ─────────────────── */
function classifyDocumentFromText(ocrText, filename) {
    var lower = (ocrText + ' ' + filename).toLowerCase();

    // Prescription
    if (/\brx\b/.test(lower) ||
        /\d+\s*(mg|ml|mcg|tablet|tab|cap|capsule|syrup|dose|twice|once|tds|bd|od|sos)/.test(lower) ||
        lower.includes('prescribed') || lower.includes('refill') ||
        lower.includes('dr.') || lower.includes('doctor')) {
        return { type: '💊 Prescription', icon: '💊', color: '#e3f2fd', textColor: '#1565c0' };
    }

    // Lab report — blood / biochemistry
    if (lower.includes('haemoglobin') || lower.includes('hemoglobin') ||
        lower.includes('creatinine') || lower.includes('serum') ||
        lower.includes('platelet') || lower.includes('wbc') || lower.includes('rbc') ||
        lower.includes('cbc') || lower.includes('complete blood count') ||
        lower.includes('blood sugar') || lower.includes('hba1c') || lower.includes('glucose') ||
        lower.includes('cholesterol') || lower.includes('triglyceride') || lower.includes('lipid') ||
        lower.includes('sgpt') || lower.includes('sgot') || lower.includes('bilirubin') ||
        lower.includes('lft') || lower.includes('liver function') ||
        lower.includes('urea') || lower.includes('rft') || lower.includes('kidney function') ||
        lower.includes('tsh') || lower.includes('thyroid') || lower.includes('t3') || lower.includes('t4') ||
        lower.includes('urine') || lower.includes('urinalysis') ||
        lower.includes('test result') || lower.includes('lab report') ||
        lower.includes('reference range') || lower.includes('normal range') ||
        lower.includes('pathology') || lower.includes('laboratory')) {
        return { type: '🧪 Lab Report', icon: '🧪', color: '#f3e5f5', textColor: '#6a1b9a' };
    }

    // Scan / Radiology / Cardiology
    if (lower.includes('impression') || lower.includes('findings') ||
        lower.includes('ultrasound') || lower.includes('usg') ||
        lower.includes('x-ray') || lower.includes('xray') ||
        lower.includes('mri') || lower.includes('ct scan') || lower.includes('computed tomography') ||
        lower.includes('radiology') || lower.includes('sonography') ||
        lower.includes('echocardiography') || lower.includes('echo') ||
        lower.includes('ecg') || lower.includes('electrocardiogram') ||
        lower.includes('eeg') || lower.includes('stress test')) {
        return { type: '🏥 Scan / Radiology / Cardiology', icon: '🏥', color: '#fff3e0', textColor: '#e65100' };
    }

    // Discharge summary / Hospital record
    if (lower.includes('discharge') || lower.includes('admitted') ||
        lower.includes('hospital') || lower.includes('ward') ||
        lower.includes('in-patient') || lower.includes('inpatient') ||
        lower.includes('diagnosis at discharge') || lower.includes('treatment given')) {
        return { type: '📋 Discharge Summary', icon: '📋', color: '#e8f5e9', textColor: '#1b5e20' };
    }

    // Vaccination / immunization
    if (lower.includes('vaccine') || lower.includes('vaccination') ||
        lower.includes('immunization') || lower.includes('dose given')) {
        return { type: '💉 Vaccination Record', icon: '💉', color: '#e0f2f1', textColor: '#004d40' };
    }

    return { type: '📄 Medical Record', icon: '📄', color: '#f5f5f5', textColor: '#424242' };
}

/* ─── File events ─────────────────────────────────────────────── */
function onDragOver(e)  { e.preventDefault(); document.getElementById('dropzone').classList.add('drag-over'); }
function onDragLeave()  { document.getElementById('dropzone').classList.remove('drag-over'); }
function onDrop(e)      { e.preventDefault(); onDragLeave(); onFileSelected(e.dataTransfer.files); }

async function onFileSelected(files) {
    if (!files || files.length === 0) return;

    var validFiles = [];
    var allowed = ['image/jpeg','image/png','image/gif','image/webp','application/pdf'];
    for (var i = 0; i < files.length; i++) {
        if (allowed.includes(files[i].type)) validFiles.push(files[i]);
    }

    if (validFiles.length === 0) {
        showAlert('Please upload JPG, PNG, or PDF files only.');
        return;
    }

    clearAlert();
    // extractedData = []; // Removed to allow appending new files

    document.getElementById('dropzone').style.display    = 'none';
    document.getElementById('processing').classList.add('visible');

    // Process files sequentially
    for (var i = 0; i < validFiles.length; i++) {
        var f = validFiles[i];
        var procEl = document.querySelector('#processing p');
        if (procEl) procEl.textContent = 'Processing file ' + (i + 1) + ' of ' + validFiles.length + ': ' + f.name;

        var ocrResult;
        if (f.type === 'application/pdf') {
            ocrResult = await runPdfOcr(f);
        } else {
            ocrResult = await runImageOcr(f);
        }
        
        var filteredText = filterMedicalText(ocrResult.ocrText);
        var classification = classifyDocumentFromText(filteredText, f.name);
        var previewText = filteredText.trim().slice(0, 400);

        extractedData.push({
            document_type: classification.type,
            filename:      f.name,
            ocr_text:      filteredText.slice(0, 4000),
            extracted_at:  new Date().toISOString(),
            _preview:      previewText,       // for display only
            _color:        classification.color,
            _textColor:    classification.textColor,
        });
    }

    showOcrResult(extractedData);
}

function showOcrResult(dataArray) {
    document.getElementById('processing').classList.remove('visible');

    var fieldsEl = document.getElementById('ocr-fields');
    fieldsEl.innerHTML = '';

    dataArray.forEach(function(doc, idx) {
        var card = document.createElement('div');
        card.style.cssText = 'border:1.5px solid ' + (doc._color ? doc._color.replace('#', '#') : '#e0e0e0') + ';border-radius:12px;overflow:hidden;margin-bottom:12px;';

        var hasText = doc._preview && doc._preview.trim().length > 10;
        var previewId = 'ocr-preview-' + idx;

        card.innerHTML =
            '<div style="background:' + (doc._color || '#f5f5f5') + ';padding:12px 16px;display:flex;align-items:center;gap:10px;">' +
                '<span style="font-size:1.3rem;">' + (doc.document_type.split(' ')[0]) + '</span>' +
                '<div style="flex:1;">' +
                    '<div style="font-weight:700;font-size:.9rem;color:' + (doc._textColor || '#333') + ';">' + escHtml(doc.document_type) + '</div>' +
                    '<div style="font-size:.78rem;color:#666;margin-top:2px;">' + escHtml(doc.filename) + '</div>' +
                '</div>' +
                (hasText ? '<button onclick="togglePreview(\'' + previewId + '\', this)" style="background:rgba(0,0,0,.08);border:none;border-radius:6px;padding:4px 10px;font-size:.75rem;cursor:pointer;color:#555;white-space:nowrap;">Show text ▼</button>' : '') +
            '</div>' +
            (hasText ? '<div id="' + previewId + '" style="display:none;padding:12px 16px;font-size:.8rem;color:#444;background:#fafafa;white-space:pre-wrap;max-height:180px;overflow-y:auto;line-height:1.6;border-top:1px solid rgba(0,0,0,.06);">' + escHtml(doc._preview) + (doc.ocr_text.length > 400 ? '\n…[truncated]' : '') + '</div>' : '') +
            '<div style="background:#f9f9f9;padding:8px 16px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid rgba(0,0,0,.05);">' +
                '<span style="font-size:.75rem;color:#888;">✅ Extracted successfully</span>' +
                (hasText ? '<span style="font-size:.75rem;color:#888;">' + doc.ocr_text.trim().split(/\s+/).length + ' words</span>' : '<span style="font-size:.75rem;color:#f57c00;">⚠ No text detected</span>') +
            '</div>';

        fieldsEl.appendChild(card);
    });

    document.getElementById('ocr-result').classList.add('visible');
    document.getElementById('btn-continue').disabled = false;
}

function togglePreview(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    if (el.style.display === 'none') {
        el.style.display = 'block';
        btn.textContent = 'Hide text ▲';
    } else {
        el.style.display = 'none';
        btn.textContent = 'Show text ▼';
    }
}

/* ── Submit ─────────────────────────────────────────────────────────────── */
function submitDocument(data) {
    clearAlert();

    // Strip display-only keys before sending
    var cleanData = null;
    if (data && data.length > 0) {
        cleanData = data.map(function(d) {
            return {
                document_type: d.document_type,
                filename:      d.filename,
                ocr_text:      d.ocr_text,
                extracted_at:  d.extracted_at,
            };
        });
    }

    var btnSkip = document.getElementById('btn-skip');
    var btnCont = document.getElementById('btn-continue');
    btnSkip.disabled = true;
    btnCont.disabled = true;

    var activeBtn = (!cleanData || cleanData.length === 0) ? btnSkip : btnCont;
    activeBtn.classList.add('loading');

    fetch(API_BASE + '/save-document.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ token: TOKEN, document_data: cleanData }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        activeBtn.classList.remove('loading');
        if (d.success) {
            window.location.href = 'summary.php?token=' + encodeURIComponent(TOKEN);
        } else {
            btnSkip.disabled = false;
            btnCont.disabled = (extractedData.length === 0);
            showAlert(d.error || 'Something went wrong. Please try again.');
        }
    })
    .catch(function() {
        activeBtn.classList.remove('loading');
        btnSkip.disabled = false;
        btnCont.disabled = (extractedData.length === 0);
        showAlert('Network error. Please check your connection.');
    });
}

function showAlert(msg) {
    var el = document.getElementById('alert');
    document.getElementById('alert-msg').textContent = msg;
    el.classList.add('visible');
}
function clearAlert() { document.getElementById('alert').classList.remove('visible'); }
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function showDropzone() {
    document.getElementById('dropzone').style.display = 'flex';
}

function filterMedicalText(rawText) {
    if (!rawText) return '';
    var lines = rawText.split('\n');
    var keptLines = [];
    var medicalKeywords = ['mg', 'ml', 'mcg', 'tablet', 'tab', 'cap', 'capsule', 'syrup', 'dose', 'twice', 'once', 'tds', 'bd', 'od', 'sos', 'rx', 'prescribed', 'doctor', 'dr.', 'haemoglobin', 'hemoglobin', 'creatinine', 'serum', 'platelet', 'wbc', 'rbc', 'cbc', 'blood sugar', 'hba1c', 'glucose', 'cholesterol', 'triglyceride', 'lipid', 'sgpt', 'sgot', 'bilirubin', 'lft', 'urea', 'rft', 'tsh', 'thyroid', 'urine', 'result', 'range', 'pathology', 'laboratory', 'impression', 'findings', 'ultrasound', 'usg', 'x-ray', 'xray', 'mri', 'ct scan', 'radiology', 'sonography', 'echo', 'ecg', 'discharge', 'hospital', 'admitted', 'diagnosis', 'treatment', 'vaccine', 'immunization'];
    
    for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        if (line.length < 3) continue;
        
        var lowerLine = line.toLowerCase();
        
        // Skip obvious noise
        if (/^page \d+ of \d+$/i.test(lowerLine) || /^page \d+$/i.test(lowerLine)) continue;
        if (/^\d{10}$/.test(lowerLine) || /^ph: \d+/.test(lowerLine)) continue; // phone numbers
        if (lowerLine.includes('www.') || lowerLine.includes('http')) continue;
        
        // Keep if it has numbers (could be values) or keywords
        var hasKeyword = medicalKeywords.some(function(kw) { return lowerLine.includes(kw); });
        var hasNumbers = /\d/.test(lowerLine);
        
        if (hasKeyword || (hasNumbers && line.length > 5)) {
            keptLines.push(line);
        }
    }
    
    // If we filtered out almost everything (e.g. it's a completely unrecognised doc), just return the raw text as fallback
    if (keptLines.length === 0 && rawText.length > 20) return rawText;
    
    return keptLines.join('\n');
}

</script>
</body>
