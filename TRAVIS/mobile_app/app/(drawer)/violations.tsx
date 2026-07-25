import React, { useState, useCallback } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  FlatList,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  StatusBar,
  RefreshControl,
  Modal,
  Alert,
  Platform,
} from 'react-native';
import { Href, useRouter, useSegments } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../../api/axiosConfig';
import * as ImagePicker from 'expo-image-picker';

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: 'rgba(247, 245, 238, 0.74)',
  header: '#102F49',
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

// ========== TYPES ==========
interface Violation {
  id: number;
  ticketNumber: string;
  driverName: string;
  licenseNumber: string;
  plateNumber: string;
  vehicleType: string;
  violationType: string;
  location: string;
  date: string;
  time: string;
  penalty: number;
  status: 'pending' | 'overdue' | 'paid' | 'cancelled';
  createdAt: string;
}

type StatusFilter = '' | 'pending' | 'overdue' | 'paid' | 'cancelled';

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `\u20b1${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'resolved') return COLORS.success;
  if (s === 'pending') return COLORS.warning;
  if (s === 'overdue' || s === 'critical') return COLORS.danger;
  if (s === 'cancelled') return COLORS.neutral;
  return COLORS.neutral;
};

const STATUS_FILTERS: { label: string; value: StatusFilter }[] = [
  { label: 'All', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Overdue', value: 'overdue' },
  { label: 'Paid', value: 'paid' },
  { label: 'Cancelled', value: 'cancelled' },
];

// ========== SCREEN ==========
export default function ViolationsScreen() {
  const router = useRouter();
  const segments = useSegments();
  const [loading, setLoading] = useState(true);
  const [violations, setViolations] = useState<Violation[]>([]);
  const [filtered, setFiltered] = useState<Violation[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [refreshing, setRefreshing] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [page, setPage] = useState(1);
  const PAGE_SIZE = 10;

  const [formData, setFormData] = useState({
    driver_name: '',
    license_number: '',
    plate_number: '',
    vehicle_type: 'Car',
    violation_type: '',
    location: '',
    penalty_amount: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [inputMethod, setInputMethod] = useState<'manual' | 'ocr'>('manual');
  const [ocrNotice, setOcrNotice] = useState('');
  const [selectedRecord, setSelectedRecord] = useState<Violation | null>(null);

  const scanTicket = async (source: 'camera' | 'gallery') => {
    const permission = source === 'camera'
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Permission required', `Allow ${source === 'camera' ? 'camera' : 'photo library'} access to scan a ticket.`);
      return;
    }
    const result = source === 'camera'
      ? await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 0.9, allowsEditing: true })
      : await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.9, allowsEditing: true });
    if (result.canceled || !result.assets[0]) return;

    setScanning(true);
    setOcrNotice('Reading ticket…');
    try {
      const asset = result.assets[0];
      const body = new FormData();
      body.append('ticket', { uri: asset.uri, name: asset.fileName || 'ticket.jpg', type: asset.mimeType || 'image/jpeg' } as any);
      const response = await api.post('scan_ticket.php', body, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 60000 });
      const fields = response.data.fields || {};
      setFormData(current => ({
        ...current,
        driver_name: fields.driver_name || current.driver_name,
        license_number: fields.license_number || current.license_number,
        plate_number: fields.plate_number || current.plate_number,
        vehicle_type: fields.vehicle_type || current.vehicle_type,
        violation_type: fields.violation_type || current.violation_type,
        location: fields.location || current.location,
        penalty_amount: fields.penalty_amount || current.penalty_amount,
      }));
      setInputMethod('ocr');
      setOcrNotice(`${response.data.recognized_fields || 0} fields detected · ${Math.round((response.data.confidence || 0) * 100)}% text confidence. Review all values.`);
    } catch (error: any) {
      setOcrNotice('');
      Alert.alert('Ticket scan failed', error.response?.data?.error || 'The ticket could not be read. Try a clearer, well-lit photo.');
    } finally { setScanning(false); }
  };

  // ===== FETCH VIOLATIONS =====
  const fetchViolations = async () => {
    try {
      setLoading(true);
      const response = await api.get('get_violations.php', {
        params: { status: statusFilter, search, limit: 100 },
      });
      if (response.data.success) {
        const data = response.data.data.map((item: any) => ({
          id: item.violation_id,
          ticketNumber: item.ticket_number,
          driverName: item.driver_name,
          licenseNumber: item.license_number,
          plateNumber: item.plate_number,
          vehicleType: item.vehicle_type,
          violationType: item.violation_type,
          location: item.violation_location,
          date: item.violation_date,
          time: item.violation_time,
          penalty: parseFloat(item.penalty_amount),
          status: item.status,
          createdAt: item.created_at,
        }));
        setViolations(data);
        setFiltered(data);
        setPage(1);
      }
    } catch (error) {
      console.error('Fetch violations error:', error);
      Alert.alert('Error', 'Failed to load violations.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchViolations();
    }, [statusFilter, search])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchViolations();
  };

  const clearFilters = () => {
    setSearch('');
    setStatusFilter('');
  };

  const totalPages = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const visibleRecords = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  // ===== ADD VIOLATION =====
  const handleAddViolation = async () => {
    const { driver_name, license_number, plate_number, vehicle_type, violation_type, location, penalty_amount } = formData;
    if (!driver_name || !license_number || !plate_number || !violation_type || !location || !penalty_amount) {
      Alert.alert('Error', 'Please fill in all fields.');
      return;
    }
    setSubmitting(true);
    try {
      const response = await api.post('add_violations.php', {
        driver_name,
        license_number,
        plate_number,
        vehicle_type,
        violation_type,
        location,
        penalty_amount: parseFloat(penalty_amount),
        input_method: inputMethod,
      });
      if (response.data.success) {
        Alert.alert('Success', 'Violation added successfully.');
        setModalVisible(false);
        setFormData({ driver_name: '', license_number: '', plate_number: '', vehicle_type: 'Car', violation_type: '', location: '', penalty_amount: '' });
        setInputMethod('manual');
        setOcrNotice('');
        fetchViolations();
      } else {
        Alert.alert('Error', response.data.error || 'Failed to add violation.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.error || 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  const openPayment = (item: Violation) => {
    const route = segments.includes('(treasurer)' as never)
      ? `/(treasurer)/payments?violation_id=${item.id}`
      : `/(drawer)/payments?violation_id=${item.id}`;
    router.push(route as Href);
  };

  const cancelViolation = (item: Violation) => Alert.alert(
    'Cancel Violation',
    `Cancel ticket ${item.ticketNumber}? This removes it from pending collections.`,
    [
      { text: 'Keep Record', style: 'cancel' },
      { text: 'Cancel Violation', style: 'destructive', onPress: async () => {
        try {
          await api.post('update_violation_status.php', { violation_id: item.id, status: 'cancelled' });
          Alert.alert('Updated', 'The violation has been cancelled.');
          fetchViolations();
        } catch (error: any) {
          Alert.alert('Unable to cancel', error.response?.data?.error || 'Please try again.');
        }
      } },
    ],
  );

  // ========== RENDER HELPERS ==========
  const renderSummaryCell = (icon: React.ReactNode, label: string, value: number, isLast: boolean) => (
    <View style={[styles.summaryCell, !isLast && styles.summaryCellDivider]}>
      {icon}
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );

  const renderViolationItem = ({ item }: { item: Violation }) => (
    <View style={styles.violationCard}>
      <View style={styles.violationRow}>
        <Text style={styles.ticketNumber}>{item.ticketNumber}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>

      <Text style={styles.driverInfo}>{item.driverName} · {item.licenseNumber}</Text>
      <Text style={styles.vehicleInfo}>{item.plateNumber} · {item.vehicleType}</Text>

      <View style={styles.violationMetaRow}>
        <Text style={styles.violationType}>{item.violationType}</Text>
        <Text style={styles.location}>{item.location}</Text>
      </View>

      <View style={styles.violationDivider} />

      <View style={styles.violationFooter}>
        <Text style={styles.dateTime}>{item.date} · {item.time}</Text>
        <Text style={styles.penalty}>{formatCurrency(item.penalty)}</Text>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButtonOutline} onPress={() => setSelectedRecord(item)}>
          <Text style={styles.actionTextOutline}>View</Text>
        </TouchableOpacity>
        {(item.status === 'pending' || item.status === 'overdue') && (
          <>
            <TouchableOpacity style={styles.payButton} onPress={() => openPayment(item)}>
              <Text style={styles.payButtonText}>Pay</Text>
            </TouchableOpacity>
            <TouchableOpacity style={styles.cancelButton} onPress={() => cancelViolation(item)}>
              <Text style={styles.cancelButtonText}>Cancel</Text>
            </TouchableOpacity>
          </>
        )}
      </View>
    </View>
  );

  // ===== COMPUTE COUNTS =====
  const counts = {
    today: violations.filter(v => v.date === new Date().toISOString().slice(0, 10)).length,
    awaiting: violations.filter(v => v.status === 'pending' || v.status === 'overdue').length,
    paid: violations.filter(v => v.status === 'paid').length,
    cancelled: violations.filter(v => v.status === 'cancelled').length,
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading violations…</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor={COLORS.bg} />

      <ScrollView
        style={styles.container}
        contentContainerStyle={{ paddingBottom: 100 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.eyebrow}>VIOLATION MANAGEMENT</Text>
          <Text style={styles.pageTitle}>Violation Records</Text>
          <Text style={styles.pageSub}>Record, review, and route unpaid traffic violations to the payment module.</Text>
        </View>

        {/* Summary panel */}
        <View style={styles.summaryPanel}>
          {renderSummaryCell(<Ionicons name="calendar-outline" size={16} color={COLORS.warning} />, 'Recorded Today', counts.today, false)}
          {renderSummaryCell(<Ionicons name="alert-circle-outline" size={16} color={COLORS.danger} />, 'Awaiting Payment', counts.awaiting, false)}
          {renderSummaryCell(<Ionicons name="checkmark-circle-outline" size={16} color={COLORS.success} />, 'Paid', counts.paid, false)}
          {renderSummaryCell(<Ionicons name="close-circle-outline" size={16} color={COLORS.neutral} />, 'Cancelled', counts.cancelled, true)}
        </View>

        {/* Search */}
        <View style={styles.searchWrap}>
          <Ionicons name="search" size={16} color={COLORS.textTertiary} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder="Ticket, driver, plate, violation, location..."
            placeholderTextColor={COLORS.textTertiary}
            value={search}
            onChangeText={setSearch}
          />
          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Ionicons name="close-circle" size={16} color={COLORS.textTertiary} />
            </TouchableOpacity>
          )}
        </View>

        {/* Status filter chips */}
        <View style={styles.filterRow}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingRight: 20 }}>
            {STATUS_FILTERS.map(f => {
              const active = statusFilter === f.value;
              return (
                <TouchableOpacity
                  key={f.label}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => setStatusFilter(f.value)}
                  activeOpacity={0.7}
                >
                  <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{f.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
          {(search.trim() || statusFilter) && (
            <TouchableOpacity onPress={clearFilters} style={styles.clearLink}>
              <Text style={styles.clearLinkText}>Clear</Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Result count */}
        <Text style={styles.resultCount}>{filtered.length} record{filtered.length === 1 ? '' : 's'} found</Text>

        {/* Violations list */}
        <FlatList
          data={visibleRecords}
          renderItem={renderViolationItem}
          keyExtractor={item => item.id.toString()}
          scrollEnabled={false}
          ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
          ListEmptyComponent={
            <View style={styles.emptyState}>
              <Ionicons name="document-text-outline" size={28} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>No violation records matched your search or filter.</Text>
              {(search.trim() || statusFilter) && (
                <TouchableOpacity onPress={clearFilters} style={styles.emptyClearButton}>
                  <Text style={styles.emptyClearButtonText}>Clear filters</Text>
                </TouchableOpacity>
              )}
            </View>
          }
        />
        {filtered.length > PAGE_SIZE && (
          <View style={styles.pagination}>
            <TouchableOpacity disabled={page === 1} onPress={() => setPage(value => Math.max(1, value - 1))} style={[styles.pageButton, page === 1 && styles.pageButtonDisabled]}>
              <Ionicons name="chevron-back" size={16} color={page === 1 ? COLORS.textTertiary : '#FFF'} />
              <Text style={[styles.pageButtonText, page === 1 && styles.pageButtonTextDisabled]}>Previous</Text>
            </TouchableOpacity>
            <Text style={styles.pageLabel}>Page {page} of {totalPages}</Text>
            <TouchableOpacity disabled={page === totalPages} onPress={() => setPage(value => Math.min(totalPages, value + 1))} style={[styles.pageButton, page === totalPages && styles.pageButtonDisabled]}>
              <Text style={[styles.pageButtonText, page === totalPages && styles.pageButtonTextDisabled]}>Next</Text>
              <Ionicons name="chevron-forward" size={16} color={page === totalPages ? COLORS.textTertiary : '#FFF'} />
            </TouchableOpacity>
          </View>
        )}
      </ScrollView>

      {/* Floating Add button */}
      <TouchableOpacity style={styles.fab} onPress={() => setModalVisible(true)} activeOpacity={0.85}>
        <Ionicons name="add" size={22} color="#FFFFFF" />
        <Text style={styles.fabText}>Add Violation</Text>
      </TouchableOpacity>

      {/* Add Violation Modal */}
      <Modal animationType="slide" transparent visible={modalVisible} onRequestClose={() => setModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeaderRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.modalTitle}>Add Violation</Text>
                <Text style={styles.modalSub}>Scan a paper ticket or enter the details manually.</Text>
              </View>
              <TouchableOpacity onPress={() => setModalVisible(false)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={22} color={COLORS.textTertiary} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false}>
              <View style={styles.scanPanel}>
                <View style={{ flex: 1 }}><Text style={styles.scanTitle}>OCR Ticket Scanner</Text><Text style={styles.scanSub}>Use a flat, well-lit image with the full ticket visible.</Text></View>
                {scanning && <ActivityIndicator color={COLORS.primary} />}
              </View>
              <View style={styles.scanActions}>
                <TouchableOpacity disabled={scanning} style={styles.scanPrimary} onPress={() => scanTicket('camera')}><Ionicons name="camera-outline" size={17} color="#FFF" /><Text style={styles.scanPrimaryText}>Take Photo</Text></TouchableOpacity>
                <TouchableOpacity disabled={scanning} style={styles.scanSecondary} onPress={() => scanTicket('gallery')}><Ionicons name="images-outline" size={17} color={COLORS.primary} /><Text style={styles.scanSecondaryText}>Gallery</Text></TouchableOpacity>
              </View>
              {!!ocrNotice && <View style={styles.ocrNotice}><Ionicons name="warning-outline" size={16} color={COLORS.warning} /><Text style={styles.ocrNoticeText}>{ocrNotice}</Text></View>}
              <Text style={styles.modalLabel}>Driver Name</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Driver name"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.driver_name}
                onChangeText={text => setFormData({ ...formData, driver_name: text })}
              />
              <Text style={styles.modalLabel}>License Number</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="License number"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.license_number}
                onChangeText={text => setFormData({ ...formData, license_number: text })}
              />
              <Text style={styles.modalLabel}>Plate Number</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Plate number"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.plate_number}
                onChangeText={text => setFormData({ ...formData, plate_number: text })}
              />
              <Text style={styles.modalLabel}>Violation Type</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Violation type"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.violation_type}
                onChangeText={text => setFormData({ ...formData, violation_type: text })}
              />
              <Text style={styles.modalLabel}>Location</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Location"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.location}
                onChangeText={text => setFormData({ ...formData, location: text })}
              />
              <Text style={styles.modalLabel}>Penalty Amount</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Penalty amount"
                placeholderTextColor={COLORS.textTertiary}
                keyboardType="numeric"
                value={formData.penalty_amount}
                onChangeText={text => setFormData({ ...formData, penalty_amount: text })}
              />
            </ScrollView>

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelModalButton}
                onPress={() => setModalVisible(false)}
                disabled={submitting}
              >
                <Text style={styles.cancelModalButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.saveModalButton}
                onPress={handleAddViolation}
                disabled={submitting}
              >
                {submitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.saveModalButtonText}>Save Violation</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
      <Modal animationType="slide" transparent visible={!!selectedRecord} onRequestClose={() => setSelectedRecord(null)}>
        <View style={styles.modalOverlay}><View style={styles.detailSheet}><View style={styles.modalHeaderRow}><View style={{ flex: 1 }}><Text style={styles.modalTitle}>{selectedRecord?.ticketNumber}</Text><Text style={styles.modalSub}>Complete violation information</Text></View><TouchableOpacity onPress={() => setSelectedRecord(null)}><Ionicons name="close" size={22} color={COLORS.textTertiary} /></TouchableOpacity></View><ScrollView>{selectedRecord && [
          ['Driver', selectedRecord.driverName], ['License Number', selectedRecord.licenseNumber], ['Plate Number', selectedRecord.plateNumber], ['Vehicle', selectedRecord.vehicleType], ['Violation', selectedRecord.violationType], ['Location', selectedRecord.location], ['Date & Time', `${selectedRecord.date} ${selectedRecord.time}`], ['Penalty', formatCurrency(selectedRecord.penalty)], ['Status', selectedRecord.status.toUpperCase()],
        ].map(([label, value]) => <View key={label} style={styles.detailRow}><Text style={styles.detailLabel}>{label}</Text><Text style={styles.detailValue}>{value}</Text></View>)}</ScrollView></View></View>
      </Modal>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1, paddingHorizontal: 20 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.bg },
  loadingText: { marginTop: 12, fontSize: 14, color: COLORS.textSecondary },

  header: { paddingTop: 18, marginBottom: 18 },
  eyebrow: { fontSize: 11, fontWeight: '700', color: COLORS.primary, letterSpacing: 1, marginBottom: 6 },
  pageTitle: { fontSize: 26, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 6, letterSpacing: -0.3 },
  pageSub: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  summaryPanel: {
    flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 18,
    borderWidth: 1, borderColor: COLORS.border, paddingVertical: 16, marginBottom: 18, ...softShadow,
  },
  summaryCell: { flex: 1, alignItems: 'center', paddingHorizontal: 4 },
  summaryCellDivider: { borderRightWidth: 1, borderRightColor: COLORS.border },
  summaryValue: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 10, color: COLORS.textTertiary, textAlign: 'center' },

  searchWrap: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 44, marginBottom: 12,
  },
  searchIcon: { marginRight: 8 },
  searchInput: { flex: 1, fontSize: 14, color: COLORS.textPrimary },

  filterRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },
  clearLink: { paddingLeft: 4 },
  clearLinkText: { fontSize: 12, fontWeight: '700', color: COLORS.primary },

  resultCount: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 12 },

  violationCard: {
    backgroundColor: COLORS.surface, borderRadius: 16, padding: 16,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  violationRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  ticketNumber: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },
  statusBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.3 },

  driverInfo: { fontSize: 14, color: COLORS.textPrimary, marginBottom: 2 },
  vehicleInfo: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 8 },

  violationMetaRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  violationType: { fontSize: 13, fontWeight: '600', color: COLORS.primary },
  location: { fontSize: 12, color: COLORS.textTertiary },

  violationDivider: { height: 1, backgroundColor: COLORS.border, marginVertical: 12 },

  violationFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  dateTime: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
  penalty: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },

  actionRow: { flexDirection: 'row', gap: 8 },
  actionButtonOutline: {
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8,
    borderWidth: 1, borderColor: COLORS.border,
  },
  actionTextOutline: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  payButton: { backgroundColor: COLORS.success, paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
  payButtonText: { fontSize: 12, fontWeight: '700', color: '#FFFFFF' },
  cancelButton: { backgroundColor: COLORS.danger + '14', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
  cancelButtonText: { fontSize: 12, fontWeight: '700', color: COLORS.danger },

  emptyState: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 10, lineHeight: 18, paddingHorizontal: 20 },
  emptyClearButton: { marginTop: 14, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 8, backgroundColor: COLORS.primary + '14' },
  emptyClearButtonText: { fontSize: 12, fontWeight: '700', color: COLORS.primary },
  pagination: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18 },
  pageButton: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, paddingHorizontal: 13, paddingVertical: 9, borderRadius: 9 },
  pageButtonDisabled: { backgroundColor: '#E2E8F0' },
  pageButtonText: { color: '#FFF', fontSize: 12, fontWeight: '700' },
  pageButtonTextDisabled: { color: COLORS.textTertiary },
  pageLabel: { color: COLORS.textSecondary, fontSize: 12, fontWeight: '700' },

  fab: {
    position: 'absolute', right: 20, bottom: 24,
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.primary,
    paddingHorizontal: 18, paddingVertical: 14, borderRadius: 28,
    shadowColor: COLORS.primary, shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.3, shadowRadius: 12, elevation: 6,
  },
  fabText: { fontSize: 14, fontWeight: '700', color: '#FFFFFF', marginLeft: 6 },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.5)', justifyContent: 'center', alignItems: 'center' },
  modalContent: {
    backgroundColor: COLORS.surface, borderRadius: 20, padding: 22, width: '90%', maxHeight: '82%',
  },
  detailSheet: { backgroundColor: COLORS.surface, borderTopLeftRadius: 22, borderTopRightRadius: 22, padding: 22, width: '100%', maxHeight: '82%', position: 'absolute', bottom: 0 },
  detailRow: { paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  detailLabel: { color: COLORS.textTertiary, fontSize: 10, fontWeight: '800', textTransform: 'uppercase', marginBottom: 4 },
  detailValue: { color: COLORS.textPrimary, fontSize: 14, lineHeight: 20 },
  modalHeaderRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 16 },
  modalTitle: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 2 },
  modalSub: { fontSize: 12, color: COLORS.textSecondary },
  scanPanel: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#EAF6F5', borderRadius: 12, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#B9DDDA' },
  scanTitle: { fontSize: 13, fontWeight: '800', color: COLORS.primary },
  scanSub: { fontSize: 10, color: COLORS.textSecondary, marginTop: 3, lineHeight: 14 },
  scanActions: { flexDirection: 'row', gap: 9, marginBottom: 4 },
  scanPrimary: { flex: 1, flexDirection: 'row', gap: 6, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.primary, borderRadius: 10, paddingVertical: 11 },
  scanPrimaryText: { color: '#FFF', fontSize: 12, fontWeight: '800' },
  scanSecondary: { flex: 1, flexDirection: 'row', gap: 6, justifyContent: 'center', alignItems: 'center', backgroundColor: '#FFF', borderRadius: 10, paddingVertical: 11, borderWidth: 1, borderColor: COLORS.primary },
  scanSecondaryText: { color: COLORS.primary, fontSize: 12, fontWeight: '800' },
  ocrNotice: { flexDirection: 'row', gap: 7, alignItems: 'flex-start', backgroundColor: '#FFF7E8', padding: 10, borderRadius: 10, marginTop: 8 },
  ocrNoticeText: { flex: 1, color: '#7C5310', fontSize: 10, lineHeight: 15 },
  modalLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginTop: 12, marginBottom: 6 },
  modalInput: {
    backgroundColor: COLORS.bg, borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10,
    borderWidth: 1, borderColor: COLORS.border, fontSize: 14, color: COLORS.textPrimary,
  },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', marginTop: 18, gap: 10 },
  cancelModalButton: { backgroundColor: COLORS.bg, paddingHorizontal: 18, paddingVertical: 11, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border },
  cancelModalButtonText: { fontSize: 13, fontWeight: '600', color: COLORS.textSecondary },
  saveModalButton: { backgroundColor: COLORS.primary, paddingHorizontal: 18, paddingVertical: 11, borderRadius: 10 },
  saveModalButtonText: { fontSize: 13, fontWeight: '700', color: '#FFFFFF' },
});
