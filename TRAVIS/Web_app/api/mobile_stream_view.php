<?php
declare(strict_types=1);

$fit = strtolower((string)($_GET['fit'] ?? 'cover'));
$fit = $fit === 'contain' ? 'contain' : 'cover';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
  <style>
    html,body,canvas{width:100%;height:100%;margin:0;background:#07131f;overflow:hidden}
    canvas{display:block}
    #state{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;color:#91a4b2;font:600 13px system-ui;text-align:center;padding:24px}
  </style>
</head>
<body>
  <canvas id="feed"></canvas>
  <div id="state">Connecting to shared CCTV feed…</div>
  <script>
    (() => {
      const canvas = document.getElementById('feed');
      const state = document.getElementById('state');
      const context = canvas.getContext('2d');
      const fit = <?= json_encode($fit) ?>;
      let busy = false;
      let failures = 0;

      function sizeCanvas() {
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.max(1, Math.round(window.innerWidth * ratio));
        canvas.height = Math.max(1, Math.round(window.innerHeight * ratio));
      }

      function schedule(delay) {
        window.setTimeout(loadFrame, delay);
      }

      function loadFrame() {
        if (busy) return;
        busy = true;
        const image = new Image();
        image.onload = () => {
          const scale = fit === 'cover'
            ? Math.max(canvas.width / image.width, canvas.height / image.height)
            : Math.min(canvas.width / image.width, canvas.height / image.height);
          const width = image.width * scale;
          const height = image.height * scale;
          context.fillStyle = '#07131f';
          context.fillRect(0, 0, canvas.width, canvas.height);
          context.drawImage(image, (canvas.width - width) / 2, (canvas.height - height) / 2, width, height);
          failures = 0;
          busy = false;
          state.style.display = 'none';
          if (window.ReactNativeWebView) window.ReactNativeWebView.postMessage('frame-ready');
          schedule(80);
        };
        image.onerror = () => {
          failures += 1;
          busy = false;
          state.style.display = 'flex';
          state.textContent = failures > 4
            ? 'Waiting for CCTV frames. Confirm that the shared analysis is running.'
            : 'Connecting to shared CCTV feed…';
          schedule(500);
        };
        image.src = 'video_snapshot.php?client=mobile&t=' + Date.now();
      }

      window.addEventListener('resize', sizeCanvas);
      sizeCanvas();
      loadFrame();
    })();
  </script>
</body>
</html>
