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
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';

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
const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;
const shortCurrency = (amount: number): string => {
  if (amount >= 1_000_000) return `₱${(amount / 1_000_000).toFixed(1)}M`;
  if (amount >= 1_000) return `₱${(amount / 1_000).toFixed(1)}K`;
  return `₱${amount.toFixed(0)}`;
};

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'completed') return '#16a34a';
  if (s === 'pending') return '#f59e0b';
  if (s === 'overdue' || s === 'failed') return '#dc2626';
  if (s === 'cancelled') return '#6b7280';
  return '#6b7280';
};

const methodIcon = (method: string): string => {
  switch (method) {
    case 'cash': return '💵';
    case 'gcash': return '📱';
    case 'bank_transfer': return '🏦';
    default: return '💳';
  }
};

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
  const [methodFilter, setMethodFilter] = useState('');

  // Selected violation for payment processing
  const [selectedViolation, setSelectedViolation] = useState<Violation | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'gcash' | 'bank_transfer' | 'other'>('cash');
  const [processing, setProcessing] = useState(false);

  // Load data on mount
  useEffect(() => {
    fetchData();
  }, []);

  // Simulate API fetch
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

  // Filter pending violations
  const filteredPending = pendingViolations.filter(v => {
    const search = pendingSearch.toLowerCase();
    return (
      v.ticket_number.toLowerCase().includes(search) ||
      v.driver_name.toLowerCase().includes(search) ||
      v.plate_number.toLowerCase().includes(search) ||
      v.violation_type.toLowerCase().includes(search)
    );
  });

  // Filter payments
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

  // Handle payment confirmation
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
    // Refresh data
    fetchData();
  };

  // Render stats cards
  const renderStatCard = (label: string, value: string | number, subtext?: string, color: string = '#2563eb') => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
      {subtext && <Text style={styles.statSubtext}>{subtext}</Text>}
    </View>
  );

  // Render pending violation item
  const renderPendingItem = ({ item }: { item: Violation }) => (
    <TouchableOpacity
      style={styles.pendingItem}
      onPress={() => {
        if (item.status === 'pending' || item.status === 'overdue') {
          setSelectedViolation(item);
          setModalVisible(true);
        }
      }}
      activeOpacity={0.7}
    >
      <View style={styles.pendingHeader}>
        <Text style={styles.ticketNumber}>{item.ticket_number}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.driverName}>{item.driver_name}</Text>
      <Text style={styles.plateInfo}>{item.plate_number} • {item.vehicle_type}</Text>
      <Text style={styles.violationType}>{item.violation_type}</Text>
      <Text style={styles.locationDate}>
        {item.violation_location} • {item.violation_date}
      </Text>
      <View style={styles.pendingFooter}>
        <Text style={styles.penalty}>{formatCurrency(item.penalty_amount)}</Text>
        <TouchableOpacity style={styles.processButton}>
          <Text style={styles.processButtonText}>Process Payment</Text>
        </TouchableOpacity>
      </View>
    </TouchableOpacity>
  );

  // Render payment transaction item
  const renderPaymentItem = ({ item }: { item: Payment }) => (
    <View style={styles.paymentItem}>
      <View style={styles.paymentHeader}>
        <Text style={styles.paymentReference}>PAY-{String(item.payment_id).padStart(6, '0')}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.payment_status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.payment_status) }]}>
            {item.payment_status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.paymentTicket}>{item.ticket_number}</Text>
      <Text style={styles.paymentDriver}>{item.driver_name} • {item.plate_number}</Text>
      <Text style={styles.paymentViolation}>{item.violation_type}</Text>
      <View style={styles.paymentFooter}>
        <View style={styles.paymentMethod}>
          <Text style={styles.methodIcon}>{methodIcon(item.payment_method)}</Text>
          <Text style={styles.methodText}>{item.payment_method.toUpperCase()}</Text>
        </View>
        <Text style={styles.paymentAmount}>{formatCurrency(item.amount_paid)}</Text>
      </View>
      <Text style={styles.paymentMeta}>
        {item.payment_date} • Received by: {item.received_by_name || 'N/A'}
      </Text>
    </View>
  );

  // Render empty state
  const renderEmpty = (message: string) => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyText}>{message}</Text>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading payments...</Text>
      </SafeAreaView>
    );
  }

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
          <Text style={styles.pageTitle}>Payment Management</Text>
          <Text style={styles.pageSub}>
            Process unpaid violations, record collections, and review completed payment transactions.
          </Text>
        </View>

        {/* Stats Cards */}
        <View style={styles.statsRow}>
          {renderStatCard('Collected Today', shortCurrency(stats.collectedToday), '', '#16a34a')}
          {renderStatCard('This Week', shortCurrency(stats.thisWeek), '', '#2563eb')}
          {renderStatCard('This Month', shortCurrency(stats.thisMonth), '', '#2563eb')}
          {renderStatCard(
            'Pending Settlement',
            shortCurrency(stats.pendingAmount),
            `${stats.pendingCount} unpaid violations`,
            '#f59e0b'
          )}
        </View>

        {/* Pending Violations Section */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Pending Violations</Text>
            <Text style={styles.sectionSub}>Select a violation to begin payment processing.</Text>
          </View>

          <View style={styles.searchContainer}>
            <TextInput
              style={styles.searchInput}
              placeholder="Search ticket, driver, plate, violation..."
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
            />
          )}
        </View>

        {/* Payment Transactions Section */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Payment Transactions</Text>
            <Text style={styles.sectionSub}>Completed and recorded collection history.</Text>
          </View>

          <View style={styles.filterContainer}>
            <TextInput
              style={[styles.searchInput, { flex: 1, marginRight: 8 }]}
              placeholder="Search ticket, driver, plate..."
              value={paymentSearch}
              onChangeText={setPaymentSearch}
            />
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={methodFilter}
                onValueChange={(itemValue) => setMethodFilter(itemValue)}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
              >
                <Picker.Item label="All Methods" value="" />
                <Picker.Item label="Cash" value="cash" />
                <Picker.Item label="GCash" value="gcash" />
                <Picker.Item label="Bank Transfer" value="bank_transfer" />
                <Picker.Item label="Other" value="other" />
              </Picker>
            </View>
          </View>

          {filteredPayments.length === 0 ? (
            renderEmpty('No payment transactions matched your current filters.')
          ) : (
            <FlatList
              data={filteredPayments}
              renderItem={renderPaymentItem}
              keyExtractor={item => item.payment_id.toString()}
              scrollEnabled={false}
            />
          )}
        </View>
      </ScrollView>

      {/* Payment Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={modalVisible}
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Process Payment</Text>
                <Text style={styles.modalSub}>Review violation before recording payment.</Text>
              </View>
              <TouchableOpacity onPress={() => setModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            {selectedViolation && (
              <ScrollView style={styles.modalBody}>
                <View style={styles.modalViolationDetails}>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Ticket Number</Text>
                    <Text style={styles.modalDetailValue}>{selectedViolation.ticket_number}</Text>
                  </View>
                  <View style={styles.modalDetailRow}>
                    <Text style={styles.modalDetailLabel}>Status</Text>
                    <View style={[styles.statusBadge, { backgroundColor: statusColor(selectedViolation.status) + '20', alignSelf: 'flex-start' }]}>
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
                    <Text style={styles.modalDetailValue}>{selectedViolation.plate_number}</Text>
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
                    <Text style={styles.modalDetailValue}>
                      {selectedViolation.violation_date} {selectedViolation.violation_time}
                    </Text>
                  </View>
                  <View style={[styles.modalDetailRow, { borderBottomWidth: 0 }]}>
                    <Text style={styles.modalDetailLabel}>Penalty</Text>
                    <Text style={[styles.modalDetailValue, styles.modalPenalty]}>
                      {formatCurrency(selectedViolation.penalty_amount)}
                    </Text>
                  </View>
                </View>

                <View style={styles.modalForm}>
                  <Text style={styles.modalFormLabel}>Payment Method</Text>
                  <View style={styles.pickerWrapper}>
                    <Picker
                      selectedValue={paymentMethod}
                      onValueChange={(itemValue) => setPaymentMethod(itemValue)}
                      style={styles.picker}
                      dropdownIconColor="#0b3d78"
                    >
                      <Picker.Item label="Cash" value="cash" />
                      <Picker.Item label="GCash" value="gcash" />
                      <Picker.Item label="Bank Transfer" value="bank_transfer" />
                      <Picker.Item label="Other" value="other" />
                    </Picker>
                  </View>
                  <Text style={styles.modalNote}>
                    A payment reference will be generated from the saved payment ID.
                  </Text>
                </View>

                <View style={styles.modalActions}>
                  <TouchableOpacity
                    style={[styles.modalButton, styles.cancelButton]}
                    onPress={() => setModalVisible(false)}
                    disabled={processing}
                  >
                    <Text style={styles.cancelButtonText}>Cancel</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.modalButton, styles.confirmButton]}
                    onPress={handlePaymentConfirm}
                    disabled={processing}
                  >
                    {processing ? (
                      <ActivityIndicator size="small" color="#fff" />
                    ) : (
                      <Text style={styles.confirmButtonText}>Confirm Payment</Text>
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
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#f8fafc' },
  container: { flex: 1, padding: 16 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f8fafc' },
  loadingText: { marginTop: 12, fontSize: 16, color: '#1e293b' },
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
    padding: 14,
    width: '48%',
    borderLeftWidth: 4,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  statLabel: { fontSize: 12, color: '#64748b', marginBottom: 2 },
  statValue: { fontSize: 20, fontWeight: '700' },
  statSubtext: { fontSize: 11, color: '#94a3b8', marginTop: 2 },

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
  sectionHeader: { marginBottom: 12 },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78' },
  sectionSub: { fontSize: 12, color: '#64748b' },

  searchContainer: { marginBottom: 12 },
  searchInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    fontSize: 14,
    height: 44,
  },
  filterContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 12,
  },
  pickerWrapper: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    height: 44,
    justifyContent: 'center',
    minWidth: 120,
  },
  picker: {
    height: 44,
    width: '100%',
  },

  pendingItem: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  pendingHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  ticketNumber: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12 },
  statusText: { fontSize: 11, fontWeight: '600' },
  driverName: { fontSize: 15, fontWeight: '500', color: '#1e293b' },
  plateInfo: { fontSize: 13, color: '#64748b' },
  violationType: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginTop: 2 },
  locationDate: { fontSize: 13, color: '#64748b' },
  pendingFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 8,
  },
  penalty: { fontSize: 16, fontWeight: '700', color: '#0b3d78' },
  processButton: {
    backgroundColor: '#2563eb',
    paddingHorizontal: 14,
    paddingVertical: 6,
    borderRadius: 20,
  },
  processButtonText: { color: '#fff', fontSize: 12, fontWeight: '600' },

  paymentItem: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  paymentHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  paymentReference: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  paymentTicket: { fontSize: 14, color: '#1e293b' },
  paymentDriver: { fontSize: 13, color: '#64748b' },
  paymentViolation: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginTop: 2 },
  paymentFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 4,
  },
  paymentMethod: { flexDirection: 'row', alignItems: 'center' },
  methodIcon: { fontSize: 16, marginRight: 4 },
  methodText: { fontSize: 13, color: '#64748b' },
  paymentAmount: { fontSize: 16, fontWeight: '700', color: '#0b3d78' },
  paymentMeta: { fontSize: 12, color: '#94a3b8', marginTop: 4 },

  emptyState: { padding: 20, alignItems: 'center' },
  emptyText: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },

  // Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 20,
    width: '92%',
    maxHeight: '85%',
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: '#0b3d78' },
  modalSub: { fontSize: 13, color: '#64748b' },
  modalClose: { fontSize: 22, color: '#94a3b8', padding: 4 },

  modalBody: { padding: 20, maxHeight: 500 },
  modalViolationDetails: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 12,
    marginBottom: 16,
  },
  modalDetailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 4,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  modalDetailLabel: { fontSize: 13, color: '#64748b' },
  modalDetailValue: { fontSize: 13, fontWeight: '500', color: '#0b3d78', textAlign: 'right', flex: 1, marginLeft: 12 },
  modalPenalty: { fontSize: 16, fontWeight: '700', color: '#0b3d78' },

  modalForm: { marginBottom: 16 },
  modalFormLabel: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginBottom: 6 },
  modalNote: { fontSize: 12, color: '#94a3b8', marginTop: 8, fontStyle: 'italic' },

  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    marginTop: 8,
  },
  modalButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    minWidth: 100,
    alignItems: 'center',
  },
  cancelButton: { backgroundColor: '#f1f5f9' },
  confirmButton: { backgroundColor: '#2563eb' },
  cancelButtonText: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  confirmButtonText: { fontSize: 14, fontWeight: '600', color: '#fff' },
});