<?php

/**
 * AI Intake — Module Bootstrap (Event Subscriber)
 *
 * Subscribes to OpenEMR's patient demographics RenderEvent to inject
 * the "AI Intake" card into the patient chart page.
 *
 * Uses RenderEvent::EVENT_SECTION_LIST_RENDER_BEFORE (same as oe-module-dashboard-context)
 * and outputs HTML directly — avoids Twig template path issues since the module
 * templates dir is not in OpenEMR's global Twig loader paths.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\AiIntake;

use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Menu\PatientMenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use stdClass;

class Bootstrap
{
    private const MODULE_WEB_PATH = '/interface/modules/custom_modules/oe-module-ai-intake';

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    // -------------------------------------------------------------------------

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(
            PatientMenuEvent::MENU_UPDATE,
            $this->addPatientMenuTab(...)
        );
    }

    // -------------------------------------------------------------------------

    public function addPatientMenuTab(PatientMenuEvent $menuEvent): void
    {
        $existingMenu = $menuEvent->getMenu();

        $menuItem = new stdClass();
        $menuItem->label = "AI Intake";
        $menuItem->url = OEGlobalsBag::getInstance()->getWebRoot() . self::MODULE_WEB_PATH . "/public/ai-intake-tab.php";
        $menuItem->menu_id = "mod_ai_intake";
        $menuItem->target = "mod";
        $menuItem->acl_req = ["patients", "med"]; // Requires medical access

        $existingMenu[] = $menuItem;

        $menuEvent->setMenu($existingMenu);
    }

    // -------------------------------------------------------------------------
    // Card HTML renderer
    // -------------------------------------------------------------------------

    private function renderCardHtml(
        int $pid,
        ?array $s,        // ai_intake_session row or null
        string $webroot,
        string $modPath,
        string $cardId
    ): string {
        $sessionId    = (int) ($s['id']                   ?? 0);
        $pathway      = htmlspecialchars($s['pathway']    ?? '', ENT_QUOTES);
        $summaryText  = htmlspecialchars($s['summary_text'] ?? '', ENT_QUOTES);
        $summaryRaw   = $s['summary_text'] ?? '';
        $redFlag      = (bool) ($s['red_flag']            ?? false);
        $encounterId  = (int) ($s['openemr_encounter_id'] ?? 0);
        $createdAt    = $s['created_at'] ?? '';

        ob_start();
        ?>
<!-- ═══ AI INTAKE CARD ══════════════════════════════════════════════════════ -->
<div class="col-12 m-0 p-0 px-2" id="ai-intake-wrapper-<?= (int)$pid ?>">
<section class="card">
  <div class="card-body p-1">
    <h6 class="card-title mb-0 d-flex p-1 justify-content-between">
      <a class="text-left font-weight-bolder" href="#"
         data-toggle="collapse" data-target="#<?= htmlspecialchars($cardId, ENT_QUOTES) ?>"
         aria-expanded="true">
        🎙 AI Intake
        <i class="ml-1 fa fa-fw fa-compress"
           data-target="#<?= htmlspecialchars($cardId, ENT_QUOTES) ?>"></i>
      </a>
    </h6>
    <div id="<?= htmlspecialchars($cardId, ENT_QUOTES) ?>" class="card-text collapse show">
      <div class="clearfix pt-2 px-2 pb-2">
<?php if (!$s): ?>
        <div style="padding:14px;color:#8899a6;font-size:.87rem;text-align:center;">
          <span style="font-size:1.5rem;display:block;margin-bottom:6px;">🎙️</span>
          No AI Intake session found for this patient.
        </div>
<?php else: ?>

        <?php /* ── Red-flag banner ──────────────────────────────────────── */ ?>
        <?php if ($redFlag): ?>
        <div style="background:#ffebee;border:1.5px solid #ef9a9a;border-radius:8px;
                    padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
          <span style="font-size:1.1rem;">🚨</span>
          <span style="font-weight:700;color:#c62828;font-size:.88rem;">
            Priority Alert: Possible clinical red flag detected. Please review immediately.
          </span>
        </div>
        <?php endif; ?>

        <?php /* ── Session meta pills ───────────────────────────────────── */ ?>
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
          <span style="background:#e3f2fd;color:#1565c0;border-radius:6px;
                       padding:3px 10px;font-size:.74rem;font-weight:700;">
            🏥 <?= htmlspecialchars(ucfirst($pathway), ENT_QUOTES) ?>
          </span>
          <?php if ($createdAt): ?>
          <span style="background:#f3f3f3;color:#555;border-radius:6px;
                       padding:3px 10px;font-size:.74rem;">
            📅 <?= htmlspecialchars(date('d M Y, h:i A', strtotime($createdAt)), ENT_QUOTES) ?>
          </span>
          <?php endif; ?>
          <?php if ($encounterId): ?>
          <span style="background:#e8f5e9;color:#2e7d32;border-radius:6px;
                       padding:3px 10px;font-size:.74rem;font-weight:700;">
            ✓ Enc #<?= (int)$encounterId ?>
          </span>
          <?php endif; ?>
        </div>

        <?php /* ── Pre-visit summary ──────────────────────────────────── */ ?>
        <?php if ($summaryRaw): ?>
        <div style="font-size:.77rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.05em;color:#8899a6;margin-bottom:8px;">
          Pre-Visit Intake Summary
        </div>
        <div id="aic-summary-display-<?= $sessionId ?>"
             style="background:#f8fcff;border:1.5px solid #d0e8f5;border-radius:10px;
                    padding:12px 14px;font-size:.86rem;line-height:1.75;color:#1a2a3a;
                    white-space:pre-wrap;font-family:'Segoe UI',system-ui,sans-serif;
                    max-height:240px;overflow-y:auto;margin-bottom:10px;"
        ><?= htmlspecialchars($summaryRaw, ENT_QUOTES | ENT_SUBSTITUTE) ?></div>

        <details style="margin-bottom:16px;">
          <summary style="cursor:pointer;font-size:.8rem;font-weight:700;color:#2c9cd4;padding:4px 0;">
            ✏ Edit Pre-Visit Note
          </summary>
          <div style="margin-top:10px;">
            <textarea id="aic-edit-ta-<?= $sessionId ?>"
                      style="width:100%;min-height:160px;padding:10px 12px;box-sizing:border-box;
                             border:1.5px solid #d0e8f5;border-radius:8px;font-size:.85rem;
                             font-family:inherit;color:#1a2a3a;background:#fff;resize:vertical;
                             outline:none;line-height:1.7;"
            ><?= htmlspecialchars($summaryRaw, ENT_QUOTES | ENT_SUBSTITUTE) ?></textarea>
            <div id="aic-edit-alert-<?= $sessionId ?>"
                 style="display:none;margin-top:8px;background:#fdecea;color:#b71c1c;
                        border-radius:6px;padding:8px 12px;font-size:.82rem;"></div>
            <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
              <button onclick="aicSavePreVisit(<?= $sessionId ?>, '<?= htmlspecialchars($webroot, ENT_QUOTES) ?>')"
                      id="aic-edit-btn-<?= $sessionId ?>"
                      style="padding:8px 18px;background:linear-gradient(135deg,#1a6fa0,#2c9cd4);
                             color:#fff;border:none;border-radius:8px;font-size:.84rem;
                             font-weight:700;cursor:pointer;">
                Confirm &amp; Save
              </button>
              <span id="aic-edit-ok-<?= $sessionId ?>"
                    style="font-size:.8rem;color:#2e7d32;display:none;">✓ Saved</span>
            </div>
          </div>
        </details>
        <?php else: ?>
        <p style="font-size:.86rem;color:#8899a6;font-style:italic;">
          Pre-visit summary not yet generated.
        </p>
        <?php endif; ?>

        <?php /* ── Ambient Recording Section ──────────────────────────── */ ?>
        <div style="border-top:1px solid #e8f0f5;margin-top:6px;padding-top:14px;">
          <div style="font-size:.77rem;font-weight:700;text-transform:uppercase;
                      letter-spacing:.05em;color:#8899a6;margin-bottom:10px;">
            🎙 Ambient Consultation Recording
          </div>

          <!-- State 0: idle -->
          <div id="aic-rec-idle-<?= $sessionId ?>">
            <button onclick="aicRecStart(<?= $sessionId ?>)"
                    style="width:100%;padding:10px;background:linear-gradient(135deg,#1a6fa0,#2c9cd4);
                           color:#fff;border:none;border-radius:8px;font-size:.86rem;
                           font-weight:700;cursor:pointer;">
              ▶ Start Consultation Recording
            </button>
            <p style="font-size:.74rem;color:#aab8c2;margin-top:5px;text-align:center;">
              Demo mode — no audio captured, SOAP note auto-generated on stop.
            </p>
          </div>

          <!-- State 1: recording -->
          <div id="aic-rec-active-<?= $sessionId ?>" style="display:none;">
            <div style="display:flex;align-items:center;justify-content:space-between;
                        background:#fff8f8;border:1.5px solid #ffcdd2;border-radius:10px;
                        padding:9px 14px;">
              <span style="font-size:.88rem;font-weight:700;color:#c62828;">
                <span class="aic-dot" style="display:inline-block;width:10px;height:10px;
                       border-radius:50%;background:#e53935;margin-right:6px;vertical-align:middle;
                       animation:aicPulse 1.2s ease-in-out infinite;"></span>
                Recording… <span id="aic-timer-<?= $sessionId ?>">00:00</span>
              </span>
              <button onclick="aicRecStop(<?= $sessionId ?>, <?= $pid ?>, '<?= htmlspecialchars($webroot, ENT_QUOTES) ?>')"
                      style="padding:6px 14px;background:linear-gradient(135deg,#c62828,#e53935);
                             color:#fff;border:none;border-radius:7px;font-size:.82rem;
                             font-weight:700;cursor:pointer;">
                ■ Stop &amp; Generate SOAP
              </button>
            </div>
          </div>

          <!-- State 2: generating -->
          <div id="aic-rec-gen-<?= $sessionId ?>"
               style="display:none;text-align:center;padding:18px 0;">
            <span style="display:inline-block;width:16px;height:16px;border-radius:50%;
                         border:2.5px solid rgba(44,156,212,.2);border-top-color:#2c9cd4;
                         animation:aicSpin .75s linear infinite;margin-right:6px;
                         vertical-align:middle;"></span>
            <span style="font-size:.87rem;color:#4a6070;font-weight:600;">Generating SOAP note…</span>
          </div>

          <!-- State 3: SOAP ready -->
          <div id="aic-soap-section-<?= $sessionId ?>" style="display:none;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
              <span style="font-size:.77rem;font-weight:700;text-transform:uppercase;
                           letter-spacing:.05em;color:#8899a6;">Generated SOAP Note</span>
              <span style="font-size:.7rem;background:#fff8e1;color:#f57f17;
                           border:1px solid #ffe082;border-radius:4px;padding:2px 8px;
                           font-weight:600;">⚠ MOCK ASR</span>
            </div>
            <textarea id="aic-soap-ta-<?= $sessionId ?>"
                      style="width:100%;min-height:230px;padding:10px 12px;box-sizing:border-box;
                             border:1.5px solid #d0e8f5;border-radius:8px;font-size:.85rem;
                             font-family:inherit;color:#1a2a3a;background:#fff;resize:vertical;
                             outline:none;line-height:1.7;"></textarea>
            <div id="aic-soap-alert-<?= $sessionId ?>"
                 style="display:none;margin-top:8px;background:#fdecea;color:#b71c1c;
                        border-radius:6px;padding:8px 12px;font-size:.82rem;"></div>
            <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
              <button id="aic-soap-btn-<?= $sessionId ?>"
                      onclick="aicSoapSave(<?= $sessionId ?>, <?= $pid ?>, '<?= htmlspecialchars($webroot, ENT_QUOTES) ?>')"
                      style="padding:8px 18px;background:linear-gradient(135deg,#1a6fa0,#2c9cd4);
                             color:#fff;border:none;border-radius:8px;font-size:.84rem;
                             font-weight:700;cursor:pointer;">
                💾 Save to Chart
              </button>
              <button onclick="aicRecReset(<?= $sessionId ?>)"
                      style="padding:8px 16px;background:transparent;color:#2c9cd4;
                             border:1.5px solid #2c9cd4;border-radius:8px;
                             font-size:.82rem;cursor:pointer;">
                ↩ Record Again
              </button>
              <span id="aic-soap-ok-<?= $sessionId ?>"
                    style="font-size:.8rem;color:#2e7d32;display:none;"></span>
            </div>
          </div>

        </div><?php /* end ambient section */ ?>
