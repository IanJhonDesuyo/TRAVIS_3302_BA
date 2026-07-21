const stopCameraBtn = document.getElementById('stopCameraBtn');
const captureSnapshotBtn = document.getElementById('captureSnapshotBtn');
const sourceStatus = document.getElementById('sourceStatus');
const aiLiveStream = document.getElementById('aiLiveStream');
const streamFallback = document.getElementById('streamFallback');
const monitoringLogsBody = document.getElementById('monitoringLogsBody');
const startAnalysisBtn = document.getElementById('startAnalysisBtn');
const stopAnalysisBtn = document.getElementById('stopAnalysisBtn');
const analysisMessage = document.getElementById('analysisMessage');
const uploadVideoBtn = document.getElementById('uploadVideoBtn');
const cctvVideoInput = document.getElementById('cctvVideoInput');
const monitoringSource = document.getElementById('monitoringSource');
const tapoCameraFields = document.getElementById('tapoCameraFields');
const uploadSourceCard = document.getElementById('uploadSourceCard');
const uploadVideoForm = document.getElementById('uploadVideoForm');
const sourceActionTitle = document.getElementById('sourceActionTitle');
const calibrationProfile = document.getElementById('calibrationProfile');
const calibrationCanvas = document.getElementById('calibrationCanvas');
const calibrationEditor = document.getElementById('calibrationEditor');
const calibrationName = document.getElementById('calibrationName');
const calibrationInstruction = document.getElementById('calibrationInstruction');
const newCalibrationBtn = document.getElementById('newCalibrationBtn');
const saveCalibrationBtn = document.getElementById('saveCalibrationBtn');
const cancelCalibrationBtn = document.getElementById('cancelCalibrationBtn');
const addOfficerZoneBtn = document.getElementById('addOfficerZoneBtn');
const calibrationCsrf = document.getElementById('calibrationCsrf');
let previousCongestionAlertState = null;
let previousCollisionState = null;
let congestionAlertTimer = null;
let streamRetryTimer = null;
let currentAnalysisStatus = 'idle';
let switchingCalibration = false;

function hideCongestionAlert() {
  document.getElementById('congestionLiveAlert')?.classList.remove('show');
  if (congestionAlertTimer) window.clearTimeout(congestionAlertTimer);
}

function showCongestionAlert(data) {
  const alert = document.getElementById('congestionLiveAlert');
  const title = document.getElementById('liveSafetyAlertTitle');
  const message = document.getElementById('congestionLiveAlertMessage');
  if (!alert || !title || !message) return;

  const visible = Number(data.vehicle_count ?? 0);
  const level = String(data.congestion_level ?? 'Heavy');
  title.textContent = 'Heavy traffic congestion detected';
  message.textContent = `${level} congestion is active with ${visible} vehicle${visible === 1 ? '' : 's'} visible in the current frame.`;
  alert.classList.add('show');

  if (congestionAlertTimer) window.clearTimeout(congestionAlertTimer);
  congestionAlertTimer = window.setTimeout(hideCongestionAlert, 10000);
}

function showCollisionAlert(data, collisionState) {
  const alert = document.getElementById('congestionLiveAlert');
  const title = document.getElementById('liveSafetyAlertTitle');
  const message = document.getElementById('congestionLiveAlertMessage');
  if (!alert || !title || !message) return;

  title.textContent = collisionState === 'confirmed'
    ? 'Collision risk confirmed'
    : 'Potential collision detected';
  message.textContent = 'Vehicle trajectories indicate a collision risk. Review the live stream and alert details immediately.';
  alert.classList.add('show');

  if (congestionAlertTimer) window.clearTimeout(congestionAlertTimer);
  congestionAlertTimer = window.setTimeout(hideCongestionAlert, 12000);
}

// =============================
// API Configuration
// =============================
const API_BASE = "/TRAVIS/Web_app/api/";

function apiUrl(file) {
    return API_BASE + file;
}

const STREAM_URL = `${window.location.protocol}//${window.location.hostname}:5000/video_feed`;

