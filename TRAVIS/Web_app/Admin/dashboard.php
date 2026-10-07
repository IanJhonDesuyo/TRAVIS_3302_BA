<?php
require_once __DIR__ . '/layout.php';
?>
<style id="travis-ai-v6-overrides">
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root{
    --navy-950:#060f1e;
    --navy-900:#0a1a30;
    --navy-800:#0f2544;
    --navy-700:#1a2a5a;
    --border-glass:rgba(255,255,255,.10);
    --blue-accent:#38bdf8;
    --blue-accent-2:#2563eb;
    --cyan-glow:#4fc3f7;
    --text-soft:#c9d8ea;
}

* {
    font-family: 'Poppins', sans-serif;
}

/* ==== Page Background with Image ==== */
body {
    background: url('../../assets/images/nasugbu-municipal-hall.jpg') center 30% / cover fixed no-repeat !important;
    min-height: 100vh;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 0;
}

/* ==== Transparent Header with Background Image ==== */
.topbar,
.app-topbar,
.top-header,
.dashboard-topbar,
header.topbar,
.navbar-top {
    background: rgba(10, 26, 48, 0.85) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.10) !important;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08) !important;
    position: relative;
    z-index: 10;
}

.topbar input,
.app-topbar input,
.top-header input,
.dashboard-topbar input,
.navbar-top input {
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #fff !important;
    box-shadow: none !important;
}

.topbar input::placeholder,
.app-topbar input::placeholder,
.top-header input::placeholder,
.dashboard-topbar input::placeholder,
.navbar-top input::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.topbar .bi-search,
.app-topbar .bi-search,
.top-header .bi-search,
.dashboard-topbar .bi-search,
.navbar-top .bi-search {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .bi-bell,
.app-topbar .bi-bell,
.top-header .bi-bell,
.dashboard-topbar .bi-bell,
.navbar-top .bi-bell,
.topbar .notif-icon,
.app-topbar .notif-icon {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .btn-icon,
.app-topbar .btn-icon,
.top-header .btn-icon,
.dashboard-topbar .btn-icon {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.10) !important;
}

.topbar .datetime,
.app-topbar .datetime,
.top-header .datetime,
.dashboard-topbar .datetime {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .user-avatar,
.app-topbar .user-avatar,
.top-header .user-avatar,
.dashboard-topbar .user-avatar {
    background: var(--blue-accent-2) !important;
    color: #fff !important;
}

.topbar .user-name,
.app-topbar .user-name,
.top-header .user-name,
.dashboard-topbar .user-name {
    color: #fff !important;
}

/* ==== Content Wrapper - White Background ==== */
#page-wrapper,
.main-content,
.container-fluid,
.dashboard-content {
    position: relative;
    z-index: 1;
    background: transparent !important;
}

/* ==== Dashboard Title Row ==== */
.dashboard-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 4px;
}

.dashboard-eyebrow {
    display: inline-block;
    color: #1a2350 !important;
    font-weight: 700;
    letter-spacing: 0.06em;
    font-size: 0.72rem;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.page-title {
    color: #0a1a30 !important;
    font-weight: 800 !important;
    margin-bottom: 6px;
}

.page-sub {
    color: #5a6a8a !important;
    margin-bottom: 0;
}

.system-online-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(52, 211, 153, 0.10) !important;
    border: 1px solid rgba(52, 211, 153, 0.25);
    color: #0a7a6a !important;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.system-online-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.20);
}

/* ==== Buttons ==== */
.btn-light {
    background: #f0f4f8 !important;
    border: 1px solid #d0d8e0 !important;
    color: #1a2a3a !important;
    font-weight: 600;
}

.btn-light:hover {
    background: #e4eaf0 !important;
    color: #0a1a30 !important;
}

.btn-primary {
    background: linear-gradient(135deg, #1a2350, #2a3a7a) !important;
    border: none !important;
    color: #fff !important;
    box-shadow: 0 4px 15px rgba(26, 35, 80, 0.25) !important;
    font-weight: 600;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(26, 35, 80, 0.35) !important;
    filter: brightness(1.05);
}

.btn-light,
.btn-primary {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px;
    width: auto !important;
    height: 36px !important;
    min-width: 0 !important;
    padding: 0 16px !important;
    font-size: 0.78rem !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    white-space: nowrap !important;
    border-radius: 8px !important;
}

.btn-light i,
.btn-primary i {
    font-size: 0.85rem;
    margin: 0 !important;
    line-height: 1;
    display: inline-flex;
    align-items: center;
}

/* ==== Cards - White with Navy Border ==== */
.stat-card,
.dashboard-stat-card,
.section-card,
.ai-decision-card,
.ai-kpi-card,
.hotspot-risk-card,
.ai-confidence-card,
.deployment-kpi,
.compact-metric,
#currentMonitoringCard .mini-metric,
.card,
[class*="card"] {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 20px !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
    color: #0a1a30 !important;
}

/* Card hover effect */
.stat-card:hover,
.dashboard-stat-card:hover,
.section-card:hover,
.ai-decision-card:hover {
    box-shadow: 0 4px 20px rgba(26, 35, 80, 0.08) !important;
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Card text colors */
.stat-card .stat-label,
.dashboard-stat-card .stat-label,
.section-card small,
.section-card .text-muted,
.ai-decision-card small,
.ai-decision-card .text-muted,
.card small,
.card .text-muted,
[class*="card"] small,
[class*="card"] .text-muted {
    color: #5a6a8a !important;
}

.stat-card .stat-value,
.dashboard-stat-card .stat-value,
.section-card h6,
.ai-decision-card h5,
.ai-decision-card h6,
.card h6,
.compact-metric h4 {
    color: #0a1a30 !important;
}

/* Stat Icons */
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    font-size: 18px;
}

.stat-icon.tone-primary {
    background: rgba(26, 35, 80, 0.08) !important;
    color: #1a2350 !important;
}

.stat-icon.tone-warning {
    background: rgba(251, 191, 36, 0.10) !important;
    color: #b8860b !important;
}

.stat-icon.tone-success {
    background: rgba(52, 211, 153, 0.10) !important;
    color: #0a7a6a !important;
}

.stat-icon.tone-danger {
    background: rgba(248, 113, 113, 0.10) !important;
    color: #b33c44 !important;
}

/* ==== AI Decision Card Specific ==== */
.ai-decision-card {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 24px !important;
}

.ai-decision-card .ai-kicker {
    color: #1a2350 !important;
    font-weight: 700;
    letter-spacing: 0.04em;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.ai-decision-card .ai-card-icon {
    background: rgba(26, 35, 80, 0.06) !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
    color: #1a2350 !important;
}

.ai-decision-card .ai-risk-badge {
    background: rgba(26, 35, 80, 0.04) !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
    color: #1a2350 !important;
}

.ai-decision-card .ai-risk-high {
    color: #b33c44 !important;
    border-color: rgba(220, 53, 69, 0.3) !important;
    background: rgba(220, 53, 69, 0.06) !important;
}

.ai-decision-card .ai-risk-medium {
    color: #b8860b !important;
    border-color: rgba(251, 191, 36, 0.3) !important;
    background: rgba(251, 191, 36, 0.06) !important;
}

.ai-decision-card .ai-risk-low {
    color: #0a7a6a !important;
    border-color: rgba(52, 211, 153, 0.3) !important;
    background: rgba(52, 211, 153, 0.06) !important;
}

.ai-decision-card .ai-confidence-card {
    background: rgba(26, 35, 80, 0.03) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px;
    padding: 14px;
    color: #0a1a30 !important;
}

.ai-decision-card .ai-confidence-progress {
    background: rgba(26, 35, 80, 0.06) !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-high {
    background: #b33c44 !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-medium {
    background: #b8860b !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-low {
    background: #0a7a6a !important;
}

.ai-decision-card .deployment-kpi {
    background: rgba(26, 35, 80, 0.03) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px;
    padding: 12px 15px;
    margin-bottom: 10px;
    color: #0a1a30 !important;
}

.ai-decision-card .deployment-kpi small {
    color: #5a6a8a !important;
}

.ai-decision-card .deployment-kpi strong {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-subsection-heading {
    border-top: 1px solid rgba(26, 35, 80, 0.08) !important;
}

.ai-decision-card .ai-subsection-heading h6 {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-subsection-heading small {
    color: #5a6a8a !important;
}

.ai-decision-card .ai-highlight-note {
    background: rgba(26, 35, 80, 0.04) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #5a6a8a !important;
}

.ai-decision-card .ai-highlight-note strong {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-action-grid li {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #0a1a30 !important;
}

.ai-decision-card .ai-action-grid li i {
    color: #1a2350 !important;
}

.ai-decision-card .hotspot-risk-card {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 16px !important;
}

.ai-decision-card .hotspot-card-head {
    border-bottom: 1px solid rgba(26, 35, 80, 0.08) !important;
}

.ai-decision-card .hotspot-card-head strong {
    color: #0a1a30 !important;
}

.ai-decision-card .hotspot-card-head .hotspot-card-label {
    color: #5a6a8a !important;
}

.ai-decision-card .hotspot-card-list li {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
}

.ai-decision-card .hotspot-list-copy strong {
    color: #0a1a30 !important;
}

.ai-decision-card .hotspot-list-copy small {
    color: #5a6a8a !important;
}

.ai-decision-card .hotspot-list-rank {
    background: linear-gradient(135deg, #1a2350, #2a3a7a) !important;
    color: #fff !important;
}

.ai-decision-card .hotspot-card-high {
    border-top: 6px solid #b33c44 !important;
}

.ai-decision-card .hotspot-card-medium {
    border-top: 6px solid #b8860b !important;
}

.ai-decision-card .hotspot-card-low {
    border-top: 6px solid #0a7a6a !important;
}

.ai-decision-card .ai-label,
.ai-decision-card .ai-period,
.ai-decision-card .ai-model-note {
    color: #5a6a8a !important;
}

.ai-decision-card .ai-loading-state,
.ai-decision-card .hotspot-loading-card {
    color: #5a6a8a !important;
}

/* ==== Hotspot Cards ==== */
.hotspot-risk-card {
    height: 290px !important;
    display: flex !important;
    flex-direction: column !important;
}

.hotspot-card-head {
    flex: 0 0 auto;
    margin-bottom: 10px !important;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(26, 35, 80, 0.08);
}

.hotspot-card-list {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding-right: 6px;
    margin-top: 4px;
}

.hotspot-card-list li {
    padding: 8px 10px !important;
    margin-bottom: 0;
}

.hotspot-card-list::-webkit-scrollbar {
    width: 7px;
}

.hotspot-card-list::-webkit-scrollbar-track {
    background: rgba(26, 35, 80, 0.04);
    border-radius: 20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb {
    background: rgba(26, 35, 80, 0.20);
    border-radius: 20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb:hover {
    background: rgba(26, 35, 80, 0.35);
}

.hotspot-card-highlighted {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12), 0 8px 25px rgba(37, 99, 235, 0.08) !important;
}

/* ==== Current Monitoring Mini Metrics ==== */
#currentMonitoringCard .mini-metric {
    min-width: 0;
    min-height: 86px;
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px !important;
    padding: 13px 14px !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

#currentMonitoringCard .mini-metric small {
    color: #5a6a8a !important;
    display: block;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}

#currentMonitoringCard .mini-metric strong {
    color: #0a1a30 !important;
    font-size: 1rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

#currentMonitoringCard .monitoring-state-notice {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid rgba(184, 134, 11, .24);
    border-radius: 10px;
    background: rgba(251, 191, 36, .08);
    color: #795f16;
    font-size: .76rem;
    line-height: 1.5;
}

#currentMonitoringCard .monitoring-state-notice i {
    flex: 0 0 auto;
    margin-top: 2px;
    color: #b8860b;
}

/* ==== Tags ==== */
.tag {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: capitalize;
    background: rgba(26, 35, 80, 0.04);
    color: #5a6a8a;
    border: 1px solid rgba(26, 35, 80, 0.06);
}

.tag-success,
.tag-online,
.tag-paid,
.tag-completed,
.tag-active,
.tag-low {
    background: rgba(52, 211, 153, 0.08) !important;
    color: #0a7a6a !important;
    border-color: rgba(52, 211, 153, 0.15) !important;
}

.tag-danger,
.tag-offline,
.tag-overdue,
.tag-high,
.tag-critical {
    background: rgba(220, 53, 69, 0.08) !important;
    color: #b33c44 !important;
    border-color: rgba(220, 53, 69, 0.15) !important;
}

.tag-warning,
.tag-pending,
.tag-unpaid,
.tag-medium {
    background: rgba(251, 191, 36, 0.08) !important;
    color: #b8860b !important;
    border-color: rgba(251, 191, 36, 0.15) !important;
}

/* ==== Empty State ==== */
.empty-state {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 14px;
    color: #5a6a8a !important;
    text-align: center;
    padding: 26px 10px;
    font-size: 0.9rem;
}

.empty-state i,
.empty-state svg {
    color: #5a6a8a !important;
    fill: #5a6a8a !important;
    opacity: 0.7;
}

/* ==== Dividers ==== */
.border-bottom {
    border-color: rgba(26, 35, 80, 0.06) !important;
}

/* ==== Links ==== */
a {
    color: #1a2350 !important;
}

a:hover {
    color: #2a3a7a !important;
}

.section-head a {
    color: #1a2350 !important;
}

/* ==== Alerts ==== */
.alert-light {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #5a6a8a !important;
}

/* ==== Modal ==== */
.modal-content {
    background: #ffffff !important;
    color: #0a1a30 !important;
    border: 2px solid #1a2350 !important;
}

/* ==== Dropdown ==== */
.dropdown-menu {
    background: #ffffff !important;
    color: #0a1a30 !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
}

.dropdown-item {
    color: #0a1a30 !important;
}

.dropdown-item:hover,
.dropdown-item:focus {
    background: rgba(26, 35, 80, 0.04) !important;
    color: #0a1a30 !important;
}

/* ==== Progress ==== */
.progress {
    background: rgba(26, 35, 80, 0.06) !important;
}

/* ==== Responsive ==== */
@media (max-width: 991.98px) {
    .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .hotspot-risk-card {
        height: 260px !important;
    }
}

@media (max-width: 575.98px) {
    .metric-grid {
        grid-template-columns: 1fr;
    }
}

/* ==== Historical Hotspots: simplified visual hierarchy ==== */
/* AI forecast cards: one container boundary, not a card inside a card. */
body.municipal-portal #aiDecisionCard .ai-top-row .ai-kpi-card {
    min-height: 300px;
    padding: 0 !important;
    overflow: hidden;
    background: rgba(255, 255, 255, .82) !important;
    border: 1px solid rgba(16, 47, 73, .16) !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 26px rgba(16, 47, 73, .08) !important;
}

body.municipal-portal #aiDecisionCard .ai-top-row .ai-card-header {
    min-height: 84px;
    margin: 0 !important;
    padding: 16px 18px !important;
    background: rgba(247, 250, 249, .84) !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(16, 47, 73, .10) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

body.municipal-portal #aiDecisionCard .ai-top-row .ai-card-icon {
    flex: 0 0 42px;
    width: 42px;
    height: 42px;
    color: #075e59 !important;
    background: rgba(8, 125, 120, .09) !important;
    border: 1px solid rgba(8, 125, 120, .14) !important;
    border-radius: 12px !important;
    box-shadow: none !important;
}

body.municipal-portal #aiDecisionCard .ai-top-row .ai-monthly-body,
body.municipal-portal #aiDecisionCard .ai-top-row .deployment-kpi-grid {
    padding: 20px !important;
}

body.municipal-portal #aiDecisionCard .ai-top-row .ai-model-note {
    margin: 0 20px 20px !important;
    padding-top: 14px;
    border-top: 1px solid rgba(16, 47, 73, .09);
}

body.municipal-portal #aiDecisionCard .ai-actions-card {
    padding: 0 !important;
    overflow: hidden;
    background: rgba(255, 255, 255, .82) !important;
    border: 1px solid rgba(16, 47, 73, .16) !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 26px rgba(16, 47, 73, .08) !important;
}

body.municipal-portal #aiDecisionCard .ai-actions-card .ai-card-header {
    min-height: 78px;
    margin: 0 !important;
    padding: 15px 18px !important;
    background: rgba(247, 250, 249, .84) !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(16, 47, 73, .10) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

body.municipal-portal #aiDecisionCard .ai-actions-card .ai-action-grid {
    gap: 10px !important;
    margin: 0 !important;
    padding: 16px 18px 18px !important;
}