<?php endif; /* end if $s */ ?>
      </div>
    </div>
  </div>
</section>
</div>
<!-- ═══ END AI INTAKE CARD ═══════════════════════════════════════════════════ -->

<style>
@keyframes aicPulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.35);opacity:.6} }
@keyframes aicSpin  { to{transform:rotate(360deg)} }
</style>

<script>
(function() {
var _SID  = <?= $sessionId ?>;
var _PID  = <?= $pid ?>;
var _ROOT = '<?= htmlspecialchars($webroot, ENT_QUOTES) ?>';
var _MOD  = _ROOT + '/interface/modules/custom_modules/oe-module-ai-intake';
var _T    = null; // timer interval
var _SEC  = 0;

/* ── Pre-visit note edit ─────────────────────────────────────────────── */
window.aicSavePreVisit = function(sid, root) {
  var btn = document.getElementById('aic-edit-btn-' + sid);
  var al  = document.getElementById('aic-edit-alert-' + sid);
  var ok  = document.getElementById('aic-edit-ok-' + sid);
  var txt = (document.getElementById('aic-edit-ta-' + sid) || {}).value || '';
  txt = txt.trim();
  if (!txt) { al.style.display='block'; al.textContent='Note cannot be empty.'; return; }
  al.style.display = 'none';
  btn.disabled = true; btn.textContent = 'Saving…';
  fetch(root + '/interface/modules/custom_modules/oe-module-ai-intake/api/update-note.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({session_id:sid, summary_text:txt}),
    credentials:'same-origin',
  }).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.textContent='Confirm & Save';
    if (d.success) {
      var disp = document.getElementById('aic-summary-display-' + sid);
      if (disp) disp.textContent = txt;
      ok.style.display='inline';
      setTimeout(()=>{ok.style.display='none';}, 3000);
    } else { al.style.display='block'; al.textContent=d.error||'Failed to save.'; }
  }).catch(()=>{ btn.disabled=false; btn.textContent='Confirm & Save';
    al.style.display='block'; al.textContent='Network error.'; });
};