function updateSourceFields() {
  const isTapo = monitoringSource?.value === 'tapo_camera';
  tapoCameraFields?.classList.toggle('d-none', !isTapo);
  uploadVideoForm?.classList.toggle('d-none', isTapo);

  if (sourceActionTitle) {
    sourceActionTitle.textContent = isTapo ? 'Tapo Camera Controls' : 'Upload CCTV Video';
  }

  const currentStartLabel = document.getElementById('startAnalysisLabel');
  if (currentStartLabel) {
    currentStartLabel.textContent = isTapo ? 'Start Tapo Camera' : 'Start Analysis';
  }
}

monitoringSource?.addEventListener('change', updateSourceFields);
updateSourceFields();

let calibrationPoints = [];
let officerZonePoints = [];
let drawingOfficerZone = false;

function sizeCalibrationCanvas() {
  if (!calibrationCanvas) return;
  const rect = calibrationCanvas.getBoundingClientRect();
  calibrationCanvas.width = Math.max(1, Math.round(rect.width));
  calibrationCanvas.height = Math.max(1, Math.round(rect.height));
  drawCalibrationLines();
}

function drawCalibrationLines() {
  if (!calibrationCanvas) return;
  const context = calibrationCanvas.getContext('2d');
  context.clearRect(0, 0, calibrationCanvas.width, calibrationCanvas.height);

  const drawLine = (points, color, label) => {
    if (!points.length) return;
    context.fillStyle = color;
    context.strokeStyle = color;
    context.lineWidth = 4;
    context.beginPath();
    context.arc(points[0][0] * calibrationCanvas.width, points[0][1] * calibrationCanvas.height, 6, 0, Math.PI * 2);
    context.fill();
    if (points.length === 2) {
      context.beginPath();
      context.moveTo(points[0][0] * calibrationCanvas.width, points[0][1] * calibrationCanvas.height);
      context.lineTo(points[1][0] * calibrationCanvas.width, points[1][1] * calibrationCanvas.height);
      context.stroke();
      context.font = 'bold 13px sans-serif';
      context.fillText(label, points[0][0] * calibrationCanvas.width + 8, points[0][1] * calibrationCanvas.height - 8);
    }
  };

  drawLine(calibrationPoints.slice(0, 2), '#22c55e', 'INBOUND');
  drawLine(calibrationPoints.slice(2, 4), '#ef4444', 'OUTBOUND');

  if (officerZonePoints.length) {
    context.fillStyle = 'rgba(34, 211, 238, .18)';
    context.strokeStyle = '#22d3ee';
    context.lineWidth = 3;
    context.beginPath();
    officerZonePoints.forEach((point, index) => {
      const x = point[0] * calibrationCanvas.width;
      const y = point[1] * calibrationCanvas.height;
      if (index === 0) context.moveTo(x, y);
      else context.lineTo(x, y);
    });
    if (officerZonePoints.length === 4) context.closePath();
    context.fill();
    context.stroke();
    officerZonePoints.forEach(point => {
      context.beginPath();
      context.arc(point[0] * calibrationCanvas.width, point[1] * calibrationCanvas.height, 5, 0, Math.PI * 2);
      context.fillStyle = '#22d3ee';
      context.fill();
    });
    context.font = 'bold 13px sans-serif';
    context.fillText(
      'ENFORCER ZONE',
      officerZonePoints[0][0] * calibrationCanvas.width + 8,
      officerZonePoints[0][1] * calibrationCanvas.height - 8
    );
  }
}

function closeCalibrationEditor() {
  calibrationPoints = [];
  officerZonePoints = [];
  drawingOfficerZone = false;
  calibrationCanvas?.classList.remove('active');
  calibrationEditor?.classList.add('d-none');
  if (calibrationName) calibrationName.value = '';
  if (saveCalibrationBtn) saveCalibrationBtn.disabled = true;
  if (addOfficerZoneBtn) {
    addOfficerZoneBtn.disabled = true;
    addOfficerZoneBtn.textContent = 'Add Enforcer Zone (Optional)';
  }
  drawCalibrationLines();
}

newCalibrationBtn?.addEventListener('click', () => {
  calibrationPoints = [];
  officerZonePoints = [];
  drawingOfficerZone = false;
  calibrationEditor?.classList.remove('d-none');
  calibrationCanvas?.classList.add('active');
  if (calibrationInstruction) calibrationInstruction.textContent = 'Click two points for the green inbound line.';
  if (saveCalibrationBtn) saveCalibrationBtn.disabled = true;
  if (addOfficerZoneBtn) addOfficerZoneBtn.disabled = true;
  sizeCalibrationCanvas();
});

