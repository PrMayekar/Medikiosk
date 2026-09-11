# OpenEMR AI Intake & Ambient Consultation Prototype

## Overview
This module (`oe-module-ai-intake`) is a comprehensive prototype that transforms the standard OpenEMR intake process into an intelligent, voice-first, multilingual kiosk experience for patients, and provides doctors with a hands-free ambient listening consultation tool.

## Key Requirements & Capabilities

### 1. Multilingual Kiosk Experience
- **Language Support**: Full UI and voice support for English and Marathi.
- **DPDP Act Consent**: Patients must agree to a localized Informed Consent form (available in both languages), which can be read aloud to them.
- **Dynamic Pathways**: Supports customized intake workflows for **Ayurvedic** and **Allopathic** care. Questions adapt based on the selected pathway (e.g., Ayurvedic asks about *Prakriti* and lifestyle).

### 2. Hands-Free, Voice-Driven Intake
- **Dual Modes**: Patients can choose "Tap Mode" (traditional touch UI) or "Voice Mode".
- **Conversational Voice Mode**: The kiosk acts as a conversational AI assistant. It uses the browser's Web Speech API (TTS) to read questions aloud and automatically triggers Speech Recognition (ASR) to listen for the patient's answers without requiring screen taps.
- **Robust Audio Handling**: TTS is chunked by sentences to prevent browser stalling, and includes workarounds for Chrome Web Speech API bugs.

### 3. Intelligent Data Processing (Groq LLM)
- **Clinical Summary Generation**: The raw Q&A transcript is sent to a backend LLM (Groq: `llama3-8b-8192`) to generate a formal, structured pre-visit clinical note.
- **Auto-Populating OpenEMR Dashboards**: The LLM extracts structured clinical entities using JSON constraints. Discovered **Medications**, **Allergies**, and **Medical Problems** are automatically pushed into OpenEMR's core `lists` table, preventing duplicates and instantly populating the doctor's dashboard.
- **On-the-fly Translation**: The generated clinical summary can be instantly translated into Marathi on the dashboard via an API toggle.

### 4. Doctor's Ambient Consultation
- **Dedicated UI**: A full-screen ambient listening window allows the doctor to start a live transcription session during the physical exam.
- **SOAP Note Generation**: The transcript is processed by the LLM to automatically generate a structured SOAP (Subjective, Objective, Assessment, Plan) note.
- **Direct Chart Integration**: The reviewed SOAP note is saved directly to OpenEMR's `form_note` table and linked to the patient's encounter.

### 5. OpenEMR Core Integration
- **Event Hooking**: Binds to OpenEMR's Event Dispatcher (`PatientMenuEvent::MENU_UPDATE`) to inject a dedicated "AI Intake" tab directly into the patient's main navigation menu.
- **Auth & Logging**: Respects OpenEMR's ACL (Access Control Lists) ensuring only users with medical privileges can view the AI Intake dashboard or start an ambient consultation.

## How to Setup & Use

### Step 1: Environment Setup
1. Open the module folder: `interface/modules/custom_modules/oe-module-ai-intake/`
2. Create a `.env` file by copying the example:
   ```bash
   cp .env.example .env
   ```
3. Add your Groq API key to the `.env` file:
   ```
   GROQ_API_KEY=your_api_key_here
   ```

### Step 2: For Patients (The AI Intake Kiosk)
The kiosk is meant to be run on an iPad or tablet in the waiting room.
1. Open Google Chrome (required for voice features) and navigate to:
   `https://[your-openemr-url]/interface/modules/custom_modules/oe-module-ai-intake/public/welcome.php`
2. **Language & Login**: The patient selects their language (English or Marathi), enters dummy details (OTP/ABHA), and agrees to the DPDP consent.
3. **Pathway Selection**: The patient chooses between Ayurvedic or Allopathic care.
4. **Interview**: The patient can choose **Tap Mode** or **Voice Mode** to answer the dynamic clinical questions. 
5. When finished, the data is sent to the AI and the patient is thanked.

### Step 3: For Doctors (Reviewing the AI Summary)
Once the patient has completed the kiosk intake, the doctor can review the AI-generated clinical summary directly in OpenEMR.
1. Log in to OpenEMR as a Provider.
2. Search for the patient in the **Patient Finder** and open their chart.
3. In the patient's main navigation menu, click the **"AI Intake"** tab.
4. You will see the AI's pre-visit summary. You can click **"🌐 View in Marathi"** to translate it instantly.
5. Check the patient's **Medical Record Dashboard** — the AI has automatically populated the **Medical Problems**, **Allergies**, and **Medications** columns based on the patient's answers!

### Step 4: For Doctors (Ambient Consultation)
If the doctor wants to record the actual physical exam and generate a SOAP note:
1. In OpenEMR, go to the **AI Intake** tab for the patient.
2. Scroll to the bottom and click **"Start Ambient Listening Session"**.
3. A new full-screen window will open. Click **"Start Recording"** when the exam begins.
4. When finished, click **"Stop Recording"**. The AI will generate a structured SOAP note.
5. Review the note, make any necessary edits, and click **"Save to OpenEMR"** to attach it to the patient's chart.
