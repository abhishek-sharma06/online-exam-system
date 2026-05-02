/**
 * Proctoring JavaScript (clean single-file implementation)
 */

let mediaStream = null;
let captureInterval = null;
let analyzeInterval = null;
let violationCount = 0;
let isDisqualified = false;
// Disqualify only after more than 3 tab switches (i.e., threshold = 4)
const MAX_VIOLATIONS = 4;
const CAPTURE_INTERVAL = 13000; // 13 seconds for capture (within 10-15 sec range)
const ANALYZE_INTERVAL = 15000; // 15 seconds for analysis
let lastViolationTime = {};
let audioContext = null;
let analyser = null;
let voiceDetected = false;
let lastCameraPath = null;
let lastScreenshotPath = null;
let lastScreenRecordTime = 0;
const SCREEN_RECORD_MIN_INTERVAL = 10000; // 10s between forced screen recordings
const SCREEN_RECORD_DURATION = 3000; // record 3 seconds of the shared screen

// Optional persistent display stream (requested once) to avoid repeated prompts
let persistentDisplayStream = null;

// health tracking for periodic captures
let lastSuccessfulCaptureTime = 0;
let captureHealthInterval = null;

let currentExamId = null;
let currentUserId = null;

async function initProctoring(examId, userId) {
    currentExamId = examId;
    currentUserId = userId;
    console.log('initProctoring()', examId, userId);

    const status = await checkDisqualificationStatus();
    if (status.disqualified) { showDisqualificationMessage(); return false; }

    // setup monitoring immediately so tab switch etc. are tracked even if camera is denied
    try {
        setupViolationMonitoring();
    } catch (e) {
        console.warn('setupViolationMonitoring failed', e);
    }

    // try to get camera (optional)
    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
        const video = document.getElementById('webcam');
        if (video) { video.srcObject = mediaStream; video.onloadedmetadata = () => video.play(); }

        // Initialize audio analysis for voice detection
        try {
            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            analyser = audioContext.createAnalyser();
            const source = audioContext.createMediaStreamAudioTrackProcessor 
                ? null 
                : audioContext.createMediaStreamSource(mediaStream);
            if (source) {
                source.connect(analyser);
                setupVoiceDetection();
            }
        } catch (e) {
            console.warn('Could not initialize audio analysis:', e);
        }

        startImageCapture();
        // start capture health checker to verify periodic captures occur
        try { startCaptureHealthCheck(); } catch (e) { console.warn('startCaptureHealthCheck failed', e); }
        startPeriodicAnalysis();
        setupHeartbeat();
        return true;
    } catch (e) {
        console.warn('getUserMedia denied or failed, continuing without camera', e);
        return true; // continue monitoring without webcam
    }
}

function startImageCapture() {
    if (captureInterval) clearInterval(captureInterval);
    captureInterval = setInterval(() => { 
        if (!isDisqualified) {
            captureAndSendImage();
            captureScreenshot();
        }
    }, CAPTURE_INTERVAL);
}

function startPeriodicAnalysis() {
    if (analyzeInterval) clearInterval(analyzeInterval);
    analyzeInterval = setInterval(() => {
        if (!isDisqualified && lastCameraPath && lastScreenshotPath) {
            analyzeCapturesForCheating(lastCameraPath, lastScreenshotPath);
        }
    }, ANALYZE_INTERVAL);
}

async function captureAndSendImage() {
    try {
        const video = document.getElementById('webcam');
        if (!video || !video.videoWidth) {
            console.warn('Webcam not available or not ready for capture');
            return;
        }
        
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth; 
        canvas.height = video.videoHeight;
        
        try {
            canvas.getContext('2d').drawImage(video, 0, 0);
        } catch (e) {
            console.warn('Failed to draw video to canvas:', e);
            return;
        }
        
        const data = canvas.toDataURL('image/jpeg', 0.7);
        console.log('Image captured, size:', data.length);

        const fd = new FormData();
        fd.append('action', 'save_capture');
        fd.append('exam_id', currentExamId);
        fd.append('image', data);
        
        console.log('Sending image capture to server...');
        const r = await fetch('../includes/proctoring.php', { method: 'POST', body: fd });
        
        if (!r.ok) {
            console.error('Server responded with status:', r.status);
            return;
        }
        
        const json = await r.json();
        if (!json.success) {
            console.warn('Image capture failed:', json.error);
            return;
        }
        
        console.log('Image capture successful:', json.path);
        lastCameraPath = json.path;
        lastSuccessfulCaptureTime = Date.now();
    } catch (e) { 
        console.error('Capture error:', e); 
    }
}

