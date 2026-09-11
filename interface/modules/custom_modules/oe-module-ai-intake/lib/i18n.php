<?php

/**
 * AI Intake — Internationalisation (i18n) Helper
 *
 * Single source of truth for every user-facing string in the kiosk UI.
 * Supported languages: 'en' (English), 'mr' (Marathi)
 *
 * Usage:
 *   require_once __DIR__ . '/i18n.php';
 *   $lang = $ks->language(); // 'en' | 'mr'
 *   $t    = i18nStrings($lang);
 *   echo $t['welcome_heading'];
 *
 * @package   OpenEMR
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

function i18nStrings(string $lang = 'en'): array
{
    $strings = [
        'en' => [
            // ── html lang attribute ──
            'html_lang' => 'en',

            // ── welcome.php ──
            'welcome_title'         => 'Welcome — AI Intake Kiosk',
            'welcome_icon'          => '👋',
            'welcome_heading'       => 'Namaste',
            'welcome_subheading'    => 'Please select your language to begin.',
            'lang_coming_soon_toast'=> 'Language coming soon. Continuing in English.',

            // ── mode-select.php ──
            'mode_title'       => 'How would you like to check in?',
            'mode_tap_label'   => 'Tap to Continue',
            'mode_voice_label' => 'Speak to Continue',
            'mode_tap_sub'     => 'Use buttons & keyboard',
            'mode_voice_sub'   => 'Use your voice',

            // ── login.php ──
            'login_title'         => 'Patient Check-In',
            'tab_abha'            => 'Have ABHA ID',
            'tab_register'        => 'New Patient',
            'abha_label'          => 'ABHA Health ID',
            'abha_placeholder'    => 'XX-XXXX-XXXX-XXXX',
            'btn_send_otp'        => 'Send OTP',
            'btn_resend_otp'      => 'Resend OTP',
            'otp_label'           => 'Enter OTP',
            'otp_placeholder'     => '6-digit OTP',
            'btn_verify'          => 'Verify & Continue',
            'btn_register'        => 'Verify & Register',
            'name_label'          => 'Full Name',
            'name_placeholder'    => 'e.g. Priya Sharma',
            'age_label'           => 'Age (years)',
            'age_placeholder'     => 'e.g. 34',
            'gender_label'        => 'Gender',
            'gender_select'       => 'Select gender',
            'gender_male'         => 'Male',
            'gender_female'       => 'Female',
            'gender_other'        => 'Other',
            'phone_label'         => 'Phone Number',
            'phone_placeholder'   => 'e.g. 9876543210',
            'abha_optional_label' => 'ABHA ID (Optional)',
            // voice prompts (TTS)
            'tts_say_abha'        => 'Tap the mic and say your 14-digit ABHA ID.',
            'tts_say_otp'         => 'Say the 6-digit OTP sent to your phone.',
            'tts_say_name'        => 'Tap the mic and say your full name.',
            'tts_say_age'         => 'Say your age in years.',
            'tts_say_gender'      => 'Say your gender: male, female, or other.',
            'tts_say_phone'       => 'Say your 10-digit mobile number.',
            'tts_say_reg_otp'     => 'Say the 6-digit OTP sent to your phone.',
            'tts_listening'       => 'Listening…',
            'tts_got_it'          => 'Got it.',
            'tts_otp_sent'        => 'O T P sent.',
            'tts_verifying'       => 'Verifying your details, please wait.',
            // voice state labels
            'voice_idle'          => 'Tap microphone to speak',
            'voice_recording'     => 'Listening…',
            'voice_processing'    => 'Processing…',
            'voice_done'          => 'Got it!',
            // errors
            'err_invalid_abha'    => 'Please enter a valid 14-digit ABHA ID.',
            'err_fill_all'        => 'Please fill all required fields.',
            'err_otp_6dig'        => 'Please enter the 6-digit OTP.',
            'err_network'         => 'Network error. Please try again.',
            'err_mic_denied'      => 'Microphone access was denied. Please allow it in your browser settings.',
            'err_mic_notsupported'=> 'Voice input is not supported in this browser. Please use Chrome.',
            'err_empty_speech'    => 'No speech detected. Please try again.',

            // ── consent.php ──
            'consent_title'     => 'Informed Consent',
            'consent_heading'   => 'Before we begin…',
            'consent_intro'     => 'Please read the following consent form carefully. You may also listen to it.',
            'btn_listen_consent'=> '🔊 Listen to Consent',
            'consent_body'      => "This AI-assisted intake system collects your health information to help your doctor prepare for your consultation.\n\nYour data is stored securely within this hospital's records system (OpenEMR) and is not shared with any third party without your explicit consent.\n\nBy agreeing, you consent to:\n• Collection of your basic medical history\n• Storage in your patient record\n• Sharing with your treating physician today",
            'consent_checkbox'  => 'I have read and agree to the above.',
            'btn_agree'         => 'I Agree & Continue →',
            'tts_consent_text'  => 'This AI-assisted intake system collects your health information to help your doctor prepare for your consultation. Your data is stored securely and shared only with your treating physician. Please check the box and tap I Agree to continue.',

            // ── pathway-selection.php ──
            'pathway_title'       => 'Choose Consultation Type',
            'pathway_heading'     => 'What type of consultation do you need?',
            'pathway_allo_title'  => 'Allopathic',
            'pathway_allo_sub'    => 'Modern medicine, diagnostics & prescriptions',
            'pathway_ayu_title'   => 'Ayurvedic',
            'pathway_ayu_sub'     => 'Traditional herbs, Prakriti & holistic care',
            'btn_back'            => '← Back',
            'tts_choose_pathway'  => 'Please choose your consultation type. Tap Allopathic for modern medicine, or Ayurvedic for traditional care.',

            // ── interview.php ──
            'interview_title'     => 'Health Interview',
            'interview_step_of'   => 'Question %d of %d',
            'btn_prev'            => '← Previous',
            'btn_next'            => 'Next →',
            'btn_finish'          => 'Finish & Continue →',
            'btn_listen'          => '🔊 Listen',
            'answer_placeholder'  => 'Your answer…',
            'transcript_heading'  => 'Live Summary',
            'transcript_empty'    => 'Your answers will appear here as you progress.',
            'followup_banner_title'=> 'Since your last visit, here\'s what we have on file:',
            'voice_tap_prompt'    => 'Tap the microphone and speak your answer clearly.',
            'tts_speak_answer'    => 'Please speak your answer clearly after the beep.',
            'err_answer_required' => 'Please provide an answer before continuing.',
            'status_recording'    => 'Recording…',
            'status_transcribing' => 'Transcribing…',
            'status_done'         => 'Done — review or tap Next',
            'status_idle'         => 'Tap microphone to answer',

            // ── document-upload.php ──
            'doc_title'          => 'Upload Documents',
            'doc_heading'        => 'Upload Medical Documents',
            'doc_optional'       => 'Optional — you can skip this step',
            'doc_drop_title'     => 'Drag & drop your files here',
            'doc_drop_sub'       => 'or tap to browse from your device',
            'doc_processing'     => 'Analysing documents…',
            'doc_processing_sub' => 'Extracting key information',
            'doc_extracted'      => 'Documents Extracted',
            'doc_ai_processed'   => 'AI Processed',
            'btn_skip'           => 'Skip',
            'btn_confirm_doc'    => 'Confirm & Continue',
            'tts_doc_upload'     => 'You can upload a prescription, lab report or scan. Tap the upload area or skip to continue.',

            // ── summary.php ──
            'summary_title'      => 'Your Intake Summary',
            'summary_heading'    => 'Review & Confirm',
            'summary_subheading' => 'Review this before confirming. Your doctor will receive it immediately.',
            'btn_confirm_summary'=> 'Confirm & Send to Doctor',
            'btn_edit_back'      => '← Edit Answers',
            'summary_sending'    => 'Sending to doctor…',
            'summary_sent'       => 'Sent successfully!',
            'redflag_heading'    => 'Priority Alert',
            'redflag_body'       => 'Please inform the front desk immediately. A healthcare provider will attend to you shortly.',

            // ── queue.php ──
            'queue_title'        => 'You\'re in the Queue',
            'queue_icon'         => '🎟️',
            'queue_heading'      => 'You\'re all set!',
            'queue_subheading'   => 'Your doctor has received your summary.',
            'queue_number_label' => 'Your Queue Number',
            'queue_wait_label'   => 'Please wait in',
            'btn_home'           => '← Finish & Return Home',
        ],

        'mr' => [
            // ── html lang ──
            'html_lang' => 'mr',

            // ── welcome.php ──
            'welcome_title'         => 'स्वागत — AI इन्टेक कियोस्क',
            'welcome_icon'          => '🙏',
            'welcome_heading'       => 'नमस्कार',
            'welcome_subheading'    => 'सुरू करण्यासाठी कृपया आपली भाषा निवडा.',
            'lang_coming_soon_toast'=> 'भाषा लवकरच येईल. इंग्रजीत सुरू राहतो.',

            // ── mode-select.php ──
            'mode_title'       => 'तुम्हाला कसे नोंदणी करायची आहे?',
            'mode_tap_label'   => 'बोटाने सुरू करा',
            'mode_voice_label' => 'बोलून सुरू करा',
            'mode_tap_sub'     => 'बटणे आणि कीबोर्ड वापरा',
            'mode_voice_sub'   => 'आवाज वापरा',

            // ── login.php ──
            'login_title'         => 'रुग्ण नोंदणी',
            'tab_abha'            => 'ABHA ID आहे',
            'tab_register'        => 'नवीन रुग्ण',
            'abha_label'          => 'ABHA आरोग्य ID',
            'abha_placeholder'    => 'XX-XXXX-XXXX-XXXX',
            'btn_send_otp'        => 'OTP पाठवा',
            'btn_resend_otp'      => 'OTP पुन्हा पाठवा',
            'otp_label'           => 'OTP प्रविष्ट करा',
            'otp_placeholder'     => '6-अंकी OTP',
            'btn_verify'          => 'सत्यापित करा व पुढे जा',
            'btn_register'        => 'सत्यापित करा व नोंदणी करा',
            'name_label'          => 'पूर्ण नाव',
            'name_placeholder'    => 'उदा. प्रिया शर्मा',
            'age_label'           => 'वय (वर्षे)',
            'age_placeholder'     => 'उदा. ३४',
            'gender_label'        => 'लिंग',
            'gender_select'       => 'लिंग निवडा',
            'gender_male'         => 'पुरुष',
            'gender_female'       => 'स्त्री',
            'gender_other'        => 'इतर',
            'phone_label'         => 'फोन नंबर',
            'phone_placeholder'   => 'उदा. ९८७६५४३२१०',
            'abha_optional_label' => 'ABHA ID (पर्यायी)',
            // voice prompts
            'tts_say_abha'        => 'मायक्रोफोनवर टॅप करा आणि तुमचा १४-अंकी ABHA ID सांगा.',
            'tts_say_otp'         => 'तुमच्या फोनवर आलेला ६-अंकी OTP सांगा.',
            'tts_say_name'        => 'मायक्रोफोनवर टॅप करा आणि तुमचे पूर्ण नाव सांगा.',
            'tts_say_age'         => 'तुमचे वय वर्षांमध्ये सांगा.',
            'tts_say_gender'      => 'तुमचे लिंग सांगा: पुरुष, स्त्री, किंवा इतर.',
            'tts_say_phone'       => 'तुमचा १०-अंकी मोबाइल नंबर सांगा.',
            'tts_say_reg_otp'     => 'तुमच्या फोनवर आलेला ६-अंकी OTP सांगा.',
            'tts_listening'       => 'ऐकत आहे…',
            'tts_got_it'          => 'समजले.',
            'tts_otp_sent'        => 'OTP पाठवला आहे.',
            'tts_verifying'       => 'तपासत आहे, कृपया थांबा.',
            // voice state labels
            'voice_idle'          => 'बोलण्यासाठी मायक्रोफोनवर टॅप करा',
            'voice_recording'     => 'ऐकत आहे…',
            'voice_processing'    => 'प्रक्रिया होत आहे…',
            'voice_done'          => 'समजले!',
            // errors
            'err_invalid_abha'    => 'कृपया वैध १४-अंकी ABHA ID प्रविष्ट करा.',
            'err_fill_all'        => 'कृपया सर्व आवश्यक माहिती भरा.',
            'err_otp_6dig'        => 'कृपया ६-अंकी OTP प्रविष्ट करा.',
            'err_network'         => 'नेटवर्क त्रुटी. कृपया पुन्हा प्रयत्न करा.',
            'err_mic_denied'      => 'मायक्रोफोन अ‍ॅक्सेस नाकारला. कृपया ब्राउझर सेटिंग्जमध्ये परवानगी द्या.',
            'err_mic_notsupported'=> 'या ब्राउझरमध्ये आवाज इनपुट समर्थित नाही. कृपया Chrome वापरा.',
            'err_empty_speech'    => 'कोणताही आवाज आढळला नाही. कृपया पुन्हा प्रयत्न करा.',

            // ── consent.php ──
            'consent_title'     => 'सहमती फॉर्म',
            'consent_heading'   => 'सुरू करण्यापूर्वी…',
            'consent_intro'     => 'कृपया खालील सहमती फॉर्म काळजीपूर्वक वाचा. तुम्ही ते ऐकू शकता.',
            'btn_listen_consent'=> '🔊 सहमती ऐका',
            'consent_body'      => "हे AI-सहाय्यित इन्टेक सिस्टम तुमची आरोग्य माहिती गोळा करते जेणेकरून तुमचे डॉक्टर सल्लामसलतीसाठी तयार होऊ शकतात.\n\nतुमचा डेटा या रुग्णालयाच्या नोंदी प्रणालीमध्ये सुरक्षितपणे साठवला जातो आणि तुमच्या स्पष्ट संमतीशिवाय कोणत्याही तृतीय पक्षाशी शेअर केला जात नाही.\n\nसहमत होऊन, तुम्ही खालील गोष्टींना संमती देता:\n• तुमच्या मूलभूत वैद्यकीय इतिहासाचे संकलन\n• तुमच्या रुग्ण नोंदीमध्ये साठवणूक\n• आजच्या उपचार करणाऱ्या डॉक्टरांशी शेअर करणे",
            'consent_checkbox'  => 'मी वरील गोष्टी वाचल्या आहेत आणि सहमत आहे.',
            'btn_agree'         => 'मी सहमत आहे व पुढे जातो →',
            'tts_consent_text'  => 'हे AI-सहाय्यित इन्टेक सिस्टम तुमची आरोग्य माहिती गोळा करते. तुमचा डेटा सुरक्षितपणे साठवला जातो आणि फक्त तुमच्या उपचार करणाऱ्या डॉक्टरांशी शेअर केला जातो. कृपया बॉक्स चेक करा आणि मी सहमत आहे वर टॅप करा.',

            // ── pathway-selection.php ──
            'pathway_title'       => 'सल्लामसलतीचा प्रकार निवडा',
            'pathway_heading'     => 'तुम्हाला कोणत्या प्रकारची सल्लामसलत हवी आहे?',
            'pathway_allo_title'  => 'ॲलोपॅथिक',
            'pathway_allo_sub'    => 'आधुनिक वैद्यक, निदान आणि प्रिस्क्रिप्शन',
            'pathway_ayu_title'   => 'आयुर्वेदिक',
            'pathway_ayu_sub'     => 'पारंपारिक औषधी, प्रकृती आणि सर्वांगीण काळजी',
            'btn_back'            => '← मागे',
            'tts_choose_pathway'  => 'कृपया तुमच्या सल्लामसलतीचा प्रकार निवडा. आधुनिक वैद्यकासाठी ॲलोपॅथिक, किंवा पारंपारिक काळजीसाठी आयुर्वेदिक टॅप करा.',

            // ── interview.php ──
            'interview_title'     => 'आरोग्य मुलाखत',
            'interview_step_of'   => 'प्रश्न %d पैकी %d',
            'btn_prev'            => '← मागे',
            'btn_next'            => 'पुढे →',
            'btn_finish'          => 'पूर्ण करा व पुढे जा →',
            'btn_listen'          => '🔊 ऐका',
            'answer_placeholder'  => 'तुमचे उत्तर…',
            'transcript_heading'  => 'थेट सारांश',
            'transcript_empty'    => 'तुमची उत्तरे येथे दिसतील.',
            'followup_banner_title'=> 'तुमच्या मागील भेटीनुसार आमच्याकडे हे नोंदलेले आहे:',
            'voice_tap_prompt'    => 'मायक्रोफोनवर टॅप करा आणि आपले उत्तर स्पष्टपणे बोला.',
            'tts_speak_answer'    => 'कृपया बीपनंतर तुमचे उत्तर स्पष्टपणे बोला.',
            'err_answer_required' => 'पुढे जाण्यापूर्वी कृपया उत्तर द्या.',
            'status_recording'    => 'रेकॉर्डिंग…',
            'status_transcribing' => 'लिप्यंतरण होत आहे…',
            'status_done'         => 'झाले — पुनरावलोकन करा किंवा पुढे टॅप करा',
            'status_idle'         => 'उत्तर देण्यासाठी मायक्रोफोनवर टॅप करा',

            // ── document-upload.php ──
            'doc_title'          => 'कागदपत्रे अपलोड करा',
            'doc_heading'        => 'वैद्यकीय कागदपत्रे अपलोड करा',
            'doc_optional'       => 'पर्यायी — तुम्ही हे सोडू शकता',
            'doc_drop_title'     => 'तुमच्या फाइल्स येथे ड्रॅग करा',
            'doc_drop_sub'       => 'किंवा तुमच्या डिव्हाइसमधून ब्राउझ करण्यासाठी टॅप करा',
            'doc_processing'     => 'कागदपत्रे विश्लेषण होत आहेत…',
            'doc_processing_sub' => 'मुख्य माहिती काढत आहे',
            'doc_extracted'      => 'कागदपत्रे काढली',
            'doc_ai_processed'   => 'AI प्रक्रिया केली',
            'btn_skip'           => 'सोडा',
            'btn_confirm_doc'    => 'पुष्टी करा व पुढे जा',
            'tts_doc_upload'     => 'तुम्ही प्रिस्क्रिप्शन, लॅब रिपोर्ट किंवा स्कॅन अपलोड करू शकता. अपलोड क्षेत्रावर टॅप करा किंवा पुढे जाण्यासाठी सोडा.',

            // ── summary.php ──
            'summary_title'      => 'तुमचा इन्टेक सारांश',
            'summary_heading'    => 'पुनरावलोकन करा आणि पुष्टी करा',
            'summary_subheading' => 'पुष्टी करण्यापूर्वी हे पुनरावलोकन करा. तुमचे डॉक्टर ते लगेच प्राप्त करतील.',
            'btn_confirm_summary'=> 'पुष्टी करा व डॉक्टरांना पाठवा',
            'btn_edit_back'      => '← उत्तरे संपादित करा',
            'summary_sending'    => 'डॉक्टरांना पाठवत आहे…',
            'summary_sent'       => 'यशस्वीरित्या पाठवले!',
            'redflag_heading'    => 'प्राधान्य सूचना',
            'redflag_body'       => 'कृपया ताबडतोब फ्रंट डेस्कला कळवा. एक आरोग्य सेवा प्रदाता लवकरच तुमच्याकडे येईल.',

            // ── queue.php ──
            'queue_title'        => 'तुम्ही रांगेत आहात',
            'queue_icon'         => '🎟️',
            'queue_heading'      => 'सर्व तयार आहे!',
            'queue_subheading'   => 'तुमच्या डॉक्टरांना तुमचा सारांश मिळाला आहे.',
            'queue_number_label' => 'तुमचा रांग क्रमांक',
            'queue_wait_label'   => 'कृपया थांबा',
            'btn_home'           => '← समाप्त करा व घरी जा',
        ],
    ];

    return $strings[$lang] ?? $strings['en'];
}

/**
 * Returns the BCP-47 language code for SpeechRecognition / SpeechSynthesis.
 */
function speechLangCode(string $lang): string
{
    return match ($lang) {
        'mr' => 'mr-IN',
        'hi' => 'hi-IN',
        'ta' => 'ta-IN',
        'te' => 'te-IN',
        'bn' => 'bn-IN',
        'gu' => 'gu-IN',
        'kn' => 'kn-IN',
        'ml' => 'ml-IN',
        'pa' => 'pa-IN',
        default => 'en-IN',
    };
}