cancelCalibrationBtn?.addEventListener('click', closeCalibrationEditor);
window.addEventListener('resize', sizeCalibrationCanvas);

addOfficerZoneBtn?.addEventListener('click', () => {
  officerZonePoints = [];
  drawingOfficerZone = true;
  addOfficerZoneBtn.textContent = 'Drawing Enforcer Zone...';
  if (saveCalibrationBtn) saveCalibrationBtn.disabled = true;
  if (calibrationInstruction) {
    calibrationInstruction.textContent = 'Click four corners around the designated enforcer area, in order.';
  }
  drawCalibrationLines();
});

calibrationCanvas?.addEventListener('click', event => {
  const rect = calibrationCanvas.getBoundingClientRect();
  const point = [
    Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
    Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height))
  ];

  if (drawingOfficerZone) {
    if (officerZonePoints.length >= 4) return;
    officerZonePoints.push(point);
    if (officerZonePoints.length === 4) {
      drawingOfficerZone = false;
      addOfficerZoneBtn.textContent = 'Redraw Enforcer Zone';
      if (saveCalibrationBtn) saveCalibrationBtn.disabled = false;
      if (calibrationInstruction) calibrationInstruction.textContent = 'Enforcer zone ready. Enter a name and save.';
    } else if (calibrationInstruction) {
      calibrationInstruction.textContent = `Click ${4 - officerZonePoints.length} more enforcer-zone corner${4 - officerZonePoints.length === 1 ? '' : 's'}.`;
    }
    drawCalibrationLines();
    return;
  }

  if (calibrationPoints.length >= 4) return;
  calibrationPoints.push(point);
  drawCalibrationLines();

  if (calibrationInstruction) {
    if (calibrationPoints.length < 2) calibrationInstruction.textContent = 'Click the second point for the green inbound line.';
    else if (calibrationPoints.length === 2) calibrationInstruction.textContent = 'Now click two points for the red outbound line.';
    else if (calibrationPoints.length === 3) calibrationInstruction.textContent = 'Click the second point for the red outbound line.';
    else calibrationInstruction.textContent = 'Both lines are ready. Optionally add an enforcer zone, or enter a name and save.';
  }
  if (calibrationPoints.length === 4 && addOfficerZoneBtn) addOfficerZoneBtn.disabled = false;
  if (saveCalibrationBtn) saveCalibrationBtn.disabled = calibrationPoints.length !== 4;
});

saveCalibrationBtn?.addEventListener('click', async () => {
  const name = calibrationName?.value.trim() ?? '';
  if (!name || calibrationPoints.length !== 4) {
    if (calibrationInstruction) calibrationInstruction.textContent = 'Enter a name and draw both lines first.';
    return;
  }

  saveCalibrationBtn.disabled = true;
  try {
    const response = await fetchJson(apiUrl('calibration_profiles.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        csrf_token: calibrationCsrf?.value ?? '',
        profile_name: name,
        inbound_line: calibrationPoints.slice(0, 2),
        outbound_line: calibrationPoints.slice(2, 4),
        officer_zone: officerZonePoints
      })
    });
    const option = new Option(response.profile.name, response.profile.file, true, true);
    calibrationProfile?.add(option);
    closeCalibrationEditor();
    if (analysisMessage) {
      analysisMessage.textContent = response.message;
      analysisMessage.className = 'small mt-2 text-success';
    }
  } catch (error) {
    saveCalibrationBtn.disabled = false;
    if (calibrationInstruction) calibrationInstruction.textContent = error.message;
  }
});

function setText(id, value) {
  const el = document.getElementById(id);
  if (!el) return;

  const nextValue = value ?? 'No data';
  if (el.textContent !== String(nextValue)) {
    el.textContent = nextValue;
  }
}