function startCaptureHealthCheck() {
    if (captureHealthInterval) clearInterval(captureHealthInterval);
    // check slightly more often than capture interval
    captureHealthInterval = setInterval(() => {
        if (isDisqualified) return;
        const now = Date.now();
        const threshold = CAPTURE_INTERVAL * 1.5;
        if (!lastSuccessfulCaptureTime || (now - lastSuccessfulCaptureTime) > threshold) {
            console.warn('[HEALTH] No successful capture within expected window');
            // show a lightweight in-page warning if candidate warnings container exists
            const container = document.getElementById('candidate-warnings');
            if (container) {
                const id = 'capture-health-warning';
                let box = document.getElementById(id);
                if (!box) {
                    box = document.createElement('div');
                    box.id = id;
                    box.className = 'alert alert-warning';
                    box.style.fontWeight = 'bold';
                    box.innerText = 'Camera captures appear to be failing. Please ensure your camera is enabled and the page has permission.';
                    container.insertBefore(box, container.firstChild);
                    // Auto-remove this transient health warning after 3 seconds
                    setTimeout(() => { if (box && box.parentNode) box.remove(); }, 3000);
                }
            } else {
                // fallback to console log
                console.warn('Candidate warnings container missing - capture health degraded');
            }
        } else {
            // remove any existing warning if captures resume
            const existing = document.getElementById('capture-health-warning');
            if (existing && existing.parentNode) existing.remove();
        }
    }, Math.max(5000, Math.floor(CAPTURE_INTERVAL / 2)));
}

async function captureScreenshot() {
    try {
        console.log('[SCREENSHOT] Capturing screen...');
        
        // Use html2canvas if available, otherwise use Canvas API on document
        if (typeof html2canvas !== 'undefined') {
            const canvas = await html2canvas(document.body, { 
                backgroundColor: '#ffffff',
                scale: 0.5
            });
            const screenshot = canvas.toDataURL('image/jpeg', 0.7);
            console.log('[SCREENSHOT] Captured, size:', screenshot.length);
            
            await sendScreenshot(screenshot);
        } else {
            console.warn('[SCREENSHOT] html2canvas not available, skipping');
        }
    } catch (e) {
        console.warn('[SCREENSHOT] Capture failed:', e);
    }
}

async function sendScreenshot(screenshotData) {
    try {
        const fd = new FormData();
        fd.append('action', 'save_capture');
        fd.append('exam_id', currentExamId);
        fd.append('image', screenshotData);
        fd.append('type', 'screenshot');
        
        const r = await fetch('../includes/proctoring.php', { 
            method: 'POST', 
            body: fd,
            credentials: 'same-origin'
        });
        
        if (!r.ok) {
            console.error('[SCREENSHOT] Server error:', r.status);
            return;
        }
        
        const json = await r.json();
        if (json.success) {
            console.log('[SCREENSHOT] Sent successfully:', json.path);
            lastScreenshotPath = json.path;
        }
    } catch (e) {
        console.error('[SCREENSHOT] Send failed:', e);
    }
}

async function startImmediateScreenRecording(reason = '') {
    const now = Date.now();
    if ((now - lastScreenRecordTime) < SCREEN_RECORD_MIN_INTERVAL) {
        console.log('[SCREEN_RECORD] Throttled, skipping immediate recording');
        return;
    }
    lastScreenRecordTime = now;

    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        console.warn('[SCREEN_RECORD] getDisplayMedia not supported in this browser');
        return;
    }

    try {
        // If we have a persistent display stream (requested once), use it to avoid a new prompt
        if (persistentDisplayStream) {
            console.log('[SCREEN_RECORD] Using persistent display stream for recording');
            await startRecordingFromStream(persistentDisplayStream, reason);
            return;
        }

        console.log('[SCREEN_RECORD] Requesting display media...');
        const stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        await startRecordingFromStream(stream, reason);
    } catch (e) {
        console.warn('[SCREEN_RECORD] Recording failed or was denied:', e);
    }
}