/* ── Recording widget ────────────────────────────────────────────────── */
window.aicRecStart = function(sid) {
  _SEC = 0;
  document.getElementById('aic-rec-idle-'   + sid).style.display = 'none';
  document.getElementById('aic-rec-active-' + sid).style.display = 'block';
  document.getElementById('aic-soap-section-'+ sid).style.display= 'none';
  _T = setInterval(function() {
    _SEC++;
    var m=Math.floor(_SEC/60), s=_SEC%60;
    var el = document.getElementById('aic-timer-' + sid);
    if (el) el.textContent = (m<10?'0':'')+m+':'+(s<10?'0':'')+s;
  }, 1000);
};

window.aicRecStop = function(sid, pid, root) {
  clearInterval(_T);
  document.getElementById('aic-rec-active-' + sid).style.display = 'none';
  document.getElementById('aic-rec-gen-'    + sid).style.display = 'block';
  /* MOCK: 2-second fake generation, then show SOAP note */
  setTimeout(function() {
    document.getElementById('aic-rec-gen-'    + sid).style.display = 'none';
    document.getElementById('aic-soap-section-'+ sid).style.display= 'block';
    var ta = document.getElementById('aic-soap-ta-' + sid);
    if (ta) ta.value = aicMockSoap();
  }, 2000);
};

