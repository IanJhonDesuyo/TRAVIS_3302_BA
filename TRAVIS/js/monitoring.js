const startCameraBtn = document.getElementById('startCameraBtn');
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

// =============================
// API Configuration
// =============================
const API_BASE = "/TRAVIS/Web_app/api/";

function apiUrl(file) {
    return API_BASE + file;
}

const STREAM_URL = "http://localhost:5000/video_feed";

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

  if (startAnalysisBtn) {
    startAnalysisBtn.disabled = isBusy;
    startAnalysisBtn.innerHTML = normalized === 'starting'
      ? '<i class="bi bi-hourglass-split me-1"></i>Starting...'
      : '<i class="bi bi-play-circle me-1"></i>Start Analysis';
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
  const label = data.source_label ?? 'Uploaded Video';
  const name = data.source_name ? ` ${data.source_name}` : '';
  setText('analysisSource', `${label}${name}`);
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

    setBadge('aiStatus', analysisStatus, 'ai');
    setText('vehicleCount', data.vehicle_count ?? 0);
    setText('inboundCount', data.inbound_count ?? 0);
    setText('outboundCount', data.outbound_count ?? 0);
    setBadge('congestionLevel', data.congestion_level ?? 'Unknown', 'congestion');
    setBadge('alertStatus', data.alert_status ?? 'NORMAL', 'alert');
    setBadge('officerPresence', data.officer_presence ?? 'Unknown', 'default');
    setBadge('potentialCollision', data.potential_collision ?? 'None', 'default');
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

function reconnectStream() {
  if (!aiLiveStream) return;

  aiLiveStream.style.display = 'block';

  if (streamFallback) {
    streamFallback.style.display = "none";
}
  if (streamFallback) {
    streamFallback.style.display = 'none';
  }

  aiLiveStream.src = `${STREAM_URL}?t=${new Date().getTime()}`;

  if (sourceStatus) {
    sourceStatus.textContent = 'Connecting AI Stream';
    sourceStatus.className = 'tag tag-info';
  }
}

function hideStream() {
  if (aiLiveStream) {
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
    const response = await fetchJson(apiUrl('start_analysis.php'), {
      method: 'POST'
    });

    if (response.success !== true) {
      throw new Error(response.message ?? 'Unable to start AI analysis.');
    }

    setAnalysisControls(
      response.analysis_status ?? 'Starting',
      response.message ?? 'Starting AI analysis...'
    );

    reconnectStream();
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

if (startCameraBtn) startCameraBtn.addEventListener('click', reconnectStream);
if (stopCameraBtn) stopCameraBtn.addEventListener('click', hideStream);
if (captureSnapshotBtn) {
  captureSnapshotBtn.addEventListener('click', () => {
    window.open(STREAM_URL, '_blank');
  });
}
if (startAnalysisBtn) startAnalysisBtn.addEventListener('click', startAnalysis);
if (stopAnalysisBtn) stopAnalysisBtn.addEventListener('click', stopAnalysis);

function refreshDashboard() {
  refreshMonitoringStatus();
  refreshMonitoringLogs();
}

refreshDashboard();
setInterval(refreshDashboard, 2000);