async function startRecordingFromStream(stream, reason = '') {
    try {
        const options = { mimeType: 'video/webm;codecs=vp9' };
        let recorder;
        try { recorder = new MediaRecorder(stream, options); } catch (e) { recorder = new MediaRecorder(stream); }

        const chunks = [];
        recorder.ondataavailable = (ev) => { if (ev.data && ev.data.size > 0) chunks.push(ev.data); };

        const stopped = new Promise((resolve, reject) => {
            recorder.onstop = resolve;
            recorder.onerror = reject;
        });

        recorder.start();
        console.log('[SCREEN_RECORD] Recording display for', SCREEN_RECORD_DURATION, 'ms');

        setTimeout(() => {
            try { if (recorder.state !== 'inactive') recorder.stop(); } catch (e) { console.warn('[SCREEN_RECORD] stop failed', e); }
        }, SCREEN_RECORD_DURATION);

        await stopped;

        const blob = new Blob(chunks, { type: 'video/webm' });

        // Convert blob to base64 data URL
        const arrayBuffer = await blob.arrayBuffer();
        let binary = '';
        const bytes = new Uint8Array(arrayBuffer);
        const chunkSize = 0x8000;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
        }
        const base64 = btoa(binary);
        const dataUrl = 'data:video/webm;base64,' + base64;

        console.log('[SCREEN_RECORD] Recorded, size (bytes):', base64.length);
        await sendScreenRecordingData(dataUrl, reason);

        // If this was a temporary stream (not persistent), stop tracks
        if (persistentDisplayStream !== stream) {
            try { stream.getTracks().forEach(t => t.stop()); } catch (e) { /* ignore */ }
        }
    } catch (e) {
        console.warn('[SCREEN_RECORD] startRecordingFromStream failed:', e);
    }
}

// Request a persistent display stream once (must be initiated by a user gesture)
async function requestPersistentDisplayShare() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getDisplayMedia) {
        console.warn('[SCREEN_SHARE] getDisplayMedia not supported');
        return false;
    }
    try {
        const stream = await navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
        persistentDisplayStream = stream;
        console.log('[SCREEN_SHARE] Persistent display stream established');

        // If stream ends (user stops sharing), clear reference
        stream.getTracks().forEach(track => track.addEventListener('ended', () => {
            console.log('[SCREEN_SHARE] persistent display track ended');
            if (persistentDisplayStream) persistentDisplayStream = null;
        }));

        return true;
    } catch (e) {
        console.warn('[SCREEN_SHARE] Request denied or failed:', e);
        return false;
    }
}

function showScreenSharePromptBanner() {
    try {
        // If banner already exists or persistent stream is active, skip
        if (persistentDisplayStream) return;
        if (document.getElementById('screen-share-banner')) return;

        const banner = document.createElement('div');
        banner.id = 'screen-share-banner';
        banner.style.cssText = 'position:fixed;bottom:10px;left:10px;right:10px;padding:12px;background:#0d6efd;color:white;border-radius:8px;z-index:1400;display:flex;justify-content:space-between;align-items:center;gap:10px;';
        banner.innerHTML = `
            <div style="font-weight:600">For stronger proctoring, allow screen sharing once.</div>
            <div>
                <button id="allowScreenShareBtn" class="btn btn-light btn-sm" style="margin-right:8px">Allow Screen Share</button>
                <button id="dismissScreenShareBtn" class="btn btn-outline-light btn-sm">Dismiss</button>
            </div>
        `;

        document.body.appendChild(banner);

        document.getElementById('allowScreenShareBtn').addEventListener('click', async () => {
            const ok = await requestPersistentDisplayShare();
            if (ok) {
                banner.remove();
            } else {
                alert('Screen share was not allowed. You can still continue without it.');
            }
        });
        document.getElementById('dismissScreenShareBtn').addEventListener('click', () => { banner.remove(); });
    } catch (e) { console.warn('Could not show screen share banner:', e); }
}

