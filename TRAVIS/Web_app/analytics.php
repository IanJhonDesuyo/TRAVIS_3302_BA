<?php
require_once __DIR__ . '/layout.php';
$predictions = fetch_all("SELECT * FROM ml_predictions ORDER BY prediction_date DESC LIMIT 10");
$hotspots = fetch_all("SELECT * FROM violation_hotspots ORDER BY FIELD(risk_level,'critical','high','medium','low'), frequency_count DESC LIMIT 10");
$topPrediction = $predictions[0] ?? null;
$criticalHotspots = scalar("SELECT COUNT(*) FROM violation_hotspots WHERE risk_level IN ('critical','high')", 0);
$trendData = monthly_violation_counts();
$hotspotLabels = array_column($hotspots, 'location');
$hotspotData = array_map('intval', array_column($hotspots, 'frequency_count'));
page_start('Analytics', 'analytics', 'Search predictions or locations...');
?>
<div class="d-flex justify-content-between flex-wrap mb-4 gap-2"><div><h3 class="page-title">Season and Location Prediction</h3><p class="page-sub">Random Forest prediction and K-Means hotspot analysis</p></div><div class="d-flex gap-2"><span class="tag tag-info">Random Forest Ready</span><span class="tag tag-info">K-Means Ready</span></div></div>
<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-cpu"></i></div><div class="stat-label">Latest Prediction</div><div class="stat-value"><?= $topPrediction ? esc($topPrediction['risk_level']) : 'None' ?></div><small class="text-muted"><?= $topPrediction ? esc($topPrediction['predicted_result']) : 'Waiting for trained model' ?></small></div></div>
  <div class="col-md-4"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-geo-alt"></i></div><div class="stat-label">High-Risk Hotspots</div><div class="stat-value"><?= num($criticalHotspots) ?></div><small class="text-muted">from violation_hotspots</small></div></div>
  <div class="col-md-4"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-clock-history"></i></div><div class="stat-label">Prediction Records</div><div class="stat-value"><?= num(count($predictions)) ?></div><small class="text-muted">stored ML outputs</small></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="section-card"><div class="section-head"><h6>Season-Based Violation Trend</h6><small class="text-muted">Uses current violation records until ML model is trained</small></div><canvas id="predChart" height="120"></canvas><div id="predEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Location-Based Violation Hotspots</h6></div><?php if (!$hotspots): empty_state('No hotspot analysis available yet. K-Means output will appear here after training.'); else: ?><ol class="list-unstyled mb-0"><?php foreach ($hotspots as $i=>$h): ?><li class="d-flex justify-content-between py-2 border-bottom"><span><strong><?= $i+1 ?>.</strong> <?= esc($h['location']) ?></span><span class="tag <?= tag_class($h['risk_level']) ?>"><?= esc($h['risk_level']) ?></span></li><?php endforeach; ?></ol><?php endif; ?></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="section-card"><div class="section-head"><h6>K-Means Hotspot Distribution</h6><small class="text-muted">Frequency count per location</small></div><canvas id="clusterChart" height="160"></canvas><div id="clusterEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-5"><div class="section-card"><div class="section-head"><h6>Random Forest Prediction Outputs</h6></div><?php if (!$predictions): empty_state('Prediction not generated yet. After training, insert results into ml_predictions.'); else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Result</th><th>Risk</th><th>Confidence</th></tr></thead><tbody><?php foreach($predictions as $p): ?><tr><td><?= esc($p['predicted_result']) ?><br><small class="text-muted"><?= esc($p['prediction_type']) ?></small></td><td><span class="tag <?= tag_class($p['risk_level']) ?>"><?= esc($p['risk_level']) ?></span></td><td><?= number_format((float)$p['confidence_score'] * 100, 2) ?>%</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(month_labels()) ?>, trend = <?= json_encode($trendData) ?>;
const hotLabels = <?= json_encode($hotspotLabels) ?>, hotData = <?= json_encode($hotspotData) ?>;
function empty(id,msg){document.getElementById(id).innerHTML='<div class="empty-state">'+msg+'</div>';}
if(trend.reduce((a,b)=>a+b,0)>0)new Chart(document.getElementById('predChart'),{type:'line',data:{labels:months,datasets:[{label:'Violations',data:trend,borderColor:'#1e3a8a',backgroundColor:'rgba(30,58,138,.15)',fill:true,tension:.4}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});else empty('predEmpty','No violation data yet.');
if(hotData.length>0)new Chart(document.getElementById('clusterChart'),{type:'bar',data:{labels:hotLabels,datasets:[{label:'Frequency',data:hotData,backgroundColor:'#16a34a',borderRadius:6}]},options:{indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true}}}});else empty('clusterEmpty','No K-Means hotspot data yet.');
</script>
<?php page_end(false); ?>
