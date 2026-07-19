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
  Modal,
  Alert,
  FlatList,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

// ========== COLOR TOKENS ==========
// Same tokens as the rest of TRAVIS (light hybrid theme) for consistency.
const COLORS = {
  bg: '#F8FAFC',
  header: '#0F172A',
  surface: '#FFFFFF',
  border: '#E2E8F0',
  textPrimary: '#0F172A',
  textSecondary: '#64748B',
  textTertiary: '#94A3B8',
  primary: '#2563EB',
  success: '#10B981',
  warning: '#F59E0B',
  danger: '#EF4444',
  neutral: '#94A3B8',
};

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });

// ========== TYPES ==========
interface Violation {
  violation_id: number;
  ticket_number: string;
  driver_name: string;
  license_number: string;
  plate_number: string;
  vehicle_type: string;
  violation_type: string;
  violation_location: string;
  violation_date: string;
  violation_time: string;
  penalty_amount: number;
  status: 'pending' | 'overdue' | 'paid' | 'cancelled';
  created_at: string;
}

interface Payment {
  payment_id: number;
  ticket_number: string;
  driver_name: string;
  plate_number: string;
  violation_type: string;
  amount_paid: number;
  payment_method: 'cash' | 'gcash' | 'bank_transfer' | 'other';
  payment_status: 'completed' | 'pending' | 'failed';
  payment_date: string;
  received_by_name: string | null;
}

type PaymentMethod = 'cash' | 'gcash' | 'bank_transfer' | 'other';

// ========== MOCK DATA ==========
const mockPendingViolations: Violation[] = [
  {
    violation_id: 1,
    ticket_number: 'TRV-20260716-001',
    driver_name: 'Juan Dela Cruz',
    license_number: 'N12-34-567890',
    plate_number: 'ABC-1234',
    vehicle_type: 'Car',
    violation_type: 'Speeding',
    violation_location: 'EDSA Ayala',
    violation_date: '2026-07-16',
    violation_time: '10:30',
    penalty_amount: 1200,
    status: 'pending',
    created_at: '2026-07-16 10:35:00',
  },
  {
    violation_id: 2,
    ticket_number: 'TRV-20260715-003',
    driver_name: 'Pedro Reyes',
    license_number: 'P11-22-334455',
    plate_number: 'DEF-9012',
    vehicle_type: 'Motorcycle',
    violation_type: 'Disregarded Signal',
    violation_location: 'Commonwealth Ave',
    violation_date: '2026-07-15',
    violation_time: '17:45',
    penalty_amount: 600,
    status: 'overdue',
    created_at: '2026-07-15 17:50:00',
  },
  {
    violation_id: 3,
    ticket_number: 'TRV-20260715-004',
    driver_name: 'Ana Reyes',
    license_number: 'A55-66-778899',
    plate_number: 'GHI-3456',
    vehicle_type: 'Van',
    violation_type: 'Overloading',
    violation_location: 'C5',
    violation_date: '2026-07-15',
    violation_time: '14:20',
    penalty_amount: 1500,
    status: 'pending',
    created_at: '2026-07-15 14:25:00',
  },
];

const mockPayments: Payment[] = [
  {
    payment_id: 1,
    ticket_number: 'TRV-20260716-002',
    driver_name: 'Maria Santos',
    plate_number: 'XYZ-5678',
    violation_type: 'Illegal Parking',
    amount_paid: 800,
    payment_method: 'cash',
    payment_status: 'completed',
    payment_date: '2026-07-16 11:00:00',
    received_by_name: 'Cashier',
  },
  {
    payment_id: 2,
    ticket_number: 'TRV-20260714-005',
    driver_name: 'Carlos Gomez',
    plate_number: 'JKL-7890',
    violation_type: 'No Helmet',
    amount_paid: 300,
    payment_method: 'gcash',
    payment_status: 'completed',
    payment_date: '2026-07-14 08:15:00',
    received_by_name: 'Admin',
  },
];

const mockStats = {
  collectedToday: 124500,
  thisWeek: 356000,
  thisMonth: 1245000,
  pendingAmount: 450000,
  pendingCount: 3,
};

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `\u20b1${amount.toLocaleString()}`;
const shortCurrency = (amount: number): string => {
  if (amount >= 1_000_000) return `\u20b1${(amount / 1_000_000).toFixed(1)}M`;
  if (amount >= 1_000) return `\u20b1${(amount / 1_000).toFixed(1)}K`;
  return `\u20b1${amount.toFixed(0)}`;
};

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'completed') return COLORS.success;
  if (s === 'pending') return COLORS.warning;
  if (s === 'overdue' || s === 'failed') return COLORS.danger;
  if (s === 'cancelled') return COLORS.neutral;
  return COLORS.neutral;
};