async function sendScreenRecordingData(dataUrl, reason = '') {
    try {
        const fd = new FormData();
        fd.append('action', 'save_capture');
        fd.append('exam_id', currentExamId);
        fd.append('image', dataUrl);
        fd.append('type', 'screen_recording');
        fd.append('reason', reason);

        const r = await fetch('../includes/proctoring.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });

        if (!r.ok) {
            console.error('[SCREEN_RECORD] Server error:', r.status);
            return;
        }

        const json = await r.json();
        if (json.success) {
            console.log('[SCREEN_RECORD] Saved successfully:', json.path || json.filename);
        } else {
            console.warn('[SCREEN_RECORD] Save failed:', json.error);
        }
    } catch (e) {
        console.error('[SCREEN_RECORD] Send failed:', e);
    }
}

async function analyzeCapturesForCheating(cameraPath, screenshotPath) {
    try {
        console.log('[ANALYSIS] Starting cheating analysis...');
        
        const fd = new FormData();
        fd.append('action', 'analyze_cheating');
        fd.append('exam_id', currentExamId);
        fd.append('camera_path', cameraPath);
        fd.append('screenshot_path', screenshotPath);
        fd.append('detection_type', 'PERIODIC_CAPTURE');
        
        const r = await fetch('../includes/proctoring.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        
        if (!r.ok) {
            console.error('[ANALYSIS] Server error:', r.status);
            return;
        }
        
        const report = await r.json();
        console.log('[ANALYSIS] Report:', report);
        
        if (report.suspicion_level === 'HIGH') {
            console.warn('[ANALYSIS] HIGH SUSPICION DETECTED:', report.indicators);
            showSuspicionWarning(report);
        } else if (report.suspicion_level === 'MEDIUM') {
            console.log('[ANALYSIS] Medium suspicion:', report.indicators);
        }
    } catch (e) {
        console.error('[ANALYSIS] Failed:', e);
    }
}

function setupVoiceDetection() {
    try {
        if (!analyser) return;
        
        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        const checkVoice = () => {
            analyser.getByteFrequencyData(dataArray);
            
            let sum = 0;
            for (let i = 0; i < dataArray.length; i++) {
                sum += dataArray[i];
            }
            const average = sum / dataArray.length;
            
            if (average > 30) { // Threshold for voice detection
                if (!voiceDetected) {
                    console.log('[VOICE] Voice activity detected');
                    voiceDetected = true;
                }
            } else {
                voiceDetected = false;
            }
            
            requestAnimationFrame(checkVoice);
        };
        checkVoice();
    } catch (e) {
        console.warn('[VOICE] Setup failed:', e);
    }
}

function showSuspicionWarning(report) {
    try {
        const container = document.getElementById('candidate-warnings');
        if (!container) return;
        
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-warning';
        alertDiv.style.cssText = 'animation: slideDown 0.5s ease-out;';
        alertDiv.innerHTML = `
            <strong>⚠️ SUSPICIOUS ACTIVITY DETECTED</strong>
            <br/>Level: ${report.suspicion_level}
            <br/>
            ${report.indicators.map(ind => `<small>• ${ind}</small>`).join('<br/>')}
        `;
        
        container.appendChild(alertDiv);
        
        // Auto-remove suspicion warning after 3 seconds
        setTimeout(() => { if (alertDiv && alertDiv.parentNode) alertDiv.remove(); }, 3000);
    } catch (e) {
        console.warn('Could not show warning:', e);
    }
}

function setupViolationMonitoring() {
    console.log('\n=== SETTING UP VIOLATION MONITORING ===\n');
    
    // Tab/Window visibility
    document.addEventListener('visibilitychange', () => { 
        console.log(`[VISIBILITY] Hidden: ${document.hidden}`); 
        if (document.hidden && !isDisqualified) { 
            console.log('[VIOLATION EVENT] Tab/Window switched!'); 
            recordViolation('TAB_SWITCH', 'Candidate switched to another tab/window'); 
            try { 
                console.log('[CAPTURE] Taking immediate screenshot due to tab switch'); 
                captureScreenshot(); 

                // Only attempt an automatic screen recording if a persistent display stream
                // has already been granted by the user earlier. Browsers WILL prompt for
                // getDisplayMedia and cannot be bypassed programmatically, so avoid
                // requesting it here (that would show a prompt on every tab switch).
                if (persistentDisplayStream) {
                    try { startImmediateScreenRecording('TAB_SWITCH'); } catch (e) { console.warn('startImmediateScreenRecording failed', e); }
                } else {
                    // Show a banner allowing the candidate to grant a one-time persistent
                    // display share so future tab switches can be recorded automatically.
                    showScreenSharePromptBanner();
                }
            } catch (e) { console.warn('Immediate screenshot on visibilitychange failed', e); }
        } 
    }, true);
    console.log('[LISTENER] visibilitychange registered');
    
    // Window focus loss
    window.addEventListener('blur', () => { 
        console.log('[BLUR] Window lost focus'); 
        if (!isDisqualified) {
            recordViolation('WINDOW_BLUR', 'Exam window lost focus'); 
            try {
                console.log('[CAPTURE] Taking immediate screenshot due to window blur');
                captureScreenshot();

                // Same behaviour as tab-switch: only auto-record if persistent stream exists.
                if (persistentDisplayStream) {
                    try { startImmediateScreenRecording('WINDOW_BLUR'); } catch (e) { console.warn('startImmediateScreenRecording failed', e); }
                } else {
                    showScreenSharePromptBanner();
                }
            } catch (e) { console.warn('Immediate screenshot on blur failed', e); }
        }
    }, true);
    console.log('[LISTENER] blur registered');
    
    // Right-click prevention
    document.addEventListener('contextmenu', (e) => { 
        e.preventDefault(); 
        console.log('[RIGHT_CLICK] Context menu attempted'); 
        if (!isDisqualified) {
            recordViolation('RIGHT_CLICK', 'Right-click attempted'); 
        }
    }, true);
    console.log('[LISTENER] contextmenu registered');
    
    // Copy prevention
    document.addEventListener('copy', (e) => { 
        e.preventDefault(); 
        console.log('[COPY] Copy attempted'); 
        if (!isDisqualified) {
            recordViolation('COPY_ATTEMPT', 'Copy operation attempted'); 
        }
    }, true);
    console.log('[LISTENER] copy registered');
    
    // Paste prevention
    document.addEventListener('paste', (e) => { 
        e.preventDefault(); 
        console.log('[PASTE] Paste attempted'); 
        if (!isDisqualified) {
            recordViolation('PASTE_ATTEMPT', 'Paste operation attempted'); 
        }
    }, true);
    console.log('[LISTENER] paste registered');
    
    // Developer tools prevention
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F12' || (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase()))) {
            e.preventDefault(); 
            console.log('[DEV_TOOLS] Developer tools attempt'); 
            if (!isDisqualified) {
                recordViolation('DEV_TOOLS', 'Developer tools opened');
            }
        }
    }, true);
    console.log('[LISTENER] keydown (DevTools) registered');
    
    // Fullscreen exit
    document.addEventListener('fullscreenchange', () => { 
        console.log(`[FULLSCREEN] Fullscreen element: ${document.fullscreenElement}`); 
        if (!document.fullscreenElement && !isDisqualified) {
            recordViolation('FULLSCREEN_EXIT', 'Exam exited fullscreen mode'); 
        }
    }, true);
    console.log('[LISTENER] fullscreenchange registered');
    
    // Navigation attempt
    window.addEventListener('beforeunload', () => { 
        console.log('[NAVIGATION] Page unload initiated'); 
        if (!isDisqualified) {
            recordViolation('NAVIGATION_ATTEMPT', 'User attempted to navigate away'); 
        }
    }, true);
    console.log('[LISTENER] beforeunload registered');
    
    console.log('\n=== VIOLATION MONITORING SETUP COMPLETE ===\n');
}

