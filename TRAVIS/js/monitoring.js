    const stopCameraBtn = document.getElementById('stopCameraBtn');
    const captureSnapshotBtn = document.getElementById('captureSnapshotBtn');
    const sourceStatus = document.getElementById('sourceStatus');
    const aiLiveStream = document.getElementById('aiLiveStream');
    const cameraStage = document.getElementById('cameraStage');
    const webrtcLiveStream = document.getElementById('webrtcLiveStream');
    const streamTransportHelp = document.getElementById('streamTransportHelp');
    const snapshotCanvas = document.getElementById('snapshotCanvas');
    const streamFallback = document.getElementById('streamFallback');
    const streamFallbackTitle = document.getElementById('streamFallbackTitle');
    const streamFallbackMessage = document.getElementById('streamFallbackMessage');
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
    const editCalibrationBtn = document.getElementById('editCalibrationBtn');
    const deleteCalibrationBtn = document.getElementById('deleteCalibrationBtn');
    const saveCalibrationBtn = document.getElementById('saveCalibrationBtn');
    const cancelCalibrationBtn = document.getElementById('cancelCalibrationBtn');
    const addOfficerZoneBtn = document.getElementById('addOfficerZoneBtn');
    const clearOfficerZoneBtn = document.getElementById('clearOfficerZoneBtn');
    const drawInboundLineBtn = document.getElementById('drawInboundLineBtn');
    const drawOutboundLineBtn = document.getElementById('drawOutboundLineBtn');
    const calibrationEditorHeading = document.getElementById('calibrationEditorHeading');
    const calibrationEditBadge = document.getElementById('calibrationEditBadge');
    const calibrationCsrf = document.getElementById('calibrationCsrf');
    const cvProcessModalElement = document.getElementById('cvProcessModal');
    const cvProcessTitle = document.getElementById('cvProcessTitle');
    const cvProcessDetail = document.getElementById('cvProcessDetail');
    const cvProcessIcon = document.getElementById('cvProcessIcon');
    const cvProcessBar = document.getElementById('cvProcessBar');
    const cvProcessStage = document.getElementById('cvProcessStage');
    const cvProcessPercent = document.getElementById('cvProcessPercent');
    const cvProcessActions = document.getElementById('cvProcessActions');
    const cvProcessCloseBtn = document.getElementById('cvProcessCloseBtn');
    const uploadResultAlert = document.getElementById('uploadResultAlert');
    let previousCongestionAlertState = null;
    let previousCollisionState = null;
    let congestionAlertTimer = null;
    let streamRetryTimer = null;
    let webrtcStartupTimer = null;
    let streamConnectionRequested = false;
    let streamHasFrame = false;
    let snapshotStreamGeneration = 0;
    let currentAnalysisStatus = 'idle';
    let currentSharedSourceType = '';
    let switchingCalibration = false;
    let calibrationSelectionTouched = false;
    let sourceSelectionTouched = false;
    let deletingCalibration = false;
    let loadingCalibration = false;
    let savingCalibration = false;
    let editingCalibrationFile = null;
    let cvActionBusy = false;
    let cvProcessMode = null;
    let cvProcessModal = null;
    let cvStartRequestedAt = 0;
    let startAnalysisRequestPending = false;
    const START_ANALYSIS_GRACE_MS = 180000;
    // TEMPORARY DIAGNOSTIC SWITCH: keep the detector alive while testing the
    // Tapo stream. Change this back to true after the continuous-run test.
    const STOP_ANALYSIS_ENABLED = false;
    let uploadInProgress = false;
    let uploadedFootageReady = uploadVideoForm?.dataset.footageReady === 'true';

    cctvVideoInput?.addEventListener('change', () => {
      if (cctvVideoInput.files?.length) {
        // A newly selected file must finish uploading before Start Analysis may
        // reuse the server-side video slot.
        uploadedFootageReady = false;
        if (uploadVideoForm) uploadVideoForm.dataset.footageReady = 'false';
      }
    });

    if (uploadResultAlert?.dataset.uploadSuccess === 'true') {
      window.setTimeout(() => {
        uploadResultAlert.classList.remove('show');
        window.setTimeout(() => uploadResultAlert.remove(), 180);
      }, 3500);
    }

    function hideCongestionAlert() {
      document.getElementById('congestionLiveAlert')?.classList.remove('show');
      if (congestionAlertTimer) window.clearTimeout(congestionAlertTimer);
    }

    function showCongestionAlert(data) {
      const alert = document.getElementById('congestionLiveAlert');
      const title = document.getElementById('liveSafetyAlertTitle');
      const message = document.getElementById('congestionLiveAlertMessage');
      if (!alert || !title || !message) return;
      alert.classList.remove('collision-alert');
      alert.classList.add('congestion-alert');

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
      // Collision uses the same visual treatment as the heavy-traffic alert.
      alert.classList.remove('collision-alert');
      alert.classList.add('congestion-alert');

      title.textContent = collisionState === 'confirmed'
        ? 'Collision confirmed'
        : 'Collision detection update';
      message.textContent = collisionState === 'confirmed'
        ? 'Computer vision confirmed a collision. Review the live stream and alert details immediately.'
        : 'Vehicle trajectories indicate a possible collision risk. Review the live stream.';
      alert.classList.add('show');

      if (congestionAlertTimer) window.clearTimeout(congestionAlertTimer);
      congestionAlertTimer = window.setTimeout(hideCongestionAlert, 12000);
    }

    // =============================
    // API Configuration
    // =============================
    const monitoringScriptUrl = document.currentScript?.src || `${window.location.origin}/TRAVIS/js/monitoring.js`;
    const API_BASE = new URL('../Web_app/api/', monitoringScriptUrl).toString();

    function apiUrl(file) {
        return API_BASE + file;
    }

    const PROXIED_STREAM_URL = `${API_BASE}video_feed.php?client=web`;
    const isLocalMonitoringHost = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname);
    const isPrivateMonitoringHost = /^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(window.location.hostname);
    const canUseDirectMonitoringStream = window.location.protocol === 'http:'
      && (isLocalMonitoringHost || isPrivateMonitoringHost);
    // On the monitoring PC, connect straight to the Python MJPEG server. This
    // removes an extra Apache/PHP buffering hop. Keep the same-origin proxy as a
    // fallback and for workstations that cannot reach port 5000 directly.
    const DIRECT_STREAM_URL = `http://${window.location.hostname}:5000/video_feed`;
    const DIRECT_SNAPSHOT_URL = `http://${window.location.hostname}:5000/snapshot`;
    const STREAM_URLS = canUseDirectMonitoringStream
      ? [DIRECT_STREAM_URL, PROXIED_STREAM_URL]
      : [PROXIED_STREAM_URL];
    // Snapshot polling is only a compatibility fallback. Requesting JPEG frames
    // every 80ms saturated Apache/PHP and made the displayed footage fall behind.
    const SNAPSHOT_URL = canUseDirectMonitoringStream
      ? DIRECT_SNAPSHOT_URL
      : `${API_BASE}video_snapshot.php?client=web`;
    // Hosted browsers receive the laptop's latest relayed JPEG. Roughly 6-7
    // frames per second is visibly smoother than the old 4 FPS preview while
    // remaining conservative enough for shared-hosting request limits.
    const SNAPSHOT_REFRESH_MS = canUseDirectMonitoringStream ? 100 : 150;
    const configuredWebrtcUrl = String(cameraStage?.dataset.webrtcUrl ?? '').trim();
    const canUseConfiguredWebrtc = configuredWebrtcUrl !== ''
      && (!window.isSecureContext || configuredWebrtcUrl.startsWith('https://'));

    function closeWebrtcStream() {
      if (webrtcStartupTimer) window.clearTimeout(webrtcStartupTimer);
      webrtcStartupTimer = null;
      if (!webrtcLiveStream) return;
      webrtcLiveStream.onload = null;
      webrtcLiveStream.onerror = null;
      webrtcLiveStream.src = 'about:blank';
      webrtcLiveStream.style.display = 'none';
    }

    function getCvProcessModal() {
      if (!cvProcessModalElement || !window.bootstrap?.Modal) return null;
      cvProcessModal = window.bootstrap.Modal.getOrCreateInstance(cvProcessModalElement, {
        backdrop: 'static',
        keyboard: false
      });
      return cvProcessModal;
    }

    function showCvProcess({ mode, title, detail, stage = 'Preparing…', icon = 'bi-cpu', percent = null }) {
      cvProcessMode = mode;
      if (mode === 'start') cvStartRequestedAt = Date.now();
      cvActionBusy = true;
      if (cvProcessTitle) cvProcessTitle.textContent = title;
      if (cvProcessDetail) cvProcessDetail.textContent = detail;
      if (cvProcessIcon) cvProcessIcon.innerHTML = `<i class="bi ${icon}"></i>`;
      if (cvProcessStage) cvProcessStage.textContent = stage;
      if (cvProcessActions) cvProcessActions.classList.add('d-none');
      updateCvProcess(percent);
      getCvProcessModal()?.show();
    }

    function updateCvProcess(percent = null, stage = null, detail = null) {
      const determinate = Number.isFinite(percent);
      if (cvProcessBar) {
        cvProcessBar.classList.toggle('progress-bar-striped', !determinate);
        cvProcessBar.classList.toggle('progress-bar-animated', !determinate);
        cvProcessBar.style.width = determinate
          ? `${Math.max(0, Math.min(100, percent))}%`
          : '35%';
      }
      if (cvProcessPercent) cvProcessPercent.textContent = determinate ? `${Math.round(percent)}%` : 'Please wait';
      if (stage !== null && cvProcessStage) cvProcessStage.textContent = stage;
      if (detail !== null && cvProcessDetail) cvProcessDetail.textContent = detail;
    }

    function completeCvProcess(message, { keepOpen = false } = {}) {
      updateCvProcess(100, 'Completed', message);
      if (cvProcessIcon) cvProcessIcon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
      cvActionBusy = false;
      cvProcessMode = null;
      cvStartRequestedAt = 0;
      if (keepOpen) {
        cvProcessActions?.classList.remove('d-none');
      } else {
        window.setTimeout(() => getCvProcessModal()?.hide(), 700);
      }
    }

    function failCvProcess(message) {
      updateCvProcess(100, 'Unable to complete', message);
      if (cvProcessBar) cvProcessBar.classList.add('bg-danger');
      if (cvProcessIcon) cvProcessIcon.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
      cvProcessActions?.classList.remove('d-none');
      cvActionBusy = false;
      cvProcessMode = null;
      cvStartRequestedAt = 0;
    }

    cvProcessCloseBtn?.addEventListener('click', () => getCvProcessModal()?.hide());
    cvProcessModalElement?.addEventListener('hidden.bs.modal', () => {
      cvProcessBar?.classList.remove('bg-danger');
      cvProcessActions?.classList.add('d-none');
    });

    function updateSourceFields() {
      const isTapo = monitoringSource?.value === 'tapo_camera';
      const isLiveCamera = isTapo;
      tapoCameraFields?.classList.toggle('d-none', !isTapo);
      uploadVideoForm?.classList.toggle('d-none', isLiveCamera);

      if (sourceActionTitle) {
        sourceActionTitle.textContent = isTapo
          ? 'Tapo Camera Controls'
          : 'Upload CCTV Video';
      }

      const currentStartLabel = document.getElementById('startAnalysisLabel');
      if (currentStartLabel) {
        currentStartLabel.textContent = isTapo
          ? 'Start Tapo Camera'
          : 'Start Analysis';
      }
    }

    monitoringSource?.addEventListener('change', () => {
      sourceSelectionTouched = true;
      updateSourceFields();
    });
    updateSourceFields();

    let inboundLinePoints = [];
    let outboundLinePoints = [];
    let officerZonePoints = [];
    let calibrationTool = null;

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

      drawLine(inboundLinePoints, '#22c55e', 'INBOUND');
      drawLine(outboundLinePoints, '#ef4444', 'OUTBOUND');

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
      inboundLinePoints = [];
      outboundLinePoints = [];
      officerZonePoints = [];
      calibrationTool = null;
      editingCalibrationFile = null;
      calibrationCanvas?.classList.remove('active');
      calibrationEditor?.classList.add('d-none');
      if (calibrationProfile) calibrationProfile.disabled = false;
      if (calibrationName) calibrationName.value = '';
      if (saveCalibrationBtn) {
        saveCalibrationBtn.disabled = true;
        saveCalibrationBtn.textContent = 'Save Configuration';
      }
      if (calibrationEditorHeading) calibrationEditorHeading.textContent = 'New Configuration';
      calibrationEditBadge?.classList.add('d-none');
      if (addOfficerZoneBtn) {
        addOfficerZoneBtn.textContent = 'Add Enforcer Zone (Optional)';
      }
      clearOfficerZoneBtn?.classList.add('d-none');
      drawCalibrationLines();
      updateDeleteCalibrationButton();
    }

    function updateDeleteCalibrationButton() {
      const selectedFile = calibrationProfile?.value ?? '';
      const analysisIsActive = ['running', 'starting'].includes(currentAnalysisStatus);
      const isDefault = selectedFile.toLowerCase() === 'example.json';
      const editorOpen = Boolean(calibrationEditor && !calibrationEditor.classList.contains('d-none'));
      const actionsBusy = deletingCalibration || loadingCalibration || savingCalibration || switchingCalibration;
      if (newCalibrationBtn) newCalibrationBtn.disabled = actionsBusy || editorOpen;
      if (deleteCalibrationBtn) {
        deleteCalibrationBtn.disabled = actionsBusy || editorOpen || analysisIsActive || !selectedFile || isDefault;
        deleteCalibrationBtn.title = isDefault
          ? 'The default configuration cannot be deleted'
          : analysisIsActive
            ? 'Stop the active analysis before deleting a configuration'
            : 'Delete selected configuration';
      }
      if (editCalibrationBtn) {
        editCalibrationBtn.disabled = actionsBusy || editorOpen || analysisIsActive || !selectedFile;
        editCalibrationBtn.title = analysisIsActive
          ? 'Stop the active analysis before editing a configuration'
          : 'Edit selected configuration';
      }
    }

    newCalibrationBtn?.addEventListener('click', () => {
      editingCalibrationFile = null;
      inboundLinePoints = [];
      outboundLinePoints = [];
      officerZonePoints = [];
      calibrationTool = null;
      calibrationEditor?.classList.remove('d-none');
      calibrationCanvas?.classList.add('active');
      if (calibrationProfile) calibrationProfile.disabled = true;
      if (calibrationName) calibrationName.value = '';
      if (calibrationEditorHeading) calibrationEditorHeading.textContent = 'New Configuration';
      calibrationEditBadge?.classList.add('d-none');
      if (saveCalibrationBtn) saveCalibrationBtn.textContent = 'Save Configuration';
      clearOfficerZoneBtn?.classList.add('d-none');
      if (calibrationInstruction) calibrationInstruction.textContent = 'Choose Inbound Line, Outbound Line, or Enforcer Zone to begin.';
      if (saveCalibrationBtn) saveCalibrationBtn.disabled = true;
      sizeCalibrationCanvas();
      updateDeleteCalibrationButton();
    });

    editCalibrationBtn?.addEventListener('click', async () => {
      const file = calibrationProfile?.value ?? '';
      if (!file || ['running', 'starting'].includes(currentAnalysisStatus)) return;

      loadingCalibration = true;
      if (calibrationProfile) calibrationProfile.disabled = true;
      updateDeleteCalibrationButton();
      try {
        const response = await fetchJson(`${apiUrl('calibration_profiles.php')}?file=${encodeURIComponent(file)}`);
        const profile = response.profile ?? {};
        const validPointSet = (points, expected) => Array.isArray(points)
          && points.length === expected
          && points.every(point => Array.isArray(point) && point.length === 2 && point.every(value => Number.isFinite(Number(value))));
        if (!validPointSet(profile.inbound_line, 2) || !validPointSet(profile.outbound_line, 2)) {
          throw new Error('This configuration does not contain valid counting lines.');
        }

        editingCalibrationFile = file;
        inboundLinePoints = profile.inbound_line.map(point => point.map(Number));
        outboundLinePoints = profile.outbound_line.map(point => point.map(Number));
        officerZonePoints = validPointSet(profile.officer_zone, 4)
          ? profile.officer_zone.map(point => point.map(Number))
          : [];
        calibrationTool = null;
        calibrationEditor?.classList.remove('d-none');
        calibrationCanvas?.classList.add('active');
        if (calibrationName) calibrationName.value = String(profile.name ?? '');
        if (calibrationEditorHeading) calibrationEditorHeading.textContent = 'Edit Configuration';
        calibrationEditBadge?.classList.remove('d-none');
        if (saveCalibrationBtn) {
          saveCalibrationBtn.textContent = 'Save Changes';
          saveCalibrationBtn.disabled = false;
        }
        if (addOfficerZoneBtn) {
          addOfficerZoneBtn.textContent = officerZonePoints.length === 4
            ? 'Redraw Enforcer Zone'
            : 'Add Enforcer Zone (Optional)';
        }
        clearOfficerZoneBtn?.classList.toggle('d-none', officerZonePoints.length === 0);
        if (calibrationInstruction) {
          calibrationInstruction.textContent = 'Existing lines are loaded. Choose a tool to redraw an item, rename it, or save the current values.';
        }
        sizeCalibrationCanvas();
      } catch (error) {
        if (analysisMessage) {
          analysisMessage.textContent = error.message;
          analysisMessage.className = 'small mt-2 text-danger';
        }
      } finally {
        loadingCalibration = false;
        if (calibrationProfile && calibrationEditor?.classList.contains('d-none')) calibrationProfile.disabled = false;
        updateDeleteCalibrationButton();
      }
    });

    cancelCalibrationBtn?.addEventListener('click', closeCalibrationEditor);
    window.addEventListener('resize', sizeCalibrationCanvas);

    function selectLineTool(tool) {
      calibrationTool = tool;
      if (tool === 'inbound') inboundLinePoints = [];
      if (tool === 'outbound') outboundLinePoints = [];
      const label = tool === 'inbound' ? 'green inbound' : 'red outbound';
      if (calibrationInstruction) calibrationInstruction.textContent = `Click two points for the ${label} line.`;
      drawCalibrationLines();
    }

    drawInboundLineBtn?.addEventListener('click', () => selectLineTool('inbound'));
    drawOutboundLineBtn?.addEventListener('click', () => selectLineTool('outbound'));

    addOfficerZoneBtn?.addEventListener('click', () => {
      officerZonePoints = [];
      calibrationTool = 'officer';
      addOfficerZoneBtn.textContent = 'Drawing Enforcer Zone...';
      if (saveCalibrationBtn) saveCalibrationBtn.disabled = true;
      if (calibrationInstruction) {
        calibrationInstruction.textContent = 'Click four corners around the designated enforcer area, in order.';
      }
      drawCalibrationLines();
    });

    clearOfficerZoneBtn?.addEventListener('click', () => {
      officerZonePoints = [];
      calibrationTool = null;
      clearOfficerZoneBtn.classList.add('d-none');
      if (addOfficerZoneBtn) addOfficerZoneBtn.textContent = 'Add Enforcer Zone (Optional)';
      if (saveCalibrationBtn) saveCalibrationBtn.disabled = inboundLinePoints.length !== 2 || outboundLinePoints.length !== 2;
      if (calibrationInstruction) calibrationInstruction.textContent = 'Enforcer zone removed. Save changes to apply it.';
      drawCalibrationLines();
    });

    calibrationCanvas?.addEventListener('click', event => {
      const rect = calibrationCanvas.getBoundingClientRect();
      const point = [
        Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
        Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height))
      ];

      if (calibrationTool === 'officer') {
        if (officerZonePoints.length >= 4) return;
        officerZonePoints.push(point);
        if (officerZonePoints.length === 4) {
          calibrationTool = null;
          addOfficerZoneBtn.textContent = 'Redraw Enforcer Zone';
          clearOfficerZoneBtn?.classList.remove('d-none');
          if (saveCalibrationBtn) saveCalibrationBtn.disabled = inboundLinePoints.length !== 2 || outboundLinePoints.length !== 2;
          if (calibrationInstruction) calibrationInstruction.textContent = 'Enforcer zone ready. Choose a line tool or save when both lines are complete.';
        } else if (calibrationInstruction) {
          calibrationInstruction.textContent = `Click ${4 - officerZonePoints.length} more enforcer-zone corner${4 - officerZonePoints.length === 1 ? '' : 's'}.`;
        }
        drawCalibrationLines();
        return;
      }

      if (calibrationTool !== 'inbound' && calibrationTool !== 'outbound') {
        if (calibrationInstruction) calibrationInstruction.textContent = 'Choose Inbound Line, Outbound Line, or Enforcer Zone first.';
        return;
      }

      const selectedPoints = calibrationTool === 'inbound' ? inboundLinePoints : outboundLinePoints;
      if (selectedPoints.length >= 2) return;
      selectedPoints.push(point);
      drawCalibrationLines();

      if (calibrationInstruction) {
        if (selectedPoints.length < 2) calibrationInstruction.textContent = `Click the second point for the ${calibrationTool} line.`;
        else if (inboundLinePoints.length === 2 && outboundLinePoints.length === 2) calibrationInstruction.textContent = 'Both lines are ready. Optionally add an enforcer zone, or enter a name and save.';
        else calibrationInstruction.textContent = `${calibrationTool === 'inbound' ? 'Inbound' : 'Outbound'} line ready. Choose the other line whenever you are ready.`;
      }
      if (selectedPoints.length === 2) calibrationTool = null;
      if (saveCalibrationBtn) saveCalibrationBtn.disabled = inboundLinePoints.length !== 2 || outboundLinePoints.length !== 2;
    });

    saveCalibrationBtn?.addEventListener('click', async () => {
      const name = calibrationName?.value.trim() ?? '';
      if (!name || inboundLinePoints.length !== 2 || outboundLinePoints.length !== 2) {
        if (calibrationInstruction) calibrationInstruction.textContent = 'Enter a name and draw both lines first.';
        return;
      }

      const fileBeingEdited = editingCalibrationFile;
      savingCalibration = true;
      saveCalibrationBtn.disabled = true;
      updateDeleteCalibrationButton();
      try {
        const response = await fetchJson(apiUrl('calibration_profiles.php'), {
          method: fileBeingEdited ? 'PUT' : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            csrf_token: calibrationCsrf?.value ?? '',
            ...(fileBeingEdited ? { file: fileBeingEdited } : {}),
            profile_name: name,
            inbound_line: inboundLinePoints,
            outbound_line: outboundLinePoints,
            officer_zone: officerZonePoints
          })
        });
        if (fileBeingEdited) {
          const option = Array.from(calibrationProfile?.options ?? []).find(item => item.value === fileBeingEdited);
          if (option) {
            option.text = response.profile.name;
            option.selected = true;
          }
        } else {
          const option = new Option(response.profile.name, response.profile.file, true, true);
          calibrationProfile?.add(option);
        }
        updateDeleteCalibrationButton();
        closeCalibrationEditor();
        if (analysisMessage) {
          analysisMessage.textContent = response.message;
          analysisMessage.className = 'small mt-2 text-success';
        }
      } catch (error) {
        saveCalibrationBtn.disabled = false;
        if (calibrationInstruction) calibrationInstruction.textContent = error.message;
      } finally {
        savingCalibration = false;
        updateDeleteCalibrationButton();
      }
    });

    uploadVideoForm?.addEventListener('submit', event => {
      if (uploadVideoForm.dataset.submitting === 'true') return;

      const file = cctvVideoInput?.files?.[0];
      if (!file) return;

      event.preventDefault();
      uploadVideoForm.dataset.submitting = 'true';
      uploadInProgress = true;
      const startedAt = performance.now();
      const formData = new FormData(uploadVideoForm);
      const originalButton = uploadVideoBtn?.innerHTML ?? '';
      showCvProcess({
        mode: 'upload',
        title: 'Uploading CCTV Footage',
        detail: `${file.name} · ${(file.size / 1048576).toFixed(1)} MB`,
        stage: 'Preparing upload…',
        icon: 'bi-cloud-arrow-up-fill',
        percent: 0
      });

      if (uploadVideoBtn) {
        uploadVideoBtn.disabled = true;
        uploadVideoBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Uploading…';
      }
      if (cctvVideoInput) cctvVideoInput.disabled = true;

      const request = new XMLHttpRequest();
      request.open('POST', uploadVideoForm.action || window.location.href);
      request.withCredentials = true;

      request.upload.addEventListener('progress', progressEvent => {
        if (!progressEvent.lengthComputable) return;

        const elapsedSeconds = Math.max((performance.now() - startedAt) / 1000, 0.1);
        const uploadedMb = progressEvent.loaded / 1048576;
        const totalMb = progressEvent.total / 1048576;
        const speedMbps = uploadedMb / elapsedSeconds;
        const percent = (progressEvent.loaded / progressEvent.total) * 100;
        const remainingSeconds = speedMbps > 0
          ? Math.max(0, (totalMb - uploadedMb) / speedMbps)
          : 0;
        const eta = remainingSeconds >= 60
          ? `${Math.ceil(remainingSeconds / 60)} min remaining`
          : `${Math.ceil(remainingSeconds)} sec remaining`;

        updateCvProcess(
          percent,
          percent >= 100 ? 'Finalizing upload…' : `${speedMbps.toFixed(1)} MB/s · ${eta}`,
          `${file.name} · ${uploadedMb.toFixed(1)} of ${totalMb.toFixed(1)} MB`
        );
      });

      request.addEventListener('load', () => {
        const responseDocument = new DOMParser().parseFromString(request.responseText || '', 'text/html');
        const resultAlert = responseDocument.getElementById('uploadResultAlert');
        const message = resultAlert?.textContent?.trim() || '';
        const succeeded = request.status >= 200
          && request.status < 300
          && resultAlert?.dataset.uploadSuccess === 'true';

        if (!succeeded) {
          failCvProcess(message || `Upload failed (HTTP ${request.status || 0}).`);
          return;
        }

        uploadedFootageReady = true;
        uploadVideoForm.dataset.footageReady = 'true';
        if (cctvVideoInput) cctvVideoInput.value = '';
        if (analysisMessage) {
          analysisMessage.textContent = message;
          analysisMessage.className = 'small mt-2 text-success';
        }
        completeCvProcess(message || 'CCTV video uploaded successfully.');
      });

      request.addEventListener('error', () => {
        failCvProcess('Network connection was interrupted during upload.');
      });

      request.addEventListener('abort', () => {
        failCvProcess('The video upload was cancelled.');
      });

      request.addEventListener('loadend', () => {
        uploadInProgress = false;
        uploadVideoForm.dataset.submitting = 'false';
        if (uploadVideoBtn) {
          uploadVideoBtn.innerHTML = originalButton;
          uploadVideoBtn.disabled = false;
        }
        if (cctvVideoInput) cctvVideoInput.disabled = false;
        refreshDashboard();
      });

      request.send(formData);
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
      currentAnalysisStatus = normalized;
      const isBusy = normalized === 'starting' || normalized === 'running';
      const canPrepareFootage = !isBusy;
      const isTapo = monitoringSource?.value === 'tapo_camera';
      const startLabel = isTapo
        ? 'Start Tapo Camera'
        : 'Start Analysis';

      if (startAnalysisBtn) {
        startAnalysisBtn.disabled = isBusy && streamConnectionRequested;
        startAnalysisBtn.innerHTML = isBusy
          ? (streamConnectionRequested
              ? '<i class="bi bi-broadcast me-1"></i>Viewing Live Feed'
              : '<i class="bi bi-box-arrow-in-right me-1"></i>Join Live Feed')
          : `<i class="bi bi-play-circle me-1"></i><span id="startAnalysisLabel">${startLabel}</span>`;
      }

      if (stopAnalysisBtn) {
        const sharedSourceIsTapo = ['tapo_camera', 'tapo'].includes(currentSharedSourceType);
        stopAnalysisBtn.disabled = !STOP_ANALYSIS_ENABLED || !isBusy || cvActionBusy;
        stopAnalysisBtn.title = STOP_ANALYSIS_ENABLED
          ? ''
          : 'Temporarily disabled while testing continuous camera operation';
        stopAnalysisBtn.innerHTML = !STOP_ANALYSIS_ENABLED
          ? '<i class="bi bi-lock me-1"></i>Stop Disabled (Testing)'
          : normalized === 'starting'
          ? '<i class="bi bi-x-circle me-1"></i>Cancel Connection'
          : sharedSourceIsTapo
            ? '<i class="bi bi-stop-circle me-1"></i>Disconnect Camera'
            : '<i class="bi bi-stop-circle me-1"></i>Stop Analysis';
      }

      if (uploadVideoBtn) {
        uploadVideoBtn.disabled = !canPrepareFootage || cvActionBusy;
      }

      if (cctvVideoInput) {
        cctvVideoInput.disabled = !canPrepareFootage || cvActionBusy;
      }

      if (monitoringSource) {
        monitoringSource.disabled = isBusy;
      }

      updateDeleteCalibrationButton();

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
        phone_camera: 'Cellphone Camera',
        phone: 'Cellphone Camera',
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

      let data;

      try {
        data = JSON.parse(raw);
      } catch (e) {
        console.error('Invalid JSON response from API:', raw);
        throw new Error('Backend did not return valid JSON.');
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
        const reportedAnalysisStatus = data.analysis_status ?? data.ai_status ?? 'Idle';
        const reportedAnalysisState = String(reportedAnalysisStatus).toLowerCase();
        // During the continuous-run diagnostic, a stale hosted status must not
        // tear down a stream that has already delivered a valid frame. The
        // detector and MJPEG connection are the source of truth for this test.
        const preserveVerifiedTestStream = !STOP_ANALYSIS_ENABLED
          && streamConnectionRequested
          && streamHasFrame
          && ['idle', 'stopped', 'completed', 'offline', 'error'].includes(reportedAnalysisState);
        // A start request and the edge worker run asynchronously. During that
        // handoff, polling can briefly return the previous Stopped/Completed
        // state. Do not let that stale response overwrite the active attempt.
        const awaitingNewStart = cvProcessMode === 'start'
          && cvStartRequestedAt > 0
          && Date.now() - cvStartRequestedAt <= START_ANALYSIS_GRACE_MS
          && ['idle', 'stopped', 'completed', 'offline'].includes(reportedAnalysisState);
        const analysisStatus = preserveVerifiedTestStream
          ? 'Running'
          : (awaitingNewStart ? 'Starting' : reportedAnalysisStatus);
        const analysisStatusMessage = preserveVerifiedTestStream
          ? 'Live camera frame verified. Ignoring stale hosted status during the continuous-run test.'
          : awaitingNewStart
          ? (monitoringSource?.value === 'tapo_camera'
              ? 'Connecting to the Tapo RTSP stream and waiting for the first video frame…'
              : 'Starting Computer Vision and waiting for the first processed frame…')
          : (data.message ?? '');
        currentAnalysisStatus = String(analysisStatus).toLowerCase();
        currentSharedSourceType = String(data.source_type ?? '');
        const liveAnalysis = ['running', 'starting'].includes(currentAnalysisStatus);
        if (!sourceSelectionTouched && monitoringSource) {
          const savedSource = String(data.source_type ?? '');
          if (['uploaded_video', 'tapo_camera'].includes(savedSource)
              && monitoringSource.value !== savedSource) {
            monitoringSource.value = savedSource;
            updateSourceFields();
          }
        }
        if (liveAnalysis && !switchingCalibration && !calibrationSelectionTouched && data.calibration_profile && calibrationProfile) {
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
        // Modal delivery is handled by actionable-alerts.js from persisted
        // monitoring_alerts rows. This keeps the modal and Alerts page in sync
        // and ensures the configured cooldown is honored.
        previousCongestionAlertState = congestionAlertState;

        setBadge('officerPresence', data.officer_presence ?? 'Unknown', 'officer');
        setBadge('potentialCollision', data.potential_collision ?? 'None', 'default');

        const collisionState = String(data.potential_collision ?? 'none').toLowerCase();
        previousCollisionState = collisionState;

        setText('lastUpdated', data.recorded_at ?? 'No data');
        setAnalysisControls(analysisStatus, analysisStatusMessage);
        setAnalysisSource(data);
        setProgress(awaitingNewStart ? { ...data, analysis_status: 'Starting' } : data);

        if (cvProcessMode === 'start') {
          if (currentAnalysisStatus === 'running') {
            completeCvProcess('Computer Vision is running and the live monitoring stream is ready.');
          } else if (currentAnalysisStatus === 'error') {
            failCvProcess(data.message || 'Computer Vision could not start.');
          } else if (
            ['idle', 'stopped', 'completed'].includes(currentAnalysisStatus)
            && cvStartRequestedAt > 0
            && !startAnalysisRequestPending
            && Date.now() - cvStartRequestedAt > START_ANALYSIS_GRACE_MS
          ) {
            failCvProcess(
              data.message || 'Computer Vision did not finish starting within three minutes. Check the detector logs and try again.'
            );
          } else {
            updateCvProcess(null, 'Starting AI models…', analysisStatusMessage || 'Waiting for the first processed video frame.');
          }
        }

        if (!liveAnalysis) {
          snapshotStreamGeneration += 1;
          if (aiLiveStream) {
            aiLiveStream.src = '';
            aiLiveStream.style.display = 'none';
          }
          if (snapshotCanvas) snapshotCanvas.style.display = 'none';
          if (streamFallback) streamFallback.style.display = 'flex';
          streamConnectionRequested = false;
        }

      if (sourceStatus && String(analysisStatus).toLowerCase() === 'running') {
        sourceStatus.textContent = streamConnectionRequested && !streamHasFrame
          ? 'Connecting AI Stream'
          : 'Live Data Active';
        sourceStatus.className = streamConnectionRequested && !streamHasFrame
          ? 'tag tag-info'
          : 'tag tag-success';
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

    function connectStreamWithRetry() {
      if (!snapshotCanvas && !aiLiveStream && !webrtcLiveStream) return;
      streamConnectionRequested = true;
      streamHasFrame = false;
      const generation = ++snapshotStreamGeneration;

      if (streamRetryTimer) {
        window.clearTimeout(streamRetryTimer);
        streamRetryTimer = null;
      }

      if (aiLiveStream) aiLiveStream.src = '';
      closeWebrtcStream();
      if (snapshotCanvas) snapshotCanvas.style.display = 'none';
      if (streamFallback) streamFallback.style.display = 'flex';

      if (sourceStatus) {
        sourceStatus.textContent = 'Connecting AI Stream';
        sourceStatus.className = 'tag tag-info';
      }

      const openMjpegStream = (streamIndex = 0) => {
        if (!aiLiveStream || !streamConnectionRequested || generation !== snapshotStreamGeneration) return;
        if (streamIndex >= STREAM_URLS.length) {
          // MJPEG is preferred for smooth local playback. If every stream URL
          // fails, use snapshot polling as the compatibility fallback.
          startSnapshotFallback();
          return;
        }

        const streamUrl = STREAM_URLS[streamIndex];
        aiLiveStream.onload = () => {
          if (!streamConnectionRequested || generation !== snapshotStreamGeneration) return;
          if (streamRetryTimer) window.clearTimeout(streamRetryTimer);
          streamRetryTimer = null;
          streamHasFrame = true;
          aiLiveStream.style.display = 'block';
          if (snapshotCanvas) snapshotCanvas.style.display = 'none';
          if (streamFallback) streamFallback.style.display = 'none';
          if (sourceStatus) {
            sourceStatus.textContent = 'Live Data Active';
            sourceStatus.className = 'tag tag-success';
          }
          if (cvProcessMode === 'start') {
            completeCvProcess('Computer Vision is running and the live monitoring stream is ready.');
          }
        };
        aiLiveStream.onerror = () => openMjpegStream(streamIndex + 1);
        aiLiveStream.style.display = 'block';
        aiLiveStream.src = `${streamUrl}${streamUrl.includes('?') ? '&' : '?'}t=${Date.now()}`;
        // Some browsers do not fire an error for a stalled MJPEG connection.
        // Fall back only when no first frame arrives within a reasonable window.
        streamRetryTimer = window.setTimeout(() => {
          if (!streamHasFrame && streamConnectionRequested && generation === snapshotStreamGeneration) {
            openMjpegStream(streamIndex + 1);
          }
        }, 8000);
      };

      const openWebrtcStream = () => {
        if (!webrtcLiveStream || !canUseConfiguredWebrtc
            || !streamConnectionRequested || generation !== snapshotStreamGeneration) {
          if (canUseDirectMonitoringStream && aiLiveStream) openMjpegStream(0);
          else startSnapshotFallback();
          return;
        }

        let accepted = false;
        const fallBack = () => {
          if (accepted || !streamConnectionRequested || generation !== snapshotStreamGeneration) return;
          closeWebrtcStream();
          if (canUseDirectMonitoringStream && aiLiveStream) openMjpegStream(0);
          else startSnapshotFallback();
        };

        webrtcLiveStream.onload = () => {
          if (!streamConnectionRequested || generation !== snapshotStreamGeneration) return;
          accepted = true;
          if (webrtcStartupTimer) window.clearTimeout(webrtcStartupTimer);
          webrtcStartupTimer = null;
          streamHasFrame = true;
          webrtcLiveStream.style.display = 'block';
          if (aiLiveStream) aiLiveStream.style.display = 'none';
          if (snapshotCanvas) snapshotCanvas.style.display = 'none';
          if (streamFallback) streamFallback.style.display = 'none';
          if (streamTransportHelp) streamTransportHelp.textContent = 'Low-latency WebRTC live stream is active.';
          if (sourceStatus) {
            sourceStatus.textContent = 'WebRTC Live';
            sourceStatus.className = 'tag tag-success';
          }
          if (cvProcessMode === 'start') {
            completeCvProcess('Computer Vision is running and the WebRTC stream is ready.');
          }
        };
        webrtcLiveStream.onerror = fallBack;
        webrtcLiveStream.style.display = 'block';
        webrtcLiveStream.src = `${configuredWebrtcUrl}${configuredWebrtcUrl.includes('?') ? '&' : '?'}t=${Date.now()}`;
        webrtcStartupTimer = window.setTimeout(fallBack, 12000);
      };

      const startSnapshotFallback = () => {
        if (!snapshotCanvas || !streamConnectionRequested || generation !== snapshotStreamGeneration) return;
        if (aiLiveStream) {
          aiLiveStream.src = '';
          aiLiveStream.style.display = 'none';
        }
        snapshotCanvas.style.display = 'block';
        snapshotCanvas.style.width = '100%';
        snapshotCanvas.style.height = '100%';
        let consecutiveFailures = 0;

        const loadFrame = () => {
          if (!streamConnectionRequested || generation !== snapshotStreamGeneration) return;
          const frame = new Image();
          frame.onload = () => {
            if (!streamConnectionRequested || generation !== snapshotStreamGeneration) return;
            streamHasFrame = true;
            consecutiveFailures = 0;
            snapshotCanvas.width = frame.naturalWidth;
            snapshotCanvas.height = frame.naturalHeight;
            snapshotCanvas.getContext('2d').drawImage(frame, 0, 0);
            if (streamFallback) streamFallback.style.display = 'none';
            if (sourceStatus) {
              sourceStatus.textContent = 'Live Data Active';
              sourceStatus.className = 'tag tag-success';
            }
            if (cvProcessMode === 'start') {
              completeCvProcess('Computer Vision is running and the first processed frame is ready.');
            }
            streamRetryTimer = window.setTimeout(loadFrame, SNAPSHOT_REFRESH_MS);
          };
          frame.onerror = () => {
            if (!streamConnectionRequested || generation !== snapshotStreamGeneration) return;
            consecutiveFailures += 1;
            // Model initialization can take several seconds on CPU. Keep polling
            // for the first processed frame before falling back to MJPEG.
            if (consecutiveFailures >= 60 && canUseDirectMonitoringStream && aiLiveStream) {
              snapshotCanvas.style.display = 'none';
              openMjpegStream(0);
              return;
            }
            if (consecutiveFailures >= 60 && !canUseDirectMonitoringStream) {
              snapshotCanvas.style.display = 'none';
              if (streamFallback) streamFallback.style.display = 'flex';
              if (sourceStatus) {
                sourceStatus.textContent = 'Hosted Frame Relay Unavailable';
                sourceStatus.className = 'tag tag-warning';
              }
              return;
            }
            streamRetryTimer = window.setTimeout(loadFrame, 700);
          };
          frame.src = `${SNAPSHOT_URL}${SNAPSHOT_URL.includes('?') ? '&' : '?'}t=${Date.now()}`;
        };

        loadFrame();
      };

      // A local browser can consume Flask's continuous MJPEG response directly,
      // avoiding the 10 FPS snapshot-polling ceiling. Hosted browsers cannot
      // reach the laptop's port 5000, so they retain the relayed snapshot path.
      if (canUseConfiguredWebrtc && webrtcLiveStream) openWebrtcStream();
      else if (canUseDirectMonitoringStream && aiLiveStream) openMjpegStream(0);
      else startSnapshotFallback();
    }

    function hideStream() {
      streamConnectionRequested = false;
      streamHasFrame = false;
      snapshotStreamGeneration += 1;
      if (streamRetryTimer) window.clearTimeout(streamRetryTimer);
      streamRetryTimer = null;
      closeWebrtcStream();
      if (aiLiveStream) {
        aiLiveStream.onload = null;
        aiLiveStream.onerror = null;
        aiLiveStream.src = '';
        aiLiveStream.style.display = 'none';
      }

      if (snapshotCanvas) {
        snapshotCanvas.style.display = 'none';
        const context = snapshotCanvas.getContext('2d');
        context?.clearRect(0, 0, snapshotCanvas.width, snapshotCanvas.height);
      }

      if (streamFallback) {
        streamFallback.style.display = 'flex';
      }

      if (sourceStatus) {
        sourceStatus.textContent = 'Stream Hidden';
        sourceStatus.className = 'tag tag-warning';
      }
    }

    function showWaitingStreamState() {
      if (streamFallbackTitle) streamFallbackTitle.textContent = 'Waiting for AI live stream';
      if (streamFallbackMessage) {
        streamFallbackMessage.textContent = 'Select a source and start analysis to activate the AI stream.';
      }
    }

    async function startAnalysis() {
      showWaitingStreamState();
      if (['running', 'starting'].includes(currentAnalysisStatus)) {
        connectStreamWithRetry();
        setAnalysisControls(currentAnalysisStatus, 'Joining the active shared monitoring session...');
        return;
      }
      if (snapshotCanvas) snapshotCanvas.style.display = 'none';

      if (monitoringSource?.value === 'uploaded_video' && !uploadedFootageReady) {
        const message = 'Upload CCTV footage first before starting analysis.';
        if (analysisMessage) {
          analysisMessage.textContent = message;
          analysisMessage.className = 'small mt-2 text-danger';
        }
        cctvVideoInput?.focus();
        return;
      }

      showCvProcess({
        mode: 'start',
        title: monitoringSource?.value === 'tapo_camera' ? 'Connecting Tapo Camera' : 'Starting Video Analysis',
        detail: 'Loading the detection model, tracker, calibration, and alert engine.',
        stage: 'Starting Computer Vision…',
        icon: 'bi-cpu'
      });

      if (startAnalysisBtn) {
        startAnalysisBtn.disabled = true;
      }

      setAnalysisControls('Starting', 'Starting AI analysis...');
      startAnalysisRequestPending = true;

      try {
        const sourceType = monitoringSource?.value ?? 'uploaded_video';
        const requestBody = { source_type: sourceType, client: 'web' };
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
        failCvProcess(message);

      } finally {
        startAnalysisRequestPending = false;

        if (startAnalysisBtn) {
          startAnalysisBtn.disabled = false;
        }

      }

    }

  async function stopAndClearSharedAnalysis() {
    if (monitoringSource?.value !== 'uploaded_video') {
      if (currentAnalysisStatus === 'starting') {
        await stopSharedAnalysis();
        hideStream();
        setAnalysisControls('Stopped', 'Tapo camera connection cancelled.');
        getCvProcessModal()?.hide();
        cvActionBusy = false;
        cvProcessMode = null;
        return;
      }
      hideStream();
      setAnalysisControls(
          currentAnalysisStatus,
          'Live viewing stopped on this browser only. AI analysis remains active for other users.'
        );
        return;
      }

      showCvProcess({
        mode: 'stop',
        title: 'Stopping Video Analysis',
        detail: 'Closing Computer Vision and clearing the uploaded footage.',
        stage: 'Stopping the analysis worker…',
        icon: 'bi-stop-circle'
      });

      try {
        if (['running', 'starting'].includes(currentAnalysisStatus)) {
          await stopSharedAnalysis();
        } else {
          hideStream();
        }

        updateCvProcess(null, 'Clearing uploaded footage…');
        const response = await fetchJson(apiUrl('upload_monitoring_video.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'clear', csrf_token: calibrationCsrf?.value ?? '' })
        });
        uploadedFootageReady = false;
        if (uploadVideoForm) uploadVideoForm.dataset.footageReady = 'false';
        if (cctvVideoInput) cctvVideoInput.value = '';
        hideStream();
        setAnalysisControls('Idle', response.message);
        completeCvProcess(response.message);
      } catch (error) {
        failCvProcess(error.message || 'Unable to stop and clear the uploaded footage.');
      }
    }

    async function stopSharedAnalysis() {

      if (!STOP_ANALYSIS_ENABLED) {
        if (analysisMessage) {
          analysisMessage.textContent = 'Stopping is temporarily disabled for the continuous Tapo stream test.';
          analysisMessage.className = 'small mt-2 text-info';
        }
        return false;
      }

      if (stopAnalysisBtn) {
        stopAnalysisBtn.disabled = true;
      }

      try {

        const response = await fetchJson(apiUrl('stop_analysis.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ client: 'web' })
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
          streamConnectionRequested = false;
          snapshotStreamGeneration += 1;
          if (snapshotCanvas) snapshotCanvas.style.display = 'none';

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
        return true;

      } catch (error) {

        const message =
          error.message && error.message !== 'Request failed'
            ? error.message
            : 'Unable to stop analysis.';

        setAnalysisControls('Error', message);
        return false;

      } finally {

        if (stopAnalysisBtn && STOP_ANALYSIS_ENABLED) {
          stopAnalysisBtn.disabled = !['running', 'starting'].includes(currentAnalysisStatus);
        }

      }

    }

    if (stopCameraBtn) stopCameraBtn.addEventListener('click', hideStream);
    if (captureSnapshotBtn) {
      captureSnapshotBtn.addEventListener('click', () => {
        window.open(PROXIED_STREAM_URL, '_blank');
      });
    }
    if (startAnalysisBtn) startAnalysisBtn.addEventListener('click', startAnalysis);
    if (stopAnalysisBtn && STOP_ANALYSIS_ENABLED) {
      stopAnalysisBtn.addEventListener('click', stopSharedAnalysis);
    }

    calibrationProfile?.addEventListener('change', async () => {
      calibrationSelectionTouched = true;
      updateDeleteCalibrationButton();
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
      showCvProcess({
        mode: 'start',
        title: 'Applying Intersection Configuration',
        detail: `${selectedName} will be applied before Computer Vision restarts.`,
        stage: 'Stopping the current worker safely…',
        icon: 'bi-sliders'
      });
      if (analysisMessage) {
        analysisMessage.textContent = `Applying ${selectedName} and restarting analysis...`;
        analysisMessage.className = 'small mt-2 text-info';
      }

      await stopSharedAnalysis();
      await new Promise(resolve => window.setTimeout(resolve, 700));
      await startAnalysis();

      calibrationProfile.disabled = false;
      switchingCalibration = false;
      updateDeleteCalibrationButton();
    });

    deleteCalibrationBtn?.addEventListener('click', async () => {
      const selectedOption = calibrationProfile?.options[calibrationProfile.selectedIndex];
      if (!selectedOption?.value || selectedOption.value.toLowerCase() === 'example.json') return;

      if (['running', 'starting'].includes(currentAnalysisStatus)) {
        if (analysisMessage) {
          analysisMessage.textContent = 'Stop the active analysis before deleting a configuration.';
          analysisMessage.className = 'small mt-2 text-danger';
        }
        return;
      }

      const confirmed = window.confirm(`Delete "${selectedOption.text}"? It will be removed from the list, but a recovery copy will be archived.`);
      if (!confirmed) return;

      deletingCalibration = true;
      updateDeleteCalibrationButton();
      try {
        const response = await fetchJson(apiUrl('calibration_profiles.php'), {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            csrf_token: calibrationCsrf?.value ?? '',
            file: selectedOption.value
          })
        });

        selectedOption.remove();
        calibrationSelectionTouched = true;
        if (analysisMessage) {
          analysisMessage.textContent = response.message;
          analysisMessage.className = 'small mt-2 text-success';
        }
      } catch (error) {
        if (analysisMessage) {
          analysisMessage.textContent = error.message;
          analysisMessage.className = 'small mt-2 text-danger';
        }
      } finally {
        deletingCalibration = false;
        updateDeleteCalibrationButton();
      }
    });

    updateDeleteCalibrationButton();

    function refreshDashboard() {
      if (uploadInProgress) return;
      refreshMonitoringStatus();
      refreshMonitoringLogs();
    }

    refreshDashboard();
    // Status needs to remain responsive, but monitoring-log refreshes do not
    // need to compete with every relayed camera frame. Separate cadences also
    // reduce shared-hosting 429 responses and leave more capacity for video.
    setInterval(() => {
      if (!uploadInProgress) refreshMonitoringStatus();
    }, 3000);
    setInterval(() => {
      if (!uploadInProgress) refreshMonitoringLogs();
    }, 10000);
