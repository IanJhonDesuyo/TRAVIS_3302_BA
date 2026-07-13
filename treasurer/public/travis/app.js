/* TRAVIS Treasurer Dashboard - vanilla JS */

/* ============ Placeholder Data (replace with PHP REST later) ============ */
const DATA = {
  stats: {
    totalViolations: 1284,
    pendingPayments: 312,
    paidViolations: 972,
    todaysCollections: 48750,
    monthlyCollections: 1245600
  },
  recentPayments: [
    { receipt:'OR-2026-000481', plate:'ABC-1234', violation:'Overspeeding',            amount:1500, date:'2026-07-11 09:12', status:'Paid' },
    { receipt:'OR-2026-000480', plate:'XYZ-5678', violation:'Illegal Parking',         amount:500,  date:'2026-07-11 08:47', status:'Paid' },
    { receipt:'OR-2026-000479', plate:'DEF-9012', violation:'No Helmet',               amount:1000, date:'2026-07-10 17:22', status:'Paid' },
    { receipt:'OR-2026-000478', plate:'GHI-3456', violation:'Beating the Red Light',   amount:2000, date:'2026-07-10 15:04', status:'Paid' },
    { receipt:'OR-2026-000477', plate:'JKL-7890', violation:'Reckless Driving',        amount:2500, date:'2026-07-10 11:30', status:'Paid' },
    { receipt:'OR-2026-000476', plate:'MNO-2468', violation:'Obstruction',             amount:750,  date:'2026-07-10 09:15', status:'Paid' }
  ],
  pendingList: [
    { id:'VIO-2026-01123', plate:'PQR-1122', type:'Overspeeding',        fine:1500, due:'2026-07-18' },
    { id:'VIO-2026-01124', plate:'STU-3344', type:'No Seatbelt',         fine:1000, due:'2026-07-19' },
    { id:'VIO-2026-01125', plate:'VWX-5566', type:'Illegal U-Turn',      fine:1200, due:'2026-07-20' },
    { id:'VIO-2026-01126', plate:'YZA-7788', type:'Beating the Red Light', fine:2000, due:'2026-07-20' },
    { id:'VIO-2026-01127', plate:'BCD-9900', type:'No Helmet',           fine:1000, due:'2026-07-21' }
  ],
  violations: [
    { id:'VIO-2026-01123', plate:'PQR-1122', vehicle:'Sedan',      violation:'Overspeeding',        location:'EDSA Ave, Barangay 5',    dt:'2026-07-11 08:22', fine:1500, status:'Pending' },
    { id:'VIO-2026-01122', plate:'ABC-1234', vehicle:'SUV',        violation:'Illegal Parking',     location:'Rizal St, Poblacion',      dt:'2026-07-11 09:12', fine:500,  status:'Paid' },
    { id:'VIO-2026-01121', plate:'XYZ-5678', vehicle:'Motorcycle', violation:'No Helmet',           location:'Main Rd, San Isidro',      dt:'2026-07-10 17:22', fine:1000, status:'Paid' },
    { id:'VIO-2026-01120', plate:'DEF-9012', vehicle:'Sedan',      violation:'Beating the Red Light', location:'JP Laurel St corner',    dt:'2026-07-10 15:04', fine:2000, status:'Paid' },
    { id:'VIO-2026-01119', plate:'GHI-3456', vehicle:'Truck',      violation:'Overloading',         location:'Highway 1, Km 4',          dt:'2026-07-10 12:18', fine:3500, status:'Overdue' },
    { id:'VIO-2026-01118', plate:'JKL-7890', vehicle:'SUV',        violation:'Reckless Driving',    location:'Mabini Ave, Brgy Sto Nino', dt:'2026-07-10 11:30', fine:2500, status:'Paid' },
    { id:'VIO-2026-01117', plate:'MNO-2468', vehicle:'Van',        violation:'Obstruction',         location:'Market Rd, Poblacion',     dt:'2026-07-10 09:15', fine:750,  status:'Paid' },
    { id:'VIO-2026-01116', plate:'STU-3344', vehicle:'Sedan',      violation:'No Seatbelt',         location:'National Hwy, San Roque',  dt:'2026-07-09 16:44', fine:1000, status:'Pending' }
  ],
  paymentHistory: [
    { receipt:'OR-2026-000481', vio:'VIO-2026-01122', plate:'ABC-1234', amount:500,  date:'2026-07-11', by:'Maria Reyes' },
    { receipt:'OR-2026-000480', vio:'VIO-2026-01121', plate:'XYZ-5678', amount:1000, date:'2026-07-11', by:'Maria Reyes' },
    { receipt:'OR-2026-000479', vio:'VIO-2026-01120', plate:'DEF-9012', amount:2000, date:'2026-07-10', by:'Maria Reyes' },
    { receipt:'OR-2026-000478', vio:'VIO-2026-01118', plate:'JKL-7890', amount:2500, date:'2026-07-10', by:'J. Cruz' },
    { receipt:'OR-2026-000477', vio:'VIO-2026-01117', plate:'MNO-2468', amount:750,  date:'2026-07-10', by:'J. Cruz' },
    { receipt:'OR-2026-000476', vio:'VIO-2026-01115', plate:'RST-1010', amount:1500, date:'2026-07-09', by:'Maria Reyes' },
    { receipt:'OR-2026-000475', vio:'VIO-2026-01114', plate:'UVW-2020', amount:1200, date:'2026-07-09', by:'Maria Reyes' }
  ],
  notifications: [
    { type:'violation', title:'New Violation Detected', text:'AI camera flagged plate GHI-3456 for Overloading at Highway 1.', time:'2 min ago' },
    { type:'pending',   title:'Payment Due Soon',      text:'5 violations approaching due date within 3 days.', time:'20 min ago' },
    { type:'payment',   title:'Payment Completed',     text:'OR-2026-000481 processed successfully for ₱1,500.', time:'1 hr ago' },
    { type:'system',    title:'System Announcement',   text:'Monthly collection report will be auto-generated on July 31.', time:'3 hrs ago' },
    { type:'violation', title:'New Violation Detected', text:'Plate PQR-1122 recorded for Overspeeding at EDSA Ave.', time:'5 hrs ago' },
    { type:'payment',   title:'Bulk Payments Cleared', text:'12 pending payments were cleared today.', time:'Yesterday' }
  ]
};

