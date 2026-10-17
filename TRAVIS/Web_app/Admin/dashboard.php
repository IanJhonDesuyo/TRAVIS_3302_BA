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
.card {
    background: #ffffff !important;
    border: 1px solid #d7e3e1 !important;
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
.card .text-muted {
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
    border: 1px solid #d7e3e1 !important;
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
    border: 1px solid #d7e3e1 !important;
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
$overviewPeriod = strtolower(trim((string)($_GET['dashboard_scope'] ?? 'all')));
$allowedOverviewPeriods = ['all', 'day', 'month', 'year'];
if (!in_array($overviewPeriod, $allowedOverviewPeriods, true)) $overviewPeriod = 'all';

$overviewValue = trim((string)($_GET['period_value'] ?? ''));
$isValidOverviewDate = static function (string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};

$overviewDateCondition = '1=1';
$previousDateCondition = '1=0';
$overviewLabel = 'All Records';
$overviewInputType = '';
$overviewInputLabel = '';
// These normalized values drive every dashboard section except the independent
// Decision Support preview.
if ($overviewPeriod === 'day') {
    if (!$isValidOverviewDate($overviewValue)) $overviewValue = date('Y-m-d');
    $selectedDay = new DateTimeImmutable($overviewValue);
    $previousDay = $selectedDay->modify('-1 day')->format('Y-m-d');
    $overviewDateCondition = "%s = '{$overviewValue}'";
    $previousDateCondition = "%s = '{$previousDay}'";
    $overviewLabel = $selectedDay->format('F j, Y');
    $overviewInputType = 'date';
    $overviewInputLabel = 'Selected day';
} elseif ($overviewPeriod === 'month') {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $overviewValue)) $overviewValue = date('Y-m');
    $selectedMonth = new DateTimeImmutable($overviewValue . '-01');
    $previousMonth = $selectedMonth->modify('-1 month')->format('Y-m');
    $overviewDateCondition = "DATE_FORMAT(%s, '%%Y-%%m') = '{$overviewValue}'";
    $previousDateCondition = "DATE_FORMAT(%s, '%%Y-%%m') = '{$previousMonth}'";
    $overviewLabel = $selectedMonth->format('F Y');
    $overviewInputType = 'month';
    $overviewInputLabel = 'Selected month';
} elseif ($overviewPeriod === 'year') {
    $selectedYear = preg_match('/^\d{4}$/', $overviewValue) ? (int)$overviewValue : (int)date('Y');
    $selectedYear = max(2000, min((int)date('Y') + 1, $selectedYear));
    $overviewValue = (string)$selectedYear;
    $overviewDateCondition = "YEAR(%s) = {$selectedYear}";
    $previousDateCondition = 'YEAR(%s) = ' . ($selectedYear - 1);
    $overviewLabel = $overviewValue;
    $overviewInputType = 'number';
    $overviewInputLabel = 'Selected year';
}

$overviewConditionFor = static function (string $column) use ($overviewDateCondition): string {
    $placeholderCount = substr_count($overviewDateCondition, '%s');
    return $placeholderCount > 0
        ? vsprintf($overviewDateCondition, array_fill(0, $placeholderCount, $column))
        : $overviewDateCondition;
};
$previousConditionFor = static function (string $column) use ($previousDateCondition): string {
    $placeholderCount = substr_count($previousDateCondition, '%s');
    return $placeholderCount > 0
        ? vsprintf($previousDateCondition, array_fill(0, $placeholderCount, $column))
        : $previousDateCondition;
};

// Charts use a compact timeline independent from the rest of the dashboard.
// Week means the current week, while month and year can be selected.
$chartPeriod = strtolower(trim((string)($_GET['chart_scope'] ?? 'year')));
$allowedChartPeriods = ['all', 'week', 'month', 'year'];
if (!in_array($chartPeriod, $allowedChartPeriods, true)) $chartPeriod = 'year';

$chartValue = trim((string)($_GET['chart_period_value'] ?? ''));
$chartDateCondition = '1=1';
$chartLabel = 'Overall';
$chartToday = new DateTimeImmutable('today');
$chartPickerType = 'month';
$chartPickerLabel = 'Choose month';

if ($chartPeriod === 'week') {
    $selectedChartWeekStart = $chartToday->modify('monday this week');
    $selectedChartWeekEnd = $selectedChartWeekStart->modify('+6 days');
    $chartWeekStartValue = $selectedChartWeekStart->format('Y-m-d');
    $chartWeekEndValue = $selectedChartWeekEnd->format('Y-m-d');
    $chartDateCondition = "%s BETWEEN '{$chartWeekStartValue}' AND '{$chartWeekEndValue}'";
    $chartLabel = $selectedChartWeekStart->format('M j') . ' – ' . $selectedChartWeekEnd->format('M j, Y');
} elseif ($chartPeriod === 'month') {
    $currentChartMonth = $chartToday->format('Y-m');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $chartValue) || $chartValue > $currentChartMonth) {
        $chartValue = $chartToday->format('Y-m');
    }
    $selectedChartMonth = new DateTimeImmutable($chartValue . '-01');
    $chartMonthValue = $selectedChartMonth->format('Y-m');
    $chartDateCondition = "DATE_FORMAT(%s, '%%Y-%%m') = '{$chartMonthValue}'";
    $chartLabel = $selectedChartMonth->format('F Y');
    $chartPickerType = 'month';
    $chartPickerLabel = 'Choose month';
} elseif ($chartPeriod === 'year') {
    $currentChartYear = (int)$chartToday->format('Y');
    $selectedChartYear = preg_match('/^\d{4}$/', $chartValue) ? (int)$chartValue : $currentChartYear;
    $selectedChartYear = max(2000, min($currentChartYear, $selectedChartYear));
    $chartValue = (string)$selectedChartYear;
    $chartDateCondition = "YEAR(%s) = {$selectedChartYear}";
    $chartLabel = (string)$selectedChartYear;
    $chartPickerType = 'number';
    $chartPickerLabel = 'Choose year';
}