async function recordViolation(type, description = '') {
    const now = Date.now();
    
    // Throttle: only one violation per type every 5 seconds
    if (lastViolationTime[type] && (now - lastViolationTime[type]) < 5000) {
        console.log(`[THROTTLED] ${type} violation (too recent)`);
        return;
    }
    
    lastViolationTime[type] = now;
    console.log(`\n>>> RECORDING VIOLATION: ${type} <<<\nDescription: ${description}\nExam: ${currentExamId}\nUser: ${currentUserId}\n`);

    // Try to capture a screenshot
    let screenshot = '';
    try {
        const video = document.getElementById('webcam');
        if (video && video.videoWidth > 0 && video.videoHeight > 0) {
            const c = document.createElement('canvas');
            c.width = video.videoWidth;
            c.height = video.videoHeight;
            const ctx = c.getContext('2d');
            if (ctx) {
                ctx.drawImage(video, 0, 0);
                screenshot = c.toDataURL('image/jpeg', 0.5);
                console.log(`[SCREENSHOT] Captured ${screenshot.length} bytes`);
            }
        } else {
            console.warn('[SCREENSHOT] Webcam not available');
        }
    } catch (e) { 
        console.warn('[SCREENSHOT] Capture failed:', e.message);
    }

    try {
        const fd = new FormData();
        fd.append('action', 'log_violation');
        fd.append('exam_id', currentExamId);
        fd.append('violation_type', type);
        fd.append('description', description);
        if (screenshot) {
            fd.append('image', screenshot);
            console.log('[AJAX] Sending violation WITH screenshot');
        } else {
            console.log('[AJAX] Sending violation WITHOUT screenshot');
        }

        console.log(`[AJAX] POST to ../includes/proctoring.php...`);
        const r = await fetch('../includes/proctoring.php', { 
            method: 'POST', 
            body: fd,
            credentials: 'same-origin'
        });
        
        console.log(`[AJAX] Response status: ${r.status} ${r.statusText}`);
        
        if (!r.ok) {
            console.error(`[AJAX] HTTP Error: ${r.status}`);
            return;
        }
        
        const responseText = await r.text();
        console.log(`[AJAX] Raw response: ${responseText.substring(0, 200)}`);
        
        let json;
        try {
            json = JSON.parse(responseText);
        } catch (parseErr) {
            console.error('[AJAX] Failed to parse JSON:', parseErr);
            return;
        }
        
        console.log(`[AJAX] Parsed response:`, json);
        
        if (!json.success) {
            console.warn(`[VIOLATION] Failed: ${json.error}`);
            return;
        }

        // Update violation count
        violationCount = json.count || json.violation_count || violationCount;
        console.log(`[VIOLATION] Count updated to: ${violationCount}/${MAX_VIOLATIONS}`);
        
        updateViolationDisplay(violationCount);
        showViolationWarning(type, violationCount);

        if (json.disqualified) { 
            console.error(`[VIOLATION] USER DISQUALIFIED!`);
            isDisqualified = true; 
            handleDisqualification(); 
        }
    } catch (e) { 
        console.error('[VIOLATION] Exception:', e.message); 
        console.error('[VIOLATION] Stack:', e.stack);
    }
}