window.aicRecReset = function(sid) {
  document.getElementById('aic-soap-section-'+ sid).style.display = 'none';
  document.getElementById('aic-rec-idle-'   + sid).style.display  = 'block';
  _SEC = 0;
};

window.aicSoapSave = function(sid, pid, root) {
  var btn  = document.getElementById('aic-soap-btn-' + sid);
  var al   = document.getElementById('aic-soap-alert-' + sid);
  var ok   = document.getElementById('aic-soap-ok-' + sid);
  var text = (document.getElementById('aic-soap-ta-' + sid) || {}).value || '';
  text = text.trim();
  if (!text) { al.style.display='block'; al.textContent='SOAP note cannot be empty.'; return; }
  al.style.display='none';
  btn.disabled=true; btn.textContent='Saving…';
  fetch(root + '/interface/modules/custom_modules/oe-module-ai-intake/api/save-consultation-note.php', {
    method:'POST', headers:{'Content-Type':'application/json'},
    body: JSON.stringify({session_id:sid, patient_id:pid, soap_note_text:text}),
    credentials:'same-origin',
  }).then(r=>r.json()).then(d=>{
    btn.disabled=false; btn.textContent='💾 Save to Chart';
    if (d.success) {
      ok.style.display='inline';
      ok.textContent = d.form_note_id ? '✓ Saved — Note #'+d.form_note_id : '✓ Saved';
      btn.disabled=true; btn.textContent='✓ Saved';
    } else { al.style.display='block'; al.textContent=d.error||'Failed to save.'; }
  }).catch(()=>{ btn.disabled=false; btn.textContent='💾 Save to Chart';
    al.style.display='block'; al.textContent='Network error.'; });
};