$chartConditionFor = static function (string $column) use ($chartDateCondition): string {
    $placeholderCount = substr_count($chartDateCondition, '%s');
    return $placeholderCount > 0
        ? vsprintf($chartDateCondition, array_fill(0, $placeholderCount, $column))
        : $chartDateCondition;
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
      AND " . $overviewConditionFor('violation_date') . "
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

$recentAlerts = fetch_all("
    SELECT alert_type, severity, message, status, generated_at
    FROM monitoring_alerts
    WHERE " . $overviewConditionFor('DATE(generated_at)') . "
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
    WHERE " . $overviewConditionFor('violation_date') . "
    ORDER BY created_at DESC
    LIMIT 5
");

$topViolators = fetch_all("
    SELECT
        MAX(ranked.driver_name) AS driver_name,
        MAX(ranked.display_license) AS license_number,
        COUNT(*) AS record_count,
        SUM(CASE WHEN ranked.status IN ('pending', 'overdue') THEN 1 ELSE 0 END) AS unpaid_count,
        COALESCE(SUM(ranked.penalty_amount), 0) AS total_penalties,
        MAX(CONCAT(ranked.violation_date, ' ', ranked.violation_time)) AS last_recorded
    FROM (
        SELECT
            v.driver_name,
            v.status,
            v.penalty_amount,
            v.violation_date,
            v.violation_time,
            CASE
                WHEN COALESCE(v.has_no_license, 0) = 0
                     AND UPPER(TRIM(v.license_number)) NOT IN ('', 'NO LICENSE')
                    THEN UPPER(TRIM(v.license_number))
                ELSE ''
            END AS display_license,
            CASE
                WHEN COALESCE(v.has_no_license, 0) = 0
                     AND UPPER(TRIM(v.license_number)) NOT IN ('', 'NO LICENSE')
                    THEN CONCAT('license:', UPPER(TRIM(v.license_number)))
                WHEN v.date_of_birth IS NOT NULL
                    THEN CONCAT('name-dob:', LOWER(TRIM(v.driver_name)), '|', v.date_of_birth)
                ELSE CONCAT('name:', LOWER(TRIM(v.driver_name)))
            END AS violator_key
        FROM violations v
        WHERE LOWER(v.status) <> 'cancelled'
          AND " . $overviewConditionFor('v.violation_date') . "
    ) ranked
    GROUP BY ranked.violator_key
    ORDER BY record_count DESC, total_penalties DESC, last_recorded DESC
    LIMIT 5
");

$dailyTrafficSql = "
    SELECT
        recorded_date,
        COALESCE(SUM(inbound_total), 0) AS inbound_total,
        COALESCE(SUM(outbound_total), 0) AS outbound_total,
        COALESCE(SUM(inbound_total + outbound_total), 0) AS vehicle_total
    FROM (
        SELECT
            DATE(recorded_at) AS recorded_date,
            camera_id,
            MAX(inbound_count) AS inbound_total,
            MAX(outbound_count) AS outbound_total
        FROM camera_monitoring_logs
        GROUP BY DATE(recorded_at), camera_id
    ) daily_camera
    GROUP BY recorded_date
";

$trafficPeriodSummary = fetch_one("
    SELECT
        COALESCE(SUM(vehicle_total), 0) AS vehicle_total,
        COALESCE(SUM(inbound_total), 0) AS inbound_total,
        COALESCE(SUM(outbound_total), 0) AS outbound_total,
        COALESCE(AVG(vehicle_total), 0) AS daily_average,
        COUNT(*) AS recorded_days
    FROM ({$dailyTrafficSql}) traffic_days
    WHERE " . $overviewConditionFor('recorded_date') . "
") ?: [];

$trafficBusiest = fetch_one("
    SELECT recorded_date, vehicle_total
    FROM ({$dailyTrafficSql}) traffic_days
    WHERE " . $overviewConditionFor('recorded_date') . "
    ORDER BY vehicle_total DESC, recorded_date DESC
    LIMIT 1
") ?: [];

$periodViolationSummary = fetch_one("
    SELECT
        COUNT(*) AS violation_total,
        COALESCE(SUM(penalty_amount), 0) AS assessed_amount,
        COALESCE(SUM(CASE WHEN LOWER(status) IN ('pending', 'unpaid', 'overdue') THEN penalty_amount ELSE 0 END), 0) AS pending_amount
    FROM violations
    WHERE LOWER(status) <> 'cancelled'
      AND " . $overviewConditionFor('violation_date') . "
") ?: [];

$previousViolationTotal = $overviewPeriod === 'all' ? 0 : (int)scalar("
    SELECT COUNT(*)
    FROM violations
    WHERE LOWER(status) <> 'cancelled'
      AND " . $previousConditionFor('violation_date') . "
", 0);

$periodCollectedAmount = (float)scalar("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM payments
    WHERE LOWER(payment_status) = 'completed'
      AND " . $overviewConditionFor('DATE(payment_date)') . "
", 0);

$periodTopViolation = fetch_one("
    SELECT violation_type AS label, COUNT(*) AS total
    FROM violations
    WHERE LOWER(status) <> 'cancelled'
      AND " . $overviewConditionFor('violation_date') . "
    GROUP BY violation_type
    ORDER BY total DESC, violation_type ASC
    LIMIT 1
") ?: [];

$periodTopLocation = fetch_one("
    SELECT violation_location AS label, COUNT(*) AS total
    FROM violations
    WHERE LOWER(status) <> 'cancelled'
      AND " . $overviewConditionFor('violation_date') . "
    GROUP BY violation_location
    ORDER BY total DESC, violation_location ASC
    LIMIT 1
") ?: [];

$periodPeak = fetch_one("
    SELECT DAYNAME(violation_date) AS peak_day, HOUR(violation_time) AS peak_hour, COUNT(*) AS total
    FROM violations
    WHERE LOWER(status) <> 'cancelled'
      AND " . $overviewConditionFor('violation_date') . "
    GROUP BY DAYNAME(violation_date), HOUR(violation_time)
    ORDER BY total DESC, peak_hour ASC
    LIMIT 1
") ?: [];

$trendLabels = [];
$trendData = [];
$collectionTrendData = [];
$violationTrendRows = [];
$collectionTrendRows = [];

if ($chartPeriod === 'week') {
    for ($offset = 0; $offset < 7; $offset++) {
        $day = $selectedChartWeekStart->modify("+{$offset} days");
        $trendLabels[$offset] = $day->format('D j');
        $trendData[$offset] = 0;
        $collectionTrendData[$offset] = 0.0;
    }
    $violationTrendRows = fetch_all("
        SELECT DATEDIFF(violation_date, '{$chartWeekStartValue}') AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $chartConditionFor('violation_date') . "
        GROUP BY DATEDIFF(violation_date, '{$chartWeekStartValue}')
    ");
    $collectionTrendRows = fetch_all("
        SELECT DATEDIFF(DATE(payment_date), '{$chartWeekStartValue}') AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $chartConditionFor('DATE(payment_date)') . "
        GROUP BY DATEDIFF(DATE(payment_date), '{$chartWeekStartValue}')
    ");
} elseif ($chartPeriod === 'month') {
    $daysInMonth = (int)$selectedChartMonth->format('t');
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $trendLabels[$day] = (string)$day;
        $trendData[$day] = 0;
        $collectionTrendData[$day] = 0.0;
    }
    $violationTrendRows = fetch_all("
        SELECT DAY(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $chartConditionFor('violation_date') . "
        GROUP BY DAY(violation_date)
    ");
    $collectionTrendRows = fetch_all("
        SELECT DAY(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $chartConditionFor('DATE(payment_date)') . "
        GROUP BY DAY(payment_date)
    ");
} elseif ($chartPeriod === 'year') {
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    foreach ($monthNames as $index => $monthName) {
        $bucket = $index + 1;
        $trendLabels[$bucket] = $monthName;
        $trendData[$bucket] = 0;
        $collectionTrendData[$bucket] = 0.0;
    }
    $violationTrendRows = fetch_all("
        SELECT MONTH(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $chartConditionFor('violation_date') . "
        GROUP BY MONTH(violation_date)
    ");
    $collectionTrendRows = fetch_all("
        SELECT MONTH(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $chartConditionFor('DATE(payment_date)') . "
        GROUP BY MONTH(payment_date)
    ");
} else {
    $violationTrendRows = fetch_all("
        SELECT YEAR(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled'
        GROUP BY YEAR(violation_date)
    ");
    $collectionTrendRows = fetch_all("
        SELECT YEAR(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed'
        GROUP BY YEAR(payment_date)
    ");
    $availableYears = array_merge(
        array_map(static fn(array $row): int => (int)$row['bucket'], $violationTrendRows),
        array_map(static fn(array $row): int => (int)$row['bucket'], $collectionTrendRows)
    );
    if (!$availableYears) $availableYears[] = (int)date('Y');
    for ($year = min($availableYears); $year <= max($availableYears); $year++) {
        $trendLabels[$year] = (string)$year;
        $trendData[$year] = 0;
        $collectionTrendData[$year] = 0.0;
    }
}

foreach ($violationTrendRows as $row) {
    $bucket = (int)($row['bucket'] ?? 0);
    if (array_key_exists($bucket, $trendData)) $trendData[$bucket] = (int)$row['total'];
}
foreach ($collectionTrendRows as $row) {
    $bucket = (int)($row['bucket'] ?? 0);
    if (array_key_exists($bucket, $collectionTrendData)) $collectionTrendData[$bucket] = (float)$row['total'];
}

$trendLabels = array_values($trendLabels);
$trendData = array_values($trendData);
$monthlyCollectionData = array_values($collectionTrendData);
$trendPeriodDescription = $chartPeriod === 'week'
    ? 'Daily records · Week of ' . $chartLabel
    : ($chartPeriod === 'month'
        ? 'Daily records · ' . $chartLabel
        : ($chartPeriod === 'year' ? 'Monthly records · ' . $chartLabel : 'Yearly records · Overall'));

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

$trafficPeriodTotal = (int)($trafficPeriodSummary['vehicle_total'] ?? 0);
$trafficPeriodInbound = (int)($trafficPeriodSummary['inbound_total'] ?? 0);
$trafficPeriodOutbound = (int)($trafficPeriodSummary['outbound_total'] ?? 0);
$trafficDailyAverage = (float)($trafficPeriodSummary['daily_average'] ?? 0);
$trafficRecordedDays = (int)($trafficPeriodSummary['recorded_days'] ?? 0);
$trafficBusiestTotal = (int)($trafficBusiest['vehicle_total'] ?? 0);
$trafficBusiestDate = trim((string)($trafficBusiest['recorded_date'] ?? ''));
$trafficBusiestDateLabel = $trafficBusiestDate !== '' ? date('M j, Y', strtotime($trafficBusiestDate)) : 'No data';
$trafficActivityLabel = $trafficPeriodTotal <= 0
    ? 'No traffic record'
    : ($trafficDailyAverage >= 100 ? 'High activity' : ($trafficDailyAverage >= 40 ? 'Moderate activity' : 'Light activity'));

$periodViolationTotal = (int)($periodViolationSummary['violation_total'] ?? 0);
$periodAssessedAmount = (float)($periodViolationSummary['assessed_amount'] ?? 0);
$periodPendingAmount = (float)($periodViolationSummary['pending_amount'] ?? 0);
$periodCollectionRate = $periodAssessedAmount > 0
    ? min(100, ($periodCollectedAmount / $periodAssessedAmount) * 100)
    : 0.0;
$periodViolationChange = ($overviewPeriod !== 'all' && $previousViolationTotal > 0)
    ? (($periodViolationTotal - $previousViolationTotal) / $previousViolationTotal) * 100
    : 0.0;
$periodChangeCopy = $overviewPeriod === 'all'
    ? 'All-time total'
    : ($previousViolationTotal > 0
        ? sprintf('%+.1f%% vs previous period', $periodViolationChange)
        : 'No previous-period baseline');

if ($periodViolationTotal === 0) {
    $periodStatusLabel = 'No records';
    $periodStatusClass = 'monthly-state-good';
    $periodStatusBadgeClass = 'text-bg-secondary';
    $periodStatusIcon = 'bi-check-circle';
} elseif ($periodViolationChange >= 25 || $periodCollectionRate < 50) {
    $periodStatusLabel = 'Needs attention';
    $periodStatusClass = 'monthly-state-critical';
    $periodStatusBadgeClass = 'text-bg-danger';
    $periodStatusIcon = 'bi-exclamation-triangle';
} elseif ($periodViolationChange > 0 || $periodCollectionRate < 75) {
    $periodStatusLabel = 'Monitor closely';
    $periodStatusClass = 'monthly-state-warning';
    $periodStatusBadgeClass = 'text-bg-warning';
    $periodStatusIcon = 'bi-eye';
} else {
    $periodStatusLabel = 'Stable';
    $periodStatusClass = 'monthly-state-good';
    $periodStatusBadgeClass = 'text-bg-success';
    $periodStatusIcon = 'bi-check-circle';
}

$periodTopViolationLabel = trim((string)($periodTopViolation['label'] ?? '')) ?: 'No data';
$periodTopLocationLabel = trim((string)($periodTopLocation['label'] ?? '')) ?: 'No data';
$periodPeakDay = trim((string)($periodPeak['peak_day'] ?? '')) ?: 'No data';
$periodPeakHour = isset($periodPeak['peak_hour'])
    ? date('g A', mktime((int)$periodPeak['peak_hour'], 0))
    : '';
$periodPeakLabel = $periodPeakHour !== '' ? $periodPeakDay . ' · ' . $periodPeakHour : $periodPeakDay;
$periodNarrative = $periodViolationTotal > 0
    ? "{$overviewLabel} recorded {$periodViolationTotal} non-cancelled violation(s). {$periodTopViolationLabel} was the most common offense, led by {$periodTopLocationLabel}."
    : "No non-cancelled violations were recorded for {$overviewLabel}.";

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
#dashboardPeriodValueField { min-width: 165px; }
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
.chart-filter-panel {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid rgba(16, 47, 73, .13);
  border-radius: 14px;
  background: linear-gradient(135deg, rgba(255,255,255,.96), rgba(235,248,246,.88));
  box-shadow: 0 8px 20px rgba(16, 47, 73, .055);
}
.chart-filter-copy { min-width: 0; }
.chart-filter-copy strong { display: block; color: #102f49; font-size: .84rem; }
.chart-filter-copy small { display: block; margin-top: 2px; color: #667a74; font-size: .68rem; }
.chart-filter-panel.is-loading { opacity: .72; pointer-events: none; }
.chart-filter-error { display: block; margin-top: 4px; color: #b42318 !important; font-size: .65rem !important; }
.chart-filter-error:empty { display: none; }
.chart-filter-form { display: flex; align-items: flex-end; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
.chart-scope-switch {
  display: grid;
  grid-template-columns: repeat(4, minmax(92px, 1fr));
  gap: 4px;
  padding: 4px;
  border: 1px solid rgba(16, 47, 73, .13);
  border-radius: 12px;
  background: rgba(255,255,255,.82);
  box-shadow: inset 0 1px 3px rgba(16,47,73,.05);
}
.chart-scope-option {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 12px;
  border: 0;
  border-radius: 9px;
  color: #60736d;
  background: transparent;
  font-size: .72rem;
  font-weight: 700;
  white-space: nowrap;
  transition: background .16s ease, color .16s ease, box-shadow .16s ease;
}
.chart-scope-option:hover { color: #087d78; background: rgba(8,125,120,.07); }
.chart-scope-option.active {
  color: #fff;
  background: linear-gradient(135deg,#087d78,#16a37f);
  box-shadow: 0 6px 14px rgba(8,125,120,.2);
}
.chart-period-picker {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}
.chart-period-picker[hidden] { display: none !important; }
.chart-period-control { min-width: 155px; }
.chart-period-control label {
  display: block;
  margin-bottom: 4px;
  color: #526b64;
  font-size: .62rem;
  font-weight: 800;
  letter-spacing: .045em;
  text-transform: uppercase;
}
.chart-period-control .form-control { min-height: 44px; border-radius: 10px; font-size: .78rem; }
.top-violators-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 10px;
}
.top-violator-entry {
  position: relative;
  display: grid;
  min-width: 0;
  gap: 12px;
  padding: 15px;
  overflow: hidden;
  border: 1px solid rgba(16, 47, 73, .13);
  border-radius: 14px;
  background: linear-gradient(145deg, #fff, #f5faf9);
  color: #102f49;
  text-decoration: none;
  transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}
.top-violator-entry:hover {
  transform: translateY(-2px);
  border-color: rgba(8, 125, 120, .38);
  color: #102f49;
  box-shadow: 0 12px 24px rgba(16, 47, 73, .1);
}
.top-violator-entry-head { display: flex; align-items: center; gap: 10px; min-width: 0; }
.top-violator-rank {
  display: grid;
  place-items: center;
  flex: 0 0 34px;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #dff4f1;
  color: #087d78;
  font-size: .76rem;
  font-weight: 900;
}
.top-violator-entry:nth-child(1) .top-violator-rank { background: #fff0bd; color: #8a6410; }
.top-violator-entry:nth-child(2) .top-violator-rank { background: #e7edf1; color: #53656f; }
.top-violator-entry:nth-child(3) .top-violator-rank { background: #f5dfcf; color: #92572f; }

/* Compact Bootstrap-style context tooltip for hover and keyboard focus. */
.dashboard-info-card {
  cursor: help;
}
.dashboard-info-card:is(a, button) { cursor: pointer; }
.dashboard-info-card:focus-visible {
  outline: 3px solid rgba(8, 125, 120, .28);
  outline-offset: 3px;
}
.dashboard-card-tooltip {
  --bs-tooltip-max-width: 250px;
  --bs-tooltip-bg: #102f49;
  --bs-tooltip-color: #fff;
  --bs-tooltip-opacity: .98;
  --bs-tooltip-padding-x: .75rem;
  --bs-tooltip-padding-y: .55rem;
}
.dashboard-card-tooltip .tooltip-inner {
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 8px;
  box-shadow: 0 8px 22px rgba(16,47,73,.24);
  font-size: .75rem;
  font-weight: 500;
  line-height: 1.45;
  text-align: left;
}
.top-violator-person { min-width: 0; }
.top-violator-person strong { display: block; overflow: hidden; color: #102f49; font-size: .82rem; font-weight: 800; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.top-violator-person small { display: block; overflow: hidden; margin-top: 2px; color: #50665f; font-size: .68rem; font-weight: 600; line-height: 1.35; text-overflow: ellipsis; white-space: nowrap; }
.top-violator-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }
.top-violator-metric { padding: 8px 9px; border: 1px solid rgba(8, 125, 120, .09); border-radius: 9px; background: #edf7f5; }
.top-violator-metric:last-child { grid-column: 1 / -1; }
body.municipal-portal .top-violator-person strong { color: #102f49 !important; }
body.municipal-portal .top-violator-person small { color: #50665f !important; }
body.municipal-portal .top-violator-metric > span { display: block; color: #4f6760 !important; font-size: .62rem; font-weight: 800; letter-spacing: .045em; text-transform: uppercase; }
body.municipal-portal .top-violator-metric > strong { display: block; margin-top: 2px; color: #102f49 !important; font-size: .82rem; font-weight: 800; }
@media (max-width: 1199.98px) { .top-violators-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 767.98px) {
  .dashboard-filter-row,
  .dashboard-filter { width: 100%; }
  .section-head { align-items: flex-start !important; }
  .dashboard-overview-heading { align-items: stretch; flex-direction: column; }
  .overview-filter-form { justify-content: stretch; }
  .overview-filter-form .dashboard-filter { flex: 1 1 145px; width: auto; }
  .chart-filter-panel { align-items: stretch; flex-direction: column; }
  .chart-filter-form { justify-content: stretch; }
  .chart-scope-switch { width: 100%; grid-template-columns: repeat(2,minmax(0,1fr)); }
  .chart-period-picker { width: 100%; }
  .chart-period-control { flex: 1 1 auto; min-width: 0; }
  .top-violators-grid { grid-template-columns: 1fr; }
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
  <form class="overview-filter-form" id="dashboardOverviewFilterForm" method="get" action="">
    <div class="dashboard-filter">
      <label for="dashboardScope">Record period</label>
      <select class="form-select" id="dashboardScope" name="dashboard_scope">
        <option value="all" <?= $overviewPeriod === 'all' ? 'selected' : '' ?>>All Records</option>
        <option value="day" <?= $overviewPeriod === 'day' ? 'selected' : '' ?>>Day</option>
        <option value="month" <?= $overviewPeriod === 'month' ? 'selected' : '' ?>>Month</option>
        <option value="year" <?= $overviewPeriod === 'year' ? 'selected' : '' ?>>Year</option>
      </select>
    </div>
    <div class="dashboard-filter" id="dashboardPeriodValueField" <?= $overviewPeriod === 'all' ? 'hidden' : '' ?>>
      <label for="dashboardPeriodValue" id="dashboardPeriodValueLabel"><?= esc($overviewInputLabel ?: 'Selected period') ?></label>
      <input
        class="form-control"
        type="<?= esc($overviewInputType ?: 'date') ?>"
        id="dashboardPeriodValue"
        name="period_value"
        value="<?= esc($overviewValue) ?>"
        <?= $overviewPeriod === 'year' ? 'min="2000" max="' . esc((string)((int)date('Y') + 1)) . '" step="1"' : '' ?>
        <?= $overviewPeriod === 'day' ? 'max="' . esc(date('Y-m-d')) . '"' : '' ?>
        <?= $overviewPeriod === 'month' ? 'max="' . esc(date('Y-m')) . '"' : '' ?>
      >
    </div>
    <input type="hidden" name="chart_scope" value="<?= esc($chartPeriod) ?>">
    <?php if (in_array($chartPeriod, ['month', 'year'], true)): ?><input type="hidden" name="chart_period_value" value="<?= esc($chartValue) ?>"><?php endif; ?>
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
  <div class="section-head"><div><span class="dashboard-eyebrow">HISTORICAL TRAFFIC</span><h5 class="mb-1">Vehicle Count — <?= esc($overviewLabel) ?></h5><small class="text-muted">Traffic volume follows the global dashboard period.</small></div><span class="overview-period-badge"><i class="bi bi-calendar3"></i><?= esc($overviewLabel) ?></span></div>
  <div class="dated-traffic-grid">
    <div class="dated-traffic-main"><small><?= esc($overviewLabel) ?></small><strong><?= num($trafficPeriodTotal) ?></strong><span><?= num($trafficPeriodInbound) ?> inbound · <?= num($trafficPeriodOutbound) ?> outbound</span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-bar-chart-line"></i></span><small>Daily average</small><strong><?= num(round($trafficDailyAverage)) ?></strong><span>Across <?= num($trafficRecordedDays) ?> recorded day<?= $trafficRecordedDays === 1 ? '' : 's' ?></span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-trophy"></i></span><small>Busiest recorded date</small><strong><?= esc($trafficBusiestDateLabel) ?></strong><span><?= $trafficBusiestDate !== '' ? num($trafficBusiestTotal) . ' vehicles recorded' : 'No recorded data' ?></span></div>
    <div class="dated-traffic-metric"><span class="dated-traffic-icon"><i class="bi bi-lightbulb"></i></span><small>Interpretation</small><strong><?= esc($trafficActivityLabel) ?></strong><span><?= $trafficPeriodTotal > 0 ? 'Based on saved monitoring data in the selected period.' : 'No monitoring data was saved in this period.' ?></span></div>
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
<div class="section-card mb-4 <?= esc($periodStatusClass) ?>" id="monthlyExecutiveSummary">
  <div class="section-head">
    <div>
      <span class="dashboard-eyebrow">PERIOD EXECUTIVE SUMMARY</span>
      <h5 class="mb-1"><?= esc($overviewLabel) ?></h5>
      <small class="text-muted">Performance, collections, and enforcement patterns for the global dashboard period</small>
    </div>
    <span class="tag <?= esc($periodStatusBadgeClass) ?>"><i class="bi <?= esc($periodStatusIcon) ?> me-1 monthly-state-icon"></i><?= esc(strtoupper($periodStatusLabel)) ?></span>
  </div>
  <div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-violations h-100"><div class="stat-label"><i class="bi bi-graph-up-arrow me-1 text-primary"></i>Period Violations</div><div class="stat-value"><?= num($periodViolationTotal) ?></div><small class="<?= $periodViolationChange > 0 ? 'text-danger' : ($periodViolationChange < 0 ? 'text-success' : 'text-muted') ?>"><?= esc($periodChangeCopy) ?></small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-collected h-100"><div class="stat-label"><i class="bi bi-cash-stack me-1 text-success"></i>Collected</div><div class="stat-value"><?= short_money($periodCollectedAmount) ?></div><small class="text-muted"><?= number_format($periodCollectionRate, 1) ?>% collection rate · <?= short_money($periodPendingAmount) ?> pending</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-offense h-100"><div class="stat-label"><i class="bi bi-cone-striped me-1 text-warning"></i>Leading Violation</div><div class="h5 mt-2 mb-1"><?= esc($periodTopViolationLabel) ?></div><small class="text-muted"><?= num($periodTopViolation['total'] ?? 0) ?> recorded cases</small></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card monthly-kpi monthly-kpi-location h-100"><div class="stat-label"><i class="bi bi-geo-alt-fill me-1 text-info"></i>Highest Activity Area</div><div class="h5 mt-2 mb-1"><?= esc($periodTopLocationLabel) ?></div><small class="text-muted">Peak: <?= esc($periodPeakLabel) ?></small></div></div>
  </div>
  <div class="alert alert-light border mt-3 mb-0" role="note">
    <strong><i class="bi bi-lightbulb me-1"></i>Management interpretation:</strong>
    <span><?= esc($periodNarrative) ?></span>
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

<div class="dashboard-section-heading"><span>Enforcement insights</span></div>
<div class="section-card mb-4">
  <div class="section-head">
    <div>
      <h6>Top 5 Violators</h6>
      <small class="text-muted">Ranked within <?= esc($overviewLabel) ?> by non-cancelled records, then assessed fines.</small>
    </div>
    <a href="<?= esc(app_url('violations.php')) ?>" class="small fw-semibold text-decoration-none">Open violations</a>
  </div>

  <?php if (!$topViolators): ?>
    <?php empty_state('No active violation records are available for ranking.'); ?>
  <?php else: ?>
    <div class="top-violators-grid">
      <?php foreach ($topViolators as $rankIndex => $topViolator): ?>
        <?php
          $topName = trim((string)($topViolator['driver_name'] ?? '')) ?: 'Unknown driver';
          $topLicense = trim((string)($topViolator['license_number'] ?? ''));
        ?>
        <a class="top-violator-entry" href="<?= esc(app_url('violations.php?search=' . rawurlencode($topName))) ?>">
          <div class="top-violator-entry-head">
            <span class="top-violator-rank">#<?= $rankIndex + 1 ?></span>
            <span class="top-violator-person">
              <strong><?= esc($topName) ?></strong>
              <small><?= esc($topLicense !== '' ? $topLicense : 'No license recorded') ?></small>
            </span>
          </div>
          <span class="top-violator-metrics">
            <span class="top-violator-metric"><span>Records</span><strong><?= num($topViolator['record_count']) ?></strong></span>
            <span class="top-violator-metric"><span>Unpaid</span><strong><?= num($topViolator['unpaid_count']) ?></strong></span>
            <span class="top-violator-metric"><span>Total fines</span><strong><?= peso($topViolator['total_penalties']) ?></strong></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Treasurer-style violation and collection charts -->
<div class="dashboard-section-heading"><span>Performance trends</span></div>
<div class="chart-filter-panel mb-3" id="chartFilterPanel">
  <div class="chart-filter-copy">
    <strong><i class="bi bi-bar-chart-line me-1"></i>Graph timeline</strong>
    <small>Choose a timeline for both charts.</small>
    <small class="chart-filter-error" id="chartFilterError" role="alert"></small>
  </div>
  <form class="chart-filter-form" id="chartFilterForm" method="get" action="">
    <input type="hidden" name="dashboard_scope" value="<?= esc($overviewPeriod) ?>">
    <?php if ($overviewPeriod !== 'all'): ?><input type="hidden" name="period_value" value="<?= esc($overviewValue) ?>"><?php endif; ?>
    <input type="hidden" id="chartScopeValue" name="chart_scope" value="<?= esc($chartPeriod) ?>">
    <div class="chart-scope-switch" role="group" aria-label="Graph period">
      <button class="chart-scope-option <?= $chartPeriod === 'all' ? 'active' : '' ?>" type="button" data-chart-scope="all" aria-pressed="<?= $chartPeriod === 'all' ? 'true' : 'false' ?>"><i class="bi bi-infinity"></i>Overall</button>
      <button class="chart-scope-option <?= $chartPeriod === 'week' ? 'active' : '' ?>" type="button" data-chart-scope="week" aria-pressed="<?= $chartPeriod === 'week' ? 'true' : 'false' ?>"><i class="bi bi-calendar-week"></i>Week</button>
      <button class="chart-scope-option <?= $chartPeriod === 'month' ? 'active' : '' ?>" type="button" data-chart-scope="month" aria-pressed="<?= $chartPeriod === 'month' ? 'true' : 'false' ?>"><i class="bi bi-calendar3"></i>Month</button>
      <button class="chart-scope-option <?= $chartPeriod === 'year' ? 'active' : '' ?>" type="button" data-chart-scope="year" aria-pressed="<?= $chartPeriod === 'year' ? 'true' : 'false' ?>"><i class="bi bi-calendar-range"></i>Year</button>
    </div>
    <div class="chart-period-picker" id="chartPeriodPicker" <?= in_array($chartPeriod, ['month', 'year'], true) ? '' : 'hidden' ?>>
      <div class="chart-period-control">
        <label for="chartPeriodValue" id="chartPeriodLabel"><?= esc($chartPickerLabel) ?></label>
        <input
          class="form-control"
          id="chartPeriodValue"
          name="chart_period_value"
          type="<?= esc($chartPickerType) ?>"
          value="<?= esc($chartValue) ?>"
          <?= $chartPeriod === 'month' ? 'max="' . esc($chartToday->format('Y-m')) . '"' : '' ?>
          <?= $chartPeriod === 'year' ? 'min="2000" max="' . esc($chartToday->format('Y')) . '" step="1"' : '' ?>
          <?= in_array($chartPeriod, ['month', 'year'], true) ? 'required' : 'disabled' ?>
        >
      </div>
    </div>
  </form>
</div>
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Violation Records</h6>
          <small class="text-muted" id="violationTrendDescription"><?= esc($trendPeriodDescription) ?></small>
        </div>
      </div>
      <div style="height:240px"><canvas id="trendChart"></canvas></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Collection Records</h6>
          <small class="text-muted" id="collectionTrendDescription"><?= esc($trendPeriodDescription) ?> · Completed payments</small>
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
// Give every compact dashboard card a useful explanation on hover and focus.
(function addDashboardCardDetails() {
  const cards = document.querySelectorAll([
    '.dashboard-kpi',
    '.dated-traffic-main',
    '.dated-traffic-metric',
    '.monthly-kpi',
    '.ai-preview-item',
    '.compact-metric',
    '#currentMonitoringCard .mini-metric',
    '.top-violator-entry'
  ].join(','));

  const descriptions = [
    ['dashboard-kpi-vehicles', 'Total vehicles recorded for the selected period, split into inbound and outbound traffic.'],
    ['dashboard-kpi-violations', 'All non-cancelled violations in the selected period, including their paid and unpaid status.'],
    ['dashboard-kpi-collections', 'Payments successfully collected during the selected dashboard period.'],
    ['dashboard-kpi-alerts', 'Alerts that remain active, plus alerts generated within the selected period.'],
    ['monthly-kpi-violations', 'Compares the selected period\'s violation count with the immediately preceding equivalent period.'],
    ['monthly-kpi-collected', 'Shows collected revenue, collection rate, and the amount still pending.'],
    ['monthly-kpi-offense', 'The violation type recorded most often during the selected period.'],
    ['monthly-kpi-location', 'The area with the highest recorded enforcement or traffic activity.'],
    ['ai-preview-risk', 'The AI model\'s forecast risk level for the upcoming reporting period.'],
    ['ai-preview-confidence-card', 'How certain the AI model is about its current forecast.'],
    ['ai-preview-location', 'The location the forecast identifies as the highest operational priority.'],
    ['ai-preview-action', 'The recommended next action based on the current forecast and hotspot data.']
  ];

  cards.forEach((card) => {
    let detail = card.dataset.hoverInfo || '';
    if (!detail) {
      const match = descriptions.find(([className]) => card.classList.contains(className));
      detail = match ? match[1] : '';
    }
    if (!detail && card.classList.contains('dated-traffic-main')) {
      detail = 'Total saved vehicle observations and their direction for the selected dashboard period.';
    }
    if (!detail && card.classList.contains('dated-traffic-metric')) {
      const label = card.querySelector('small')?.textContent.trim().toLowerCase() || 'traffic metric';
      detail = `Explains the ${label} calculated from saved monitoring data in the selected period.`;
    }
    if (!detail && card.classList.contains('compact-metric')) {
      const label = card.querySelector('small')?.textContent.trim() || 'operational metric';
      detail = `Current dashboard summary for ${label.toLowerCase()}.`;
    }
    if (!detail && card.classList.contains('mini-metric')) {
      const label = card.querySelector('small')?.textContent.trim() || 'live metric';
      detail = `Latest live Computer Vision reading for ${label.toLowerCase()}.`;
    }
    if (!detail && card.classList.contains('top-violator-entry')) {
      detail = 'Open this person\'s violation records and enforcement history.';
    }
    if (!detail) return;

    card.classList.add('dashboard-info-card');
    card.setAttribute('title', detail);
    card.setAttribute('data-bs-toggle', 'tooltip');
    card.setAttribute('data-bs-placement', 'top');
    card.setAttribute('data-bs-custom-class', 'dashboard-card-tooltip');
    if (!card.matches('a, button, [tabindex]')) card.tabIndex = 0;
  });

  document.addEventListener('DOMContentLoaded', () => {
    if (!window.bootstrap?.Tooltip) return;
    document.querySelectorAll('[data-bs-toggle="tooltip"].dashboard-info-card').forEach((card) => {
      bootstrap.Tooltip.getOrCreateInstance(card, {
        container: 'body',
        boundary: 'viewport',
        offset: [0, 10],
        trigger: 'hover focus',
        delay: { show: 220, hide: 80 }
      });
    });
  });
})();

const dashboardScope = document.getElementById('dashboardScope');
const dashboardOverviewFilterForm = document.getElementById('dashboardOverviewFilterForm');
const dashboardPeriodField = document.getElementById('dashboardPeriodValueField');
const dashboardPeriodInput = document.getElementById('dashboardPeriodValue');
const dashboardPeriodLabel = document.getElementById('dashboardPeriodValueLabel');
const dashboardToday = <?= json_encode(date('Y-m-d')) ?>;
const dashboardCurrentMonth = <?= json_encode(date('Y-m')) ?>;
const dashboardCurrentYear = <?= json_encode(date('Y')) ?>;
let dashboardFilterTimer = null;

function syncDashboardPeriodInput(resetValue = false) {
  if (!dashboardScope || !dashboardPeriodField || !dashboardPeriodInput || !dashboardPeriodLabel) return;
  const scope = dashboardScope.value;
  dashboardPeriodField.hidden = scope === 'all';
  dashboardPeriodInput.disabled = scope === 'all';
  dashboardPeriodInput.required = scope !== 'all';
  dashboardPeriodInput.removeAttribute('min');
  dashboardPeriodInput.removeAttribute('max');
  dashboardPeriodInput.removeAttribute('step');

  if (scope === 'day') {
    dashboardPeriodLabel.textContent = 'Selected day';
    dashboardPeriodInput.type = 'date';
    dashboardPeriodInput.max = dashboardToday;
    if (resetValue || !/^\d{4}-\d{2}-\d{2}$/.test(dashboardPeriodInput.value)) dashboardPeriodInput.value = dashboardToday;
  } else if (scope === 'month') {
    dashboardPeriodLabel.textContent = 'Selected month';
    dashboardPeriodInput.type = 'month';
    dashboardPeriodInput.max = dashboardCurrentMonth;
    if (resetValue || !/^\d{4}-\d{2}$/.test(dashboardPeriodInput.value)) dashboardPeriodInput.value = dashboardCurrentMonth;
  } else if (scope === 'year') {
    dashboardPeriodLabel.textContent = 'Selected year';
    dashboardPeriodInput.type = 'number';
    dashboardPeriodInput.min = '2000';
    dashboardPeriodInput.max = String(Number(dashboardCurrentYear) + 1);
    dashboardPeriodInput.step = '1';
    if (resetValue || !/^\d{4}$/.test(dashboardPeriodInput.value)) dashboardPeriodInput.value = dashboardCurrentYear;
  }
}

function scheduleDashboardFilter() {
  if (!dashboardOverviewFilterForm || !dashboardScope || !dashboardPeriodInput) return;

  const scope = dashboardScope.value;
  const value = dashboardPeriodInput.value;
  const validFormat = scope === 'all'
    || (scope === 'day' && /^\d{4}-\d{2}-\d{2}$/.test(value))
    || (scope === 'month' && /^\d{4}-\d{2}$/.test(value))
    || (scope === 'year' && /^\d{4}$/.test(value));

  if (!validFormat || !dashboardOverviewFilterForm.reportValidity()) return;

  window.clearTimeout(dashboardFilterTimer);
  dashboardFilterTimer = window.setTimeout(() => {
    dashboardOverviewFilterForm.requestSubmit();
  }, 250);
}

dashboardScope?.addEventListener('change', () => {
  syncDashboardPeriodInput(true);
  scheduleDashboardFilter();
});
dashboardPeriodInput?.addEventListener('input', scheduleDashboardFilter);
dashboardPeriodInput?.addEventListener('change', scheduleDashboardFilter);
syncDashboardPeriodInput(false);

const chartFilterForm = document.getElementById('chartFilterForm');
const chartScopeValue = document.getElementById('chartScopeValue');
const chartScopeOptions = document.querySelectorAll('[data-chart-scope]');
const chartPeriodPicker = document.getElementById('chartPeriodPicker');
const chartPeriodInput = document.getElementById('chartPeriodValue');
const chartPeriodLabel = document.getElementById('chartPeriodLabel');
const chartFilterPanel = document.getElementById('chartFilterPanel');
const chartFilterError = document.getElementById('chartFilterError');
const violationTrendDescription = document.getElementById('violationTrendDescription');
const collectionTrendDescription = document.getElementById('collectionTrendDescription');
let violationChart = null;
let collectionChart = null;
let chartPeriodRefreshTimer = null;

function selectChartScope(scope, submitImmediate = false) {
  if (!chartFilterForm || !chartScopeValue || !chartPeriodPicker || !chartPeriodInput || !chartPeriodLabel) return;

  const previousScope = chartScopeValue.value;
  chartScopeValue.value = scope;
  chartScopeOptions.forEach((button) => {
    const isActive = button.dataset.chartScope === scope;
    button.classList.toggle('active', isActive);
    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
  });

  const needsPicker = scope === 'month' || scope === 'year';
  chartPeriodPicker.hidden = !needsPicker;
  chartPeriodInput.disabled = !needsPicker;
  chartPeriodInput.required = needsPicker;
  chartPeriodInput.removeAttribute('min');
  chartPeriodInput.removeAttribute('max');
  chartPeriodInput.removeAttribute('step');

  if (scope === 'month') {
    chartPeriodLabel.textContent = 'Choose month';
    chartPeriodInput.type = 'month';
    chartPeriodInput.max = dashboardCurrentMonth;
    if (previousScope !== 'month' || !/^\d{4}-\d{2}$/.test(chartPeriodInput.value)) {
      chartPeriodInput.value = dashboardCurrentMonth;
    }
    window.setTimeout(() => chartPeriodInput.focus(), 0);
  } else if (scope === 'year') {
    chartPeriodLabel.textContent = 'Choose year';
    chartPeriodInput.type = 'number';
    chartPeriodInput.min = '2000';
    chartPeriodInput.max = dashboardCurrentYear;
    chartPeriodInput.step = '1';
    if (previousScope !== 'year' || !/^\d{4}$/.test(chartPeriodInput.value)) {
      chartPeriodInput.value = dashboardCurrentYear;
    }
    window.setTimeout(() => chartPeriodInput.focus(), 0);
  }

  if (submitImmediate && (!needsPicker || chartPeriodInput.checkValidity())) {
    chartFilterForm.requestSubmit();
  }
}

chartScopeOptions.forEach((button) => {
  button.addEventListener('click', () => {
    const scope = button.dataset.chartScope || 'all';
    selectChartScope(scope, true);
  });
});

function scheduleChartPeriodRefresh() {
  if (!chartFilterForm || !chartPeriodInput || chartPeriodInput.disabled) return;

  const scope = chartScopeValue?.value || '';
  const hasValidFormat = scope === 'month'
    ? /^\d{4}-\d{2}$/.test(chartPeriodInput.value)
    : /^\d{4}$/.test(chartPeriodInput.value);

  if (!hasValidFormat || !chartPeriodInput.checkValidity()) return;

  window.clearTimeout(chartPeriodRefreshTimer);
  chartPeriodRefreshTimer = window.setTimeout(() => {
    chartFilterForm.requestSubmit();
  }, 250);
}

chartPeriodInput?.addEventListener('input', scheduleChartPeriodRefresh);
chartPeriodInput?.addEventListener('change', scheduleChartPeriodRefresh);

async function refreshDashboardCharts() {
  if (!chartFilterForm || !chartScopeValue || !violationChart || !collectionChart) return;

  chartFilterError.textContent = '';
  chartFilterPanel?.classList.add('is-loading');
  chartFilterPanel?.setAttribute('aria-busy', 'true');

  try {
    const parameters = new URLSearchParams();
    parameters.set('chart_scope', chartScopeValue.value);
    if (!chartPeriodInput.disabled && chartPeriodInput.value) {
      parameters.set('chart_period_value', chartPeriodInput.value);
    }

    const response = await fetch(`../api/dashboard_chart_data.php?${parameters.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.success) {
      throw new Error(payload?.message || 'Unable to update the charts.');
    }

    violationChart.data.labels = payload.labels;
    violationChart.data.datasets[0].data = payload.violations;
    collectionChart.data.labels = payload.labels;
    collectionChart.data.datasets[0].data = payload.collections;
    violationChart.update();
    collectionChart.update();

    if (violationTrendDescription) violationTrendDescription.textContent = payload.description;
    if (collectionTrendDescription) collectionTrendDescription.textContent = `${payload.description} · Completed payments`;
    chartScopeValue.value = payload.scope;
    if ((payload.scope === 'month' || payload.scope === 'year') && payload.value) {
      chartPeriodInput.value = payload.value;
    }

    const pageUrl = new URL(window.location.href);
    pageUrl.searchParams.set('chart_scope', payload.scope);
    if (payload.value) pageUrl.searchParams.set('chart_period_value', payload.value);
    else pageUrl.searchParams.delete('chart_period_value');
    window.history.replaceState({}, '', pageUrl);
  } catch (error) {
    chartFilterError.textContent = error instanceof Error ? error.message : 'Unable to update the charts.';
  } finally {
    chartFilterPanel?.classList.remove('is-loading');
    chartFilterPanel?.removeAttribute('aria-busy');
  }
}

chartFilterForm?.addEventListener('submit', (event) => {
  event.preventDefault();
  if (!chartFilterForm.reportValidity()) return;
  refreshDashboardCharts();
});

const MONTHLY_PREDICTION_ENDPOINT = '../api/predict_monthly.php';
const HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const trendLabels = <?= json_encode($trendLabels) ?>;
const trendData = <?= json_encode($trendData) ?>;
const monthlyCollectionData = <?= json_encode($monthlyCollectionData) ?>;
const adminChartGrid = 'rgba(71, 85, 105, .16)';
const adminChartTicks = '#52616b';
const adminChartAxisBorder = 'rgba(71, 85, 105, .28)';

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
      x: {
        grid: { display: false },
        border: { color: adminChartAxisBorder },
        ticks: { color: adminChartTicks, font: { size: 11, weight: '600' }, padding: 7 }
      },
      y: {
        beginAtZero: true,
        grid: { color: adminChartGrid },
        border: { color: adminChartAxisBorder },
        ticks: { precision: 0, color: adminChartTicks, font: { size: 11, weight: '600' }, padding: 7 }
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
      x: {
        grid: { display: false },
        border: { color: adminChartAxisBorder },
        ticks: { color: adminChartTicks, font: { size: 11, weight: '600' }, padding: 7 }
      },
      y: {
        beginAtZero: true,
        grid: { color: adminChartGrid },
        border: { color: adminChartAxisBorder },
        ticks: {
          color: adminChartTicks,
          font: { size: 11, weight: '600' },
          padding: 7,
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

violationChart = new Chart(violationContext, {
  type: 'line',
  data: {
    labels: trendLabels,
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

collectionChart = new Chart(collectionContext, {
  type: 'bar',
  data: {
    labels: trendLabels,
    datasets: [{
      label: 'Collection Records',
      data: monthlyCollectionData,
      backgroundColor: collectionGradient,
      borderRadius: 6,
      borderSkipped: false
    }]
  },
  options: collectionChartOptions()
});
</script>

<?php page_end(false); ?>