body.municipal-portal #aiDecisionCard .ai-actions-card .ai-action-grid li {
    min-height: 48px;
    padding: 11px 13px !important;
    background: rgba(255, 255, 255, .88) !important;
    border: 1px solid rgba(16, 47, 73, .11) !important;
    border-radius: 11px !important;
    box-shadow: none !important;
}

#hotspotContent .hotspot-kpi-grid {
    --bs-gutter-x: 1rem;
    --bs-gutter-y: 1rem;
}

#hotspotContent .hotspot-risk-card {
    height: 300px !important;
    padding: 0 !important;
    overflow: hidden;
    background: rgba(255, 255, 255, .86) !important;
    border: 1px solid rgba(16, 47, 73, .16) !important;
    border-radius: 16px !important;
    box-shadow: 0 10px 26px rgba(16, 47, 73, .08) !important;
}

#hotspotContent .hotspot-card-high { border-top: 4px solid #dc3545 !important; }
#hotspotContent .hotspot-card-medium { border-top: 4px solid #e5a100 !important; }
#hotspotContent .hotspot-card-low { border-top: 4px solid #1fad72 !important; }

#hotspotContent .hotspot-card-head {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    min-height: 92px;
    margin: 0 !important;
    padding: 16px 18px !important;
    background: rgba(247, 250, 249, .88) !important;
    border: 0 !important;
    border-bottom: 1px solid rgba(16, 47, 73, .10) !important;
}

#hotspotContent .hotspot-card-icon {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    flex: 0 0 42px;
    width: 42px !important;
    height: 42px !important;
    color: #405a53 !important;
    background: #fff !important;
    border: 1px solid rgba(16, 47, 73, .14) !important;
    border-radius: 12px !important;
    box-shadow: none !important;
}

