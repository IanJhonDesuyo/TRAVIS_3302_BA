export type OfficialReportType = 'violations' | 'payments' | 'monitoring';

type Column = { key: string; label: string; money?: boolean };

const columns: Record<OfficialReportType, Column[]> = {
  violations: [
    { key: 'ticket_number', label: 'Ticket' }, { key: 'driver_name', label: 'Driver' },
    { key: 'plate_number', label: 'Plate' }, { key: 'violation_type', label: 'Violation' },
    { key: 'violation_location', label: 'Location' }, { key: 'violation_date', label: 'Date' },
    { key: 'penalty_amount', label: 'Penalty', money: true }, { key: 'status', label: 'Status' },
  ],
  payments: [
    { key: 'receipt_reference', label: 'Receipt' }, { key: 'ticket_number', label: 'Ticket' },
    { key: 'driver_name', label: 'Payor / Driver' }, { key: 'plate_number', label: 'Plate' },
    { key: 'amount_paid', label: 'Amount', money: true }, { key: 'payment_method', label: 'Method' },
    { key: 'payment_date', label: 'Payment Date' }, { key: 'payment_status', label: 'Status' },
  ],
  monitoring: [
    { key: 'recorded_at', label: 'Recorded At' }, { key: 'camera_id', label: 'Camera' },
    { key: 'vehicle_count', label: 'Vehicles' }, { key: 'inbound_count', label: 'Inbound' },
    { key: 'outbound_count', label: 'Outbound' }, { key: 'congestion_level', label: 'Congestion' },
    { key: 'officer_presence', label: 'Officer' }, { key: 'potential_collision', label: 'Collision' },
  ],
};

const titles: Record<OfficialReportType, string> = {
  violations: 'Violation Report', payments: 'Payment Collection Report', monitoring: 'Traffic Monitoring Report',
};

const escapeHtml = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character] || character));
const display = (value: unknown, money = false) => {
  if (value === null || value === undefined || value === '') return '—';
  if (money) return `&#8369;${Number(value).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  return escapeHtml(value);
};

export function buildOfficialReportHtml(input: {
  type: OfficialReportType;
  rows: Record<string, unknown>[];
  summary: Record<string, string | number>;
  dateFrom: string;
  dateTo: string;
  statusFilter?: string;
  locationFilter?: string;
  preparedBy: string;
  approvedBy: string;
  generatedAt?: string;
}): string {
  const reportColumns = columns[input.type];
  const generatedAt = input.generatedAt || new Date().toLocaleString('en-PH');
  const filterText = [input.statusFilter && `Status: ${input.statusFilter}`, input.locationFilter && `Location: ${input.locationFilter}`].filter(Boolean).join(' | ') || 'None';
  const body = input.rows.length
    ? input.rows.map(row => `<tr>${reportColumns.map(column => `<td>${display(row[column.key], column.money)}</td>`).join('')}</tr>`).join('')
    : `<tr><td colspan="${reportColumns.length}" class="empty">No records matched the selected filters.</td></tr>`;
  const summary = Object.entries(input.summary).map(([label, value]) => `<div><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');

  return `<!DOCTYPE html><html><head><meta charset="utf-8"><style>
    @page{size:A4 landscape;margin:14mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;color:#111827;font-size:9px;margin:0}
    header{text-align:center;border-bottom:2px solid #17324d;padding-bottom:9px;margin-bottom:12px}.republic{font-family:Georgia,serif;font-size:10px;letter-spacing:.12em;text-transform:uppercase}
    h1{font-family:Georgia,serif;color:#102f49;font-size:20px;margin:3px 0}header p{margin:2px 0;color:#374151;font-size:10px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;text-align:left;margin-top:10px}
    .summary{display:flex;gap:8px;margin:10px 0}.summary div{flex:1;border:1px solid #cbd5e1;padding:7px}.summary span{display:block;color:#64748b;font-size:8px;text-transform:uppercase}.summary strong{display:block;font-size:12px;margin-top:3px;color:#102f49}
    table{width:100%;border-collapse:collapse;table-layout:auto}thead{display:table-header-group}th{background:#e9eef2;color:#102f49;border:1px solid #aebdca;padding:6px 4px;text-align:left}td{border:1px solid #d5dde4;padding:5px 4px;vertical-align:top;word-break:break-word}tr{page-break-inside:avoid}.empty{text-align:center;padding:25px;color:#64748b}
    footer{margin-top:22px;page-break-inside:avoid;font-size:10px}.certification{line-height:1.5}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:36px}.signature{text-align:center;border-top:1px solid #111827;padding-top:5px}.signature strong{display:block;text-transform:uppercase}.signature span{display:block;color:#475569;margin-top:2px}
    .footer-note{text-align:center;color:#64748b;font-size:8px;margin-top:22px}
  </style></head><body>
    <header><div class="republic">Republic of the Philippines</div><h1>Municipality of Nasugbu</h1><p>Traffic Management Office — TRAVIS Command Center</p><div class="meta"><div><strong>Report:</strong> ${titles[input.type]}</div><div><strong>Period:</strong> ${escapeHtml(input.dateFrom)} to ${escapeHtml(input.dateTo)}</div><div><strong>Generated:</strong> ${escapeHtml(generatedAt)}</div><div><strong>Filters:</strong> ${escapeHtml(filterText)}</div><div><strong>Prepared by:</strong> ${escapeHtml(input.preparedBy)}</div><div><strong>Total records:</strong> ${input.rows.length}</div></div></header>
    <section class="summary">${summary}</section><table><thead><tr>${reportColumns.map(column => `<th>${column.label}</th>`).join('')}</tr></thead><tbody>${body}</tbody></table>
    <footer><p class="certification">I certify that this report was generated from the official TRAVIS records for the period stated above and that the information presented is true and correct based on the records available at the time of generation.</p><div class="signatures"><div class="signature"><strong>${escapeHtml(input.preparedBy)}</strong><span>Prepared by / Date</span></div><div class="signature"><strong>${escapeHtml(input.approvedBy || ' ')}</strong><span>Reviewed and Approved by / Date</span></div></div><div class="footer-note">Computer-generated official report from TRAVIS. Signature is required for certification.</div></footer>
  </body></html>`;
}
