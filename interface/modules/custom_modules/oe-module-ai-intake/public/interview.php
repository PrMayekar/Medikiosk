<?php

/**
 * AI Intake — Voice Interview Screen (Step 3)
 *
 * Presents pathway-specific questions one at a time.
 * Voice mode: Real browser ASR with live rolling transcript (interimResults).
 * Tap mode:   Text area + optional mic button using same real ASR.
 * Language:   Marathi or English, set from kiosk session.
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
require_once(__DIR__ . '/../../../../globals.php');
require_once(__DIR__ . '/../lib/kiosk-session.php');
require_once(__DIR__ . '/../lib/i18n.php');

$ks      = KioskSession::requireValid('login.php');
$token   = $ks->token();
$ks->requireStep('consent');
$ks->requireStep('pathway');
$ks->markStep('pathway');

$pathway         = $ks->pathway() ?? 'allopathic';
$lang            = $ks->language();
$interactionMode = $ks->interactionMode() ?? 'tap';
$speechLang      = speechLangCode($lang);
$t               = i18nStrings($lang);

$apiBase = ($GLOBALS['webroot'] ?? '')
    . '/interface/modules/custom_modules/oe-module-ai-intake/api';

// ── Returning Patient Detection ──────────────────────────────────────────────
$pid        = $ks->pid();
$isFollowUp = false;
$prevInterviewHtml = '';

$prevSession = sqlQuery(
    "SELECT interview_data FROM ai_intake_session
     WHERE pid = ? AND status = 'completed' AND interview_data IS NOT NULL
     ORDER BY created_at DESC LIMIT 1",
    [$pid]
);

if ($prevSession && !empty($prevSession['interview_data'])) {
    $isFollowUp = true;
    $prevData   = json_decode($prevSession['interview_data'], true);
    if (is_array($prevData)) {
        foreach ($prevData as $item) {
            $prevInterviewHtml .=
                '<div style="margin-bottom:10px;">'
              . '<div style="font-size:.74rem;color:#666;font-weight:600;margin-bottom:2px;">'
              . htmlspecialchars($item['question'] ?? '')
              . '</div>'
              . '<div style="font-size:.88rem;color:#1a2a3a;">'
              . htmlspecialchars($item['answer'] ?? '')
              . '</div></div>';
        }
    }
}

// ── Question sets ────────────────────────────────────────────────────────────
$questionSets = [
    'allopathic' => [
        'en' => [
            ['id' => 'chief_complaint', 'q' => 'What is your main health concern today?',           'hint' => 'Describe your primary symptom or reason for visiting.'],
            ['id' => 'duration',        'q' => 'How long have you been experiencing this?',          'hint' => 'E.g. "3 days", "since last week", "about a month".'],
            ['id' => 'severity',        'q' => 'On a scale of 1 to 10, how severe is it right now?','hint' => '1 = barely noticeable, 10 = worst pain imaginable.'],
            ['id' => 'aggravating',     'q' => 'What makes it worse?',                              'hint' => 'E.g. movement, food, stress, cold, heat — or "nothing specific".'],
            ['id' => 'relieving',       'q' => 'What makes it better, or provides relief?',         'hint' => 'E.g. rest, medication, warm compress, or "nothing helps".'],
            ['id' => 'past_history',    'q' => 'Do you have any past medical conditions we should know about?', 'hint' => 'E.g. diabetes, hypertension, asthma — or "none known".'],
            ['id' => 'meds_allergies',  'q' => 'Are you currently taking any medications, or do you have any known allergies?', 'hint' => 'List medications and any drug/food allergies — or "none".'],
        ],
        'mr' => [
            ['id' => 'chief_complaint', 'q' => 'आज तुमची मुख्य आरोग्य समस्या काय आहे?',              'hint' => 'तुमचे मुख्य लक्षण किंवा भेटीचे कारण सांगा.'],
            ['id' => 'duration',        'q' => 'तुम्हाला हे किती दिवसांपासून होत आहे?',                'hint' => 'उदा. "३ दिवस", "मागच्या आठवड्यापासून", "एक महिन्यापासून".'],
            ['id' => 'severity',        'q' => '१ ते १० च्या प्रमाणात, सध्या हे किती तीव्र आहे?',    'hint' => '१ = जवळजवळ जाणवत नाही, १० = सर्वात जास्त वेदना.'],
            ['id' => 'aggravating',     'q' => 'कोणत्या गोष्टी त्रास वाढवतात?',                       'hint' => 'उदा. हालचाल, जेवण, ताण, थंडी, उष्णता — किंवा "काहीही नाही".'],
            ['id' => 'relieving',       'q' => 'कोणत्या गोष्टींनी आराम मिळतो?',                       'hint' => 'उदा. विश्रांती, औषध, उष्ण शेक — किंवा "काहीही नाही".'],
            ['id' => 'past_history',    'q' => 'तुम्हाला कोणतेही जुने आजार आहेत का?',                 'hint' => 'उदा. मधुमेह, रक्तदाब, दमा — किंवा "नाही".'],
            ['id' => 'meds_allergies',  'q' => 'तुम्ही सध्या कोणती औषधे घेत आहात किंवा कोणत्या गोष्टींची ॲलर्जी आहे का?', 'hint' => 'औषधे व ॲलर्जी सांगा — किंवा "नाही".'],
        ],
    ],

    // ── FULL AYURVEDIC INTAKE — Prakriti / Dosha / Lifestyle focused ──────────
    'ayurvedic' => [
        'en' => [
            ['id' => 'chief_complaint',   'q' => 'What is your main health concern today?',
             'hint' => 'Describe your primary symptom or reason for visiting the Ayurvedic doctor.'],
            ['id' => 'duration_season',   'q' => 'How long have you had these symptoms, and do they get worse in any particular season?',
             'hint' => 'E.g. "3 weeks, worse in winter" or "2 months, worse in summer/rainy season".'],
            ['id' => 'severity',          'q' => 'On a scale of 1 to 10, how much is this affecting your daily life right now?',
             'hint' => '1 = minor inconvenience, 10 = unable to do daily activities.'],
            ['id' => 'prakriti_body',     'q' => 'How would you describe your natural body build and skin?',
             'hint' => 'Lean, thin, dry skin (Vata) | Medium build, sharp, oily skin (Pitta) | Stocky, heavy, soft skin (Kapha).'],
            ['id' => 'digestion',         'q' => 'How is your digestion and appetite on most days?',
             'hint' => 'Irregular appetite, bloating, gas (Vata) | Strong hunger, acidity, heartburn (Pitta) | Slow, heavy feeling after meals (Kapha).'],
            ['id' => 'sleep_energy',      'q' => 'Describe your sleep quality and energy levels.',
             'hint' => 'Light/disturbed sleep, low energy (Vata) | Less sleep needed, sharp mind (Pitta) | Heavy/long sleep, sluggish mornings (Kapha).'],
            ['id' => 'stress_response',   'q' => 'When you are stressed or anxious, how do you typically feel or behave?',
             'hint' => 'Anxious, fearful, restless (Vata) | Irritable, angry, critical (Pitta) | Withdrawn, quiet, comfort eating (Kapha).'],
            ['id' => 'diet_habits',       'q' => 'Describe your typical diet — any food intolerances, cravings, or habits?',
             'hint' => 'E.g. craving sweet/salty, intolerance to spicy foods, vegetarian/non-vegetarian, meal timings.'],
            ['id' => 'past_history',      'q' => 'Any significant past illnesses, surgeries, or family health history?',
             'hint' => 'E.g. diabetes in family, past jaundice, kidney stones, frequent infections — or "none known".'],
            ['id' => 'ayurvedic_history', 'q' => 'Have you ever tried Ayurvedic treatment, Panchakarma, or any herbal remedies? What worked or did not?',
             'hint' => 'E.g. "Tried Ashwagandha — it helped", "Panchakarma 2 years ago", or "No, this is my first time".'],
        ],
        'mr' => [
            ['id' => 'chief_complaint',   'q' => 'आज तुमची मुख्य आरोग्य समस्या काय आहे?',
             'hint' => 'तुमचे मुख्य लक्षण किंवा आयुर्वेदिक डॉक्टरकडे येण्याचे कारण सांगा.'],
            ['id' => 'duration_season',   'q' => 'हे किती दिवसांपासून होत आहे, आणि कोणत्या ऋतूत जास्त त्रास होतो?',
             'hint' => 'उदा. "३ आठवडे, हिवाळ्यात जास्त" किंवा "२ महिने, उन्हाळ्यात जास्त".'],
            ['id' => 'severity',          'q' => '१ ते १० च्या प्रमाणात, हे तुमच्या दैनंदिन जीवनावर किती परिणाम करत आहे?',
             'hint' => '१ = किरकोळ त्रास, १० = कामे करणे कठीण.'],
            ['id' => 'prakriti_body',     'q' => 'तुमची शरीरयष्टी आणि त्वचा कशी असते?',
             'hint' => 'बारीक, हलके, कोरडी त्वचा (वात) | मध्यम, तीक्ष्ण, तेलकट त्वचा (पित्त) | जड, भारदस्त, मऊ त्वचा (कफ).'],
            ['id' => 'digestion',         'q' => 'बहुतेक दिवसांत तुमची पचनशक्ती आणि भूक कशी असते?',
             'hint' => 'अनियमित भूक, पोट फुगणे (वात) | तीव्र भूक, आम्लपित्त (पित्त) | जेवणानंतर जड वाटणे (कफ).'],
            ['id' => 'sleep_energy',      'q' => 'तुमची झोप आणि ऊर्जा पातळी सांगा.',
             'hint' => 'हलकी/अस्वस्थ झोप, कमी ऊर्जा (वात) | कमी झोप पुरते (पित्त) | जड/जास्त झोप, सकाळी सुस्ती (कफ).'],
            ['id' => 'stress_response',   'q' => 'ताण किंवा काळजी आल्यावर तुम्ही साधारणतः कसे वागता?',
             'hint' => 'काळजी, भीती, अस्वस्थता (वात) | राग, चिडचिड (पित्त) | एकांत, शांत, जास्त खाणे (कफ).'],
            ['id' => 'diet_habits',       'q' => 'तुमचा रोजचा आहार कसा असतो? कोणते पदार्थ सहन होत नाहीत किंवा जास्त खाव्याशा वाटतात?',
             'hint' => 'उदा. तिखट सहन होत नाही, गोड/खारट आवडते, जेवणाच्या वेळा.'],
            ['id' => 'past_history',      'q' => 'कोणते मागील आजार, शस्त्रक्रिया, किंवा घरात कोणाला काही आजार आहे का?',
             'hint' => 'उदा. घरात मधुमेह, यापूर्वी कावीळ, मूतखडा — किंवा "नाही".'],
            ['id' => 'ayurvedic_history', 'q' => 'तुम्ही यापूर्वी आयुर्वेदिक उपचार, पंचकर्म, किंवा हर्बल उपाय केले आहेत का? काय फायदा झाला?',
             'hint' => 'उदा. "अश्वगंधा घेतली — बरे वाटले", "पंचकर्म केले", किंवा "नाही, प्रथमच".'],
        ],
    ],

    // ── ADAPTIVE FOLLOW-UP — built dynamically below ─────────────────────────
    'follow_up' => [
        'en' => [], // populated below
        'mr' => [], // populated below
    ],
];

// ── Extract previous chief complaint for adaptive follow-up ───────────────────
$prevComplaint = '';
if ($isFollowUp && isset($prevData) && is_array($prevData)) {
    foreach ($prevData as $qa) {
        $qLower = strtolower($qa['question'] ?? '');
        if (str_contains($qLower, 'main health concern') || str_contains($qLower, 'मुख्य आरोग्य')) {
            $prevComplaint = trim($qa['answer'] ?? '');
            break;
        }
    }
}
$prevComplaintShort = $prevComplaint
    ? '"' . mb_substr($prevComplaint, 0, 80) . (mb_strlen($prevComplaint) > 80 ? '…' : '') . '"'
    : 'your previous concern';
$prevComplaintMr = $prevComplaint
    ? '"' . mb_substr($prevComplaint, 0, 80) . '"'
    : 'तुमची मागील समस्या';

// Visit count (for summary labelling)
$visitCountRow = sqlQuery(
    "SELECT COUNT(*) as cnt FROM ai_intake_session WHERE pid = ? AND status = 'completed'",
    [$pid]
);
$visitCount = (int)($visitCountRow['cnt'] ?? 0) + 1;

// Build adaptive English follow-up questions
$questionSets['follow_up']['en'] = [
    ['id' => 'followup_same_or_new', 'q' => 'Are you here for ' . $prevComplaintShort . ' again, or is this a new concern?',
     'hint' => 'Say "same issue" or briefly describe the new concern.'],
    ['id' => 'symptoms_change',      'q' => 'Since your last visit, how have your symptoms changed?',
     'hint' => 'E.g. "Much better", "Same as before", "Got worse — now also have fever".'],
    ['id' => 'treatment_response',   'q' => 'Did the treatment or advice from your last visit help? What did you try?',
     'hint' => 'E.g. "Took the tablets, felt better" or "Did not help much".'],
    ['id' => 'new_symptoms',         'q' => 'Any new symptoms since your last visit?',
     'hint' => 'E.g. "Now also have swelling" or "No new symptoms".'],
    ['id' => 'new_meds',             'q' => 'Any new medications or supplements since your last visit?',
     'hint' => 'List any new medicines — or say "None".'],
    ['id' => 'current_severity',     'q' => 'On a scale of 1 to 10, how is your condition today compared to last time?',
     'hint' => '1 = completely fine, 10 = much worse than before.'],
    ['id' => 'additional_concerns',  'q' => 'Anything else you would like your doctor to know today?',
     'hint' => 'Lifestyle changes, stress, diet, family events — anything at all.'],
];

// Build adaptive Marathi follow-up questions
$questionSets['follow_up']['mr'] = [
    ['id' => 'followup_same_or_new', 'q' => 'तुम्ही ' . $prevComplaintMr . ' साठी पुन्हा आला आहात, की नवीन समस्या आहे?',
     'hint' => '"तीच समस्या" किंवा नवीन समस्या थोडक्यात सांगा.'],
    ['id' => 'symptoms_change',      'q' => 'मागच्या भेटीपासून तुमच्या लक्षणांमध्ये काय बदल झाला?',
     'hint' => 'उदा. "खूप बरे वाटते", "तसेच आहे", "त्रास वाढला — आता ताप पण आहे".'],
    ['id' => 'treatment_response',   'q' => 'मागच्या भेटीत दिलेल्या उपचाराने फायदा झाला का? काय केले ते सांगा.',
     'hint' => 'उदा. "गोळ्या घेतल्या, बरे वाटले" किंवा "फारसा फायदा झाला नाही".'],
    ['id' => 'new_symptoms',         'q' => 'मागच्या भेटीनंतर काही नवीन लक्षणे आली आहेत का?',
     'hint' => 'उदा. "आता सूज पण आहे" किंवा "नाही, नवीन काही नाही".'],
    ['id' => 'new_meds',             'q' => 'मागच्या भेटीनंतर काही नवीन औषधे किंवा पूरक आहार सुरू केले आहेत का?',
     'hint' => 'नवीन औषधे सांगा — किंवा "नाही".'],
    ['id' => 'current_severity',     'q' => '१ ते १० च्या प्रमाणात, आज तुमची स्थिती मागच्या भेटीपेक्षा कशी आहे?',
     'hint' => '१ = पूर्णपणे बरे, १० = खूपच जास्त त्रास.'],
    ['id' => 'additional_concerns',  'q' => 'आज डॉक्टरांना आणखी काही सांगायचे आहे का?',
     'hint' => 'जीवनशैली, ताण, आहार, किंवा इतर कोणतीही गोष्ट.'],
];

if ($isFollowUp) {
    $questions = $questionSets['follow_up'][$lang] ?? $questionSets['follow_up']['en'];
} else {
    $qSet      = $questionSets[$pathway] ?? $questionSets['allopathic'];
    $questions = $qSet[$lang]            ?? $qSet['en'];
}

$totalQ        = count($questions);
$pathwayLabel  = ucfirst($pathway);
$pathwayIcon   = $pathway === 'ayurvedic' ? '🌿' : '🏥';
$questionsJson = json_encode($questions, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

?><!DOCTYPE html>
<html lang="<?= htmlspecialchars($t['html_lang']) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($t['interview_title']) ?> — AI Intake</title>
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
            --error:          #e53935;
            --radius-card:    20px;
            --radius-inner:   12px;
            --shadow-card:    0 8px 40px rgba(44,156,212,.18),0 2px 8px rgba(0,0,0,.06);
            --transition:     .22s cubic-bezier(.4,0,.2,1);
            --font:           'Segoe UI',system-ui,-apple-system,sans-serif;
        }
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
        html,body { min-height:100%; font-family:var(--font); background:var(--brand-gradient); color:var(--text-primary); }
        body::before { content:''; position:fixed; width:500px; height:500px; border-radius:50%; top:-120px; left:-120px; background:#00d4ff; opacity:.1; animation:blob 18s ease-in-out infinite; pointer-events:none; }
        @keyframes blob { 0%,100%{transform:scale(1) translate(0,0)} 50%{transform:scale(1.1) translate(30px,-20px)} }

        .page-wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; padding:28px 16px 48px; }

        /* Progress bar */
        .topbar { width:100%; max-width:900px; margin-bottom:20px; }
        .topbar-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .pathway-pill { display:inline-flex; align-items:center; gap:6px; background:rgba(255,255,255,.18); color:#fff; border:1px solid rgba(255,255,255,.35); border-radius:50px; padding:5px 14px; font-size:.8rem; font-weight:600; }
        .step-label { color:rgba(255,255,255,.8); font-size:.78rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
        .progress-track { height:5px; background:rgba(255,255,255,.25); border-radius:3px; overflow:hidden; }
        .progress-fill { height:100%; background:#fff; border-radius:3px; transition:width .5s cubic-bezier(.4,0,.2,1); }

        /* Layout */
        .main-grid { width:100%; max-width:900px; display:grid; grid-template-columns:1fr 300px; gap:18px; }
        @media(max-width:720px) { .main-grid { grid-template-columns:1fr; } }

        /* Interview card */
        .interview-card { background:var(--surface-glass); backdrop-filter:blur(16px); border:1px solid rgba(255,255,255,.6); border-radius:var(--radius-card); box-shadow:var(--shadow-card); display:flex; flex-direction:column; overflow:hidden; }

        /* Speech bubble */
        .bubble-wrap { padding:28px 28px 0; }
        .bubble { background:linear-gradient(135deg,#e8f4fd,#d4edf9); border:1.5px solid rgba(44,156,212,.25); border-radius:16px 16px 16px 4px; padding:18px 22px; }
        .q-number { font-size:.72rem; font-weight:700; color:var(--brand-primary); text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
        .q-text { font-size:1.05rem; font-weight:600; color:var(--text-primary); line-height:1.4; display:inline; }
        .q-hint { font-size:.8rem; color:var(--text-muted); margin-top:6px; }
        .listen-btn { background:transparent; border:none; color:var(--brand-primary); font-size:1.1rem; cursor:pointer; padding:4px 8px; border-radius:8px; transition:background var(--transition); vertical-align:middle; margin-left:6px; }
        .listen-btn:hover { background:rgba(44,156,212,.12); }
        .listen-btn.playing { animation:pulseBlue 1.5s infinite; }
        @keyframes pulseBlue { 0%,100%{background:transparent} 50%{background:rgba(44,156,212,.2)} }

        /* Answer area */
        .answer-section { padding:20px 28px; display:flex; flex-direction:column; gap:14px; flex:1; }
        .answer-label { font-size:.78rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.04em; display:flex; align-items:center; justify-content:space-between; }
        .answer-textarea {
            width:100%; min-height:88px; padding:13px 16px;
            border:2px solid var(--border); border-radius:var(--radius-inner);
            font-size:.95rem; font-family:var(--font); color:var(--text-primary);
            background:#f8fcff; resize:vertical; outline:none;
            transition:border-color var(--transition), box-shadow var(--transition);
        }
        .answer-textarea:focus { border-color:var(--brand-primary); box-shadow:0 0 0 3px rgba(44,156,212,.12); background:#fff; }
        .answer-textarea.listening { border-color:#e53935; box-shadow:0 0 0 3px rgba(229,57,53,.12); }

        /* Mic button (inline in tap mode) */
        .mic-row { display:flex; align-items:center; gap:10px; }
        .mic-inline-btn {
            width:44px; height:44px; border-radius:50%; border:2px solid var(--border);
            background:#f4fafd; color:var(--brand-primary); font-size:1.3rem;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            transition:all .2s; flex-shrink:0;
        }
        .mic-inline-btn:hover { border-color:var(--brand-primary); background:#e8f4fd; }
        .mic-inline-btn.recording { border-color:#e53935; background:#fdecea; color:#e53935; animation:micBeat .6s ease-in-out infinite alternate; }
        @keyframes micBeat { from{transform:scale(1)} to{transform:scale(1.08)} }
        .mic-inline-btn:disabled { opacity:.4; cursor:not-allowed; }
        .mic-state-label { font-size:.78rem; font-weight:600; color:var(--text-muted); transition:color .2s; }
        .mic-state-label.recording { color:#e53935; }
        .mic-state-label.done      { color:var(--success); }

        /* Voice mode — big centered mic */
        .voice-center { display:flex; flex-direction:column; align-items:center; padding:24px; gap:12px; }
        .voice-big-mic {
            width:100px; height:100px; border-radius:50%;
            background:var(--brand-gradient); border:none; color:#fff; font-size:3rem;
            cursor:pointer; display:flex; align-items:center; justify-content:center;
            box-shadow:0 8px 32px rgba(44,156,212,.4); transition:all .2s; position:relative; outline:none;
        }
        .voice-big-mic::before { content:''; position:absolute; inset:-10px; border-radius:50%; background:rgba(44,156,212,.2); z-index:-1; opacity:0; transition:opacity .3s; }
        .voice-big-mic.recording { background:linear-gradient(135deg,#c62828,#e53935); transform:scale(1.05); }
        .voice-big-mic.recording::before { opacity:1; animation:bigPulse 1.2s infinite; }
        .voice-big-mic:disabled { opacity:.5; cursor:not-allowed; }
        @keyframes bigPulse { 0%{transform:scale(.88);opacity:.8} 100%{transform:scale(1.4);opacity:0} }
        .voice-live-display {
            min-height:60px; font-size:1rem; color:var(--brand-primary); font-style:italic;
            text-align:center; padding:0 12px; line-height:1.5;
            background:#f0f8ff; border-radius:10px; width:100%; display:flex; align-items:center; justify-content:center;
        }
        .voice-interim-text { color:#aaa; }

        /* Alert */
        .alert-inline { width:100%; border-radius:10px; padding:10px 14px; font-size:.85rem; display:none; align-items:center; gap:8px; }
        .alert-inline.visible { display:flex; }
        .alert-error { background:#fdecea; color:#b71c1c; border:1px solid #f5c6c6; }

        /* Nav buttons */
        .nav-row { padding:0 28px 28px; display:flex; gap:12px; }
        .btn { flex:1; padding:14px; border:none; border-radius:var(--radius-inner); font-size:.95rem; font-weight:700; cursor:pointer; position:relative; overflow:hidden; transition:all var(--transition); }
        .btn:active:not(:disabled) { transform:scale(.98); }
        .btn:disabled { opacity:.4; cursor:not-allowed; }
        .btn-primary { background:var(--brand-gradient); color:#fff; box-shadow:0 4px 16px rgba(44,156,212,.35); }
        .btn-primary:not(:disabled):hover { box-shadow:0 6px 24px rgba(44,156,212,.5); }
        .btn-ghost { background:transparent; color:var(--brand-primary); border:1.5px solid var(--brand-primary); }
        .btn-ghost:hover:not(:disabled) { background:rgba(44,156,212,.06); }
        .spinner { display:none; width:16px; height:16px; border:2.5px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; position:absolute; right:16px; top:50%; transform:translateY(-50%); }
        .btn.loading .spinner { display:block; }
        @keyframes spin { to { transform:translateY(-50%) rotate(360deg); } }

        /* Transcript panel */
        .transcript-card { background:var(--surface-glass); backdrop-filter:blur(16px); border:1px solid rgba(255,255,255,.6); border-radius:var(--radius-card); box-shadow:var(--shadow-card); display:flex; flex-direction:column; overflow:hidden; max-height:560px; }
        .transcript-header { padding:18px 20px 12px; border-bottom:1px solid var(--border); font-size:.82rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.05em; display:flex; align-items:center; gap:8px; }
        .transcript-dot { width:8px; height:8px; border-radius:50%; background:var(--success); animation:blink 1.5s ease-in-out infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        .transcript-body { flex:1; overflow-y:auto; padding:16px 20px; display:flex; flex-direction:column; gap:14px; }
        .transcript-body::-webkit-scrollbar { width:5px; }
        .transcript-body::-webkit-scrollbar-thumb { background:#c0d8e8; border-radius:3px; }
        .qa-item { animation:fadeUp .3s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .qa-q { font-size:.74rem; color:var(--text-muted); margin-bottom:3px; font-weight:600; }
        .qa-a { font-size:.88rem; color:var(--text-primary); line-height:1.5; background:#f4fafd; border-radius:8px; padding:8px 12px; border-left:3px solid var(--brand-primary); }
        .transcript-empty { color:var(--text-muted); font-size:.85rem; text-align:center; padding:32px 16px; line-height:1.6; }
        @media(max-width:720px) { .transcript-card { max-height:240px; } }

        /* Follow-up banner */
        .followup-banner { background:#e8f5e9; border:1px solid #c8e6c9; border-radius:12px; padding:16px; margin-bottom:20px; width:100%; max-width:900px; }
        .followup-title  { font-size:1rem; color:#2e7d32; margin-bottom:10px; display:flex; align-items:center; gap:8px; font-weight:700; }
        .followup-scroll { background:#fff; border-radius:8px; padding:12px; max-height:180px; overflow-y:auto; border:1px solid #c8e6c9; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Progress -->
    <div class="topbar">
        <div class="topbar-row">
            <?php if ($isFollowUp): ?>
            <div class="pathway-pill" style="background:rgba(46,125,50,.8);border-color:rgba(46,125,50,1);">🔄 <?= $lang === 'mr' ? 'पाठपुरावा भेट' : 'Follow-Up Visit' ?></div>
            <?php else: ?>
            <div class="pathway-pill"><?= htmlspecialchars($pathwayIcon) ?> <?= htmlspecialchars($pathwayLabel) ?></div>
            <?php endif; ?>
            <span class="step-label" id="step-label"></span>
        </div>
        <div class="progress-track"><div class="progress-fill" id="progress-fill"></div></div>
    </div>

    <?php if ($isFollowUp): ?>
    <div class="followup-banner">
        <div class="followup-title">🔄 <?= htmlspecialchars($t['followup_banner_title']) ?></div>
        <div class="followup-scroll"><?= $prevInterviewHtml ?></div>
    </div>
    <?php endif; ?>

    <div class="main-grid">

        <!-- Interview card -->
        <div class="interview-card">
            <div class="bubble-wrap">
                <div class="bubble">
                    <div class="q-number" id="q-number"></div>
                    <span class="q-text" id="q-text"></span>
                    <button id="btn-listen" class="listen-btn" onclick="playQuestion()" title="Listen" aria-label="Listen to question"><?= htmlspecialchars($t['btn_listen']) ?></button>
                    <div class="q-hint" id="q-hint"></div>
                </div>
            </div>

            <?php if ($interactionMode === 'voice'): ?>
            <!-- Voice Mode: big centered mic -->
            <div class="voice-center">
                <div class="voice-live-display" id="voice-live" aria-live="polite" aria-label="Live transcript">
                    <span id="voice-live-text" class="voice-interim-text"><?= htmlspecialchars($t['status_idle']) ?></span>
                </div>
                <button class="voice-big-mic" id="btn-voice-mic" type="button"
                        onclick="onVoiceMicClick()"
                        aria-label="<?= htmlspecialchars($t['status_idle']) ?>">🎤</button>
                <div class="mic-state-label" id="voice-mic-state"><?= htmlspecialchars($t['status_idle']) ?></div>
                <textarea id="answer-input" style="display:none;" aria-hidden="true"></textarea>
            </div>
            <?php else: ?>
            <!-- Tap Mode: textarea + optional inline mic -->
            <div class="answer-section">
                <div>
                    <label class="answer-label" for="answer-input">
                        <?= $lang === 'mr' ? 'तुमचे उत्तर' : 'Your Answer' ?>
                        <div class="mic-row">
                            <button class="mic-inline-btn" id="btn-mic-inline" type="button"
                                    onclick="onTapMicClick()" title="Speak your answer" aria-label="Speak answer">🎤</button>
                            <span class="mic-state-label" id="tap-mic-state"><?= htmlspecialchars($t['status_idle']) ?></span>
                        </div>
                    </label>
                    <textarea id="answer-input" class="answer-textarea"
                              placeholder="<?= htmlspecialchars($t['answer_placeholder']) ?>"
                              aria-label="Answer input"
                              rows="3"></textarea>
                </div>
                <div id="alert" class="alert-inline alert-error" role="alert">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    <span id="alert-msg"></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="nav-row">
                <button id="btn-prev" class="btn btn-ghost" type="button" onclick="prevQuestion()" disabled>
                    <?= htmlspecialchars($t['btn_prev']) ?>
                </button>
                <button id="btn-next" class="btn btn-primary" type="button" onclick="nextQuestion()">
                    <?= htmlspecialchars($t['btn_next']) ?>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Transcript panel -->
        <div class="transcript-card">
            <div class="transcript-header">
                <div class="transcript-dot" aria-hidden="true"></div>
                <?= htmlspecialchars($t['transcript_heading']) ?>
            </div>
            <div class="transcript-body" id="transcript-body">
                <p class="transcript-empty"><?= htmlspecialchars($t['transcript_empty']) ?></p>
            </div>
        </div>

    </div>
</div>

<script>
/* ─── Config from PHP ──────────────────────────────────────────────────────── */
var TOKEN       = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
var API_BASE    = '<?= htmlspecialchars($apiBase, ENT_QUOTES) ?>';
var TOTAL_Q     = <?= $totalQ ?>;
var SPEECH_LANG = '<?= htmlspecialchars($speechLang, ENT_QUOTES) ?>';
var QUESTIONS   = <?= $questionsJson ?>;
var IS_VOICE_MODE = <?= $interactionMode === 'voice' ? 'true' : 'false' ?>;
var STRINGS = {
    step_of:          '<?= htmlspecialchars(str_replace(['%d'], ['%s'], $t['interview_step_of']), ENT_QUOTES) ?>',
    btn_next:         '<?= htmlspecialchars($t['btn_next'],         ENT_QUOTES) ?>',
    btn_finish:       '<?= htmlspecialchars($t['btn_finish'],       ENT_QUOTES) ?>',
    answer_required:  '<?= htmlspecialchars($t['err_answer_required'], ENT_QUOTES) ?>',
    status_recording: '<?= htmlspecialchars($t['status_recording'], ENT_QUOTES) ?>',
    status_transcribing:'<?= htmlspecialchars($t['status_transcribing'], ENT_QUOTES) ?>',
    status_done:      '<?= htmlspecialchars($t['status_done'],      ENT_QUOTES) ?>',
    status_idle:      '<?= htmlspecialchars($t['status_idle'],      ENT_QUOTES) ?>',
    err_mic_denied:   '<?= htmlspecialchars($t['err_mic_denied'],   ENT_QUOTES) ?>',
    err_mic_notsupported: '<?= htmlspecialchars($t['err_mic_notsupported'], ENT_QUOTES) ?>',
    err_empty_speech: '<?= htmlspecialchars($t['err_empty_speech'], ENT_QUOTES) ?>',
    err_network:      '<?= htmlspecialchars($t['err_network'],      ENT_QUOTES) ?>',
    voice_tap_prompt: '<?= htmlspecialchars($t['voice_tap_prompt'], ENT_QUOTES) ?>',
};

var currentIdx  = 0;
var answers     = [];
var isRecording = false;
var activeRecognition = null;
var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
var conversationActive = false;  // true = auto TTS→ASR loop is running
var conversationPaused = false;

/* ─── Init ─────────────────────────────────────────────────────────────────── */
renderQuestion(0);
if (IS_VOICE_MODE) {
    // Small delay so browser TTS voices have time to load
    setTimeout(startConversation, 1000);
}

/* ─── TTS helper ───────────────────────────────────────────────────── */
function speak(text, onDone) {
    if (!('speechSynthesis' in window)) { if (onDone) onDone(); return; }
    window.speechSynthesis.cancel();
    var utter = new SpeechSynthesisUtterance(text);
    utter.lang = SPEECH_LANG;
    utter.rate = 0.92;
    var voices = window.speechSynthesis.getVoices();
    var match  = voices.find(v => v.lang.startsWith(SPEECH_LANG.split('-')[0]));
    if (match) utter.voice = match;
    if (onDone) utter.onend = onDone;
    
    // Workaround for Chrome bug: speech gets stuck if not resumed
    window.speechSynthesis.resume();
    window.speechSynthesis.speak(utter);
}
if ('speechSynthesis' in window) {
    window.speechSynthesis.onvoiceschanged = function() { window.speechSynthesis.getVoices(); };
}

// Manual replay button (tap mode or user wants to re-hear)
function playQuestion() {
    var btn = document.getElementById('btn-listen');
    btn.classList.add('playing');
    speak(QUESTIONS[currentIdx].q, function() { btn.classList.remove('playing'); });
}

/* ─── VOICE CONVERSATION LOOP ─────────────────────────────────────────────── */
// Aria speaks → auto-listens → captures answer → says "Got it" → next question.
// No tapping required in voice mode.

function startConversation() {
    conversationActive = true;
    conversationPaused = false;
    var intro = 'Hello! I am Aria, your AI health assistant. I will ask you '
        + TOTAL_Q + ' questions about your health today. '
        + 'Please answer each one out loud. Let us begin.';
    speak(intro, function() {
        setTimeout(voiceConversationLoop, 400);
    });
    updateConversationButton();
}

function voiceConversationLoop() {
    if (!conversationActive || conversationPaused) return;

    var liveText = document.getElementById('voice-live-text');
    var q = QUESTIONS[currentIdx].q;

    // Show speaking state
    setVoiceMicState('speaking');
    if (liveText) {
        liveText.className = 'voice-interim-text';
        liveText.textContent = '🔊 ' + q;
    }

    speak(q, function() {
        if (!conversationActive || conversationPaused) return;

        // Auto-start listening immediately after speaking
        setTimeout(function() {
            if (!conversationActive || conversationPaused) return;

            setVoiceMicState('recording');
            if (liveText) { liveText.className = ''; liveText.textContent = ''; }

            runASR(liveText,
                function(answer) {
                    // Got a real answer
                    setVoiceMicState('done');
                    document.getElementById('answer-input').value = answer;
                    if (liveText) liveText.textContent = answer;

                    // Save answer
                    answers[currentIdx] = { question: QUESTIONS[currentIdx].q, answer: answer };
                    addToTranscript(currentIdx, QUESTIONS[currentIdx].q, answer);

                    if (currentIdx < TOTAL_Q - 1) {
                        // Speak confirmation then advance
                        speak('Got it.', function() {
                            currentIdx++;
                            renderQuestion(currentIdx);
                            setTimeout(voiceConversationLoop, 300);
                        });
                    } else {
                        // All questions answered
                        speak('Thank you! I have all your answers. Submitting now.', function() {
                            submitInterview();
                        });
                    }
                },
                function(err) {
                    // No speech / error — say prompt again after brief pause
                    setVoiceMicState('idle');
                    if (liveText) { liveText.className = 'voice-interim-text'; liveText.textContent = 'I did not catch that. Please speak again.'; }
                    speak('Sorry, I did not catch that. Please try again.', function() {
                        setTimeout(function() {
                            if (conversationActive && !conversationPaused) voiceConversationLoop();
                        }, 500);
                    });
                }
            );
        }, 350);
    });
}

function pauseConversation() {
    conversationPaused = true;
    if (isRecording && activeRecognition) { try { activeRecognition.stop(); } catch(e) {} }
    window.speechSynthesis.cancel();
    setVoiceMicState('paused');
    updateConversationButton();
}

function resumeConversation() {
    conversationPaused = false;
    updateConversationButton();
    setTimeout(voiceConversationLoop, 400);
}

function updateConversationButton() {
    var btn = document.getElementById('btn-voice-mic');
    var lbl = document.getElementById('voice-mic-state');
    if (!btn) return;
    if (conversationPaused) {
        btn.textContent = '▶️';
        btn.title = 'Resume';
        if (lbl) lbl.textContent = 'Paused — tap to resume';
    } else {
        btn.textContent = '⏸️';
        btn.title = 'Pause';
        if (lbl) lbl.textContent = 'Aria is running…';
    }
}

/* ─── Render question ──────────────────────────────────────────────────────── */
function renderQuestion(idx) {
    var q  = QUESTIONS[idx];
    var n1 = idx + 1;
    var stepStr = STRINGS.step_of.replace('%s', n1).replace('%s', TOTAL_Q);
    document.getElementById('q-number').textContent   = stepStr;
    document.getElementById('q-text').textContent     = q.q;
    document.getElementById('q-hint').textContent     = q.hint;
    document.getElementById('step-label').textContent = stepStr;
    document.getElementById('answer-input').value     = answers[idx] ? answers[idx].answer : '';
    document.getElementById('btn-prev').disabled      = (idx === 0) || isRecording;
    var btnNext = document.getElementById('btn-next');
    btnNext.textContent = (idx === TOTAL_Q - 1) ? STRINGS.btn_finish : STRINGS.btn_next;
    var sp = document.createElement('span');
    sp.className = 'spinner'; sp.setAttribute('aria-hidden','true');
    btnNext.appendChild(sp);
    document.getElementById('progress-fill').style.width = Math.round(((idx + 1) / TOTAL_Q) * 100) + '%';
    clearAlert();

    // Reset voice state
    if (IS_VOICE_MODE) {
        setVoiceMicState('idle');
        document.getElementById('voice-live-text').textContent = STRINGS.status_idle;
        document.getElementById('voice-live-text').className   = 'voice-interim-text';
    } else {
        if (document.getElementById('tap-mic-state')) {
            setTapMicState('idle');
        }
        document.getElementById('answer-input').focus();
    }
}

/* ─── Navigation ────────────────────────────────────────────────────────────── */
function nextQuestion() {
    // In voice mode, conversation loop handles advancing; manual next just saves + advances
    if (isRecording) return;
    clearAlert();
    var answer = document.getElementById('answer-input').value.trim();
    if (!answer) { showAlert(STRINGS.answer_required); return; }
    answers[currentIdx] = { question: QUESTIONS[currentIdx].q, answer: answer };
    addToTranscript(currentIdx, QUESTIONS[currentIdx].q, answer);
    if (currentIdx < TOTAL_Q - 1) {
        currentIdx++;
        renderQuestion(currentIdx);
        if (IS_VOICE_MODE && !conversationPaused) {
            // Resume conversation loop at new question
            setTimeout(voiceConversationLoop, 400);
        }
    } else {
        submitInterview();
    }
}

function prevQuestion() {
    if (isRecording || currentIdx === 0) return;
    // Pause conversation so it doesn't auto-advance while user reviews
    if (IS_VOICE_MODE && conversationActive) pauseConversation();
    var answer = document.getElementById('answer-input').value.trim();
    if (answer) answers[currentIdx] = { question: QUESTIONS[currentIdx].q, answer: answer };
    currentIdx--;
    renderQuestion(currentIdx);
    // Replay previous question aloud so user knows where they are
    if (IS_VOICE_MODE) setTimeout(function() { speak(QUESTIONS[currentIdx].q); }, 300);
}

/* ─── Transcript panel ──────────────────────────────────────────────────────── */
function addToTranscript(idx, question, answer) {
    var body = document.getElementById('transcript-body');
    var empty = body.querySelector('.transcript-empty');
    if (empty) empty.remove();
    var existing = document.getElementById('ti-' + idx);
    if (existing) {
        existing.querySelector('.qa-a').textContent = answer;
    } else {
        var div = document.createElement('div');
        div.className = 'qa-item'; div.id = 'ti-' + idx;
        div.innerHTML = '<div class="qa-q">Q' + (idx+1) + ': ' + escHtml(question) + '</div>'
                      + '<div class="qa-a">' + escHtml(answer) + '</div>';
        body.appendChild(div);
        body.scrollTop = body.scrollHeight;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════════
   REAL ASR — Web Speech API
   HOW IT WORKS:
   - continuous=false → browser fires onend automatically when silence detected
   - We cancel TTS before starting so the mic does NOT pick up the computer's voice
   - accumulatedTranscript tracks ALL final text across multiple result events
   - Tap mic again to stop manually before silence is detected
   ═══════════════════════════════════════════════════════════════════════════════ */

var accumulatedTranscript = ''; // Track real transcript in JS, not DOM

function runASR(liveTextEl, onFinal, onError) {
    if (!SpeechRecognition) {
        onError('Speech recognition is not supported in this browser. Please use Google Chrome.');
        return;
    }
    // If already recording, stop it (tap-to-stop)
    if (isRecording) {
        if (activeRecognition) { try { activeRecognition.stop(); } catch(e) {} }
        return;
    }

    // ── CRITICAL: stop TTS before opening mic, or mic records the speaker ──
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();

    isRecording = true;
    accumulatedTranscript = '';

    activeRecognition = new SpeechRecognition();
    activeRecognition.lang            = SPEECH_LANG;
    activeRecognition.interimResults  = true;  // show words as you speak
    activeRecognition.continuous      = false; // browser auto-stops on silence → reliable onend
    activeRecognition.maxAlternatives = 1;

    var handled = false;

    activeRecognition.onstart = function() {
        // Mic is confirmed open
        if (liveTextEl) liveTextEl.textContent = '🎙 Listening...';
    };

    activeRecognition.onresult = function(event) {
        var interim = '';
        // Accumulate finals; interim is just for live display
        for (var i = event.resultIndex; i < event.results.length; i++) {
            var t = event.results[i][0].transcript;
            if (event.results[i].isFinal) {
                accumulatedTranscript += t + ' ';
            } else {
                interim += t;
            }
        }
        // Update live display
        var display = accumulatedTranscript.trim();
        if (liveTextEl) {
            liveTextEl.innerHTML = display
                ? escHtml(display) + '<span class="voice-interim-text"> ' + escHtml(interim) + '</span>'
                : '<span class="voice-interim-text">' + escHtml(interim) + '</span>';
        }
        // Mirror into textarea
        var input = document.getElementById('answer-input');
        if (input) input.value = (display + ' ' + interim).trim();
    };

    activeRecognition.onend = function() {
        isRecording = false;
        if (handled) return;
        handled = true;

        var finalText = accumulatedTranscript.trim();
        // Also check textarea as a fallback
        if (!finalText) {
            var input = document.getElementById('answer-input');
            finalText = input ? input.value.trim() : '';
        }
        if (!finalText) {
            onError('No speech detected. Please try again and speak clearly.');
            return;
        }
        onFinal(finalText);
    };

    activeRecognition.onerror = function(event) {
        if (handled) return;
        handled = true;
        isRecording = false;
        var msg = event.error;
        if (msg === 'not-allowed' || msg === 'service-not-allowed') {
            onError('Microphone access was denied. Please allow microphone in browser settings and reload.');
        } else if (msg === 'network') {
            onError('Speech recognition needs an internet connection (Google speech servers). Check your connection.');
        } else if (msg === 'no-speech') {
            onError('No speech was heard. Please tap mic and speak louder.');
        } else if (msg === 'aborted') {
            // User stopped manually — not an error, onend will fire
        } else {
            onError('Mic error: ' + msg + '. Make sure you are on Chrome and allow microphone.');
        }
    };

    try {
        activeRecognition.start();
    } catch(e) {
        isRecording = false;
        if (!handled) { handled = true; onError('Could not start microphone: ' + e.message); }
    }
}

/* ─── Voice mode mic button — now a PAUSE / RESUME toggle ───────────────── */
function onVoiceMicClick() {
    if (!conversationActive) {
        // Conversation not started yet — start it
        startConversation();
        return;
    }
    if (conversationPaused) {
        resumeConversation();
    } else {
        pauseConversation();
    }
}

function setVoiceMicState(state) {
    var micBtn  = document.getElementById('btn-voice-mic');
    var stateEl = document.getElementById('voice-mic-state');
    if (!micBtn) return;
    micBtn.classList.remove('recording');
    if (state === 'speaking') {
        micBtn.textContent = '⏸️';
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = '🔊 Aria is speaking…'; stateEl.className = 'mic-state-label'; }
    } else if (state === 'recording') {
        micBtn.classList.add('recording');
        micBtn.textContent = '⏸️';
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = '🔴 Listening… speak now'; stateEl.className = 'mic-state-label recording'; }
    } else if (state === 'done') {
        micBtn.textContent = '⏸️';
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = '✅ Got it!'; stateEl.className = 'mic-state-label done'; }
    } else if (state === 'paused') {
        micBtn.textContent = '▶️';
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = '⏸️ Paused — tap to resume'; stateEl.className = 'mic-state-label'; }
    } else {
        micBtn.textContent = '🎤';
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = STRINGS.status_idle; stateEl.className = 'mic-state-label'; }
    }
}

/* ─── Tap mode inline mic click ─────────────────────────────────────────────── */
function onTapMicClick() {
    // If recording → stop
    if (isRecording) {
        if (activeRecognition) { try { activeRecognition.stop(); } catch(e) {} }
        return;
    }
    var micBtn  = document.getElementById('btn-mic-inline');
    var stateEl = document.getElementById('tap-mic-state');
    var input   = document.getElementById('answer-input');

    setTapMicState('recording');
    input.classList.add('listening');

    runASR(null,
        function(text) {
            setTapMicState('done');
            input.classList.remove('listening');
            input.value = text;
        },
        function(err) {
            setTapMicState('idle');
            input.classList.remove('listening');
            showAlert(err);
        }
    );
}

function setTapMicState(state) {
    var micBtn  = document.getElementById('btn-mic-inline');
    var stateEl = document.getElementById('tap-mic-state');
    if (!micBtn) return;
    micBtn.classList.remove('recording');
    if (state === 'recording') {
        micBtn.classList.add('recording');
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = '🔴 Recording... (tap to stop)'; stateEl.className = 'mic-state-label recording'; }
    } else if (state === 'done') {
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = STRINGS.status_done; stateEl.className = 'mic-state-label done'; }
    } else {
        micBtn.disabled = false;
        if (stateEl) { stateEl.textContent = STRINGS.status_idle; stateEl.className = 'mic-state-label'; }
    }
}

/* ─── Submit interview ─────────────────────────────────────────────────────── */
function submitInterview() {
    var btn = document.getElementById('btn-next');
    btn.disabled = true; btn.classList.add('loading');

    fetch(API_BASE + '/save-interview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: TOKEN, answers: answers }),
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        btn.classList.remove('loading');
        if (d.success) {
            window.location.href = 'document-upload.php?token=' + encodeURIComponent(TOKEN);
        } else {
            btn.disabled = false;
            showAlert(d.error || STRINGS.err_network);
        }
    })
    .catch(function() {
        btn.classList.remove('loading'); btn.disabled = false;
        showAlert(STRINGS.err_network);
    });
}

/* ─── Helpers ──────────────────────────────────────────────────────────────── */
function showAlert(msg) {
    var el = document.getElementById('alert');
    if (!el) { alert(msg); return; }
    document.getElementById('alert-msg').textContent = msg;
    el.classList.add('visible');
}
function clearAlert() {
    var el = document.getElementById('alert');
    if (el) el.classList.remove('visible');
}
function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