#hotspotContent .hotspot-card-head > div {
    display: grid !important;
    grid-template-columns: auto 1fr;
    align-items: baseline;
    column-gap: 9px;
    min-width: 0;
}

#hotspotContent .hotspot-card-label {
    grid-column: 1 / -1;
    padding: 0 !important;
    color: #405a53 !important;
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    font-size: .72rem !important;
    font-weight: 800 !important;
    letter-spacing: .06em;
}

#hotspotContent .hotspot-card-head strong {
    font-size: 1.75rem !important;
    line-height: 1 !important;
}

#hotspotContent .hotspot-card-head small {
    font-size: .76rem !important;
}

#hotspotContent .hotspot-card-list {
    flex: 1 1 auto !important;
    min-height: 0;
    margin: 0 !important;
    padding: 12px !important;
    background: rgba(247, 250, 249, .46);
    border: 0 !important;
    border-radius: 0 !important;
    scrollbar-gutter: stable;
}

#hotspotContent .hotspot-card-list li {
    min-height: 54px;
    margin: 0 0 8px !important;
    padding: 10px 12px !important;
    background: rgba(255, 255, 255, .90) !important;
    border: 1px solid rgba(16, 47, 73, .11) !important;
    border-radius: 11px !important;
    box-shadow: 0 3px 9px rgba(16, 47, 73, .04) !important;
}

#hotspotContent .hotspot-card-list li:last-child { margin-bottom: 0 !important; }

body.municipal-portal #aiDecisionCard #hotspotContent .hotspot-risk-card {
    background: rgba(255, 255, 255, .86) !important;
    border-right: 1px solid rgba(16, 47, 73, .16) !important;
    border-bottom: 1px solid rgba(16, 47, 73, .16) !important;
    border-left: 1px solid rgba(16, 47, 73, .16) !important;
    box-shadow: 0 10px 26px rgba(16, 47, 73, .08) !important;
}

body.municipal-portal #aiDecisionCard #hotspotContent .hotspot-card-list li {
    background: rgba(255, 255, 255, .90) !important;
    border: 1px solid rgba(16, 47, 73, .11) !important;
    box-shadow: 0 3px 9px rgba(16, 47, 73, .04) !important;
}

#hotspotContent .hotspot-list-rank {
    background: rgba(8, 125, 120, .10) !important;
    color: #075e59 !important;
    box-shadow: none !important;
}

@media (max-width: 991.98px) {
    #hotspotContent .hotspot-risk-card { height: 280px !important; }
}

/* ==== Grid Layout ==== */
.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

body.municipal-portal #currentMonitoringCard {
    padding: 20px !important;
    border: 1px solid rgba(16, 47, 73, .16) !important;
    box-shadow: 0 10px 26px rgba(16, 47, 73, .07) !important;
}

body.municipal-portal #currentMonitoringCard .metric-grid {
    display: grid !important;
    grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    gap: 12px !important;
}

body.municipal-portal #currentMonitoringCard .monitoring-offline-state {
    display: flex;
    min-height: 150px;
    padding: 24px;
    align-items: center;
    justify-content: center;
    gap: 16px;
    text-align: left;
    border: 1px dashed rgba(16, 47, 73, .22);
    border-radius: 14px;
    background: rgba(247, 250, 249, .68);
}

body.municipal-portal #currentMonitoringCard .monitoring-offline-icon {
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    flex: 0 0 48px;
    color: #8a6818;
    background: rgba(235, 148, 31, .12);
    border-radius: 14px;
    font-size: 1.25rem;
}

body.municipal-portal #currentMonitoringCard .monitoring-offline-copy strong,
body.municipal-portal #currentMonitoringCard .monitoring-offline-copy small {
    display: block;
}

body.municipal-portal #currentMonitoringCard .monitoring-offline-copy small {
    margin: 4px 0 12px;
}

body.municipal-portal #currentMonitoringCard .mini-metric {
    min-height: 88px;
    padding: 14px 15px !important;
    background: rgba(247, 250, 249, .72) !important;
    border: 1px solid rgba(16, 47, 73, .12) !important;
    border-radius: 12px !important;
    box-shadow: none !important;
}

body.municipal-portal #currentMonitoringCard .mini-metric small {
    color: #58706a !important;
    font-size: .67rem !important;
    font-weight: 800;
}

body.municipal-portal #currentMonitoringCard .mini-metric strong {
    color: #142a35 !important;
    font-size: 1rem !important;
}

@media (max-width: 1199.98px) {
    body.municipal-portal #currentMonitoringCard .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 575.98px) {
    body.municipal-portal #currentMonitoringCard .metric-grid {
        grid-template-columns: 1fr !important;
    }
}

/* ==== Compact Metrics ==== */
.compact-metric h4 {
    color: #0a1a30 !important;
    font-weight: 800;
}

.compact-metric small {
    color: #5a6a8a !important;
}
</style>

<?php
$overviewPeriod = strtolower(trim((string)($_GET['overview_period'] ?? 'today')));
$allowedOverviewPeriods = ['all', 'today', 'week', 'month', 'custom'];
if (!in_array($overviewPeriod, $allowedOverviewPeriods, true)) {
    $overviewPeriod = 'all';
}

$overviewFrom = trim((string)($_GET['overview_from'] ?? ''));
$overviewTo = trim((string)($_GET['overview_to'] ?? ''));
$isValidOverviewDate = static function (string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};

$overviewDateCondition = '1=1';
$overviewLabel = 'All Records';
if ($overviewPeriod === 'today') {
    $overviewDateCondition = '%s = CURDATE()';
    $overviewLabel = 'Today';
} elseif ($overviewPeriod === 'week') {
    $overviewDateCondition = 'YEARWEEK(%s, 1) = YEARWEEK(CURDATE(), 1)';
    $overviewLabel = 'This Week';
} elseif ($overviewPeriod === 'month') {
    $overviewDateCondition = 'YEAR(%s) = YEAR(CURDATE()) AND MONTH(%s) = MONTH(CURDATE())';
    $overviewLabel = 'This Month';
} elseif ($overviewPeriod === 'custom') {
    if ($isValidOverviewDate($overviewFrom) && $isValidOverviewDate($overviewTo) && $overviewFrom <= $overviewTo) {
        $overviewDateCondition = "%s BETWEEN '{$overviewFrom}' AND '{$overviewTo}'";
        $overviewLabel = date('M j, Y', strtotime($overviewFrom)) . ' – ' . date('M j, Y', strtotime($overviewTo));
    } else {
        $overviewPeriod = 'all';
        $overviewLabel = 'All Records';
        $overviewFrom = '';
        $overviewTo = '';
    }
}

$overviewConditionFor = static function (string $column) use ($overviewDateCondition): string {
    $placeholderCount = substr_count($overviewDateCondition, '%s');
    return $placeholderCount > 0
        ? vsprintf($overviewDateCondition, array_fill(0, $placeholderCount, $column))
        : $overviewDateCondition;
};

