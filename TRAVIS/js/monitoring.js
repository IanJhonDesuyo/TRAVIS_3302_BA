const startCameraBtn = document.getElementById('startCameraBtn');
const stopCameraBtn = document.getElementById('stopCameraBtn');
const captureSnapshotBtn = document.getElementById('captureSnapshotBtn');
const sourceStatus = document.getElementById('sourceStatus');
const aiLiveStream = document.getElementById('aiLiveStream');
const streamFallback = document.getElementById('streamFallback');
const monitoringLogsBody = document.getElementById('monitoringLogsBody');
const analyzeVideoBtn = document.getElementById('analyzeVideoBtn');
const analysisMessage = document.getElementById('analysisMessage');

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
    if (normalized === 'running') return 'tag tag-success';
    if (normalized === 'completed') return 'tag tag-success';
    if (normalized === 'starting') return 'tag tag-warning';
    if (normalized === 'idle' || normalized === 'offline') return 'tag tag-muted';
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

  if (analyzeVideoBtn) {
    analyzeVideoBtn.disabled = isBusy;
    analyzeVideoBtn.innerHTML = isBusy
      ? '<i class="bi bi-hourglass-split me-1"></i>Analysis Running'
      : '<i class="bi bi-play-circle me-1"></i>Analyze Video';
  }

  if (analysisMessage) {
    analysisMessage.textContent = message ?? '';
    analysisMessage.className = normalized === 'error'
      ? 'small mt-2 text-danger'
      : 'small mt-2 text-muted';
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
  if (!response.ok) {
    throw new Error('Request failed.');
  }
  return response.json();
}

async function refreshMonitoringStatus() {
  try {
    const data = await fetchJson('api/get_status.php');
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

    if (sourceStatus) {
      sourceStatus.textContent = 'Live Data Active';
      sourceStatus.className = 'tag tag-success';
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
    const data = await fetchJson('api/get_monitoring_logs.php');
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
    streamFallback.style.display = 'none';
  }

  aiLiveStream.src = 'http://localhost:5000/video_feed?t=' + new Date().getTime();

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
  if (analyzeVideoBtn) {
    analyzeVideoBtn.disabled = true;
  }
  setAnalysisControls('Starting', 'Starting AI analysis...');

  try {
    const response = await fetchJson('api/start_analysis.php', {
      method: 'POST'
    });
    setAnalysisControls(response.analysis_status ?? 'Starting', response.message ?? '');
    reconnectStream();
    refreshDashboard();
  } catch (error) {
    setAnalysisControls('Error', 'Unable to start analysis. Check upload and server permissions.');
  }
}

if (startCameraBtn) startCameraBtn.addEventListener('click', reconnectStream);
if (stopCameraBtn) stopCameraBtn.addEventListener('click', hideStream);
if (captureSnapshotBtn) {
  captureSnapshotBtn.addEventListener('click', () => {
    window.open('http://localhost:5000/video_feed', '_blank');
  });
}
if (analyzeVideoBtn) analyzeVideoBtn.addEventListener('click', startAnalysis);

function refreshDashboard() {
  refreshMonitoringStatus();
  refreshMonitoringLogs();
}

refreshDashboard();
setInterval(refreshDashboard, 2000);
