import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  StatusBar,
  RefreshControl,
  Share,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import DateTimePicker from '@react-native-community/datetimepicker';

// ========== TYPES ==========
interface ReportRecord {
  [key: string]: any;
}

interface ReportSummary {
  [key: string]: string | number;
}

interface SavedReport {
  id: number;
  title: string;
  report_type: string;
  period_start: string;
  period_end: string;
  status: string;
  generated_by_name: string;
  generated_at: string;
}

// ========== MOCK DATA (replace with API calls) ==========
const mockHistory: SavedReport[] = [
  {
    id: 1,
    title: 'Violation Report Q2 2026',
    report_type: 'violations',
    period_start: '2026-04-01',
    period_end: '2026-06-30',
    status: 'completed',
    generated_by_name: 'Admin',
    generated_at: '2026-07-01 10:00:00',
  },
  {
    id: 2,
    title: 'Payment Collection June 2026',
    report_type: 'payments',
    period_start: '2026-06-01',
    period_end: '2026-06-30',
    status: 'pending',
    generated_by_name: 'User',
    generated_at: '2026-07-02 09:15:00',
  },
];

const mockPreviewData: { [key: string]: { rows: ReportRecord[], summary: ReportSummary, title: string } } = {
  violations: {
    title: 'Traffic Violation Report',
    summary: {
      'Total Records': 45,
      'Paid Violations': 20,
      'Pending / Unpaid': 25,
      'Total Penalties': '₱78,500.00',
    },
    rows: [
      { ticket_number: 'TRV-20260716-001', driver_name: 'Juan Dela Cruz', plate_number: 'ABC-1234', vehicle_type: 'Car', violation_type: 'Speeding', violation_location: 'EDSA Ayala', violation_date: '2026-07-16', violation_time: '10:30', penalty_amount: 1200, status: 'pending' },
      { ticket_number: 'TRV-20260716-002', driver_name: 'Maria Santos', plate_number: 'XYZ-5678', vehicle_type: 'SUV', violation_type: 'Illegal Parking', violation_location: 'BGC 32nd', violation_date: '2026-07-16', violation_time: '09:15', penalty_amount: 800, status: 'paid' },
      { ticket_number: 'TRV-20260715-003', driver_name: 'Pedro Reyes', plate_number: 'DEF-9012', vehicle_type: 'Motorcycle', violation_type: 'Disregarded Signal', violation_location: 'Commonwealth Ave', violation_date: '2026-07-15', violation_time: '17:45', penalty_amount: 600, status: 'overdue' },
    ],
  },
  payments: {
    title: 'Payment Collection Report',
    summary: {
      'Total Transactions': 12,
      'Completed Payments': 10,
      'Other Statuses': 2,
      'Total Collected': '₱25,400.00',
    },
    rows: [
      { payment_reference: 'PAY-000001', ticket_number: 'TRV-20260716-002', driver_name: 'Maria Santos', plate_number: 'XYZ-5678', violation_type: 'Illegal Parking', amount_paid: 800, payment_method: 'cash', payment_status: 'completed', payment_date: '2026-07-16 11:00', received_by_name: 'Cashier' },
    ],
  },
  monitoring: {
    title: 'Traffic Monitoring Report',
    summary: {
      'Monitoring Records': 8,
      'Vehicle Observations': 256,
      'High Congestion Records': 3,
      'Potential Collision Flags': 1,
    },
    rows: [
      { camera_name: 'Main Intersection', location: 'EDSA Ayala', vehicle_count: 34, inbound_count: 12, outbound_count: 22, congestion_level: 'Moderate', officer_presence: 'Present', potential_collision: 'None', recorded_at: '2026-07-16 12:12:18' },
    ],
  },
};

const statusOptions: { [key: string]: { [key: string]: string } } = {
  violations: {
    '': 'All Statuses',
    pending: 'Pending',
    overdue: 'Overdue',
    paid: 'Paid',
    cancelled: 'Cancelled',
  },
  payments: {
    '': 'All Statuses',
    completed: 'Completed',
    pending: 'Pending',
    failed: 'Failed',
  },
  monitoring: {
    '': 'All Congestion Levels',
    none: 'None',
    low: 'Low',
    moderate: 'Moderate',
    high: 'High',
    heavy: 'Heavy',
    critical: 'Critical',
  },
};

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'completed') return '#16a34a';
  if (s === 'pending' || s === 'overdue') return '#f59e0b';
  if (s === 'cancelled' || s === 'failed') return '#dc2626';
  return '#6b7280';
};

const getStatusLabel = (type: string, value: string): string => {
  const options = statusOptions[type] || {};
  return options[value] || value;
};