function aicMockSoap() {
  var d = new Date().toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'});
  return 'SUBJECTIVE\nPatient presents with chief complaint of persistent headache for 3 days.\n' +
    'Severity 7/10, throbbing, bilateral, worse on exertion and bright light.\n' +
    'Partially relieved by rest. Denies fever, nausea. No known drug allergies.\n\n' +
    'OBJECTIVE\nVitals: BP 126/80 mmHg | HR 76 bpm | Temp 37.0°C | SpO₂ 99%\n' +
    'General: Alert, oriented x3, mild distress. Neck supple. No focal deficit.\n' +
    '[Remaining examination to be completed by physician]\n\n' +
    'ASSESSMENT\n1. Tension-type headache (G44.209) — primary.\n' +
    '2. Rule out migraine without aura (G43.009).\n\n' +
    'PLAN\n1. Ibuprofen 400 mg PO TID × 3 days (with food).\n' +
    '2. Paracetamol 500 mg PO Q6H PRN as rescue.\n' +
    '3. Adequate hydration, regular sleep, avoid triggers.\n' +
    '4. Return in 7 days or sooner if symptoms worsen.\n\n' +
    '─────────────────────────────────\n' +
    'AI Consultation SOAP Note — MOCK ASR (' + d + ')\n' +
    'Doctor must review and amend before finalisation.\n' +
    '─────────────────────────────────';
}
})();
</script>
<?php
        return ob_get_clean();
    }
}
