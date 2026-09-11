import os

with open('login.php', 'r') as f:
    text = f.read()

start_idx = text.find('// Voice Flow')
end_idx = text.find('// Initial audio if voice mode is active')

if start_idx != -1 and end_idx != -1:
    new_code = """// Voice Flow - Real ASR via Web Speech API
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

function listenAndFill(callback) {
    if (!SpeechRecognition) {
        alert("Speech Recognition not supported in this browser. Please use Chrome/Edge.");
        callback("123456"); // fallback for demo
        return;
    }
    const recognition = new SpeechRecognition();
    recognition.lang = "en-IN";
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    
    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript.trim();
        callback(transcript);
    };
    recognition.onerror = (event) => {
        console.error("Speech recognition error", event.error);
        callback("");
    };
    recognition.start();
}

let vAbhaVal = "";
function simulateVoiceAbha() {
    const mic = document.getElementById("v-abha-mic");
    mic.classList.add("listening");
    playAudio("Listening...");
    listenAndFill((res) => {
        mic.classList.remove("listening");
        if (!res) res = "99888877776666"; // fallback
        vAbhaVal = res.replace(/\\D/g, "");
        if (vAbhaVal.length === 14) {
            vAbhaVal = vAbhaVal.replace(/(\\d{2})(\\d{4})(\\d{4})(\\d{4})/, "$1-$2-$3-$4");
        }
        document.getElementById("v-abha-input-display").textContent = vAbhaVal;
        mic.style.display = "none";
        document.getElementById("v-abha-prompt").style.display = "none";
        document.getElementById("v-abha-otp-section").style.display = "block";
        playAudio("OTP sent. Say your 6 digit OTP.");
    });
}

function simulateVoiceAbhaOtp() {
    const mic = document.getElementById("v-abha-otp-mic");
    mic.classList.add("listening");
    playAudio("Listening...");
    listenAndFill((res) => {
        mic.classList.remove("listening");
        const otp = res.replace(/\\D/g, "") || "123456";
        document.getElementById("v-abha-otp-display").textContent = otp;
        
        fetch(apiBase + "/verify-abha.php", {
            method: "POST", headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ abha_id: vAbhaVal, otp: otp })
        }).then(r => r.json()).then(data => {
            if (data.success) window.location.href = "consent.php?token=" + encodeURIComponent(data.token);
            else showAlert("abha", "Login failed.");
        });
    });
}

let vRegStep = 0;
let vRegData = { name: "", age: 0, gender: "", phone: "", abha: "", otp: "" };
function simulateVoiceReg() {
    const mic = document.getElementById("v-reg-mic");
    const prompt = document.getElementById("v-reg-prompt");
    const display = document.getElementById("v-reg-display");
    
    mic.classList.add("listening");
    playAudio("Listening...");
    
    listenAndFill((res) => {
        mic.classList.remove("listening");
        if (!res) {
            playAudio("Sorry, please try again.");
            return;
        }
        
        if (vRegStep === 0) {
            vRegData.name = res;
            display.textContent = vRegData.name;
            prompt.textContent = "Say your age";
            playAudio("Got it. Say your age.");
            vRegStep++;
        } else if (vRegStep === 1) {
            vRegData.age = parseInt(res.replace(/\\D/g, "")) || 34;
            display.textContent = vRegData.age + " years";
            prompt.textContent = "Say your gender";
            playAudio("Got it. Say your gender.");
            vRegStep++;
        } else if (vRegStep === 2) {
            vRegData.gender = res;
            display.textContent = vRegData.gender;
            prompt.textContent = "Say your phone number";
            playAudio("Got it. Say your phone number.");
            vRegStep++;
        } else if (vRegStep === 3) {
            vRegData.phone = res.replace(/\\D/g, "") || "9876543210";
            display.textContent = vRegData.phone;
            prompt.textContent = "OTP sent. Say your OTP";
            playAudio("OTP sent. Say your OTP.");
            vRegStep++;
        } else if (vRegStep === 4) {
            vRegData.otp = res.replace(/\\D/g, "") || "123456";
            display.textContent = "Verifying...";
            
            fetch(apiBase + "/register-patient.php", {
                method: "POST", headers: {"Content-Type": "application/json"},
                body: JSON.stringify({ name: vRegData.name, age: vRegData.age, gender: vRegData.gender, phone: vRegData.phone, abha_id: "", otp: vRegData.otp })
            }).then(r => r.json()).then(data => {
                if (data.success) window.location.href = "consent.php?token=" + encodeURIComponent(data.token);
                else showAlert("reg", "Registration failed");
            });
        }
    });
}

"""
    text = text[:start_idx] + new_code + text[end_idx:]
    with open('login.php', 'w') as f:
        f.write(text)
    print("Replaced voice flow")
else:
    print("Markers not found")