function updateViolationDisplay(count) {
    let el = document.getElementById('violation-indicator');
    if (!el) { 
        el = document.createElement('div'); 
        el.id = 'violation-indicator'; 
        el.style.cssText = 'position:fixed;top:80px;right:20px;padding:15px;background:yellow;color:black;border-radius:6px;z-index:1000;font-weight:bold;border: 3px solid orange;';
        document.body.appendChild(el); 
        console.log('Created violation indicator');
    }
    const remaining = MAX_VIOLATIONS - count;
    el.innerHTML = `⚠️ VIOLATIONS: ${count}/${MAX_VIOLATIONS}<br>Remaining: ${remaining}`;
    el.style.display = 'block';
    
    if (remaining <= 1) { 
        el.style.background = 'red'; 
        el.style.color = 'white'; 
        el.style.fontSize = '16px';
        el.classList.add('blink');
    } else if (remaining <= 2) {
        el.style.background = 'orange';
        el.style.color = 'white';
        el.classList.remove('blink');
    } else {
        el.classList.remove('blink');
    }
    
    console.log('Updated violation display - count:', count, 'remaining:', remaining);
}

function showViolationWarning(type, count) {
    const text = `⚠️ VIOLATION DETECTED ⚠️\n${type.replace(/_/g,' ')}\nViolation ${count}/${MAX_VIOLATIONS}`;
    console.log(`[WARNING] Displaying: ${text}`);
    
    // Show in warnings container
    const container = document.getElementById('candidate-warnings');
    if (container) {
        const box = document.createElement('div'); 
        box.className = 'alert alert-danger alert-dismissible fade show'; 
        box.style.zIndex = '1300'; 
        box.style.marginBottom = '10px';
        box.style.fontWeight = 'bold';
        box.style.fontSize = '16px';
        box.innerHTML = `
            <strong style="font-size: 18px;">⚠️ VIOLATION DETECTED ⚠️</strong><br>
            <span style="font-size: 14px;">${type.replace(/_/g,' ')}</span><br>
            <span style="font-size: 12px;">Violation ${count}/${MAX_VIOLATIONS}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" style="position: absolute; right: 10px; top: 10px;"></button>
        `;
        container.insertBefore(box, container.firstChild);
        // Auto-remove this violation warning after 3 seconds
        setTimeout(() => { if (box && box.parentNode) box.remove(); }, 3000);
        // Trim older warnings to avoid covering content (keep max 3)
        while (container.children.length > 3) { container.removeChild(container.lastChild); }
        
        // Also show as modal-like alert if this is critical
        if (count >= 2) {
            const alertBox = document.createElement('div');
            alertBox.style.cssText = `
                position: fixed;
                top: 50px;
                left: 50%;
                transform: translateX(-50%);
                background: #dc3545;
                color: white;
                padding: 20px 40px;
                border-radius: 8px;
                z-index: 1301;
                font-weight: bold;
                font-size: 18px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.3);
                animation: slideDown 0.3s ease-out;
            `;
            alertBox.textContent = `⚠️ VIOLATION #${count}: ${type.replace(/_/g,' ')} ⚠️`;
            document.body.appendChild(alertBox);
            
            setTimeout(() => {
                if (alertBox.parentNode) alertBox.remove();
            }, 3000);
        }
    }
    
    // No global lingering: each warning is removed individually after 3s
}

