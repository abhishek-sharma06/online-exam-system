/**
 * AI Proctoring Script (Client-Side) - High Performance
 * Integrates face-api.js for face/gaze detection and TensorFlow.js (COCO-SSD) for object detection.
 */

let aiInitialized = false;
let objectModel = null;
let lastVideoTime = -1;
let objectCheckInterval = null;

// Optimizing interval for faster object detection
const OBJECT_CHECK_INTERVAL_MS = 1000; 

// Using jsdelivr for model weights
const FACE_API_MODELS = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';

// Global init function called by proctoring.js
window.initAIProctoring = async function() {
    try {
        console.log('[AI] Initializing High-Performance AI Proctoring...');
        
        // Ensure scripts are loaded
        if (typeof faceapi === 'undefined' || typeof cocoSsd === 'undefined') {
            console.warn('[AI] Libraries not loaded yet. Retrying in 1s...');
            setTimeout(window.initAIProctoring, 1000);
            return;
        }

        // 1. Load face-api.js models
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(FACE_API_MODELS),
            faceapi.nets.faceLandmark68Net.loadFromUri(FACE_API_MODELS)
        ]);
        console.log('[AI] Face API models loaded successfully.');

        // 2. Load COCO-SSD for object detection
        objectModel = await cocoSsd.load();
        console.log('[AI] Object detection model loaded successfully.');

        aiInitialized = true;
        startAILoops();
    } catch (e) {
        console.error('[AI] Initialization failed:', e);
    }
};

function startAILoops() {
    const video = document.getElementById('webcam');
    if (!video) {
        console.warn('[AI] Webcam element not found');
        return;
    }

    console.log('[AI] Starting high-performance continuous monitoring loops...');

    // 1. Start continuous face tracking loop using requestAnimationFrame for zero-lag
    requestAnimationFrame(() => detectFaceContinuously(video));

    // 2. Start object detection loop
    if (objectCheckInterval) clearInterval(objectCheckInterval);
    objectCheckInterval = setInterval(() => detectObjects(video), OBJECT_CHECK_INTERVAL_MS);
}

async function detectFaceContinuously(video) {
    if (!aiInitialized || (typeof isDisqualified !== 'undefined' && isDisqualified) || video.paused || video.ended) {
        return; // Stop if exam ended
    }

    try {
        if (lastVideoTime !== video.currentTime) {
            lastVideoTime = video.currentTime;
            
            // First check lighting
            const isLowLight = checkLighting(video);

            // Lowered scoreThreshold from default 0.5 to 0.3 to detect faces better in poor conditions
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({ scoreThreshold: 0.3 })).withFaceLandmarks();
            
            if (detections.length === 0) {
                // Only warn about no face if lighting is adequate. Otherwise, low light is the real issue.
                if (!isLowLight && typeof recordViolation === 'function') {
                    recordViolation('NO_FACE_DETECTED', 'No face detected in webcam frame');
                }
            } else if (detections.length > 1) {
                if (typeof recordViolation === 'function') {
                    recordViolation('MULTIPLE_FACES', `Multiple faces detected (${detections.length})`);
                }
            } else {
                // Check Head Pose/Focus loosely using landmarks
                const landmarks = detections[0].landmarks;
                const nose = landmarks.getNose()[0];
                const leftEye = landmarks.getLeftEye()[0];
                const rightEye = landmarks.getRightEye()[0];
                
                // Advanced 3D-like Pitch and Yaw estimation from 2D points
                // Distance between eyes
                const eyeDist = Math.sqrt(Math.pow(rightEye.x - leftEye.x, 2) + Math.pow(rightEye.y - leftEye.y, 2));
                
                // Horizontal turning (Yaw)
                const leftDist = Math.abs(nose.x - leftEye.x);
                const rightDist = Math.abs(rightEye.x - nose.x);
                const yawRatio = Math.max(leftDist, rightDist) / Math.min(leftDist, rightDist);
                
                // If yawRatio is very high (> 2.5), it means the nose is extremely close to one eye (head turned far)
                if (yawRatio > 2.5) {
                    if (typeof recordViolation === 'function') {
                        recordViolation('OFF_SCREEN_FOCUS', 'Candidate appears to be looking away from screen');
                    }
                }
            }
        }
    } catch (e) {
        console.warn('[AI] Error in face tracking loop:', e);
    }

    // Call again for next frame
    requestAnimationFrame(() => detectFaceContinuously(video));
}

async function detectObjects(video) {
    if (!aiInitialized || (typeof isDisqualified !== 'undefined' && isDisqualified) || video.paused || video.ended) {
        return;
    }

    try {
        if (objectModel) {
            const predictions = await objectModel.detect(video);
            const forbiddenObjects = ['cell phone', 'laptop', 'tv', 'remote', 'book'];
            
            predictions.forEach(p => {
                // Confidence score threshold set to 0.40 to aggressively detect dark phones
                if (forbiddenObjects.includes(p.class.toLowerCase()) && p.score > 0.40) {
                    if (typeof recordViolation === 'function') {
                        recordViolation('UNAUTHORIZED_DEVICE', `Unauthorized object detected: ${p.class}`);
                    }
                }
            });
        }
    } catch (e) {
        console.warn('[AI] Error in object detection loop:', e);
    }
}

// Ensure lighting check doesn't block heavily. Called from the RAF loop
function checkLighting(video) {
    if (!video.videoWidth) return false;
    
    // Throttle lighting check (only once per second)
    if (!checkLighting.lastCheck || (performance.now() - checkLighting.lastCheck > 1000)) {
        checkLighting.lastCheck = performance.now();
        
        const canvas = document.createElement('canvas');
        // scale down drastically for just finding average brightness
        canvas.width = 64; 
        canvas.height = 48;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;
        let colorSum = 0;
        
        let count = 0;
        for(let x = 0, len = data.length; x < len; x+=16) {
            colorSum += (data[x] + data[x+1] + data[x+2]) / 3;
            count++;
        }
        
        const brightness = Math.floor(colorSum / count);
        
        if (brightness < 45) {
            if (typeof recordViolation === 'function') {
                recordViolation('LOW_LIGHT', 'Lighting is too low, please move to a well-lit area');
            }
            checkLighting.lastResult = true;
        } else {
            checkLighting.lastResult = false;
        }
    }
    
    return checkLighting.lastResult || false;
}

// Auto-init if video is already present
if (document.getElementById('webcam') && document.getElementById('webcam').srcObject) {
    if (typeof window.initAIProctoring === 'function' && !aiInitialized) {
        window.initAIProctoring();
    }
}