$overviewMonitoringCondition = $overviewConditionFor('DATE(recorded_at)');
$todaySummary = fetch_one("
    SELECT
        COALESCE(SUM(daily_camera.inbound_total), 0) AS inbound_total,
        COALESCE(SUM(daily_camera.outbound_total), 0) AS outbound_total
    FROM (
        SELECT DATE(recorded_at) AS recorded_date, camera_id,
               MAX(inbound_count) AS inbound_total,
               MAX(outbound_count) AS outbound_total
        FROM camera_monitoring_logs
        WHERE {$overviewMonitoringCondition}
        GROUP BY DATE(recorded_at), camera_id
    ) daily_camera
") ?: [];

// Monitoring logs are periodic AI samples, not unique incidents. Alerts are
// already deduplicated by camera, incident state, and cooldown, so they are
// the authoritative event-level records for these dashboard counters.
$monitoringIncidentSummary = fetch_one("
    SELECT
        COALESCE(SUM(LOWER(alert_type) = 'congestion'), 0) AS congestion_events,
        COALESCE(SUM(LOWER(alert_type) = 'collision'), 0) AS collision_events
    FROM monitoring_alerts
    WHERE " . $overviewConditionFor('DATE(generated_at)') . "
") ?: [];

$violationSummary = fetch_one("
    SELECT
        COUNT(*) AS total_today,
        SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END) AS paid_today,
        SUM(CASE WHEN LOWER(status) IN ('pending', 'unpaid', 'overdue') THEN 1 ELSE 0 END) AS unpaid_today
    FROM violations
    WHERE " . $overviewConditionFor('violation_date') . "
") ?: [];

$allPendingViolations = scalar("
    SELECT COUNT(*)
    FROM violations
    WHERE LOWER(status) IN ('pending', 'unpaid', 'overdue')
", 0);

$paymentSummary = fetch_one("
    SELECT
        COALESCE(SUM(amount_paid), 0) AS collection_today,
        COUNT(*) AS completed_payments_today
    FROM payments
    WHERE " . $overviewConditionFor('DATE(payment_date)') . "
      AND LOWER(payment_status) = 'completed'
") ?: [];

$alertSummary = fetch_one("
    SELECT
        COUNT(*) AS alerts_today,
        SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) AS active_alerts
    FROM monitoring_alerts
    WHERE " . $overviewConditionFor('DATE(generated_at)') . "
") ?: [];

$monitoringStaleAfterSeconds = 120;
$latestMonitoring = fetch_one("
    SELECT
        l.vehicle_count,
        l.inbound_count,
        l.outbound_count,
        l.congestion_level,
        l.officer_presence,
        l.potential_collision,
        l.recorded_at,
        GREATEST(0, TIMESTAMPDIFF(SECOND, l.recorded_at, NOW())) AS record_age_seconds,
        c.camera_name,
        c.location,
        c.status AS camera_status
    FROM camera_monitoring_logs l
    LEFT JOIN cameras c ON c.camera_id = l.camera_id
    ORDER BY l.recorded_at DESC
    LIMIT 1
");

$onlineCameras = scalar("
    SELECT COUNT(*)
    FROM cameras c
    WHERE LOWER(c.status) = 'online'
      AND EXISTS (
          SELECT 1
          FROM camera_monitoring_logs l
          WHERE l.camera_id = c.camera_id
            AND l.recorded_at >= DATE_SUB(NOW(), INTERVAL {$monitoringStaleAfterSeconds} SECOND)
      )
", 0);

$totalCameras = scalar("SELECT COUNT(*) FROM cameras", 0);

$trendData = monthly_violation_counts();
$monthlyCollectionData = monthly_collection_totals();

$recentAlerts = fetch_all("
    SELECT alert_type, severity, message, status, generated_at
    FROM monitoring_alerts
    ORDER BY generated_at DESC
    LIMIT 5
");

$recentViolations = fetch_all("
    SELECT
        ticket_number,
        plate_number,
        violation_type,
        violation_location,
        penalty_amount,
        status,
        created_at
    FROM violations
    ORDER BY created_at DESC
    LIMIT 5
");

$inboundToday = (int) ($todaySummary['inbound_total'] ?? 0);
$outboundToday = (int) ($todaySummary['outbound_total'] ?? 0);
$vehiclesToday = $inboundToday + $outboundToday;
$congestionEvents = (int) ($monitoringIncidentSummary['congestion_events'] ?? 0);
$collisionEvents = (int) ($monitoringIncidentSummary['collision_events'] ?? 0);

$violationsToday = (int) ($violationSummary['total_today'] ?? 0);
$paidViolationsToday = (int) ($violationSummary['paid_today'] ?? 0);
$unpaidViolationsToday = (int) ($violationSummary['unpaid_today'] ?? 0);

$paymentsToday = (float) ($paymentSummary['collection_today'] ?? 0);
$completedPaymentsToday = (int) ($paymentSummary['completed_payments_today'] ?? 0);

$alertsToday = (int) ($alertSummary['alerts_today'] ?? 0);
$activeAlerts = (int) ($alertSummary['active_alerts'] ?? 0);

$currentVehicles = (int) ($latestMonitoring['vehicle_count'] ?? 0);
$currentInbound = (int) ($latestMonitoring['inbound_count'] ?? 0);
$currentOutbound = (int) ($latestMonitoring['outbound_count'] ?? 0);
$monitoringRecordAgeSeconds = (int) ($latestMonitoring['record_age_seconds'] ?? PHP_INT_MAX);
$isMonitoringLive = $latestMonitoring !== null
    && $monitoringRecordAgeSeconds <= $monitoringStaleAfterSeconds;
$monitoringStatusLabel = $isMonitoringLive ? 'Online' : 'Offline';
$monitoringRecordedAt = trim((string) ($latestMonitoring['recorded_at'] ?? ''));
$monitoringRecordedAtLabel = $monitoringRecordedAt !== '' ? $monitoringRecordedAt : 'No timestamp';
$monitoringRecordedAtTimestamp = $monitoringRecordedAt !== '' ? strtotime($monitoringRecordedAt) : false;
if ($monitoringRecordedAtTimestamp !== false) {
    $monitoringRecordedAtLabel = date('F j, Y, g:i A', $monitoringRecordedAtTimestamp);
}
$latestTrafficDate = (string)scalar("SELECT COALESCE(DATE(MAX(recorded_at)), CURDATE()) FROM camera_monitoring_logs", date('Y-m-d'));

page_start('Dashboard', 'dashboard', 'Search violations, plates, locations...');
?>

<style>
.dashboard-section-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 6px 0 14px;
}
.dashboard-section-heading::after {
  content: "";
  height: 1px;
  flex: 1;
  background: rgba(16, 47, 73, .12);
}
.dashboard-section-heading span {
  color: #526b64;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .1em;
  text-transform: uppercase;
  white-space: nowrap;
}
.dashboard-overview-heading {
  align-items: flex-end;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(16, 47, 73, .12);
}
.dashboard-overview-heading::after { display: none; }
.overview-filter-form {
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}
.overview-filter-form .dashboard-filter { min-width: 145px; }
.overview-filter-form .overview-custom-date { min-width: 155px; }
.overview-period-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-left: 8px;
  padding: 5px 9px;
  border-radius: 999px;
  background: rgba(8, 125, 120, .09);
  color: #087d78;
  font-size: .64rem;
  font-weight: 800;
  letter-spacing: normal;
  text-transform: none;
}
.dashboard-filter-row {
  display: flex;
  align-items: flex-end;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
}
.dashboard-filter {
  display: grid;
  gap: 5px;
  min-width: 175px;
}
.dashboard-filter label {
  margin: 0;
  color: #526b64;
  font-size: .64rem;
  font-weight: 800;
  letter-spacing: .07em;
  line-height: 1.2;
  text-transform: uppercase;
}
.dashboard-filter .form-control,
.dashboard-filter .form-select {
  width: 100% !important;
  min-height: 42px;
  padding: .5rem .75rem;
  border: 1px solid #9bb7bd !important;
  border-radius: 10px !important;
  background-color: #fff !important;
  color: #102f49 !important;
  font-size: .78rem;
  font-weight: 700;
  box-shadow: 0 3px 10px rgba(16, 47, 73, .05) !important;
}
.dashboard-filter .form-control:focus,
.dashboard-filter .form-select:focus {
  border-color: #087d78 !important;
  box-shadow: 0 0 0 3px rgba(8, 125, 120, .12) !important;
}
.dashboard-filter-action {
  min-height: 42px;
  padding-right: 16px;
  padding-left: 16px;
  border-radius: 10px !important;
  font-weight: 700;
}
@media (max-width: 767.98px) {
  .dashboard-filter-row,
  .dashboard-filter { width: 100%; }
  .dashboard-filter-action { width: 100%; }
  .section-head { align-items: flex-start !important; }
  .dashboard-overview-heading { align-items: stretch; flex-direction: column; }
  .overview-filter-form { justify-content: stretch; }
  .overview-filter-form .dashboard-filter { flex: 1 1 145px; width: auto; }
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS COMMAND CENTER</span>
    <h3 class="page-title">Operations Dashboard</h3>
    <p class="page-sub">Real-time traffic monitoring, predictive insights, and hotspot intelligence.</p>
  </div>
</div>

<!-- Main statistics -->
<div class="dashboard-section-heading dashboard-overview-heading">
  <span>Operational overview <span class="overview-period-badge"><i class="bi bi-funnel"></i><?= esc($overviewLabel) ?></span></span>
  <form class="overview-filter-form" method="get" action="">
    <div class="dashboard-filter">
      <label for="overviewPeriod">Show records</label>
      <select class="form-select" id="overviewPeriod" name="overview_period">
        <option value="all" <?= $overviewPeriod === 'all' ? 'selected' : '' ?>>All Records</option>
        <option value="today" <?= $overviewPeriod === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $overviewPeriod === 'week' ? 'selected' : '' ?>>This Week</option>
        <option value="month" <?= $overviewPeriod === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="custom" <?= $overviewPeriod === 'custom' ? 'selected' : '' ?>>Custom Range</option>
      </select>
    </div>
    <div class="dashboard-filter overview-custom-date" <?= $overviewPeriod === 'custom' ? '' : 'hidden' ?>>
      <label for="overviewFrom">From</label>
      <input class="form-control" type="date" id="overviewFrom" name="overview_from" value="<?= esc($overviewFrom) ?>" max="<?= esc(date('Y-m-d')) ?>">
    </div>
    <div class="dashboard-filter overview-custom-date" <?= $overviewPeriod === 'custom' ? '' : 'hidden' ?>>
      <label for="overviewTo">To</label>
      <input class="form-control" type="date" id="overviewTo" name="overview_to" value="<?= esc($overviewTo) ?>" max="<?= esc(date('Y-m-d')) ?>">
    </div>
    <button class="btn btn-primary dashboard-filter-action" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
  </form>
</div>
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card dashboard-kpi dashboard-kpi-vehicles h-100">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicles Counted</div>
      <div class="stat-value"><?= num($vehiclesToday) ?></div>
      <small class="text-muted">
        <?= num($inboundToday) ?> inbound • <?= num($outboundToday) ?> outbound
      </small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card dashboard-kpi dashboard-kpi-violations h-100">
      <div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Violations</div>
      <div class="stat-value"><?= num($violationsToday) ?></div>
      <small class="text-muted">
        <?= num($paidViolationsToday) ?> paid • <?= num($unpaidViolationsToday) ?> unpaid
      </small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card dashboard-kpi dashboard-kpi-collections h-100">
      <div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Total Collected</div>
      <div class="stat-value"><?= short_money($paymentsToday) ?></div>
      <small class="text-muted"><?= num($completedPaymentsToday) ?> completed payments</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card dashboard-kpi dashboard-kpi-alerts h-100">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Active Alerts</div>
      <div class="stat-value"><?= num($activeAlerts) ?></div>
      <small class="text-muted"><?= num($alertsToday) ?> generated in selected period</small>
    </div>
  </div>
</div>

<style>
.dated-traffic-card{overflow:hidden;border:1px solid #78b8c6!important;background:radial-gradient(circle at 95% 10%,rgba(8,125,120,.12),transparent 24%),linear-gradient(135deg,#fff,#eaf8fa)!important}
.dated-traffic-card .section-head{align-items:center}
.dated-traffic-grid{display:grid;grid-template-columns:minmax(220px,1.2fr) repeat(3,minmax(0,1fr));gap:14px;align-items:stretch;min-width:0}
.dated-traffic-main,.dated-traffic-metric{position:relative;min-width:0;overflow:hidden;padding:20px;border:1px solid rgba(16,47,73,.11);border-radius:16px;background:#fff;box-shadow:0 8px 20px rgba(16,47,73,.06)}
.dated-traffic-main{border-color:rgba(16,47,73,.11);background:#fff!important;color:#102f49!important}.dated-traffic-main:after{content:"";position:absolute;width:115px;height:115px;right:-35px;bottom:-55px;border-radius:50%;background:rgba(8,125,120,.07)}.dated-traffic-main small{color:#60736d!important}.dated-traffic-main strong{display:block;margin:7px 0 3px;color:#102f49!important;font-size:2.45rem;line-height:1}.dated-traffic-main span{position:relative;z-index:1;font-size:.76rem;color:#526b64!important}
.dated-traffic-metric:nth-child(2){border-color:#e3bd61;background:linear-gradient(145deg,#fff,#fff6dd)}.dated-traffic-metric:nth-child(3){border-color:#8298d8;background:linear-gradient(145deg,#fff,#edf1ff)}.dated-traffic-metric:nth-child(4){border-color:#71c49f;background:linear-gradient(145deg,#fff,#eaf9f2)}
.dated-traffic-icon{display:grid;width:36px;height:36px;margin-bottom:13px;place-items:center;border-radius:10px;font-size:1rem}.dated-traffic-metric:nth-child(2) .dated-traffic-icon{color:#b47700;background:#fff0bf}.dated-traffic-metric:nth-child(3) .dated-traffic-icon{color:#526dc0;background:#e1e7ff}.dated-traffic-metric:nth-child(4) .dated-traffic-icon{color:#16845d;background:#d9f3e7}
.dated-traffic-metric small{display:block;color:#60736d!important;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.055em}.dated-traffic-metric strong{display:block;margin-top:7px;color:#102f49!important;font-size:1.18rem;line-height:1.25;overflow-wrap:anywhere}.dated-traffic-metric > span:not(.dated-traffic-icon){display:block;margin-top:6px;color:#667a74!important;font-size:.69rem;line-height:1.4}
@media(max-width:1199.98px){.dated-traffic-grid{grid-template-columns:1fr 1fr}}@media(max-width:575.98px){.dated-traffic-grid{grid-template-columns:1fr}.dated-traffic-card .section-head{align-items:flex-start}}
</style>
<div class="dashboard-section-heading"><span>Historical analytics</span></div>
<div class="section-card dated-traffic-card mb-4">
  <div class="section-head"><div><span class="dashboard-eyebrow">HISTORICAL TRAFFIC</span><h5 class="mb-1">Vehicle Count by Date</h5><small class="text-muted">Select a recorded date to compare traffic volume and identify busier days.</small></div><div class="dashboard-filter-row"><div class="dashboard-filter"><label for="vehicleCountDate">Traffic date</label><input class="form-control" type="date" id="vehicleCountDate" value="<?= esc($latestTrafficDate) ?>" max="<?= esc(date('Y-m-d')) ?>"></div></div></div>
  <div class="dated-traffic-grid">
    <div class="dated-traffic-main"><small id="vehicleDateLabel">Selected date</small><strong id="datedVehicleTotal">—</strong><span id="datedVehicleDirections">Loading vehicle counts…</span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-bar-chart-line"></i></span><small>Compared with average</small><strong id="datedVehicleComparison">—</strong><span id="datedVehicleAverage">Daily average unavailable</span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-trophy"></i></span><small>Busiest recorded date</small><strong id="busiestTrafficDate">—</strong><span id="busiestTrafficTotal">No recorded data</span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-lightbulb"></i></span><small>Interpretation</small><strong id="datedTrafficLevel">—</strong><span id="datedTrafficNarrative">Select a date to review traffic.</span></div>
  </div>
</div>

<style>
#monthlyExecutiveSummary { border-width: 2px; transition: background-color .25s ease, border-color .25s ease; overflow: hidden; }
#monthlyExecutiveSummary.monthly-state-critical { background: linear-gradient(135deg, #fffafa 0%, #fff0f1 100%) !important; border-color: #dc3545 !important; box-shadow: 0 14px 35px rgba(220,53,69,.12) !important; }
#monthlyExecutiveSummary.monthly-state-warning { background: linear-gradient(135deg, #fffdf7 0%, #fff5d9 100%) !important; border-color: #e5a000 !important; box-shadow: 0 14px 35px rgba(229,160,0,.12) !important; }
#monthlyExecutiveSummary.monthly-state-good { background: linear-gradient(135deg, #f9fffb 0%, #e9f9ef 100%) !important; border-color: #2f9e5b !important; box-shadow: 0 14px 35px rgba(47,158,91,.12) !important; }
#monthlyExecutiveSummary .monthly-state-icon { font-size: 1.1rem; }
.dashboard-kpi { position: relative; overflow: hidden; border-width: 1px !important; }
.dashboard-kpi::after { content: ''; position: absolute; width: 110px; height: 110px; border-radius: 50%; right: -42px; top: -50px; opacity: .35; pointer-events: none; }
.dashboard-kpi-vehicles { background: linear-gradient(145deg, #ffffff, #eef1ff) !important; border-color: #9ba8df !important; }
.dashboard-kpi-vehicles::after { background: #7182d2; }
.dashboard-kpi-violations { background: linear-gradient(145deg, #ffffff, #fff5d9) !important; border-color: #e6bd58 !important; }
.dashboard-kpi-violations::after { background: #f4bf32; }
.dashboard-kpi-collections { background: linear-gradient(145deg, #ffffff, #e7faf3) !important; border-color: #68c9a6 !important; }
.dashboard-kpi-collections::after { background: #42c49a; }
.dashboard-kpi-alerts { background: linear-gradient(145deg, #ffffff, #fff0ed) !important; border-color: #ee9a8e !important; }
.dashboard-kpi-alerts::after { background: #ef7464; }
.dashboard-kpi .stat-icon { box-shadow: 0 8px 22px rgba(16,47,73,.12); }
.dashboard-kpi .stat-value { color: #102f49 !important; }
#monthlyExecutiveSummary .monthly-kpi { border-width: 1px !important; position: relative; overflow: hidden; }
#monthlyExecutiveSummary .monthly-kpi-violations { background: linear-gradient(145deg, #fff, #eef2ff) !important; border-color: #9aa9df !important; }
#monthlyExecutiveSummary .monthly-kpi-collected { background: linear-gradient(145deg, #fff, #e9faf2) !important; border-color: #72c9a7 !important; }
#monthlyExecutiveSummary .monthly-kpi-offense { background: linear-gradient(145deg, #fff, #fff4dc) !important; border-color: #e7bc58 !important; }
#monthlyExecutiveSummary .monthly-kpi-location { background: linear-gradient(145deg, #fff, #e9f7fa) !important; border-color: #6eb7c4 !important; }
</style>
<div class="section-card mb-4" id="monthlyExecutiveSummary">
  <div class="section-head">
    <div>
      <span class="dashboard-eyebrow">MONTHLY EXECUTIVE SUMMARY</span>
      <h5 class="mb-1" id="monthlySummaryTitle"><?= esc(date('F Y')) ?></h5>
      <small class="text-muted">Performance, collections, enforcement patterns, and month-over-month movement</small>
    </div>
    <div class="dashboard-filter-row">
      <span class="tag" id="monthlySummaryStatus"><i class="bi bi-circle-fill me-1 monthly-state-icon"></i>Loading</span>
      <div class="dashboard-filter">
        <label for="monthlySummaryMonth">Summary month</label>
        <input class="form-control" type="month" id="monthlySummaryMonth" value="<?= esc(date('Y-m')) ?>">
      </div>
    </div>
  </div>
  <div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-violations h-100"><div class="stat-label"><i class="bi bi-graph-up-arrow me-1 text-primary"></i>Monthly Violations</div><div class="stat-value" id="monthlyViolationCount">—</div><small id="monthlyViolationChange" class="text-muted">Compared with last month</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-collected h-100"><div class="stat-label"><i class="bi bi-cash-stack me-1 text-success"></i>Collected</div><div class="stat-value" id="monthlyCollected">—</div><small id="monthlyCollectionRate" class="text-muted">Collection rate</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-offense h-100"><div class="stat-label"><i class="bi bi-cone-striped me-1 text-warning"></i>Leading Violation</div><div class="h5 mt-2 mb-1" id="monthlyTopViolation">—</div><small id="monthlyTopViolationCount" class="text-muted">No records</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-location h-100"><div class="stat-label"><i class="bi bi-geo-alt-fill me-1 text-info"></i>Highest Activity Area</div><div class="h5 mt-2 mb-1" id="monthlyTopLocation">—</div><small id="monthlyPeakPeriod" class="text-muted">Peak period unavailable</small></div></div>
  </div>
  <div class="alert alert-light border mt-3 mb-0" role="note">
    <strong><i class="bi bi-lightbulb me-1"></i>Management interpretation:</strong>
    <span id="monthlySummaryNarrative">Loading monthly performance summary...</span>
  </div>
</div>

<!-- AI executive preview; full analysis belongs in Decision Support. -->
<style>
body.municipal-portal #aiDecisionCard{
  position:relative;overflow:hidden;padding:0!important;border:1px solid rgba(8,125,120,.28)!important;border-radius:22px!important;
  background:linear-gradient(135deg,rgba(255,255,255,.97),rgba(240,250,249,.96))!important;
  box-shadow:0 18px 44px rgba(16,47,73,.12)!important
}
body.municipal-portal #aiDecisionCard::before{content:"";position:absolute;inset:0 auto 0 0;width:6px;background:linear-gradient(180deg,#2563eb,#12b8b0,#18a878)}
body.municipal-portal #aiDecisionCard::after{content:"";position:absolute;width:280px;height:280px;right:-110px;top:-165px;border-radius:50%;background:radial-gradient(circle,rgba(56,189,248,.16),rgba(56,189,248,0) 70%);pointer-events:none}
body.municipal-portal #aiDecisionCard .ai-section-head{position:relative;z-index:1;align-items:center;margin:0;padding:24px 26px 20px;border-bottom:1px solid rgba(16,47,73,.09)}
.ai-preview-heading{display:flex;align-items:center;gap:14px}
.ai-preview-heading-icon{display:grid;place-items:center;flex:0 0 48px;width:48px;height:48px;border-radius:15px;color:#087d78;background:linear-gradient(145deg,#e1f8f4,#e5f1ff);border:1px solid rgba(8,125,120,.16);font-size:1.2rem;box-shadow:0 8px 20px rgba(8,125,120,.10)}
.ai-preview-heading .ai-kicker{display:block;margin-bottom:4px}
body.municipal-portal #aiDecisionCard .ai-full-link{position:relative;z-index:1;height:42px!important;padding:0 18px!important;background:linear-gradient(135deg,#087d78,#0a9b8e)!important;box-shadow:0 10px 22px rgba(8,125,120,.22)!important}
.ai-preview-body{position:relative;z-index:1;padding:20px 26px 26px}
.ai-preview-grid{display:grid;grid-template-columns:1.05fr .85fr 1.15fr 1.25fr;gap:14px}
body.municipal-portal #aiDecisionCard .ai-preview-item{position:relative;min-width:0;min-height:142px;padding:17px!important;overflow:hidden;border:1px solid rgba(16,47,73,.11)!important;border-radius:16px!important;background:rgba(255,255,255,.82)!important;box-shadow:0 8px 20px rgba(16,47,73,.055)!important;transition:transform .22s ease,box-shadow .22s ease,border-color .22s ease}
body.municipal-portal #aiDecisionCard .ai-preview-item:hover{transform:translateY(-3px);border-color:rgba(8,125,120,.28)!important;box-shadow:0 13px 26px rgba(16,47,73,.09)!important}
.ai-preview-item::after{content:"";position:absolute;right:-25px;bottom:-38px;width:90px;height:90px;border-radius:50%;background:var(--preview-glow,rgba(8,125,120,.07))}
.ai-preview-item-head{display:flex;align-items:center;gap:9px;margin-bottom:14px}
.ai-preview-item-icon{display:grid;place-items:center;flex:0 0 34px;width:34px;height:34px;border-radius:10px;color:var(--preview-color,#087d78);background:var(--preview-soft,#e6f7f4);font-size:.9rem}
.ai-preview-item small{display:block;margin:0;color:#60736d!important;font-size:.64rem;font-weight:800;letter-spacing:.055em;line-height:1.35;text-transform:uppercase;white-space:normal!important}
.ai-preview-item strong{position:relative;z-index:1;display:block;color:#102f49!important;font-size:1rem;line-height:1.42;overflow-wrap:anywhere}
.ai-preview-risk{--preview-color:#2563eb;--preview-soft:#eaf1ff;--preview-glow:rgba(37,99,235,.08);background:linear-gradient(145deg,#fff,#f2f6ff)!important}
.ai-preview-confidence-card{--preview-color:#526dc0;--preview-soft:#eef1ff;--preview-glow:rgba(82,109,192,.08)}
.ai-preview-location{--preview-color:#087d78;--preview-soft:#e5f7f4;--preview-glow:rgba(8,125,120,.08)}
.ai-preview-action{--preview-color:#b47700;--preview-soft:#fff2c9;--preview-glow:rgba(235,148,31,.10)}
.ai-preview-risk .ai-risk-badge{position:relative;z-index:1;display:inline-flex;align-items:center;min-width:128px;justify-content:center;padding:9px 16px;border-radius:999px;font-size:1.02rem;font-weight:900;letter-spacing:.055em}
.ai-preview-confidence-value{display:flex!important;align-items:baseline;gap:5px;font-size:1.45rem!important}
.ai-preview-confidence-value span{color:#71849a;font-size:.7rem;font-weight:700}
.ai-preview-confidence{position:relative;z-index:1;height:8px;margin-top:12px;border-radius:999px;background:rgba(16,47,73,.09);overflow:hidden}
.ai-preview-confidence span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#526dc0,#26a7c7);box-shadow:0 0 12px rgba(38,167,199,.25)}
.ai-preview-caption{position:relative;z-index:1;display:block;margin-top:8px;color:#748680!important;font-size:.66rem!important;letter-spacing:0!important;text-transform:none!important}
@media(max-width:1199.98px){.ai-preview-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){body.municipal-portal #aiDecisionCard .ai-section-head{align-items:flex-start;padding:20px;flex-direction:column}.ai-preview-body{padding:16px 20px 20px}.ai-preview-heading-icon{display:none}.ai-full-link{width:100%!important}.ai-preview-grid{grid-template-columns:1fr}}
</style>
<div class="dashboard-section-heading"><span>AI intelligence</span></div>
<div class="section-card ai-decision-card mb-4" id="aiDecisionCard">
  <div class="section-head ai-section-head">
    <div class="ai-preview-heading">
      <span class="ai-preview-heading-icon" aria-hidden="true"><i class="bi bi-cpu-fill"></i></span>
      <div>
        <span class="ai-kicker">TRAVIS AI ENGINE</span>
        <h5 class="mb-1">Decision-Support Snapshot</h5>
        <small class="text-muted">Next-month outlook and the most important operational signal</small>
      </div>
    </div>
    <a class="btn btn-primary ai-full-link" href="<?= esc(app_url('decision_support.php')) ?>">
      <i class="bi bi-graph-up-arrow me-1"></i>View Full Analysis
    </a>
  </div>

  <div class="ai-preview-body">
    <div id="aiPredictionLoading" class="ai-loading-state">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      Loading decision-support snapshot...
    </div>
    <div id="aiPredictionError" class="alert alert-danger d-none mb-0" role="alert"></div>

    <div id="aiPredictionContent" class="d-none">
    <div class="ai-preview-grid">
      <div class="ai-preview-item ai-preview-risk">
        <div class="ai-preview-item-head">
          <span class="ai-preview-item-icon"><i class="bi bi-shield-exclamation"></i></span>
          <small>Forecast risk<br><span id="aiPredictionPeriod">—</span></small>
        </div>
        <span class="ai-risk-badge" id="aiRiskBadge">—</span>
      </div>
      <div class="ai-preview-item ai-preview-confidence-card">
        <div class="ai-preview-item-head">
          <span class="ai-preview-item-icon"><i class="bi bi-speedometer2"></i></span>
          <small>Prediction confidence</small>
        </div>
        <strong class="ai-preview-confidence-value"><span id="aiConfidenceText">—</span></strong>
        <div class="ai-preview-confidence" aria-hidden="true"><span id="aiConfidenceBar" style="width:0%"></span></div>
        <small class="ai-preview-caption">Model certainty</small>
      </div>
      <div class="ai-preview-item ai-preview-location">
        <div class="ai-preview-item-head">
          <span class="ai-preview-item-icon"><i class="bi bi-geo-alt-fill"></i></span>
          <small>Priority location</small>
        </div>
        <strong id="aiPriorityLocation">—</strong>
      </div>
      <div class="ai-preview-item ai-preview-action">
        <div class="ai-preview-item-head">
          <span class="ai-preview-item-icon"><i class="bi bi-lightning-charge-fill"></i></span>
          <small>Immediate action</small>
        </div>
        <strong id="aiImmediateAction">—</strong>
      </div>
    </div>
    </div>
  </div>
</div>

<!-- Compact operational counters -->
<div class="dashboard-section-heading"><span>Live operations</span></div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Pending Violations</small>
      <h4 class="mb-0 mt-1"><?= num($allPendingViolations) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Congestion Events &middot; <?= esc($overviewLabel) ?></small>
      <h4 class="mb-0 mt-1"><?= num($congestionEvents) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Collision Alerts &middot; <?= esc($overviewLabel) ?></small>
      <h4 class="mb-0 mt-1"><?= num($collisionEvents) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Online Cameras</small>
      <h4 class="mb-0 mt-1"><?= num($onlineCameras) ?> / <?= num($totalCameras) ?></h4>
    </div>
  </div>
</div>

<!-- Live monitoring summary: stale records are intentionally not displayed. -->
<div class="section-card mb-4 <?= $isMonitoringLive ? 'monitoring-live' : 'monitoring-stale' ?>" id="currentMonitoringCard">
  <div class="section-head">
    <div>
      <h6>Live Computer-Vision Status</h6>
      <small class="text-muted"><?= $isMonitoringLive ? 'Current detection results from the active CV worker' : 'Live results appear only while Computer Vision is running' ?></small>
    </div>

    <span class="tag <?= $isMonitoringLive ? 'tag-success' : 'tag-offline' ?>">
      <?= esc($monitoringStatusLabel) ?>
    </span>
  </div>

  <?php if ($isMonitoringLive): ?>
    <div class="metric-grid">
      <div class="mini-metric">
        <small>Visible Vehicles</small>
        <strong><?= num($currentVehicles) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Inbound</small>
        <strong><?= num($currentInbound) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Outbound</small>
        <strong><?= num($currentOutbound) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Congestion</small>
        <strong><?= esc($latestMonitoring['congestion_level'] ?? 'none') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Traffic Officer</small>
        <strong><?= esc($latestMonitoring['officer_presence'] ?? 'unknown') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Collision</small>
        <strong><?= esc($latestMonitoring['potential_collision'] ?? 'none') ?></strong>
      </div>
    </div>

    <div class="mt-3 small text-muted">
      Last updated:
      <time<?= $monitoringRecordedAt !== '' ? ' datetime="' . esc($monitoringRecordedAt) . '"' : '' ?>><?= esc($monitoringRecordedAtLabel) ?></time>
    </div>
  <?php else: ?>
    <div class="monitoring-offline-state" role="status">
      <span class="monitoring-offline-icon" aria-hidden="true"><i class="bi bi-camera-video-off"></i></span>
      <div class="monitoring-offline-copy">
        <strong>Computer Vision is not currently sending live data.</strong>
        <small class="text-muted">Previous monitoring-session values are hidden to prevent them from being mistaken for current road conditions.</small>
        <a class="btn btn-light" href="<?= esc(app_url('monitoring.php')) ?>">
          <i class="bi bi-camera-video me-1"></i>Open Monitoring
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Treasurer-style violation and collection charts -->
<div class="dashboard-section-heading"><span>Performance trends</span></div>
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Violation Records</h6>
          <small class="text-muted">Monthly records · <?= esc(date('Y')) ?></small>
        </div>
      </div>
      <div style="height:240px"><canvas id="trendChart"></canvas></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Monthly Collection</h6>
          <small class="text-muted">Completed payments · <?= esc(date('Y')) ?></small>
        </div>
      </div>
      <div style="height:240px"><canvas id="monthlyCollectionChart"></canvas></div>
    </div>
  </div>
</div>

<!-- Recent operational activity -->
<div class="dashboard-section-heading"><span>Recent activity</span></div>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Recent Alerts</h6>
        <a href="<?= esc(app_url('alerts.php')) ?>" class="small fw-semibold text-decoration-none">View all</a>
      </div>

      <?php if (!$recentAlerts): ?>
        <?php empty_state('No recent alerts found.'); ?>
      <?php else: ?>
        <?php foreach ($recentAlerts as $alert): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= esc(ucfirst($alert['alert_type'])) ?></strong>
              <span class="tag <?= tag_class($alert['severity']) ?>"><?= esc($alert['severity']) ?></span>
            </div>
            <small class="text-muted d-block"><?= esc($alert['message']) ?></small>
            <small class="text-muted"><?= esc($alert['generated_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Recent Violations</h6>
        <a href="<?= esc(app_url('violations.php')) ?>" class="small fw-semibold text-decoration-none">View all</a>
      </div>

      <?php if (!$recentViolations): ?>
        <?php empty_state('No violation records found.'); ?>
      <?php else: ?>
        <?php foreach ($recentViolations as $violation): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= esc($violation['ticket_number']) ?></strong>
              <span class="tag <?= tag_class($violation['status']) ?>"><?= esc($violation['status']) ?></span>
            </div>
            <small class="text-muted d-block">
              <?= esc($violation['plate_number']) ?> • <?= esc($violation['violation_type']) ?>
            </small>
            <small class="text-muted">
              <?= esc($violation['violation_location']) ?> • <?= peso($violation['penalty_amount']) ?>
            </small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const overviewPeriodSelect = document.getElementById('overviewPeriod');
const overviewCustomDateFields = document.querySelectorAll('.overview-custom-date');
const syncOverviewCustomDates = () => {
  const showCustomDates = overviewPeriodSelect?.value === 'custom';
  overviewCustomDateFields.forEach((field) => {
    field.hidden = !showCustomDates;
  });
};
overviewPeriodSelect?.addEventListener('change', syncOverviewCustomDates);
syncOverviewCustomDates();

const MONTHLY_PREDICTION_ENDPOINT = '../api/predict_monthly.php';
const MONTHLY_SUMMARY_ENDPOINT = '../../api/get_monthly_summary.php';
const HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const months = <?= json_encode(month_labels()) ?>;
const trendData = <?= json_encode($trendData) ?>;
const monthlyCollectionData = <?= json_encode($monthlyCollectionData) ?>;
const adminChartGrid = 'rgba(148, 163, 184, .16)';
const adminChartTicks = '#c9d8ea';

function normalizeRisk(value) {
  return String(value || '').trim().toLowerCase().replace(' risk', '');
}

function applyRiskStyle(element, riskLevel) {
  const risk = normalizeRisk(riskLevel);

  element.classList.remove(
    'ai-risk-high',
    'ai-risk-medium',
    'ai-risk-low'
  );

  if (risk === 'high') {
    element.classList.add('ai-risk-high');
  } else if (risk === 'medium') {
    element.classList.add('ai-risk-medium');
  } else {
    element.classList.add('ai-risk-low');
  }
}

function formatMonthlyMoney(value) {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    maximumFractionDigits: 0
  }).format(Number(value || 0));
}

async function loadMonthlySummary() {
  const selectedMonth = document.getElementById('monthlySummaryMonth')?.value || '';
  const endpoint = selectedMonth
    ? `${MONTHLY_SUMMARY_ENDPOINT}?month=${encodeURIComponent(selectedMonth)}`
    : MONTHLY_SUMMARY_ENDPOINT;

  try {
    const response = await fetch(endpoint, { cache: 'no-store', credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load monthly summary.');

    const summary = payload.data;
    const change = Number(summary.change_percent || 0);
    const status = String(summary.status || 'Stable');
    const statusElement = document.getElementById('monthlySummaryStatus');
    const summaryCard = document.getElementById('monthlyExecutiveSummary');
    const stateClass = status === 'Critical' ? 'monthly-state-critical' : status === 'Needs Attention' ? 'monthly-state-warning' : 'monthly-state-good';
    const badgeClass = status === 'Critical' ? 'text-bg-danger' : status === 'Needs Attention' ? 'text-bg-warning' : 'text-bg-success';
    const iconClass = status === 'Critical' ? 'bi-exclamation-octagon-fill' : status === 'Needs Attention' ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill';
    summaryCard.classList.remove('monthly-state-critical', 'monthly-state-warning', 'monthly-state-good');
    summaryCard.classList.add(stateClass);
    statusElement.innerHTML = `<i class="bi ${iconClass} me-1"></i>${status.toUpperCase()}`;
    statusElement.className = `tag ${badgeClass}`;

    document.getElementById('monthlySummaryTitle').textContent = summary.month_label;
    document.getElementById('monthlyViolationCount').textContent = Number(summary.violations || 0).toLocaleString();
    document.getElementById('monthlyViolationChange').textContent = `${change > 0 ? '▲' : change < 0 ? '▼' : '•'} ${Math.abs(change)}% vs previous month`;
    document.getElementById('monthlyViolationChange').className = change > 0 ? 'text-danger' : change < 0 ? 'text-success' : 'text-muted';
    document.getElementById('monthlyCollected').textContent = formatMonthlyMoney(summary.collected_amount);
    const collectedAmount = Number(summary.collected_amount || 0);
    const pendingAmount = Number(summary.pending_amount || 0);
    const collectionMessage = pendingAmount > 0
      ? `${formatMonthlyMoney(pendingAmount)} still unpaid`
      : collectedAmount > 0
        ? 'No unpaid balance'
        : 'No payments recorded';
    document.getElementById('monthlyCollectionRate').textContent = collectionMessage;
    document.getElementById('monthlyTopViolation').textContent = summary.top_violation?.label || 'No data';
    document.getElementById('monthlyTopViolationCount').textContent = `${Number(summary.top_violation?.total || 0).toLocaleString()} recorded cases`;
    document.getElementById('monthlyTopLocation').textContent = summary.top_location?.label || 'No data';
    document.getElementById('monthlyPeakPeriod').textContent = `Peak: ${summary.peak_day}, ${summary.peak_hour}`;
    document.getElementById('monthlySummaryNarrative').textContent = summary.summary;
  } catch (error) {
    document.getElementById('monthlySummaryStatus').textContent = 'UNAVAILABLE';
    document.getElementById('monthlySummaryNarrative').textContent = error.message || 'Monthly summary is temporarily unavailable.';
  }
}

function getHotspotLocations(payload) {
  if (Array.isArray(payload?.data?.locations)) return payload.data.locations;
  if (Array.isArray(payload?.locations)) return payload.locations;

  return [
    ...(payload?.high_risk || []),
    ...(payload?.medium_risk || []),
    ...(payload?.low_risk || [])
  ];
}

function classifyHotspots(records) {
  return records.reduce(
    (groups, record) => {
      const risk = normalizeRisk(record['Risk Level'] || record.risk_level);

      if (risk === 'high') groups.high.push(record);
      else if (risk === 'medium') groups.medium.push(record);
      else groups.low.push(record);

      return groups;
    },
    { high: [], medium: [], low: [] }
  );
}

function sortHotspots(records) {
  return [...records].sort(
    (a, b) =>
      Number(b['Total Violations'] ?? b.Total_Violations ?? b.total ?? 0) -
      Number(a['Total Violations'] ?? a.Total_Violations ?? a.total ?? 0)
  );
}

async function loadMonthlyPrediction() {
  const loading = document.getElementById('aiPredictionLoading');
  const errorBox = document.getElementById('aiPredictionError');
  const content = document.getElementById('aiPredictionContent');

  loading.classList.remove('d-none');
  errorBox.classList.add('d-none');
  content.classList.add('d-none');

  try {
    const now = new Date();
    const forecastDate = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const predictionUrl = `${MONTHLY_PREDICTION_ENDPOINT}?year=${forecastDate.getFullYear()}&month=${forecastDate.getMonth() + 1}`;
    const requestOptions = {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    };
    const [predictionResponse, hotspotResponse] = await Promise.all([
      fetch(predictionUrl, requestOptions),
      fetch(HOTSPOT_ENDPOINT, requestOptions)
    ]);
    const [payload, hotspotPayload] = await Promise.all([
      predictionResponse.json(),
      hotspotResponse.json()
    ]);

    if (!predictionResponse.ok || !payload.success) {
      throw new Error(payload.message || 'Unable to load the prediction.');
    }
    if (!hotspotResponse.ok || !hotspotPayload.success) {
      throw new Error(hotspotPayload.message || 'Unable to load hotspot results.');
    }

    const prediction = payload.data || payload.prediction || {};
    const riskLevel = prediction.risk_level || 'Unknown';
    const confidence = Number(prediction.confidence || 0);
    const hotspots = classifyHotspots(getHotspotLocations(hotspotPayload));
    const priorityRecord =
      sortHotspots(hotspots.high)[0] ||
      sortHotspots(hotspots.medium)[0] ||
      sortHotspots(hotspots.low)[0];
    const priorityLocation =
      priorityRecord?.Location ||
      priorityRecord?.location ||
      priorityRecord?.violation_location ||
      'No hotspot data';
    const normalizedRisk = normalizeRisk(riskLevel);
    const immediateAction = normalizedRisk === 'high'
      ? 'Plan a focused 7-day operation at the priority location'
      : normalizedRisk === 'medium'
        ? 'Observe the priority location and check results twice a week'
        : 'Continue regular monitoring';

    const riskBadge = document.getElementById('aiRiskBadge');
    riskBadge.textContent = String(riskLevel).toUpperCase();
    applyRiskStyle(riskBadge, riskLevel);

    document.getElementById('aiPredictionPeriod').textContent =
      `${prediction.month_name || 'Current month'} ${prediction.year || ''}`.trim();

    document.getElementById('aiConfidenceText').textContent =
      `${confidence.toFixed(1)}%`;

    const confidenceBar = document.getElementById('aiConfidenceBar');
    confidenceBar.style.width = `${Math.max(0, Math.min(100, confidence))}%`;
    document.getElementById('aiPriorityLocation').textContent = priorityLocation;
    document.getElementById('aiImmediateAction').textContent = immediateAction;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      `${error.message} Make sure the Flask API is running on port 5001.`;
    errorBox.classList.remove('d-none');
  }
}

document.getElementById('monthlySummaryMonth')?.addEventListener('change', loadMonthlySummary);

loadMonthlySummary();
loadMonthlyPrediction();

Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = adminChartTicks;

function violationChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#102f49',
        titleColor: '#ffffff',
        bodyColor: '#e8f0ed',
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label: context => {
            const count = Number(context.parsed.y || 0);
            return `${count.toLocaleString()} violation${count === 1 ? '' : 's'}`;
          }
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: adminChartTicks } },
      y: {
        beginAtZero: true,
        grid: { color: adminChartGrid },
        ticks: { precision: 0, color: adminChartTicks }
      }
    }
  };
}

function collectionChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#102f49',
        titleColor: '#ffffff',
        bodyColor: '#e8f0ed',
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label: context => `₱${Number(context.parsed.y || 0).toLocaleString()}`
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: adminChartTicks } },
      y: {
        beginAtZero: true,
        grid: { color: adminChartGrid },
        ticks: {
          color: adminChartTicks,
          callback: value => `₱${Number(value).toLocaleString()}`
        }
      }
    }
  };
}

const violationContext = document.getElementById('trendChart').getContext('2d');
const violationGradient = violationContext.createLinearGradient(0, 0, 0, 240);
violationGradient.addColorStop(0, 'rgba(8, 125, 120, .34)');
violationGradient.addColorStop(1, 'rgba(8, 125, 120, 0)');

new Chart(violationContext, {
  type: 'line',
  data: {
    labels: months,
    datasets: [{
      label: 'Violation Records',
      data: trendData,
      borderColor: '#087d78',
      backgroundColor: violationGradient,
      fill: true,
      tension: .4,
      borderWidth: 3,
      pointBackgroundColor: '#eb941f',
      pointBorderColor: '#fffdf7',
      pointBorderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 6
    }]
  },
  options: violationChartOptions()
});

const collectionContext = document.getElementById('monthlyCollectionChart').getContext('2d');
const collectionGradient = collectionContext.createLinearGradient(0, 0, 0, 240);
collectionGradient.addColorStop(0, '#087d78');
collectionGradient.addColorStop(1, '#eb941f');

new Chart(collectionContext, {
  type: 'bar',
  data: {
    labels: months,
    datasets: [{
      label: 'Monthly Collection',
      data: monthlyCollectionData,
      backgroundColor: collectionGradient,
      borderRadius: 6,
      borderSkipped: false
    }]
  },
  options: collectionChartOptions()
});
</script>

<script>
(() => {
  const picker = document.getElementById('vehicleCountDate');
  if (!picker) return;
  const loadVehicleDate = async () => {
    const total = document.getElementById('datedVehicleTotal');
    total.textContent = '…';
    try {
      const response = await fetch(`../api/vehicle_count_by_date.php?date=${encodeURIComponent(picker.value)}`, {cache:'no-store',credentials:'same-origin'});
      const payload = await response.json();
      if (!response.ok || !payload.success) throw new Error(payload.message || 'Unable to load vehicle counts.');
      const data = payload.data;
      const difference = Number(data.difference_percent || 0);
      const comparisonText = Math.abs(difference) < 1
        ? 'Near average'
        : difference > 100
          ? `About ${(1 + (difference / 100)).toFixed(1)} times the average`
          : `${Math.abs(difference).toFixed(1)}% ${difference > 0 ? 'higher' : 'lower'}`;
      document.getElementById('vehicleDateLabel').textContent = data.date_label;
      total.textContent = Number(data.vehicle_total).toLocaleString();
      document.getElementById('datedVehicleDirections').textContent = `${Number(data.inbound_total).toLocaleString()} inbound · ${Number(data.outbound_total).toLocaleString()} outbound`;
      document.getElementById('datedVehicleComparison').textContent = comparisonText;
      document.getElementById('datedVehicleAverage').textContent = `${Number(data.daily_average).toLocaleString()} vehicles daily average`;
      document.getElementById('busiestTrafficDate').textContent = data.busiest_date || 'No data';
      document.getElementById('busiestTrafficTotal').textContent = data.busiest_date ? `${Number(data.busiest_total).toLocaleString()} vehicles recorded` : 'No recorded data';
      const level = difference >= 20 ? 'Busier than usual' : difference <= -20 ? 'Quieter than usual' : 'Typical traffic volume';
      document.getElementById('datedTrafficLevel').textContent = data.vehicle_total > 0 ? level : 'No traffic record';
      document.getElementById('datedTrafficNarrative').textContent = data.vehicle_total > 0 ? `Based on ${Number(data.recorded_days).toLocaleString()} recorded days.` : 'No monitoring data was saved on this date.';
    } catch (error) {
      total.textContent = 'Unavailable';
      document.getElementById('datedTrafficNarrative').textContent = error.message;
    }
  };
  picker.addEventListener('change', loadVehicleDate);
  loadVehicleDate();
})();
</script>

<?php page_end(false); ?>