function badgeClass(type, value) {
  const normalized = String(value ?? '').toLowerCase();

  if (type === 'congestion') {
    if (normalized === 'light' || normalized === 'low') return 'tag tag-success';
    if (normalized === 'moderate') return 'tag tag-warning';
    if (normalized === 'heavy') return 'tag tag-danger';
  }

  if (type === 'alert') {
    if (normalized === 'normal') return 'tag tag-success';
    if (normalized === 'warning') return 'tag tag-warning';
    if (normalized === 'alert') return 'tag tag-danger';
  }

  if (type === 'officer') {
    if (normalized === 'detected') return 'tag tag-success';
    if (normalized === 'multiple') return 'tag tag-info';
    if (normalized === 'none') return 'tag tag-warning';
    if (normalized === 'unknown') return 'tag tag-muted';
  }

  if (type === 'ai') {
    if (normalized === 'running' || normalized === 'completed') return 'tag tag-success';
    if (normalized === 'starting') return 'tag tag-warning';
    if (normalized === 'idle' || normalized === 'offline' || normalized === 'stopped') return 'tag tag-muted';
    if (normalized === 'error') return 'tag tag-danger';
  }

  if (normalized === 'none' || normalized === 'unknown') return 'tag tag-muted';
  if (normalized === 'possible' || normalized === 'warning') return 'tag tag-warning';
  if (normalized === 'confirmed' || normalized === 'alert') return 'tag tag-danger';

  return 'tag tag-info';
}

function setBadge(id, value, type) {
  const el = document.getElementById(id);
  if (!el) return;

  const nextValue = value ?? 'Unknown';
  if (el.textContent !== String(nextValue)) {
    el.textContent = nextValue;
  }
  el.className = badgeClass(type, nextValue);
}

function setAnalysisControls(status, message) {
  const normalized = String(status ?? 'Idle').toLowerCase();
  const isBusy = normalized === 'starting' || normalized === 'running';
  const isTapo = monitoringSource?.value === 'tapo_camera';
  const startLabel = isTapo ? 'Start Tapo Camera' : 'Start Analysis';

  if (startAnalysisBtn) {
    startAnalysisBtn.disabled = isBusy;
    startAnalysisBtn.innerHTML = normalized === 'starting'
      ? '<i class="bi bi-hourglass-split me-1"></i>Starting...'
      : `<i class="bi bi-play-circle me-1"></i><span id="startAnalysisLabel">${startLabel}</span>`;
  }

  if (stopAnalysisBtn) {
    stopAnalysisBtn.disabled = !isBusy;
  }

  if (uploadVideoBtn) {
    uploadVideoBtn.disabled = isBusy;
  }

  if (cctvVideoInput) {
    cctvVideoInput.disabled = isBusy;
  }

  if (analysisMessage) {
    analysisMessage.textContent = message ?? '';
    analysisMessage.className = normalized === 'error'
      ? 'small mt-2 text-danger'
      : 'small mt-2 text-muted';
  }
}

function setAnalysisSource(data) {
  const labels = {
    tapo_camera: 'Tapo Camera',
    tapo: 'Tapo Camera',
    uploaded_video: 'Uploaded Video',
    video: 'Uploaded Video'
  };
  const label = data.source_label ?? labels[data.source_type] ?? 'Video Source';
  const profile = data.calibration_profile ? ` · ${data.calibration_profile}` : '';
  setText('analysisSource', `${label}${profile}`);
}