function handleDisqualification() { stopExam(); showDisqualificationMessage(); }

async function checkDisqualificationStatus() {
    try {
        const fd = new FormData(); fd.append('action','check_status');
        const r = await fetch('../includes/proctoring.php',{ method:'POST', body: fd });
        return await r.json();
    } catch (e) { console.error('status check error', e); return { disqualified: false }; }
}

function setupHeartbeat() { setInterval(async () => { if (!isDisqualified) { const s = await checkDisqualificationStatus(); if (s.disqualified && !isDisqualified) { isDisqualified = true; handleDisqualification(); } } }, 30000); }

function stopExam() { if (captureInterval) { clearInterval(captureInterval); captureInterval = null; } if (captureHealthInterval) { clearInterval(captureHealthInterval); captureHealthInterval = null; } if (mediaStream) { mediaStream.getTracks().forEach(t => t.stop()); mediaStream = null; } const form = document.getElementById('examForm'); if (form) form.querySelectorAll('input,textarea,select,button').forEach(i => i.disabled = true); }

function showDisqualificationMessage() {
    const modal = document.createElement('div'); 
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.95);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        backdrop-filter: blur(5px);
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        ">
            <div style="font-size: 60px; margin-bottom: 20px;">❌</div>
            <h2 style="color: #dc3545; margin: 0 0 15px; font-size: 32px;">EXAMINATION DISQUALIFIED</h2>
            <p style="color: #555; font-size: 16px; margin: 15px 0;">
                You have been disqualified from this examination due to multiple proctoring violations.
            </p>
            <p style="color: #999; font-size: 14px; margin: 10px 0;">
                Your exam has been terminated and reported to the administration.
            </p>
            <button onclick="window.location.href='dashboard.php'" style="
                background: #dc3545;
                color: white;
                border: none;
                padding: 12px 30px;
                border-radius: 6px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                margin-top: 20px;
                transition: background 0.3s;
            " onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">
                Return to Dashboard
            </button>
        </div>
    `;
    
    document.body.appendChild(modal);
    console.log('[DISQUALIFICATION] Modal displayed');
}

function showCameraError() { alert('Camera access is required for proctoring.'); setTimeout(() => window.location.href = 'dashboard.php', 1500); }

// Expose functions globally (final assignment below)


/**
 * Stop the exam (cleanup)
 */
function stopExam() {
    // Stop capture interval
    if (captureInterval) {
        clearInterval(captureInterval);
        captureInterval = null;
    }
    
    // Stop media tracks
    if (mediaStream) {
        mediaStream.getTracks().forEach(track => track.stop());
        mediaStream = null;
    }
    
    // Disable form submission if needed
    const examForm = document.getElementById('examForm');
    if (examForm) {
        const submitBtn = examForm.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
    }
}

// Export functions for global use
window.initProctoring = initProctoring;
window.stopProctoring = stopExam;