const peso = n => '₱' + n.toLocaleString('en-PH', {minimumFractionDigits:0});
const statusBadge = s => {
  const map = { Paid:'success', Pending:'warning', Overdue:'danger', Processing:'info' };
  return `<span class="badge badge-${map[s]||'neutral'}">${s}</span>`;
};

/* ============ Page Templates ============ */
const PAGES = {

  dashboard: () => `
    <div class="stats-grid">
      ${statCard('Total Violations', DATA.stats.totalViolations.toLocaleString(), '+8.2% this week', 'blue',
        '<path d="M12 2 3 7v6c0 5 4 9 9 10 5-1 9-5 9-10V7l-9-5z"/>')}
      ${statCard('Pending Payments', DATA.stats.pendingPayments, '-3.1% from last week', 'amber',
        '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>', true)}
      ${statCard('Paid Violations', DATA.stats.paidViolations.toLocaleString(), '+12.4% this month', 'green',
        '<path d="M20 6 9 17l-5-5"/>')}
      ${statCard("Today's Collections", peso(DATA.stats.todaysCollections), '+15.6% vs yesterday', 'navy',
        '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/>')}
      ${statCard('Monthly Collections', peso(DATA.stats.monthlyCollections), '+22.8% MoM', '',
        '<path d="M3 3v18h18"/><path d="M7 15l4-4 4 3 5-7"/>')}
    </div>

    <div class="grid-3">
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Recent Payments</div><div class="card-sub">Latest processed transactions</div></div>
          <button class="btn btn-outline btn-sm" onclick="switchPage('history')">View All</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Receipt No.</th><th>Plate</th><th>Violation</th><th>Amount</th><th>Date Paid</th><th>Status</th></tr></thead>
            <tbody>${DATA.recentPayments.map(r=>`
              <tr><td class="mono">${r.receipt}</td><td class="mono">${r.plate}</td><td>${r.violation}</td>
              <td class="amount">${peso(r.amount)}</td><td>${r.date}</td><td>${statusBadge(r.status)}</td></tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Payment Status</div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartStatus"></canvas></div></div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><div class="card-title">Daily Collection</div><div class="card-sub">Last 7 days</div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartDaily"></canvas></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Monthly Collection</div><div class="card-sub">Fiscal year overview</div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartMonthly"></canvas></div></div>
      </div>
    </div>

    <div class="card" style="margin-top:20px">
      <div class="card-header">
        <div><div class="card-title">Pending Payments</div><div class="card-sub">Violations awaiting settlement</div></div>
        <button class="btn btn-primary btn-sm" onclick="switchPage('payment')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Process New Payment
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Violation ID</th><th>Plate Number</th><th>Violation Type</th><th>Fine Amount</th><th>Due Date</th><th>Action</th></tr></thead>
          <tbody>${DATA.pendingList.map(v=>`
            <tr>
              <td class="mono">${v.id}</td><td class="mono">${v.plate}</td><td>${v.type}</td>
              <td class="amount">${peso(v.fine)}</td><td>${v.due}</td>
              <td><div class="btn-group">
                <button class="btn btn-outline btn-sm" onclick="viewViolation('${v.id}','${v.plate}','${v.type}','${v.fine}')">View</button>
                <button class="btn btn-primary btn-sm" onclick="switchPage('payment');prefillPayment('${v.id}','${v.plate}',${v.fine})">Process</button>
              </div></td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>
  `,

  violations: () => `
    <div class="card">
      <div class="filters">
        <input class="filter-input" placeholder="Search plate, ID, location..." />
        <input class="filter-input" type="date" />
        <select class="filter-select"><option>All Statuses</option><option>Paid</option><option>Pending</option><option>Overdue</option></select>
        <select class="filter-select"><option>All Violation Types</option><option>Overspeeding</option><option>Illegal Parking</option><option>No Helmet</option><option>Beating the Red Light</option><option>Reckless Driving</option></select>
        <button class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54z"/></svg>
          Apply Filters
        </button>
        <button class="btn btn-navy btn-sm" style="margin-left:auto">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
          Export
        </button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Violation ID</th><th>Plate</th><th>Vehicle</th><th>Violation</th><th>Location</th><th>Date & Time</th><th>Fine</th><th>Status</th><th>Action</th></tr></thead>
          <tbody>${DATA.violations.map(v=>`
            <tr>
              <td class="mono">${v.id}</td><td class="mono">${v.plate}</td><td>${v.vehicle}</td>
              <td>${v.violation}</td><td>${v.location}</td><td>${v.dt}</td>
              <td class="amount">${peso(v.fine)}</td><td>${statusBadge(v.status)}</td>
              <td><div class="btn-group">
                <button class="btn-icon" title="View" onclick="viewViolation('${v.id}','${v.plate}','${v.violation}',${v.fine})">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
                <button class="btn-icon" title="Print" onclick="showToast('Sending record to printer...')">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                </button>
              </div></td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
      ${paginationEl(1, 12)}
    </div>
  `,

  payment: () => `
    <div class="pay-grid">
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Process Payment</div><div class="card-sub">Fill in the details to record a violation payment</div></div>
        </div>
        <div class="card-body">
          <form onsubmit="event.preventDefault();savePayment()">
            <div class="form-grid">
              <div class="form-group"><label class="form-label">Violation ID</label><input id="pf-vio" class="form-control" placeholder="VIO-2026-01123" required /></div>
              <div class="form-group"><label class="form-label">Plate Number</label><input id="pf-plate" class="form-control" placeholder="ABC 1234" required /></div>
              <div class="form-group"><label class="form-label">Fine Amount (₱)</label><input id="pf-fine" class="form-control" type="number" placeholder="1500" required /></div>
              <div class="form-group"><label class="form-label">Official Receipt Number</label><input class="form-control" value="OR-2026-000482" required /></div>
              <div class="form-group"><label class="form-label">Payment Date</label><input class="form-control" type="date" value="${new Date().toISOString().slice(0,10)}" required /></div>
              <div class="form-group"><label class="form-label">Payment Method</label>
                <select class="form-control"><option>Cash</option><option>Debit Card</option><option>Credit Card</option><option>GCash</option><option>Bank Transfer</option></select>
              </div>
              <div class="form-group full"><label class="form-label">Notes</label><textarea class="form-control" placeholder="Optional remarks..."></textarea></div>
            </div>
            <div style="display:flex;gap:10px;margin-top:22px;justify-content:flex-end">
              <button type="button" class="btn btn-outline">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cancel
              </button>
              <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                Save Payment
              </button>
            </div>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Pending Violations</div><div class="card-sub">Click a row to auto-fill</div></div>
          <span class="badge badge-warning">${DATA.pendingList.length} pending</span>
        </div>
        <div class="table-wrap">
          <table class="pending-table">
            <thead><tr><th>ID</th><th>Plate</th><th style="text-align:right">Fine</th></tr></thead>
            <tbody>${DATA.pendingList.map(v=>`
              <tr class="pending-row" onclick="prefillPayment('${v.id}','${v.plate}',${v.fine})">
                <td class="mono">${v.id}</td>
                <td><span class="plate-badge">${v.plate}</span></td>
                <td class="amount" style="text-align:right">${peso(v.fine)}.00</td>
              </tr>`).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `,


  reports: () => `
    <div class="grid-4" style="margin-bottom:20px">
      ${reportCard('Daily Collections', peso(48750), 'Today · Jul 11')}
      ${reportCard('Weekly Collections', peso(286400), 'Week 28')}
      ${reportCard('Monthly Collections', peso(1245600), 'July 2026')}
      ${reportCard('Annual Collections', peso(14832000), 'FY 2026')}
    </div>

    <div class="card">
      <div class="card-header">
        <div><div class="card-title">Generate Report</div><div class="card-sub">Select date range and export format</div></div>
      </div>
      <div class="card-body">
        <div class="form-grid" style="grid-template-columns:1fr 1fr 1fr 1fr;align-items:end">
          <div class="form-group"><label class="form-label">From Date</label><input class="form-control" type="date" /></div>
          <div class="form-group"><label class="form-label">To Date</label><input class="form-control" type="date" /></div>
          <div class="form-group"><label class="form-label">Report Type</label>
            <select class="form-control"><option>Summary</option><option>Detailed</option><option>By Violation Type</option><option>By Payment Method</option></select>
          </div>
          <div class="form-group"><button class="btn btn-primary" onclick="showToast('Report generated!')">Generate</button></div>
        </div>
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
          <button class="btn btn-navy" onclick="showToast('Downloading PDF...')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Export PDF
          </button>
          <button class="btn btn-navy" onclick="showToast('Downloading Excel...')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8M16 8l-8 8"/></svg>
            Export Excel
          </button>
          <button class="btn btn-outline" onclick="showToast('Sending to printer...')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print Report
          </button>
        </div>
      </div>
    </div>

    <div class="grid-2" style="margin-top:20px">
      <div class="card">
        <div class="card-header"><div class="card-title">Collection Trend</div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartTrend"></canvas></div></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Violation Type Breakdown</div></div>
        <div class="card-body"><div class="chart-box"><canvas id="chartTypes"></canvas></div></div>
      </div>
    </div>
  `,

  history: () => `
    <div class="card">
      <div class="filters">
        <input class="filter-input" placeholder="Search receipt, plate..." />
        <input class="filter-input" type="date" />
        <input class="filter-input" type="date" />
        <select class="filter-select"><option>All Cashiers</option><option>Maria Reyes</option><option>J. Cruz</option></select>
        <button class="btn btn-outline btn-sm">Apply</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Receipt No.</th><th>Violation ID</th><th>Plate Number</th><th>Amount</th><th>Payment Date</th><th>Processed By</th><th>Action</th></tr></thead>
          <tbody>${DATA.paymentHistory.map(h=>`
            <tr>
              <td class="mono">${h.receipt}</td><td class="mono">${h.vio}</td><td class="mono">${h.plate}</td>
              <td class="amount">${peso(h.amount)}</td><td>${h.date}</td><td>${h.by}</td>
              <td><button class="btn btn-outline btn-sm" onclick="showToast('Printing receipt ${h.receipt}...')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/></svg>
                Print</button></td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
      ${paginationEl(1, 8)}
    </div>
  `,

  notifications: () => `
    <div class="notif-grid">
      ${DATA.notifications.map(n=>`
        <div class="notif ${n.type}">
          <div class="notif-title">${n.title}</div>
          <div class="notif-text">${n.text}</div>
          <div class="notif-time">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            ${n.time}
          </div>
        </div>`).join('')}
    </div>
  `,

  profile: () => `
    <div class="profile-hero">
      <div class="profile-avatar">MR</div>
      <div class="profile-info" style="flex:1">
        <h2>Maria D. Reyes</h2>
        <p>Municipal Treasurer's Office · TRAVIS Access Level 2</p>
        <span class="profile-badge">Treasurer</span>
      </div>
      <div style="display:flex;gap:10px;position:relative;z-index:1">
        <button class="btn btn-primary">Edit Profile</button>
        <button class="btn btn-outline" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.4)">Change Password</button>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Personal Information</div></div>
      <div class="card-body">
        <div class="info-grid">
          <div class="info-item"><div class="k">Full Name</div><div class="v">Maria D. Reyes</div></div>
          <div class="info-item"><div class="k">Employee ID</div><div class="v mono">LGU-TR-00234</div></div>
          <div class="info-item"><div class="k">Office</div><div class="v">Municipal Treasurer's Office</div></div>
          <div class="info-item"><div class="k">Email Address</div><div class="v">maria.reyes@lgu.gov.ph</div></div>
          <div class="info-item"><div class="k">Contact Number</div><div class="v">+63 917 555 0123</div></div>
          <div class="info-item"><div class="k">Access Role</div><div class="v">Treasurer</div></div>
        </div>
      </div>
    </div>
  `
};

/* ============ Helpers ============ */
function statCard(label, value, delta, variant, icon, down){
  return `<div class="stat variant-${variant}">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${icon}</svg></div>
    <div class="stat-label">${label}</div>
    <div class="stat-value">${value}</div>
    <div class="stat-delta ${down?'down':''}">${delta}</div>
  </div>`;
}
function reportCard(label, value, sub){
  return `<div class="stat variant-navy">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-4 4 3 5-7"/></svg></div>
    <div class="stat-label">${label}</div>
    <div class="stat-value">${value}</div>
    <div class="stat-delta">${sub}</div>
  </div>`;
}
function paginationEl(page, total){
  return `<div class="pagination">
    <div class="page-info">Showing 1-${total} of ${total*3} entries</div>
    <div class="page-btns">
      <button class="page-btn">‹</button>
      <button class="page-btn active">1</button>
      <button class="page-btn">2</button>
      <button class="page-btn">3</button>
      <button class="page-btn">›</button>
    </div>
  </div>`;
}

/* ============ Navigation ============ */
const TITLES = {
  dashboard:['Dashboard Overview','Welcome back, Treasurer'],
  violations:['Traffic Violation Records','All AI-captured violations'],
  payment:['Payment Management','Process new violation payments'],
  reports:['Collection Reports','Generate and export reports'],
  history:['Payment History','Complete transaction log'],
  notifications:['Notifications','System alerts and updates'],
  profile:['My Profile','Manage your account details']
};
function switchPage(key){
  document.querySelectorAll('.nav-item[data-page]').forEach(el=>el.classList.toggle('active', el.dataset.page===key));
  const [t,s] = TITLES[key] || ['',''];
  document.getElementById('pageTitle').textContent = t;
  document.getElementById('pageSub').textContent = s;
  const content = document.getElementById('content');
  content.innerHTML = `<div class="page active">${PAGES[key]()}</div>`;
  window.scrollTo({top:0,behavior:'smooth'});
  if (key==='dashboard') renderDashboardCharts();
  if (key==='reports') renderReportCharts();
  if (window.innerWidth<=768) document.getElementById('sidebar').classList.remove('open');
}

document.querySelectorAll('.nav-item[data-page]').forEach(el=>{
  el.addEventListener('click', e => { e.preventDefault(); switchPage(el.dataset.page); });
});

/* ============ Modal / Toast ============ */
function openModal(title, body, footer){
  document.getElementById('modalTitle').innerHTML = title;
  document.getElementById('modalBody').innerHTML = body;
  document.getElementById('modalFooter').innerHTML = footer || '';
  document.getElementById('modalBack').classList.add('open');
}
function closeModal(){ document.getElementById('modalBack').classList.remove('open'); }
document.getElementById('modalBack').addEventListener('click', e => { if(e.target.id==='modalBack') closeModal(); });

function showToast(msg, type){
  const wrap = document.getElementById('toastWrap');
  const el = document.createElement('div');
  el.className = 'toast ' + (type||'');
  el.innerHTML = `<div class="toast-icon">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
  </div><div class="toast-msg">${msg}</div>`;
  wrap.appendChild(el);
  setTimeout(()=>{el.style.opacity='0';el.style.transform='translateX(120%)';el.style.transition='.3s'},2600);
  setTimeout(()=>el.remove(),3000);
}

function viewViolation(id, plate, type, fine){
  openModal(`Violation Details · <span class="mono" style="color:var(--teal-600)">${id}</span>`, `
    <div class="detail-grid">
      <div>
        <div class="detail-img" style="margin-bottom:12px"><span>Vehicle Snapshot</span></div>
        <div class="detail-img" style="background:linear-gradient(135deg,#0d9488,#134e4a)"><span>AI Captured Frame</span></div>
      </div>
      <div class="detail-list">
        <div class="detail-row"><span class="k">Plate Number</span><span class="v mono">${plate}</span></div>
        <div class="detail-row"><span class="k">Vehicle Type</span><span class="v">Sedan</span></div>
        <div class="detail-row"><span class="k">Driver</span><span class="v">— (unregistered)</span></div>
        <div class="detail-row"><span class="k">Violation Type</span><span class="v">${type}</span></div>
        <div class="detail-row"><span class="k">Date</span><span class="v">July 11, 2026</span></div>
        <div class="detail-row"><span class="k">Time</span><span class="v">08:22 AM</span></div>
        <div class="detail-row"><span class="k">Location</span><span class="v">EDSA Ave, Brgy 5</span></div>
        <div class="detail-row"><span class="k">Fine Amount</span><span class="v">${peso(fine)}</span></div>
        <div class="detail-row"><span class="k">Payment Status</span><span class="v">${statusBadge('Pending')}</span></div>
      </div>
    </div>
  `, `
    <button class="btn btn-outline" onclick="closeModal()">Close</button>
    <button class="btn btn-outline" onclick="showToast('Printing violation...')">Print Violation</button>
    <button class="btn btn-primary" onclick="closeModal();switchPage('payment');prefillPayment('${id}','${plate}',${fine})">Process Payment</button>
  `);
}

function prefillPayment(id, plate, fine){
  setTimeout(()=>{
    document.getElementById('pf-vio').value = id;
    document.getElementById('pf-plate').value = plate;
    document.getElementById('pf-fine').value = fine;
  }, 50);
}

function savePayment(){
  showToast('Payment saved successfully!');
  setTimeout(()=>switchPage('history'), 800);
}

/* ============ Charts ============ */
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b';

function renderDashboardCharts(){
  const grad = (ctx, c1, c2) => { const g = ctx.createLinearGradient(0,0,0,260); g.addColorStop(0,c1); g.addColorStop(1,c2); return g; };

  // Daily
  const d = document.getElementById('chartDaily').getContext('2d');
  new Chart(d,{type:'line',data:{
    labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],
    datasets:[{label:'Collection',data:[32500,28400,41200,38700,52300,48750,44100],
      borderColor:'#14b8a6',backgroundColor:grad(d,'rgba(20,184,166,.35)','rgba(20,184,166,0)'),
      fill:true,tension:.4,borderWidth:3,pointBackgroundColor:'#14b8a6',pointBorderColor:'#fff',pointBorderWidth:2,pointRadius:5}]
  },options:baseOpts()});

  // Monthly
  const m = document.getElementById('chartMonthly').getContext('2d');
  new Chart(m,{type:'bar',data:{
    labels:['Jan','Feb','Mar','Apr','May','Jun','Jul'],
    datasets:[{label:'Collection',data:[985000,1120000,1045000,1234000,1189000,1315000,1245600],
      backgroundColor:grad(m,'#0d9488','#14b8a6'),borderRadius:8,borderSkipped:false}]
  },options:baseOpts()});

  // Status donut
  const s = document.getElementById('chartStatus').getContext('2d');
  new Chart(s,{type:'doughnut',data:{
    labels:['Paid','Pending','Overdue'],
    datasets:[{data:[972,312,64],backgroundColor:['#14b8a6','#f59e0b','#ef4444'],borderWidth:0,hoverOffset:10}]
  },options:{
    responsive:true,maintainAspectRatio:false,cutout:'68%',
    plugins:{legend:{position:'bottom',labels:{usePointStyle:true,padding:16,font:{size:12}}}}
  }});
}

function renderReportCharts(){
  const t = document.getElementById('chartTrend').getContext('2d');
  const g = t.createLinearGradient(0,0,0,260); g.addColorStop(0,'rgba(30,58,138,.35)'); g.addColorStop(1,'rgba(30,58,138,0)');
  new Chart(t,{type:'line',data:{
    labels:['Wk 22','Wk 23','Wk 24','Wk 25','Wk 26','Wk 27','Wk 28'],
    datasets:[{label:'Weekly',data:[218000,245000,232000,278000,264000,289000,286400],
      borderColor:'#1e3a8a',backgroundColor:g,fill:true,tension:.4,borderWidth:3,
      pointBackgroundColor:'#1e3a8a',pointBorderColor:'#fff',pointBorderWidth:2,pointRadius:5}]
  },options:baseOpts()});

  const ty = document.getElementById('chartTypes').getContext('2d');
  new Chart(ty,{type:'polarArea',data:{
    labels:['Overspeeding','Illegal Parking','No Helmet','Red Light','Reckless','Others'],
    datasets:[{data:[320,245,198,142,98,281],backgroundColor:[
      'rgba(20,184,166,.7)','rgba(30,58,138,.7)','rgba(245,158,11,.7)',
      'rgba(239,68,68,.7)','rgba(59,130,246,.7)','rgba(100,116,139,.6)'
    ],borderWidth:2,borderColor:'#fff'}]
  },options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{usePointStyle:true,font:{size:11}}}}}});
}

function baseOpts(){
  return {
    responsive:true,maintainAspectRatio:false,
    plugins:{legend:{display:false},tooltip:{
      backgroundColor:'#0f1f47',padding:12,cornerRadius:8,titleFont:{size:13,weight:'600'},bodyFont:{size:12},
      callbacks:{label:c => '₱' + c.parsed.y.toLocaleString()}
    }},
    scales:{
      y:{grid:{color:'rgba(148,163,184,.15)'},ticks:{callback:v => '₱'+(v/1000)+'k',font:{size:11}}},
      x:{grid:{display:false},ticks:{font:{size:11}}}
    }
  };
}

/* ============ Boot ============ */
switchPage('dashboard');
