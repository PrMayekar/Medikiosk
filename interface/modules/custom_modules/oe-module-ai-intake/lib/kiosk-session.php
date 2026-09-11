<?php

/**
 * AI Intake — Kiosk Session Helper
 *
 * Shared utility for all kiosk pages and API endpoints.
 * Provides token validation, pid resolution, step-ordering enforcement,
 * and session mutation helpers — all in one place so nothing is duplicated.
 *
 * Usage (pages):
 *   require_once __DIR__ . '/../lib/kiosk-session.php';
 *   $ks = KioskSession::requireValid();          // validates token or renders 403 and exits
 *   $pid = $ks->pid();
 *
 * Usage (APIs):
 *   require_once __DIR__ . '/../lib/kiosk-session.php';
 *   $ks = KioskSession::requireValidApi();       // validates token or emits JSON 401 and exits
 *   $pid = $ks->pid();
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

class KioskSession
{
    /** Session namespace key */
    private const NS = 'ai_intake_sessions';

    /** Token TTL in seconds (30 minutes) */
    public const TTL = 1800;

    /** Ordered step names — each page checks that the previous step is done */
    public const STEPS = ['login', 'consent', 'pathway', 'interview', 'document'];

    private string $token;
    private array  $data;

    private function __construct(string $token, array &$data)
    {
        $this->token = $token;
        $this->data  = &$data;
    }

    // -------------------------------------------------------------------------
    // Factory / validation
    // -------------------------------------------------------------------------

    /**
     * For page controllers: validates the token from $_GET['token'].
     * On failure renders a full HTML 403 error page and exits.
     */
    public static function requireValid(string $redirectOnExpiry = 'login.php'): self
    {
        self::startSession();
        $token = $_GET['token'] ?? '';
        $data  = &$_SESSION[self::NS][$token] ?? null;

        if (!$token || !isset($_SESSION[self::NS][$token]) || time() > $_SESSION[self::NS][$token]['expires_at']) {
            self::renderExpiredPage($redirectOnExpiry);
            exit;
        }

        return new self($token, $_SESSION[self::NS][$token]);
    }

    /**
     * For API endpoints: validates the token from the JSON body.
     * On failure emits a JSON 401 response and exits.
     */
    public static function requireValidApi(array $body): self
    {
        self::startSession();
        $token = $body['token'] ?? '';

        if (!$token || !isset($_SESSION[self::NS][$token]) || time() > $_SESSION[self::NS][$token]['expires_at']) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid or expired session token.']);
            exit;
        }

        return new self($token, $_SESSION[self::NS][$token]);
    }

    // -------------------------------------------------------------------------
    // Step ordering
    // -------------------------------------------------------------------------

    /**
     * Ensure a required preceding step was completed.
     * For pages: redirects back with an error flash if not done.
     * For APIs: emits JSON 403 and exits.
     */
    public function requireStep(string $requiredStep, bool $isApi = false): void
    {
        if (!in_array($requiredStep, $this->data['steps_done'], true)) {
            if ($isApi) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => "Step '{$requiredStep}' must be completed first."]);
                exit;
            }
            // Redirect to the appropriate page for the missing step.
            $redirect = match ($requiredStep) {
                'login'     => 'login.php',
                'consent'   => 'consent.php?token='            . urlencode($this->token),
                'pathway'   => 'pathway-selection.php?token='  . urlencode($this->token),
                'interview' => 'interview.php?token='          . urlencode($this->token),
                default     => 'login.php',
            };
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Mark a step as done and update the session timestamp.
     */
    public function markStep(string $step): void
    {
        if (!in_array($step, $_SESSION[self::NS][$this->token]['steps_done'], true)) {
            $_SESSION[self::NS][$this->token]['steps_done'][] = $step;
        }
        // Extend TTL on every forward progress.
        $_SESSION[self::NS][$this->token]['expires_at'] = time() + self::TTL;
    }

    // -------------------------------------------------------------------------
    // Session data accessors
    // -------------------------------------------------------------------------

    public function pid(): int
    {
        return (int) $_SESSION[self::NS][$this->token]['pid'];
    }

    public function token(): string
    {
        return $this->token;
    }

    public function isNewPatient(): bool
    {
        return (bool) ($_SESSION[self::NS][$this->token]['is_new_patient'] ?? false);
    }

    public function language(): string
    {
        return (string) ($_SESSION[self::NS][$this->token]['language'] ?? 'en');
    }

    public function interactionMode(): string
    {
        return (string) ($_SESSION[self::NS][$this->token]['interaction_mode'] ?? 'tap');
    }

    public function abhaId(): ?string
    {
        return $_SESSION[self::NS][$this->token]['abha_id'] ?? null;
    }

    public function intakeSessionId(): ?int
    {
        $id = $_SESSION[self::NS][$this->token]['intake_session_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function pathway(): ?string
    {
        return $_SESSION[self::NS][$this->token]['pathway'] ?? null;
    }

    /** Store arbitrary data in the kiosk session. */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[self::NS][$this->token][$key] = $value;
    }

    /** Retrieve arbitrary cached data from the kiosk session (returns null if not set). */
    public function intakeSessionData(string $key): mixed
    {
        return $_SESSION[self::NS][$this->token][$key] ?? null;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION[self::NS])) {
            $_SESSION[self::NS] = [];
        }
    }

    private static function renderExpiredPage(string $backHref): void
    {
        http_response_code(403);
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Session Expired — AI Intake</title>
  <style>
    :root { --brand: #2c9cd4; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; font-family: 'Segoe UI', system-ui, sans-serif;
                 background: linear-gradient(135deg,#1a6fa0,#2c9cd4,#00d4ff);
                 display: flex; align-items: center; justify-content: center; }
    .box { background: rgba(255,255,255,.94); border-radius: 20px; padding: 48px 52px;
           text-align: center; box-shadow: 0 8px 40px rgba(44,156,212,.2); max-width: 440px; }
    h1 { font-size: 1.3rem; color: #c62828; margin-bottom: 14px; }
    p  { color: #4a6070; line-height: 1.65; }
    a  { display: inline-block; margin-top: 28px; color: var(--brand);
         font-weight: 600; text-decoration: none; font-size: .9rem; }
    a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="box">
    <h1>⚠️ Session expired or invalid</h1>
    <p>Your check-in session has expired or the link is no longer valid.<br>
       Please start the check-in process again.</p>
    <a href="{$backHref}">← Return to Check-In</a>
  </div>
</body>
</html>
HTML;
    }
}