// ========== SCREEN ==========
export default function ReportsScreen() {
  const router = useRouter();

  // Form state
  const [reportType, setReportType] = useState<'violations' | 'payments' | 'monitoring'>('violations');
  const [dateFrom, setDateFrom] = useState(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [dateTo, setDateTo] = useState(new Date());
  const [statusFilter, setStatusFilter] = useState('');
  const [locationFilter, setLocationFilter] = useState('');

  // UI state
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showDatePicker, setShowDatePicker] = useState<{ type: 'from' | 'to' | null }>({ type: null });

  // Preview data
  const [previewData, setPreviewData] = useState<{ rows: ReportRecord[], summary: ReportSummary, title: string } | null>(null);
  const [history, setHistory] = useState<SavedReport[]>([]);
  const [totalReports, setTotalReports] = useState(0);
  const [reportsToday, setReportsToday] = useState(0);
  const [reportsThisMonth, setReportsThisMonth] = useState(0);
  const [lastGenerated, setLastGenerated] = useState<string | null>(null);

  // Load initial history
  useEffect(() => {
    fetchHistory();
    fetchStats();
  }, []);

  const fetchHistory = async () => {
    // Replace with actual API: fetch('/api/reports/history')
    await new Promise(resolve => setTimeout(resolve, 500));
    setHistory(mockHistory);
  };

  const fetchStats = async () => {
    // Replace with actual API: fetch('/api/reports/stats')
    await new Promise(resolve => setTimeout(resolve, 300));
    setTotalReports(42);
    setReportsToday(3);
    setReportsThisMonth(18);
    setLastGenerated('2026-07-16 14:30:00');
  };

  const generateReport = async () => {
    setLoading(true);
    try {
      // Replace with actual API call: fetch('/api/reports/generate', { method: 'POST', body: JSON.stringify({ reportType, dateFrom, dateTo, status: statusFilter, location: locationFilter }) })
      await new Promise(resolve => setTimeout(resolve, 1000));
      // Use mock data based on reportType
      const mock = mockPreviewData[reportType];
      if (mock) {
        setPreviewData({
          rows: mock.rows,
          summary: mock.summary,
          title: mock.title,
        });
      } else {
        setPreviewData(null);
      }
      setLoading(false);
    } catch (error) {
      Alert.alert('Error', 'Failed to generate report.');
      setLoading(false);
    }
  };

  const resetForm = () => {
    setReportType('violations');
    setDateFrom(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    setDateTo(new Date());
    setStatusFilter('');
    setLocationFilter('');
    setPreviewData(null);
  };

  const onRefresh = () => {
    setRefreshing(true);
    Promise.all([fetchHistory(), fetchStats()]).then(() => setRefreshing(false));
  };

  const shareCSV = async () => {
    if (!previewData || previewData.rows.length === 0) {
      Alert.alert('No Data', 'There is no report data to share.');
      return;
    }

    // Build CSV content
    const headers = Object.keys(previewData.rows[0]);
    const rows = previewData.rows.map(row => headers.map(key => row[key] ?? '').join(','));
    const csv = [headers.join(','), ...rows].join('\n');

    try {
      await Share.share({
        message: csv,
        title: `${previewData.title} CSV`,
      });
    } catch (error) {
      Alert.alert('Error', 'Unable to share CSV.');
    }
  };

  // Format date for display
  const formatDate = (date: Date): string => {
    return date.toISOString().split('T')[0];
  };

  const formatDateDisplay = (date: Date): string => {
    return date.toLocaleDateString('en-PH', { year: 'numeric', month: '2-digit', day: '2-digit' });
  };

  // Render functions
  const renderStatCard = (label: string, value: string | number, color: string = '#2563eb') => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );

  const renderSummaryItem = (label: string, value: string | number) => (
    <View style={styles.summaryItem}>
      <Text style={styles.summaryLabel}>{label}</Text>
      <Text style={styles.summaryValue}>{value}</Text>
    </View>
  );

  const renderRecordItem = ({ item, index }: { item: ReportRecord, index: number }) => {
    const keys = Object.keys(item);
    return (
      <View style={[styles.recordItem, index % 2 === 0 ? styles.recordEven : styles.recordOdd]}>
        {keys.map(key => {
          let displayValue = item[key] ?? '';
          // Format certain fields
          if (['penalty_amount', 'amount_paid'].includes(key) && typeof displayValue === 'number') {
            displayValue = formatCurrency(displayValue);
          } else if (['status', 'payment_status', 'congestion_level'].includes(key)) {
            displayValue = <View style={[styles.statusBadge, { backgroundColor: statusColor(displayValue) + '20' }]}>
              <Text style={[styles.statusText, { color: statusColor(displayValue) }]}>
                {displayValue.toUpperCase()}
              </Text>
            </View>;
          } else if (['payment_method'].includes(key)) {
            displayValue = displayValue.toUpperCase();
          }
          return (
            <View key={key} style={styles.recordField}>
              <Text style={styles.recordLabel}>{key.replace(/_/g, ' ').toUpperCase()}</Text>
              <Text style={styles.recordValue}>{displayValue}</Text>
            </View>
          );
        })}
      </View>
    );
  };

  const renderHistoryItem = ({ item }: { item: SavedReport }) => (
    <View style={styles.historyItem}>
      <View style={styles.historyRow}>
        <Text style={styles.historyTitle}>{item.title}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.historyDetail}>
        {item.report_type.toUpperCase()} • {item.period_start} to {item.period_end}
      </Text>
      <Text style={styles.historyMeta}>
        Generated by: {item.generated_by_name} • {item.generated_at}
      </Text>
    </View>
  );

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.pageTitle}>Reports</Text>
          <Text style={styles.pageSub}>Generate, preview, print, and export operational records.</Text>
        </View>

        {/* Stats */}
        <View style={styles.statsRow}>
          {renderStatCard('Total Saved Reports', totalReports, '#2563eb')}
          {renderStatCard('Generated Today', reportsToday, '#16a34a')}
          {renderStatCard('This Month', reportsThisMonth, '#f59e0b')}
          {renderStatCard('Last Generated', lastGenerated ? lastGenerated.split(' ')[0] : 'N/A', '#6b7280')}
        </View>

        {/* Report Generator */}
        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Report Generator</Text>
          <Text style={styles.sectionSub}>Choose a report type and date range, then generate a preview.</Text>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Report Type</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={reportType}
                onValueChange={(itemValue) => setReportType(itemValue)}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
              >
                <Picker.Item label="Violation Report" value="violations" />
                <Picker.Item label="Payment Collection Report" value="payments" />
                <Picker.Item label="Traffic Monitoring Report" value="monitoring" />
              </Picker>
            </View>
          </View>

          <View style={styles.dateRow}>
            <View style={styles.dateGroup}>
              <Text style={styles.label}>Start Date</Text>
              <TouchableOpacity style={styles.dateInput} onPress={() => setShowDatePicker({ type: 'from' })}>
                <Text>{formatDateDisplay(dateFrom)}</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.dateGroup}>
              <Text style={styles.label}>End Date</Text>
              <TouchableOpacity style={styles.dateInput} onPress={() => setShowDatePicker({ type: 'to' })}>
                <Text>{formatDateDisplay(dateTo)}</Text>
              </TouchableOpacity>
            </View>
          </View>

          {showDatePicker.type && (
            <DateTimePicker
              value={showDatePicker.type === 'from' ? dateFrom : dateTo}
              mode="date"
              display="default"
              onChange={(event, selectedDate) => {
                if (selectedDate) {
                  if (showDatePicker.type === 'from') setDateFrom(selectedDate);
                  else setDateTo(selectedDate);
                }
                setShowDatePicker({ type: null });
              }}
            />
          )}

          <View style={styles.formGroup}>
            <Text style={styles.label}>{reportType === 'monitoring' ? 'Congestion Level' : 'Status'}</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={statusFilter}
                onValueChange={(itemValue) => setStatusFilter(itemValue)}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
              >
                {Object.entries(statusOptions[reportType]).map(([value, label]) => (
                  <Picker.Item key={value} label={label} value={value} />
                ))}
              </Picker>
            </View>
          </View>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Location Contains</Text>
            <TextInput
              style={[styles.textInput, reportType === 'payments' && styles.disabledInput]}
              value={locationFilter}
              onChangeText={setLocationFilter}
              placeholder="Example: J.P. Laurel Street"
              editable={reportType !== 'payments'}
            />
          </View>

          <View style={styles.actionRow}>
            <TouchableOpacity style={[styles.button, styles.primaryButton]} onPress={generateReport} disabled={loading}>
              {loading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.buttonText}>Generate Preview</Text>}
            </TouchableOpacity>
            <TouchableOpacity style={[styles.button, styles.resetButton]} onPress={resetForm}>
              <Text style={[styles.buttonText, { color: '#0b3d78' }]}>Reset</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Report Preview */}
        {previewData && (
          <View style={styles.sectionCard}>
            <View style={styles.previewHeader}>
              <View>
                <Text style={styles.previewTitle}>{previewData.title}</Text>
                <Text style={styles.previewSub}>
                  Period: {formatDate(dateFrom)} to {formatDate(dateTo)}
                  {statusFilter && ` • Filter: ${getStatusLabel(reportType, statusFilter)}`}
                  {locationFilter && reportType !== 'payments' && ` • Location contains “${locationFilter}”`}
                </Text>
              </View>
              <TouchableOpacity style={styles.exportButton} onPress={shareCSV}>
                <Text style={styles.exportButtonText}>📤 CSV</Text>
              </TouchableOpacity>
            </View>

            {/* Summary */}
            <View style={styles.summaryRow}>
              {Object.entries(previewData.summary).map(([label, value]) => (
                renderSummaryItem(label, value)
              ))}
            </View>

            {/* Records List */}
            {previewData.rows.length === 0 ? (
              <View style={styles.emptyState}>
                <Text style={styles.emptyText}>No records matched the selected report filters.</Text>
              </View>
            ) : (
              <View>
                {previewData.rows.map((item, index) => renderRecordItem({ item, index }))}
                <Text style={styles.recordCount}>Showing {previewData.rows.length} record(s).</Text>
              </View>
            )}
          </View>
        )}

        {/* Saved Report History */}
        <View style={styles.sectionCard}>
          <Text style={styles.sectionTitle}>Saved Report History</Text>
          <Text style={styles.sectionSub}>Metadata previously stored in the reports table.</Text>

          {history.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyText}>No saved report metadata was found. Generated previews are not automatically saved to the reports table.</Text>
            </View>
          ) : (
            history.map(item => renderHistoryItem({ item }))
          )}
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#f8fafc' },
  container: { flex: 1, padding: 16 },
  header: { marginBottom: 16 },
  pageTitle: { fontSize: 24, fontWeight: '700', color: '#0b3d78', marginBottom: 4 },
  pageSub: { fontSize: 14, color: '#64748b' },

  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  statCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 12,
    width: '48%',
    borderLeftWidth: 4,
    marginBottom: 10,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  statLabel: { fontSize: 12, color: '#64748b', marginBottom: 2 },
  statValue: { fontSize: 18, fontWeight: '700' },

  sectionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78' },
  sectionSub: { fontSize: 12, color: '#64748b', marginBottom: 12 },

  formGroup: { marginBottom: 12 },
  label: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginBottom: 4 },
  pickerWrapper: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    height: 44,
    justifyContent: 'center',
  },
  picker: { height: 44, width: '100%' },
  dateRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 12 },
  dateGroup: { flex: 1, marginRight: 8 },
  dateInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    height: 44,
    justifyContent: 'center',
  },
  textInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    height: 44,
    fontSize: 14,
  },
  disabledInput: { backgroundColor: '#f1f5f9', opacity: 0.6 },

  actionRow: { flexDirection: 'row', gap: 10, marginTop: 4 },
  button: {
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderRadius: 8,
    flex: 1,
    alignItems: 'center',
  },
  primaryButton: { backgroundColor: '#2563eb' },
  resetButton: { backgroundColor: '#f1f5f9' },
  buttonText: { fontSize: 14, fontWeight: '600', color: '#fff' },

  previewHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  previewTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78' },
  previewSub: { fontSize: 12, color: '#64748b' },
  exportButton: {
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 20,
  },
  exportButtonText: { fontSize: 12, fontWeight: '600', color: '#0b3d78' },

  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginBottom: 12,
  },
  summaryItem: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    padding: 8,
    marginRight: 8,
    marginBottom: 8,
    minWidth: '22%',
  },
  summaryLabel: { fontSize: 11, color: '#64748b' },
  summaryValue: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },

  emptyState: { padding: 20, alignItems: 'center' },
  emptyText: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },

  recordItem: {
    padding: 12,
    borderRadius: 8,
    marginBottom: 6,
    backgroundColor: '#f8fafc',
  },
  recordEven: { backgroundColor: '#f8fafc' },
  recordOdd: { backgroundColor: '#f1f5f9' },
  recordField: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 2 },
  recordLabel: { fontSize: 12, color: '#64748b' },
  recordValue: { fontSize: 13, fontWeight: '500', color: '#0b3d78' },

  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12, alignSelf: 'flex-start' },
  statusText: { fontSize: 11, fontWeight: '600' },

  recordCount: { fontSize: 12, color: '#94a3b8', marginTop: 8 },

  historyItem: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  historyRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  historyTitle: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  historyDetail: { fontSize: 13, color: '#1e293b' },
  historyMeta: { fontSize: 12, color: '#94a3b8' },
});