function setProgress(data) {
  const analysisStatus = String(data.analysis_status ?? data.ai_status ?? 'Idle').toLowerCase();
  const progressText = document.getElementById('analysisProgressText');
  const progressBar = document.getElementById('analysisProgressBar');

  if (analysisStatus === 'idle' || analysisStatus === 'stopped' || analysisStatus === 'completed') {
    if (progressText) {
      progressText.textContent = analysisStatus.charAt(0).toUpperCase() + analysisStatus.slice(1);
    }
    if (progressBar) {
      progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
      progressBar.style.width = analysisStatus === 'completed' ? '100%' : '0%';
    }
    return;
  }

  const currentFrame = Number(data.current_frame ?? 0);
  const totalFrames = Number(data.total_frames ?? 0);
  const percent = Number(data.progress_percent ?? 0);

  if (progressText) {
    progressText.textContent = totalFrames > 0
      ? `${currentFrame} / ${totalFrames} frames (${percent.toFixed(1)}%)`
      : 'Waiting for frames';
  }

  if (progressBar) {
    progressBar.classList.remove('progress-bar-striped', 'progress-bar-animated');
    progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
  }
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function renderLogs(logs) {
  if (!monitoringLogsBody) return;

  if (!logs || logs.length === 0) {
    monitoringLogsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">No monitoring logs yet.</td></tr>';
    return;
  }

  const rows = logs.map(log => {
    const alertStatus = Number(log.alert_generated) === 1 ? 'ALERT' : 'NORMAL';
    return `
      <tr>
        <td>${escapeHtml(log.recorded_at)}</td>
        <td>${escapeHtml(log.vehicle_count)}</td>
        <td>${escapeHtml(log.inbound_count)}</td>
        <td>${escapeHtml(log.outbound_count)}</td>
        <td><span class="${badgeClass('congestion', log.congestion_level)}">${escapeHtml(log.congestion_level)}</span></td>
        <td><span class="${badgeClass('alert', alertStatus)}">${alertStatus}</span></td>
      </tr>
    `;
  }).join('');

  if (monitoringLogsBody.innerHTML.trim() !== rows.trim()) {
    monitoringLogsBody.innerHTML = rows;
  }
}

async function fetchJson(url, options = {}) {
  const response = await fetch(url, {
    cache: 'no-store',
    ...options
  });

  const raw = await response.text();

  // DEBUG OUTPUT
  console.group("API DEBUG");
  console.log("URL:", url);
  console.log("HTTP Status:", response.status);
  console.log("Raw Response:");
  console.log(raw);
  console.groupEnd();

  let data;

  try {
    data = JSON.parse(raw);
  } catch (e) {

    alert(
      "BACKEND RESPONSE:\n\n" +
      raw
    );

    throw new Error(
      "Backend did not return valid JSON.\n\nCheck browser console."
    );
  }

  if (!response.ok) {
    throw new Error(data.message ?? `HTTP ${response.status}`);
  }

  return data;
}

function parseJsonResponse(raw) {
  const text = String(raw ?? '').trim();

  if (text === '') {
    return {};
  }

  try {
    return JSON.parse(text);
  } catch (error) {
    console.error('Invalid JSON response from API:', text);

    const objectStart = text.indexOf('{');
    const objectEnd = text.lastIndexOf('}');
    const arrayStart = text.indexOf('[');
    const arrayEnd = text.lastIndexOf(']');

    const objectJson = objectStart !== -1 && objectEnd > objectStart
      ? text.slice(objectStart, objectEnd + 1)
      : '';
    const arrayJson = arrayStart !== -1 && arrayEnd > arrayStart
      ? text.slice(arrayStart, arrayEnd + 1)
      : '';

    const candidate = objectJson || arrayJson;
    if (candidate) {
      try {
        return JSON.parse(candidate);
      } catch (candidateError) {
        throw new Error('Invalid JSON response.');
      }
    }

    throw new Error('Invalid JSON response.');
  }
}

async function refreshMonitoringStatus() {
  try {
    const data = await fetchJson(apiUrl('get_status.php'));
    const analysisStatus = data.analysis_status ?? data.ai_status ?? 'Idle';
    currentAnalysisStatus = String(analysisStatus).toLowerCase();
    if (!switchingCalibration && data.calibration_profile && calibrationProfile) {
      const activeOption = Array.from(calibrationProfile.options).find(
        option => option.text === data.calibration_profile || option.value === data.calibration_profile
      );
      if (activeOption) calibrationProfile.value = activeOption.value;
    }

    setBadge('aiStatus', analysisStatus, 'ai');
    setText('vehicleCount', data.vehicle_count ?? 0);
    setText('inboundCount', data.inbound_count ?? 0);
    setText('outboundCount', data.outbound_count ?? 0);
    setBadge('congestionLevel', data.congestion_level ?? 'Unknown', 'congestion');
    setBadge('alertStatus', data.alert_status ?? 'NORMAL', 'alert');

    const congestionAlertState = String(data.alert_status ?? 'NORMAL').toLowerCase();
    if (congestionAlertState === 'alert' && previousCongestionAlertState !== 'alert') {
      showCongestionAlert(data);
    }
    previousCongestionAlertState = congestionAlertState;

    setBadge('officerPresence', data.officer_presence ?? 'Unknown', 'officer');
    setBadge('potentialCollision', data.potential_collision ?? 'None', 'default');

    const collisionState = String(data.potential_collision ?? 'none').toLowerCase();
    if (
      (collisionState === 'possible' || collisionState === 'confirmed')
      && collisionState !== previousCollisionState
    ) {
      showCollisionAlert(data, collisionState);
    }
    previousCollisionState = collisionState;

    setText('lastUpdated', data.recorded_at ?? 'No data');
    setAnalysisControls(analysisStatus, data.message ?? '');
    setAnalysisSource(data);
    setProgress(data);

    if (sourceStatus && String(analysisStatus).toLowerCase() === 'running') {
      sourceStatus.textContent = 'Live Data Active';
      sourceStatus.className = 'tag tag-success';
    } else if (sourceStatus && String(analysisStatus).toLowerCase() === 'starting') {
      sourceStatus.textContent = 'Starting AI Stream';
      sourceStatus.className = 'tag tag-info';
    } else if (sourceStatus) {
      sourceStatus.textContent = analysisStatus;
      sourceStatus.className = badgeClass('ai', analysisStatus);
    }
  } catch (error) {
    setBadge('aiStatus', 'Offline', 'ai');
    setAnalysisControls('Idle', '');
    if (sourceStatus) {
      sourceStatus.textContent = 'Waiting for AI Data';
      sourceStatus.className = 'tag tag-warning';
    }
  }
}

async function refreshMonitoringLogs() {
  try {
    const data = await fetchJson(apiUrl('get_monitoring_logs.php'))
    renderLogs(data.logs ?? []);
  } catch (error) {
    if (monitoringLogsBody) {
      monitoringLogsBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Unable to load monitoring logs.</td></tr>';
    }
  }
}

function connectStreamWithRetry(attempt = 0) {
  if (!aiLiveStream) return;

  if (streamRetryTimer) {
    window.clearTimeout(streamRetryTimer);
    streamRetryTimer = null;
  }

  aiLiveStream.style.display = 'block';
  if (streamFallback) {
    streamFallback.style.display = 'none';
  }

  aiLiveStream.onload = () => {
    if (streamRetryTimer) window.clearTimeout(streamRetryTimer);
    streamRetryTimer = null;
    aiLiveStream.style.display = 'block';
    if (streamFallback) streamFallback.style.display = 'none';
    if (sourceStatus) {
      sourceStatus.textContent = 'Live Data Active';
      sourceStatus.className = 'tag tag-success';
    }
  };

  aiLiveStream.onerror = () => {
    aiLiveStream.style.display = 'none';
    if (attempt < 12) {
      streamRetryTimer = window.setTimeout(
        () => connectStreamWithRetry(attempt + 1),
        1500
      );
      return;
    }
    if (streamFallback) streamFallback.style.display = 'flex';
    if (sourceStatus) {
      sourceStatus.textContent = 'AI Stream Unavailable';
      sourceStatus.className = 'tag tag-danger';
    }
  };

  aiLiveStream.src = `${STREAM_URL}?t=${new Date().getTime()}`;

  if (sourceStatus) {
    sourceStatus.textContent = 'Connecting AI Stream';
    sourceStatus.className = 'tag tag-info';
  }
}

function hideStream() {
  if (streamRetryTimer) window.clearTimeout(streamRetryTimer);
  streamRetryTimer = null;
  if (aiLiveStream) {
    aiLiveStream.onload = null;
    aiLiveStream.onerror = null;
    aiLiveStream.style.display = 'none';
  }

  if (streamFallback) {
    streamFallback.style.display = 'flex';
  }

  if (sourceStatus) {
    sourceStatus.textContent = 'Stream Hidden';
    sourceStatus.className = 'tag tag-warning';
  }
}

async function startAnalysis() {
  if (startAnalysisBtn) {
    startAnalysisBtn.disabled = true;
  }

  setAnalysisControls('Starting', 'Starting AI analysis...');

  try {
    const sourceType = monitoringSource?.value ?? 'uploaded_video';
    const requestBody = { source_type: sourceType };
    requestBody.calibration_profile = calibrationProfile?.value ?? '';

    if (sourceType === 'tapo_camera') {
      requestBody.tapo_host = document.getElementById('tapoHost')?.value.trim() ?? '';
      requestBody.tapo_username = document.getElementById('tapoUsername')?.value.trim() ?? '';
      requestBody.tapo_password = document.getElementById('tapoPassword')?.value ?? '';
      requestBody.tapo_stream = document.getElementById('tapoStream')?.value ?? 'stream2';
    }

    const response = await fetchJson(apiUrl('start_analysis.php'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(requestBody)
    });

    if (response.success !== true) {
      throw new Error(response.message ?? 'Unable to start AI analysis.');
    }

    setAnalysisControls(
      response.analysis_status ?? 'Starting',
      response.message ?? 'Starting AI analysis...'
    );

    connectStreamWithRetry();
    refreshDashboard();

  } catch (error) {
    const message =
      error.message && error.message !== 'Request failed.'
        ? error.message
        : 'Unable to start analysis. Check upload and server permissions.';

    setAnalysisControls('Error', message);

  } finally {

    if (startAnalysisBtn) {
      startAnalysisBtn.disabled = false;
    }

  }

}

async function stopAnalysis() {

  if (stopAnalysisBtn) {
    stopAnalysisBtn.disabled = true;
  }

  try {

    const response = await fetchJson(apiUrl('stop_analysis.php'), {
      method: 'POST'
    });

    // Update dashboard status
    setAnalysisControls(
      response.analysis_status ?? 'Stopped',
      response.message ?? 'Analysis stopped.'
    );

    // ==========================
    // STOP THE VIDEO STREAM
    // ==========================

    if (aiLiveStream) {

      if (streamRetryTimer) window.clearTimeout(streamRetryTimer);
      streamRetryTimer = null;
      aiLiveStream.onload = null;
      aiLiveStream.onerror = null;

      // Disconnect the Flask stream
      aiLiveStream.src = "";

      // Hide the <img>
      aiLiveStream.style.display = "none";

    }

    if (streamFallback) {

      // Show the "Waiting for AI live stream" placeholder again
      streamFallback.style.display = "flex";

    }

    if (sourceStatus) {

      sourceStatus.textContent = "Waiting for AI Data";
      sourceStatus.className = "tag tag-warning";

    }

    refreshDashboard();

  } catch (error) {

    const message =
      error.message && error.message !== 'Request failed'
        ? error.message
        : 'Unable to stop analysis.';

    setAnalysisControls('Error', message);

  } finally {

    if (stopAnalysisBtn) {
      stopAnalysisBtn.disabled = false;
    }

  }

}

if (stopCameraBtn) stopCameraBtn.addEventListener('click', hideStream);
if (captureSnapshotBtn) {
  captureSnapshotBtn.addEventListener('click', () => {
    window.open(STREAM_URL, '_blank');
  });
}
if (startAnalysisBtn) startAnalysisBtn.addEventListener('click', startAnalysis);
if (stopAnalysisBtn) stopAnalysisBtn.addEventListener('click', stopAnalysis);

calibrationProfile?.addEventListener('change', async () => {
  const selectedName = calibrationProfile.options[calibrationProfile.selectedIndex]?.text ?? 'Selected configuration';
  if (!['running', 'starting'].includes(currentAnalysisStatus)) {
    if (analysisMessage) {
      analysisMessage.textContent = `${selectedName} selected. It will be used when analysis starts.`;
      analysisMessage.className = 'small mt-2 text-info';
    }
    return;
  }

  if (switchingCalibration) return;
  switchingCalibration = true;
  calibrationProfile.disabled = true;
  if (analysisMessage) {
    analysisMessage.textContent = `Applying ${selectedName} and restarting analysis...`;
    analysisMessage.className = 'small mt-2 text-info';
  }

  await stopAnalysis();
  await new Promise(resolve => window.setTimeout(resolve, 700));
  await startAnalysis();

  calibrationProfile.disabled = false;
  switchingCalibration = false;
});

function refreshDashboard() {
  refreshMonitoringStatus();
  refreshMonitoringLogs();
}

refreshDashboard();
setInterval(refreshDashboard, 2000);
