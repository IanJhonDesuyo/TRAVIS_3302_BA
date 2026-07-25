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
  Platform,
  Modal,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import DateTimePicker from '@react-native-community/datetimepicker';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import api from '../../api/axiosConfig';

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: 'rgba(247, 245, 238, 0.74)',
  header: '#102F49',
  headerAccent: '#16445D',
  surface: 'rgba(255, 253, 247, 0.92)',
  border: 'rgba(16, 47, 73, 0.24)',
  textPrimary: '#10202C',
  textSecondary: '#526B64',
  textTertiary: '#72847D',
  primary: '#087D78',
  success: '#15966F',
  warning: '#EB941F',
  danger: '#C84B45',
  neutral: '#8B9B96',
};

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });
const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

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

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;
const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'completed' || s === 'low' || s === 'none') return COLORS.success;
  if (s === 'pending' || s === 'overdue' || s === 'moderate') return COLORS.warning;
  if (s === 'cancelled' || s === 'failed' || s === 'high' || s === 'heavy' || s === 'critical') return COLORS.danger;
  return COLORS.neutral;
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

// ========== SCREEN ==========
export default function ReportsScreen() {
  const router = useRouter();

  const [reportType, setReportType] = useState<'violations' | 'payments' | 'monitoring'>('violations');
  const [dateFrom, setDateFrom] = useState(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
  const [dateTo, setDateTo] = useState(new Date());
  const [statusFilter, setStatusFilter] = useState('');
  const [locationFilter, setLocationFilter] = useState('');

  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [showDatePicker, setShowDatePicker] = useState<{ type: 'from' | 'to' | null }>({ type: null });

  const [previewData, setPreviewData] = useState<{ rows: ReportRecord[], summary: ReportSummary, title: string } | null>(null);
  const [history, setHistory] = useState<SavedReport[]>([]);
  const [totalReports, setTotalReports] = useState(0);
  const [reportsToday, setReportsToday] = useState(0);
  const [reportsThisMonth, setReportsThisMonth] = useState(0);
  const [lastGenerated, setLastGenerated] = useState<string | null>(null);
  const [previewPage, setPreviewPage] = useState(1);
  const [selectedRecord, setSelectedRecord] = useState<ReportRecord | null>(null);
  const [showAllHistory, setShowAllHistory] = useState(false);
  const PREVIEW_PAGE_SIZE = 8;

  // ===== FETCH HISTORY =====
  const fetchHistory = async () => {
    try {
      const res = await api.get('get_report_history.php');
      if (res.data.success) {
        setHistory(res.data.data);
        // Update stats based on history
        setTotalReports(res.data.data.length);
        const today = new Date().toISOString().slice(0, 10);
        const thisMonth = new Date().toISOString().slice(0, 7);
        setReportsToday(res.data.data.filter((h: any) => h.generated_at.startsWith(today)).length);
        setReportsThisMonth(res.data.data.filter((h: any) => h.generated_at.startsWith(thisMonth)).length);
        setLastGenerated(res.data.data.length > 0 ? res.data.data[0].generated_at : null);
      }
    } catch (error) {
      console.error('History error:', error);
    }
  };

  useEffect(() => {
    fetchHistory();
  }, []);

  // ===== GENERATE REPORT =====
  const generateReport = async () => {
    setLoading(true);
    try {
      const response = await api.post('generate_report.php', {
        report_type: reportType,
        date_from: dateFrom.toISOString().slice(0, 10),
        date_to: dateTo.toISOString().slice(0, 10),
        status: statusFilter,
        location: locationFilter,
      });
      if (response.data.success) {
        setPreviewData({
          rows: response.data.data || [],
          summary: response.data.summary || {},
          title: response.data.meta?.report_type || 'Report',
        });
        setPreviewPage(1);
        Alert.alert('Success', 'Report generated.');
      } else {
        Alert.alert('Error', response.data.error || 'Failed to generate report.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
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
    fetchHistory().then(() => setRefreshing(false));
  };

  const shareCSV = async () => {
    if (!previewData || previewData.rows.length === 0) {
      Alert.alert('No Data', 'There is no report data to share.');
      return;
    }
    const headers = Object.keys(previewData.rows[0]);
    const rows = previewData.rows.map(row => headers.map(key => row[key] ?? '').join(','));
    const csv = [headers.join(','), ...rows].join('\n');
    try {
      await Share.share({ message: csv, title: `${previewData.title} CSV` });
    } catch (error) {
      Alert.alert('Error', 'Unable to share CSV.');
    }
  };

  const formatDateDisplay = (date: Date) => date.toLocaleDateString('en-PH', { year: 'numeric', month: '2-digit', day: '2-digit' });

  // ========== RENDER HELPERS ==========
  const renderStatCard = (label: string, value: string | number, color: string = COLORS.primary) => (
    <View style={styles.statCard}>
      <View style={[styles.statAccentDot, { backgroundColor: color }]} />
      <Text style={styles.statLabel}>{label.toUpperCase()}</Text>
      <Text style={[styles.statValue, { color: COLORS.textPrimary }]}>{value}</Text>
    </View>
  );

  const renderSummaryItem = (label: string, value: string | number) => (
    <View style={styles.summaryItem}>
      <Text style={styles.summaryLabel}>{label.toUpperCase()}</Text>
      <Text style={styles.summaryValue}>{value}</Text>
    </View>
  );

  const renderRecordItem = ({ item, index }: { item: ReportRecord, index: number }) => {
    const ticket = item.ticket_number || item.receipt_number || `Record ${index + 1}`;
    const person = item.driver_name || item.camera_name || item.received_by_name || 'No name recorded';
    const secondary = item.plate_number || item.location || item.payment_method || '';
    const category = item.violation_type || item.congestion_level || item.payment_method || reportType;
    const date = item.violation_date || item.payment_date || item.recorded_at || item.created_at || '';
    const amount = item.penalty_amount ?? item.amount_paid;
    const status = String(item.status || item.payment_status || item.congestion_level || 'recorded');
    return (
      <TouchableOpacity key={index} style={styles.recordItem} onPress={() => setSelectedRecord(item)} activeOpacity={0.75}>
        <View style={styles.recordHeader}><Text style={styles.recordTicket} numberOfLines={1}>{ticket}</Text><View style={[styles.statusBadge, { backgroundColor: statusColor(status) + '1A' }]}><View style={[styles.statusBadgeDot, { backgroundColor: statusColor(status) }]} /><Text style={[styles.statusText, { color: statusColor(status) }]}>{status.toUpperCase()}</Text></View></View>
        <Text style={styles.recordPerson} numberOfLines={1}>{person}{secondary ? ` · ${secondary}` : ''}</Text>
        <View style={styles.recordFooter}><Text style={styles.recordMeta} numberOfLines={1}>{String(category).replace(/_/g, ' ')} · {String(date).slice(0, 10)}</Text>{amount !== undefined && amount !== null && <Text style={styles.recordAmount}>{formatCurrency(Number(amount))}</Text>}</View>
        <Text style={styles.viewHint}>Tap to view complete details</Text>
      </TouchableOpacity>
    );
  };

  const previewTotalPages = Math.max(1, Math.ceil((previewData?.rows.length || 0) / PREVIEW_PAGE_SIZE));
  const previewRows = previewData?.rows.slice((previewPage - 1) * PREVIEW_PAGE_SIZE, previewPage * PREVIEW_PAGE_SIZE) || [];

  const renderHistoryItem = (item: SavedReport) => (
    <View key={item.id} style={styles.historyItem}>
      <View style={styles.historyRow}>
        <Text style={styles.historyTitle}>{item.title}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
          <View style={[styles.statusBadgeDot, { backgroundColor: statusColor(item.status) }]} />
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.historyDetail}>
        {item.report_type.toUpperCase()} · {item.period_start} to {item.period_end}
      </Text>
      <Text style={styles.historyMeta}>
        GENERATED BY {item.generated_by_name.toUpperCase()} · {item.generated_at}
      </Text>
    </View>
  );

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.header} />
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <View style={styles.heroCard}>
          <View style={styles.brandRow}>
            <View style={styles.brandBadge}>
              <Ionicons name="document-text" size={16} color="#7DB4FF" />
            </View>
            <View>
              <Text style={styles.brandName}>REPORTS</Text>
              <Text style={styles.brandSubtitle}>Generate, preview, and export operational records</Text>
            </View>
          </View>
        </View>

        {/* Stats */}
        <View style={styles.statsRow}>
          {renderStatCard('Total Saved Reports', totalReports, COLORS.primary)}
          {renderStatCard('Generated Today', reportsToday, COLORS.success)}
          {renderStatCard('This Month', reportsThisMonth, COLORS.warning)}
          {renderStatCard('Last Generated', lastGenerated ? lastGenerated.split(' ')[0] : 'N/A', COLORS.neutral)}
        </View>

        {/* Report Generator */}
        <Text style={styles.sectionLabel}>REPORT GENERATOR</Text>
        <View style={styles.panel}>
          <Text style={styles.panelSub}>Choose a report type and date range, then generate a preview.</Text>

          <View style={styles.formGroup}>
            <Text style={styles.label}>Report Type</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={reportType}
                onValueChange={(itemValue) => setReportType(itemValue)}
                style={styles.picker}
                dropdownIconColor={COLORS.primary}
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
              <TouchableOpacity style={styles.dateInput} onPress={() => setShowDatePicker({ type: 'from' })} activeOpacity={0.7}>
                <Ionicons name="calendar-outline" size={14} color={COLORS.textTertiary} style={{ marginRight: 8 }} />
                <Text style={styles.dateInputText}>{formatDateDisplay(dateFrom)}</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.dateGroup}>
              <Text style={styles.label}>End Date</Text>
              <TouchableOpacity style={styles.dateInput} onPress={() => setShowDatePicker({ type: 'to' })} activeOpacity={0.7}>
                <Ionicons name="calendar-outline" size={14} color={COLORS.textTertiary} style={{ marginRight: 8 }} />
                <Text style={styles.dateInputText}>{formatDateDisplay(dateTo)}</Text>
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
                dropdownIconColor={COLORS.primary}
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
              placeholderTextColor={COLORS.textTertiary}
              editable={reportType !== 'payments'}
            />
          </View>

          <View style={styles.actionRow}>
            <TouchableOpacity style={[styles.button, styles.primaryButton]} onPress={generateReport} disabled={loading} activeOpacity={0.85}>
              {loading ? <ActivityIndicator color="#fff" size="small" /> : <Text style={styles.buttonText}>Generate Preview</Text>}
            </TouchableOpacity>
            <TouchableOpacity style={[styles.button, styles.resetButton]} onPress={resetForm} activeOpacity={0.7}>
              <Text style={[styles.buttonText, { color: COLORS.primary }]}>Reset</Text>
            </TouchableOpacity>
          </View>
        </View>

        {/* Report Preview */}
        {previewData && (
          <>
            <View style={styles.sectionHeaderRow}>
              <Text style={styles.sectionLabel}>{previewData.title.toUpperCase()}</Text>
              <TouchableOpacity style={styles.exportButton} onPress={shareCSV} activeOpacity={0.7}>
                <Ionicons name="share-outline" size={13} color={COLORS.primary} />
                <Text style={styles.exportButtonText}>CSV</Text>
              </TouchableOpacity>
            </View>
            <View style={styles.panel}>
              <Text style={styles.panelSub}>
                Period: {dateFrom.toISOString().slice(0, 10)} to {dateTo.toISOString().slice(0, 10)}
                {statusFilter && ` · Filter: ${statusFilter}`}
                {locationFilter && reportType !== 'payments' && ` · Location contains "${locationFilter}"`}
              </Text>

              {/* Summary */}
              <View style={styles.summaryRow}>
                {Object.entries(previewData.summary).map(([label, value]) => (
                  <React.Fragment key={label}>{renderSummaryItem(label, value)}</React.Fragment>
                ))}
              </View>

              {/* Records List */}
              {previewData.rows.length === 0 ? (
                <View style={styles.emptyState}>
                  <Ionicons name="document-outline" size={16} color={COLORS.textTertiary} />
                  <Text style={styles.emptyText}>No records matched the selected report filters.</Text>
                </View>
              ) : (
                <View>
                  <View style={styles.panelDivider} />
                  {previewRows.map((item, index) => renderRecordItem({ item, index: (previewPage - 1) * PREVIEW_PAGE_SIZE + index }))}
                  <Text style={styles.recordCount}>SHOWING {(previewPage - 1) * PREVIEW_PAGE_SIZE + 1}–{Math.min(previewPage * PREVIEW_PAGE_SIZE, previewData.rows.length)} OF {previewData.rows.length} RECORD(S)</Text>
                  {previewData.rows.length > PREVIEW_PAGE_SIZE && <View style={styles.pagination}><TouchableOpacity disabled={previewPage === 1} onPress={() => setPreviewPage(value => value - 1)} style={[styles.pageButton, previewPage === 1 && styles.pageButtonDisabled]}><Text style={[styles.pageButtonText, previewPage === 1 && styles.pageButtonTextDisabled]}>Previous</Text></TouchableOpacity><Text style={styles.pageLabel}>{previewPage} / {previewTotalPages}</Text><TouchableOpacity disabled={previewPage === previewTotalPages} onPress={() => setPreviewPage(value => value + 1)} style={[styles.pageButton, previewPage === previewTotalPages && styles.pageButtonDisabled]}><Text style={[styles.pageButtonText, previewPage === previewTotalPages && styles.pageButtonTextDisabled]}>Next</Text></TouchableOpacity></View>}
                </View>
              )}
            </View>
          </>
        )}

        {/* Saved Report History */}
        <Text style={styles.sectionLabel}>SAVED REPORT HISTORY</Text>
        <View style={styles.panel}>
          <Text style={styles.panelSub}>Metadata previously stored in the reports table.</Text>

          {history.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="folder-open-outline" size={16} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>No saved report metadata was found.</Text>
            </View>
          ) : (
            <>
              <View style={styles.panelDivider} />
              {(showAllHistory ? history : history.slice(0, 5)).map(item => renderHistoryItem(item))}
              {history.length > 5 && <TouchableOpacity style={styles.showMoreButton} onPress={() => setShowAllHistory(value => !value)}><Text style={styles.showMoreText}>{showAllHistory ? 'Show less' : `Show all ${history.length} reports`}</Text></TouchableOpacity>}
            </>
          )}
        </View>

        <View style={{ height: 40 }} />
      </ScrollView>
      <Modal visible={!!selectedRecord} transparent animationType="slide" onRequestClose={() => setSelectedRecord(null)}>
        <View style={styles.modalOverlay}><View style={styles.detailSheet}><View style={styles.detailHeader}><View><Text style={styles.detailTitle}>Record Details</Text><Text style={styles.detailSub}>Complete database information</Text></View><TouchableOpacity onPress={() => setSelectedRecord(null)} style={styles.closeButton}><Ionicons name="close" size={21} color={COLORS.textSecondary} /></TouchableOpacity></View><ScrollView showsVerticalScrollIndicator={false}>{selectedRecord && Object.entries(selectedRecord).map(([key, value]) => <View key={key} style={styles.detailField}><Text style={styles.detailLabel}>{key.replace(/_/g, ' ').toUpperCase()}</Text><Text style={styles.detailValue} selectable>{value === null || value === '' ? '—' : String(value)}</Text></View>)}</ScrollView></View></View>
      </Modal>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1 },
  scrollContent: { paddingHorizontal: 20, paddingTop: 18, paddingBottom: 20 },

  heroCard: {
    backgroundColor: COLORS.header, borderRadius: 22, padding: 20, marginBottom: 16,
    ...softShadow, shadowOpacity: 0.18,
  },
  brandRow: { flexDirection: 'row', alignItems: 'center' },
  brandBadge: {
    width: 32, height: 32, borderRadius: 10, backgroundColor: COLORS.headerAccent,
    justifyContent: 'center', alignItems: 'center', marginRight: 10,
  },
  brandName: { fontSize: 18, fontWeight: '800', color: '#FFFFFF', letterSpacing: 1 },
  brandSubtitle: { fontSize: 11, color: '#94A3B8', marginTop: 2, maxWidth: 260 },

  statsRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', marginBottom: 4 },
  statCard: {
    backgroundColor: COLORS.surface, borderRadius: 16, padding: 14, width: '48%',
    marginBottom: 12, borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  statAccentDot: { width: 8, height: 8, borderRadius: 4, marginBottom: 8 },
  statLabel: { fontSize: 10, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6, marginBottom: 4 },
  statValue: { fontSize: 17, fontWeight: '700', fontFamily: mono },

  sectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12, marginTop: 4 },
  sectionHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, marginTop: 4 },

  panel: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 20,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  panelSub: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 16, lineHeight: 17 },
  panelDivider: { height: 1, backgroundColor: COLORS.border, marginBottom: 14 },

  formGroup: { marginBottom: 14 },
  label: { fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 0.3, marginBottom: 6 },
  pickerWrapper: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    height: 46, justifyContent: 'center', overflow: 'hidden',
  },
  picker: { height: 46, width: '100%', color: COLORS.textPrimary },

  dateRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 14, gap: 10 },
  dateGroup: { flex: 1 },
  dateInput: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.bg, borderRadius: 12,
    borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 12, height: 46,
  },
  dateInputText: { fontSize: 13, color: COLORS.textPrimary, fontFamily: mono },

  textInput: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 46, fontSize: 14, color: COLORS.textPrimary,
  },
  disabledInput: { backgroundColor: '#F1F5F9', opacity: 0.6 },

  actionRow: { flexDirection: 'row', gap: 10, marginTop: 6 },
  button: { paddingVertical: 13, paddingHorizontal: 16, borderRadius: 12, flex: 1, alignItems: 'center' },
  primaryButton: { backgroundColor: COLORS.primary, ...softShadow, shadowOpacity: 0.15 },
  resetButton: { backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border },
  buttonText: { fontSize: 14, fontWeight: '700', color: '#fff' },

  exportButton: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 20,
    paddingHorizontal: 12, paddingVertical: 6, ...softShadow,
  },
  exportButtonText: { fontSize: 11, fontWeight: '700', color: COLORS.primary, marginLeft: 5, letterSpacing: 0.3 },

  summaryRow: { flexDirection: 'row', flexWrap: 'wrap', marginBottom: 4 },
  summaryItem: {
    backgroundColor: COLORS.bg, borderRadius: 12, padding: 10, marginRight: 8, marginBottom: 8,
    minWidth: '22%', borderWidth: 1, borderColor: COLORS.border,
  },
  summaryLabel: { fontSize: 9, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.5, marginBottom: 3 },
  summaryValue: { fontSize: 14, fontWeight: '700', color: COLORS.textPrimary },

  emptyState: { flexDirection: 'row', alignItems: 'center', paddingVertical: 16, gap: 8 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, flex: 1, lineHeight: 18 },

  recordItem: {
    padding: 14, borderRadius: 14, marginBottom: 10, backgroundColor: COLORS.bg,
    borderWidth: 1, borderColor: COLORS.border,
  },
  recordHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: 8 },
  recordTicket: { flex: 1, fontSize: 13, fontWeight: '800', color: COLORS.textPrimary, fontFamily: mono },
  recordPerson: { fontSize: 13, color: COLORS.textSecondary, marginTop: 8 },
  recordFooter: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, marginTop: 9 },
  recordMeta: { flex: 1, fontSize: 11, color: COLORS.textTertiary, textTransform: 'capitalize' },
  recordAmount: { fontSize: 14, fontWeight: '800', color: COLORS.primary },
  viewHint: { fontSize: 10, color: COLORS.primary, marginTop: 9, fontWeight: '700' },

  statusBadge: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 3,
    borderRadius: 8, alignSelf: 'flex-start',
  },
  statusBadgeDot: { width: 5, height: 5, borderRadius: 2.5, marginRight: 5 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.4, fontFamily: mono },

  recordCount: { fontSize: 10, color: COLORS.textTertiary, marginTop: 6, letterSpacing: 0.5, fontFamily: mono },
  pagination: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 14 },
  pageButton: { backgroundColor: COLORS.primary, borderRadius: 9, paddingHorizontal: 14, paddingVertical: 9 },
  pageButtonDisabled: { backgroundColor: '#E2E8F0' },
  pageButtonText: { color: '#FFF', fontSize: 12, fontWeight: '700' },
  pageButtonTextDisabled: { color: COLORS.textTertiary },
  pageLabel: { color: COLORS.textSecondary, fontSize: 12, fontWeight: '700' },

  historyItem: { paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  historyRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 5 },
  historyTitle: { fontSize: 14, fontWeight: '700', color: COLORS.textPrimary, flex: 1, marginRight: 8 },
  historyDetail: { fontSize: 12, color: COLORS.textSecondary, marginBottom: 3 },
  historyMeta: { fontSize: 10, color: COLORS.textTertiary, letterSpacing: 0.3, fontFamily: mono },
  showMoreButton: { alignItems: 'center', paddingVertical: 13, marginTop: 5 },
  showMoreText: { color: COLORS.primary, fontWeight: '800', fontSize: 12 },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,.55)', justifyContent: 'flex-end' },
  detailSheet: { backgroundColor: '#FFF', maxHeight: '82%', borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 20 },
  detailHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  detailTitle: { fontSize: 19, fontWeight: '800', color: COLORS.textPrimary },
  detailSub: { fontSize: 11, color: COLORS.textTertiary, marginTop: 2 },
  closeButton: { width: 38, height: 38, borderRadius: 19, backgroundColor: COLORS.bg, alignItems: 'center', justifyContent: 'center' },
  detailField: { paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: '#E8EEE9' },
  detailLabel: { fontSize: 9, fontWeight: '800', color: COLORS.textTertiary, letterSpacing: .6, marginBottom: 5 },
  detailValue: { fontSize: 13, lineHeight: 19, color: COLORS.textPrimary, flexShrink: 1 },
});