const methodIcon = (method: string): keyof typeof Ionicons.glyphMap => {
  switch (method) {
    case 'cash': return 'cash-outline';
    case 'gcash': return 'phone-portrait-outline';
    case 'bank_transfer': return 'business-outline';
    default: return 'card-outline';
  }
};

const methodLabel = (method: string): string => {
  switch (method) {
    case 'cash': return 'Cash';
    case 'gcash': return 'GCash';
    case 'bank_transfer': return 'Bank Transfer';
    default: return 'Other';
  }
};

const METHOD_FILTERS: { label: string; value: PaymentMethod | '' }[] = [
  { label: 'All Methods', value: '' },
  { label: 'Cash', value: 'cash' },
  { label: 'GCash', value: 'gcash' },
  { label: 'Bank Transfer', value: 'bank_transfer' },
  { label: 'Other', value: 'other' },
];

const METHOD_OPTIONS: { label: string; value: PaymentMethod }[] = [
  { label: 'Cash', value: 'cash' },
  { label: 'GCash', value: 'gcash' },
  { label: 'Bank Transfer', value: 'bank_transfer' },
  { label: 'Other', value: 'other' },
];

// ========== SCREEN ==========
export default function PaymentsScreen() {
  const router = useRouter();

  // State for data
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [pendingViolations, setPendingViolations] = useState<Violation[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [stats, setStats] = useState(mockStats);

  // Filter states
  const [pendingSearch, setPendingSearch] = useState('');
  const [paymentSearch, setPaymentSearch] = useState('');
  const [methodFilter, setMethodFilter] = useState<PaymentMethod | ''>('');

  // Selected violation for payment processing
  const [selectedViolation, setSelectedViolation] = useState<Violation | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    // Replace with actual API calls
    await new Promise(resolve => setTimeout(resolve, 800));
    setPendingViolations(mockPendingViolations);
    setPayments(mockPayments);
    setStats(mockStats);
    setLoading(false);
    setRefreshing(false);
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  const filteredPending = pendingViolations.filter(v => {
    const search = pendingSearch.toLowerCase();
    return (
      v.ticket_number.toLowerCase().includes(search) ||
      v.driver_name.toLowerCase().includes(search) ||
      v.plate_number.toLowerCase().includes(search) ||
      v.violation_type.toLowerCase().includes(search)
    );
  });

  const filteredPayments = payments.filter(p => {
    const search = paymentSearch.toLowerCase();
    const matchSearch = (
      p.ticket_number.toLowerCase().includes(search) ||
      p.driver_name.toLowerCase().includes(search) ||
      p.plate_number.toLowerCase().includes(search)
    );
    const matchMethod = methodFilter ? p.payment_method === methodFilter : true;
    return matchSearch && matchMethod;
  });

  const handlePaymentConfirm = async () => {
    if (!selectedViolation) return;
    setProcessing(true);
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert(
      'Payment Recorded',
      `Payment for ${selectedViolation.ticket_number} has been successfully recorded.`
    );
    setProcessing(false);
    setModalVisible(false);
    setSelectedViolation(null);
    fetchData();
  };

  // ---------- RENDER HELPERS ----------
  const renderSummaryCell = (icon: React.ReactNode, label: string, value: string, isLast: boolean) => (
    <View style={[styles.summaryCell, !isLast && styles.summaryCellDivider]}>
      {icon}
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );

  const renderPendingItem = ({ item }: { item: Violation }) => (
    <View style={styles.pendingItem}>
      <View style={styles.itemHeader}>
        <Text style={styles.ticketNumber}>{item.ticket_number}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.driverName}>{item.driver_name}</Text>
      <Text style={styles.plateInfo}>{item.plate_number} · {item.vehicle_type}</Text>
      <View style={styles.itemMetaRow}>
        <Text style={styles.violationType}>{item.violation_type}</Text>
        <Text style={styles.locationDate}>{item.violation_location} · {item.violation_date}</Text>
      </View>

      <View style={styles.itemDivider} />

      <View style={styles.pendingFooter}>
        <Text style={styles.penalty}>{formatCurrency(item.penalty_amount)}</Text>
        <TouchableOpacity
          style={styles.processButton}
          onPress={() => { setSelectedViolation(item); setPaymentMethod('cash'); setModalVisible(true); }}
          activeOpacity={0.8}
        >
          <Text style={styles.processButtonText}>Process Payment</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  const renderPaymentItem = ({ item }: { item: Payment }) => (
    <View style={styles.paymentItem}>
      <View style={styles.itemHeader}>
        <Text style={styles.paymentReference}>{`PAY-${String(item.payment_id).padStart(6, '0')}`}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.payment_status) + '1A' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.payment_status) }]}>
            {item.payment_status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.paymentTicket}>{item.ticket_number}</Text>
      <Text style={styles.paymentDriver}>{item.driver_name} · {item.plate_number}</Text>
      <Text style={styles.paymentViolation}>{item.violation_type}</Text>

      <View style={styles.itemDivider} />

      <View style={styles.paymentFooter}>
        <View style={styles.paymentMethodRow}>
          <Ionicons name={methodIcon(item.payment_method)} size={14} color={COLORS.textSecondary} />
          <Text style={styles.methodText}>{methodLabel(item.payment_method)}</Text>
        </View>
        <Text style={styles.paymentAmount}>{formatCurrency(item.amount_paid)}</Text>
      </View>
      <Text style={styles.paymentMeta}>{item.payment_date} · Received by {item.received_by_name || 'N/A'}</Text>
    </View>
  );

  const renderEmpty = (message: string) => (
    <View style={styles.emptyState}>
      <Ionicons name="document-text-outline" size={26} color={COLORS.textTertiary} />
      <Text style={styles.emptyText}>{message}</Text>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading payments…</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor={COLORS.bg} />
      <ScrollView
        style={styles.container}
        contentContainerStyle={{ paddingBottom: 40 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.eyebrow}>PAYMENT MANAGEMENT</Text>
          <Text style={styles.pageTitle}>Payments & Collections</Text>
          <Text style={styles.pageSub}>Process unpaid violations, record collections, and review completed payment transactions.</Text>
        </View>

        {/* Summary panel */}
        <View style={styles.summaryPanel}>
          {renderSummaryCell(<Ionicons name="cash-outline" size={16} color={COLORS.success} />, 'Collected Today', shortCurrency(stats.collectedToday), false)}
          {renderSummaryCell(<Ionicons name="calendar-outline" size={16} color={COLORS.primary} />, 'This Week', shortCurrency(stats.thisWeek), false)}
          {renderSummaryCell(<Ionicons name="stats-chart-outline" size={16} color={COLORS.primary} />, 'This Month', shortCurrency(stats.thisMonth), false)}
          {renderSummaryCell(<Ionicons name="alert-circle-outline" size={16} color={COLORS.warning} />, `${stats.pendingCount} Unpaid`, shortCurrency(stats.pendingAmount), true)}
        </View>

        {/* Pending Violations */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Pending Violations</Text>
            <Text style={styles.sectionSub}>Select a violation to begin payment processing.</Text>
          </View>

          <View style={styles.searchWrap}>
            <Ionicons name="search" size={16} color={COLORS.textTertiary} style={styles.searchIcon} />
            <TextInput
              style={styles.searchInput}
              placeholder="Search ticket, driver, plate, violation..."
              placeholderTextColor={COLORS.textTertiary}
              value={pendingSearch}
              onChangeText={setPendingSearch}
            />
          </View>

          {filteredPending.length === 0 ? (
            renderEmpty('No pending or overdue violations were found.')
          ) : (
            <FlatList
              data={filteredPending}
              renderItem={renderPendingItem}
              keyExtractor={item => item.violation_id.toString()}
              scrollEnabled={false}
              ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
            />
          )}
        </View>

        {/* Payment Transactions */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Payment Transactions</Text>
            <Text style={styles.sectionSub}>Completed and recorded collection history.</Text>
          </View>

          <View style={styles.searchWrap}>
            <Ionicons name="search" size={16} color={COLORS.textTertiary} style={styles.searchIcon} />
            <TextInput
              style={styles.searchInput}
              placeholder="Search ticket, driver, plate..."
              placeholderTextColor={COLORS.textTertiary}
              value={paymentSearch}
              onChangeText={setPaymentSearch}
            />
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.methodFilterRow} contentContainerStyle={{ paddingRight: 4 }}>
            {METHOD_FILTERS.map(f => {
              const active = methodFilter === f.value;
              return (
                <TouchableOpacity
                  key={f.label}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => setMethodFilter(f.value)}
                  activeOpacity={0.7}
                >
                  <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{f.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {filteredPayments.length === 0 ? (
            renderEmpty('No payment transactions matched your current filters.')
          ) : (
            <FlatList
              data={filteredPayments}
              renderItem={renderPaymentItem}
              keyExtractor={item => item.payment_id.toString()}
              scrollEnabled={false}
              ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
            />
          )}
        </View>
      </ScrollView>

      {/* Payment Modal */}
      <Modal animationType="slide" transparent visible={modalVisible} onRequestClose={() => setModalVisible(false)}>
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View style={{ flex: 1 }}>
                <Text style={styles.modalTitle}>Process Payment</Text>
                <Text style={styles.modalSub}>Review violation before recording payment.</Text>
              </View>
              <TouchableOpacity onPress={() => setModalVisible(false)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={22} color={COLORS.textTertiary} />
              </TouchableOpacity>
            </View>

            {selectedViolation && (
              <ScrollView style={styles.modalBody} showsVerticalScrollIndicator={false}>
                <View style={styles.modalViolationDetails}>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Ticket Number</Text>
                    <Text style={[styles.modalDetailValue, { fontFamily: mono }]}>{selectedViolation.ticket_number}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Status</Text>
                    <View style={[styles.statusBadge, { backgroundColor: statusColor(selectedViolation.status) + '1A' }]}>
                      <Text style={[styles.statusText, { color: statusColor(selectedViolation.status) }]}>
                        {selectedViolation.status.toUpperCase()}
                      </Text>
                    </View>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Driver</Text>
                    <Text style={styles.modalDetailValue}>{selectedViolation.driver_name}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Plate Number</Text>
                    <Text style={[styles.modalDetailValue, { fontFamily: mono }]}>{selectedViolation.plate_number}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Violation</Text>
                    <Text style={styles.modalDetailValue}>{selectedViolation.violation_type}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Location</Text>
                    <Text style={styles.modalDetailValue}>{selectedViolation.violation_location}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Date & Time</Text>
                    <Text style={styles.modalDetailValue}>{selectedViolation.violation_date} {selectedViolation.violation_time}</Text>
                  </View>
                  <View style={[styles.modalDetailRow, { borderBottomWidth: 0 }]}>
                    <Text style={styles.modalDetailLabel}>Penalty</Text>
                    <Text style={[styles.modalDetailValue, styles.modalPenalty]}>{formatCurrency(selectedViolation.penalty_amount)}</Text>
                  </View>
                </View>

                <View style={styles.modalForm}>
                  <Text style={styles.modalFormLabel}>Payment Method</Text>
                  <View style={styles.methodOptionsRow}>
                    {METHOD_OPTIONS.map(opt => {
                      const active = paymentMethod === opt.value;
                      return (
                        <TouchableOpacity
                          key={opt.value}
                          style={[styles.methodOption, active && styles.methodOptionActive]}
                          onPress={() => setPaymentMethod(opt.value)}
                          activeOpacity={0.7}
                        >
                          <Ionicons name={methodIcon(opt.value)} size={16} color={active ? COLORS.primary : COLORS.textSecondary} />
                          <Text style={[styles.methodOptionText, active && styles.methodOptionTextActive]}>{opt.label}</Text>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                  <Text style={styles.modalNote}>A payment reference will be generated from the saved payment ID.</Text>
                </View>

                <View style={styles.modalActions}>
                  <TouchableOpacity
                    style={styles.cancelModalButton}
                    onPress={() => setModalVisible(false)}
                    disabled={processing}
                  >
                    <Text style={styles.cancelModalButtonText}>Cancel</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={styles.confirmModalButton}
                    onPress={handlePaymentConfirm}
                    disabled={processing}
                  >
                    {processing ? (
                      <ActivityIndicator size="small" color="#FFFFFF" />
                    ) : (
                      <Text style={styles.confirmModalButtonText}>Confirm Payment</Text>
                    )}
                  </TouchableOpacity>
                </View>
              </ScrollView>
            )}
          </View>
        </View>
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

  // Summary panel
  summaryPanel: {
    flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 18,
    borderWidth: 1, borderColor: COLORS.border, paddingVertical: 16, marginBottom: 18, ...softShadow,
  },
  summaryCell: { flex: 1, alignItems: 'center', paddingHorizontal: 4 },
  summaryCellDivider: { borderRightWidth: 1, borderRightColor: COLORS.border },
  summaryValue: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 9, color: COLORS.textTertiary, textAlign: 'center' },

  // Section card
  sectionCard: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 16, marginBottom: 18,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  sectionHeader: { marginBottom: 14 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 2 },
  sectionSub: { fontSize: 12, color: COLORS.textTertiary },

  // Search
  searchWrap: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.bg,
    borderRadius: 10, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 42, marginBottom: 12,
  },
  searchIcon: { marginRight: 8 },
  searchInput: { flex: 1, fontSize: 14, color: COLORS.textPrimary },

  // Method filter chips
  methodFilterRow: { marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },

  // Pending violation item
  pendingItem: {
    backgroundColor: COLORS.bg, borderRadius: 14, padding: 14,
    borderWidth: 1, borderColor: COLORS.border,
  },
  itemHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 6 },
  ticketNumber: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },
  statusBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.3 },
  driverName: { fontSize: 14, fontWeight: '600', color: COLORS.textPrimary },
  plateInfo: { fontSize: 12, color: COLORS.textSecondary, marginBottom: 6 },
  itemMetaRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  violationType: { fontSize: 13, fontWeight: '600', color: COLORS.primary },
  locationDate: { fontSize: 11, color: COLORS.textTertiary },
  itemDivider: { height: 1, backgroundColor: COLORS.border, marginVertical: 10 },
  pendingFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  penalty: { fontSize: 16, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },
  processButton: { backgroundColor: COLORS.primary, paddingHorizontal: 16, paddingVertical: 9, borderRadius: 20 },
  processButtonText: { color: '#FFFFFF', fontSize: 12, fontWeight: '700' },

  // Payment item
  paymentItem: {
    backgroundColor: COLORS.bg, borderRadius: 14, padding: 14,
    borderWidth: 1, borderColor: COLORS.border,
  },
  paymentReference: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },
  paymentTicket: { fontSize: 13, color: COLORS.textSecondary, fontFamily: mono },
  paymentDriver: { fontSize: 13, color: COLORS.textSecondary },
  paymentViolation: { fontSize: 13, fontWeight: '600', color: COLORS.primary, marginTop: 2 },
  paymentFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  paymentMethodRow: { flexDirection: 'row', alignItems: 'center' },
  methodText: { fontSize: 12, color: COLORS.textSecondary, marginLeft: 6 },
  paymentAmount: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },
  paymentMeta: { fontSize: 11, color: COLORS.textTertiary, marginTop: 8 },

  // Empty state
  emptyState: { alignItems: 'center', paddingVertical: 30 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 8, lineHeight: 18 },

  // Modal
  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.5)', justifyContent: 'center', alignItems: 'center' },
  modalContent: { backgroundColor: COLORS.surface, borderRadius: 20, width: '92%', maxHeight: '85%' },
  modalHeader: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start',
    padding: 20, borderBottomWidth: 1, borderBottomColor: COLORS.border,
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary },
  modalSub: { fontSize: 12, color: COLORS.textSecondary, marginTop: 2 },

  modalBody: { padding: 20, maxHeight: 520 },
  modalViolationDetails: { backgroundColor: COLORS.bg, borderRadius: 12, padding: 12, marginBottom: 18 },
  modalDetailRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: COLORS.border,
  },
  modalDetailLabel: { fontSize: 12, color: COLORS.textTertiary },
  modalDetailValue: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, textAlign: 'right', flex: 1, marginLeft: 12 },
  modalPenalty: { fontSize: 16, fontWeight: '700', fontFamily: mono },

  modalForm: { marginBottom: 8 },
  modalFormLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginBottom: 10 },
  methodOptionsRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  methodOption: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.bg,
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 10,
    paddingHorizontal: 12, paddingVertical: 9,
  },
  methodOptionActive: { backgroundColor: COLORS.primary + '14', borderColor: COLORS.primary },
  methodOptionText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary, marginLeft: 6 },
  methodOptionTextActive: { color: COLORS.primary },
  modalNote: { fontSize: 11, color: COLORS.textTertiary, marginTop: 10, fontStyle: 'italic' },

  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 8, paddingBottom: 4 },
  cancelModalButton: {
    paddingVertical: 11, paddingHorizontal: 20, borderRadius: 10, minWidth: 100, alignItems: 'center',
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
  },
  cancelModalButtonText: { fontSize: 13, fontWeight: '600', color: COLORS.textSecondary },
  confirmModalButton: {
    paddingVertical: 11, paddingHorizontal: 20, borderRadius: 10, minWidth: 100, alignItems: 'center',
    backgroundColor: COLORS.primary,
  },
  confirmModalButtonText: { fontSize: 13, fontWeight: '700', color: '#FFFFFF' },
});