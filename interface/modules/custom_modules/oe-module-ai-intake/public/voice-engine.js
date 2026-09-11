/**
 * AI Intake Kiosk — Shared Voice Engine
 * Provides Chrome-safe speak() and startASR() used by all kiosk pages.
 * Include this BEFORE page-specific voice scripts.
 * Requires: window.SPEECH_LANG set before this file loads.
 */

/* ── Chrome-safe TTS ────────────────────────────────────────────────────── */
var _ttsResumeTimer = null;

function speak(text, onDone) {
    if (!('speechSynthesis' in window)) {
        if (onDone) setTimeout(onDone, 100);
        return;
    }
    if (_ttsResumeTimer) { clearInterval(_ttsResumeTimer); _ttsResumeTimer = null; }
    window.speechSynthesis.cancel();

    function _doSpeak() {
        var utter = new SpeechSynthesisUtterance(text);
        utter.lang   = window.SPEECH_LANG || 'en-IN';
        utter.rate   = 0.92;
        utter.pitch  = 1.0;
        utter.volume = 1.0;

        var voices = window.speechSynthesis.getVoices();
        var lang0  = (window.SPEECH_LANG || 'en').split('-')[0];
        var match  = voices.find(function(v) { return v.lang === window.SPEECH_LANG; })
                  || voices.find(function(v) { return v.lang.startsWith(lang0) && /google/i.test(v.name); })
                  || voices.find(function(v) { return v.lang.startsWith(lang0); })
                  || null;
        if (match) utter.voice = match;

        var done = false;
        var safetyMs = Math.max(4000, text.length * 80 + 3000);

        function markDone() {
            if (done) return;
            done = true;
            if (_ttsResumeTimer) { clearInterval(_ttsResumeTimer); _ttsResumeTimer = null; }
            if (onDone) onDone();
        }

        utter.onend   = markDone;
        utter.onerror = function(e) {
            if (e.error !== 'interrupted') console.warn('[VE] TTS error:', e.error);
            markDone();
        };

        // Chrome bug: long texts silently pause after ~14 s
        _ttsResumeTimer = setInterval(function() {
            if (window.speechSynthesis.paused) window.speechSynthesis.resume();
        }, 5000);

        // Hard safety: if onend never fires
        setTimeout(markDone, safetyMs);

        window.speechSynthesis.resume();
        window.speechSynthesis.speak(utter);
    }

    // Guard: voices may not be loaded on first call
    if (window.speechSynthesis.getVoices().length > 0) {
        _doSpeak();
    } else {
        var _t = setTimeout(_doSpeak, 1500);
        window.speechSynthesis.onvoiceschanged = function() {
            clearTimeout(_t);
            window.speechSynthesis.onvoiceschanged = null;
            _doSpeak();
        };
    }
}

/* ── ASR Engine ─────────────────────────────────────────────────────────── */
var _isRecording           = false;
var _activeRecognition     = null;
var _accumulatedTranscript = '';
var _SpeechRecognition     = window.SpeechRecognition || window.webkitSpeechRecognition;

function startASR(onFinal, onError, liveEl) {
    if (!_SpeechRecognition) {
        if (onError) onError('not-supported');
        return;
    }
    if (_isRecording) {
        if (_activeRecognition) { try { _activeRecognition.stop(); } catch(e) {} }
        return;
    }

    // Cancel TTS before opening mic
    if (_ttsResumeTimer) { clearInterval(_ttsResumeTimer); _ttsResumeTimer = null; }
    window.speechSynthesis.cancel();

    _isRecording = true;
    _accumulatedTranscript = '';

    _activeRecognition = new _SpeechRecognition();
    _activeRecognition.lang            = window.SPEECH_LANG || 'en-IN';
    _activeRecognition.interimResults  = true;
    _activeRecognition.maxAlternatives = 1;
    _activeRecognition.continuous      = false;

    var handled = false;

    _activeRecognition.onstart = function() {
        if (liveEl) liveEl.textContent = '🎙 Listening…';
    };

    _activeRecognition.onresult = function(event) {
        var interim = '';
        for (var i = event.resultIndex; i < event.results.length; i++) {
            var t = event.results[i][0].transcript;
            if (event.results[i].isFinal) _accumulatedTranscript += t + ' ';
            else interim += t;
        }
        var display = _accumulatedTranscript.trim();
        if (liveEl) {
            liveEl.innerHTML = display
                ? _esc(display) + '<span style="opacity:.6"> ' + _esc(interim) + '</span>'
                : '<span style="opacity:.6">' + _esc(interim) + '</span>';
        }
    };

    _activeRecognition.onend = function() {
        _isRecording = false;
        if (handled) return;
        handled = true;
        var finalText = _accumulatedTranscript.trim();
        if (!finalText) { if (onError) onError('no-speech'); return; }
        if (onFinal) onFinal(finalText);
    };

    _activeRecognition.onerror = function(event) {
        if (handled) return;
        handled = true;
        _isRecording = false;
        if (event.error === 'aborted') return;
        if (onError) onError(event.error);
    };

    try { _activeRecognition.start(); }
    catch(e) {
        _isRecording = false;
        if (!handled) { handled = true; if (onError) onError(e.message); }
    }
}

function stopASR() {
    if (_isRecording && _activeRecognition) {
        try { _activeRecognition.stop(); } catch(e) {}
    }
}

function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Preload voices
if ('speechSynthesis' in window) {
    window.speechSynthesis.getVoices();
    window.speechSynthesis.onvoiceschanged = function() { window.speechSynthesis.getVoices(); };
}
