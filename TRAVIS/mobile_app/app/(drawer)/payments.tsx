import React, { useState, useCallback, useEffect } from 'react';
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
import { useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../../api/axiosConfig';
import * as Print from 'expo-print';

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
  receipt_reference?: string;
  ticket_number: string;
  driver_name: string;
  plate_number: string;
  violation_type: string;
  amount_paid: number;
  payment_method: string;
  payment_status: 'completed' | 'pending' | 'failed';
  payment_date: string;
  received_by_name: string | null;
}

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

// ========== SCREEN ==========
export default function PaymentsScreen() {
  const { violation_id } = useLocalSearchParams<{ violation_id?: string }>();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [pendingViolations, setPendingViolations] = useState<Violation[]>([]);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [stats, setStats] = useState({ collectedToday: 0, collectedThisWeek: 0, collectedThisMonth: 0, pendingCount: 0, pendingAmount: 0 });
  const [pendingSearch, setPendingSearch] = useState('');
  const [paymentSearch, setPaymentSearch] = useState('');
  const [selectedViolation, setSelectedViolation] = useState<Violation | null>(null);
  const [modalVisible, setModalVisible] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [handledPaymentLink, setHandledPaymentLink] = useState<string | null>(null);
  const [selectedPayment, setSelectedPayment] = useState<Payment | null>(null);
  const [printingReceipt, setPrintingReceipt] = useState(false);

  useEffect(() => {
    if (!violation_id || handledPaymentLink === violation_id || pendingViolations.length === 0) return;
    const violation = pendingViolations.find(item => String(item.violation_id) === String(violation_id));
    setHandledPaymentLink(violation_id);
    if (violation) {
      setSelectedViolation(violation);
      setModalVisible(true);
    } else {
      Alert.alert('Payment unavailable', 'This violation is no longer pending or overdue.');
    }
  }, [violation_id, handledPaymentLink, pendingViolations]);

  // ===== FETCH DATA =====
  const fetchData = async () => {
    try {
      setLoading(true);

      const pendingRes = await api.get('get_violations.php', {
        params: { status: 'pending,overdue', limit: 100 },
      });
      if (pendingRes.data.success) {
        setPendingViolations(pendingRes.data.data);
      }

      const paymentsRes = await api.get('get_payments.php');
      if (paymentsRes.data.success) {
        setPayments(paymentsRes.data.data);
      }

      const statsRes = await api.get('get_dashboard_stats.php');
      if (statsRes.data.success) {
        const d = statsRes.data.data;
        setStats({
          collectedToday: d.collected_today || 0,
          collectedThisWeek: d.collected_this_week || 0,
          collectedThisMonth: d.collected_this_month || 0,
          pendingCount: d.pending_violations || 0,
          pendingAmount: d.pending_amount || 0,
        });
      }
    } catch (error) {
      console.error('Payments fetch error:', error);
      Alert.alert('Error', 'Failed to load payment data.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchData();
    }, [])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // ===== PROCESS PAYMENT =====
  const handlePaymentConfirm = async () => {
    if (!selectedViolation) return;
    setProcessing(true);
    try {
      const response = await api.post('process_payment.php', {
        violation_id: selectedViolation.violation_id,
        amount_paid: selectedViolation.penalty_amount,
        payment_method: 'cash',
      });
      if (response.data.success) {
        Alert.alert('Success', `Payment for ${selectedViolation.ticket_number} recorded.`);
        setModalVisible(false);
        setSelectedViolation(null);
        fetchData();
      } else {
        Alert.alert('Error', response.data.error || 'Payment failed.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.error || 'Network error.');
    } finally {
      setProcessing(false);
    }
  };

  const printReceipt = async () => {
    if (!selectedPayment) return;
    const payment = selectedPayment;
    const reference = payment.receipt_reference || `PAY-${String(payment.payment_id).padStart(6, '0')}`;
    const safe = (value: unknown) => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character] || character));
    const details = [
      ['Ticket Number', payment.ticket_number], ['Driver', payment.driver_name], ['Plate Number', payment.plate_number],
      ['Violation', payment.violation_type], ['Payment Method', methodLabel(payment.payment_method)],
      ['Payment Date', payment.payment_date], ['Collecting Officer', payment.received_by_name || 'Not recorded'],
    ];
    const rows = details.map(([label, value]) => `<div class="row"><span>${safe(label)}</span><b>${safe(value)}</b></div>`).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A5 portrait;margin:14mm}body{font-family:Arial,sans-serif;color:#10202c;margin:0}.head{text-align:center;border-bottom:2px solid #102f49;padding-bottom:12px}.republic{font:10px Georgia,serif;letter-spacing:.1em}.head h1{font:700 20px Georgia,serif;color:#102f49;margin:4px}.head p{font-size:10px;margin:2px;color:#526b64}.title{text-align:center;margin:18px 0}.title strong{display:block;color:#087d78;letter-spacing:.08em}.title span{display:block;font-size:17px;font-weight:700;margin-top:5px}.row{display:flex;justify-content:space-between;gap:18px;padding:9px 0;border-bottom:1px solid #dce5e2;font-size:11px}.row b{text-align:right}.total{display:flex;justify-content:space-between;background:#102f49;color:#fff;padding:14px;border-radius:10px;margin-top:16px;font-weight:700}.note{text-align:center;color:#526b64;font-size:9px;line-height:1.5;margin-top:16px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:35px;margin-top:38px}.signature{text-align:center;border-top:1px solid #10202c;padding-top:5px;font-size:9px}</style></head><body><div class="head"><div class="republic">REPUBLIC OF THE PHILIPPINES</div><h1>Municipality of Nasugbu</h1><p>Municipal Treasurer's Office · Traffic Management Office</p></div><div class="title"><strong>OFFICIAL PAYMENT RECEIPT</strong><span>${safe(reference)}</span></div>${rows}<div class="total"><span>TOTAL AMOUNT PAID</span><span>&#8369;${Number(payment.amount_paid).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span></div><p class="note">Payment received in settlement of the traffic violation stated above. This receipt is subject to verification in the official TRAVIS payment ledger.</p><div class="signatures"><div class="signature">Collecting Officer / Date</div><div class="signature">Payor / Date</div></div></body></html>`;
    setPrintingReceipt(true);
    try { await Print.printAsync({ html }); }
    catch { Alert.alert('Print unavailable', 'The receipt could not be sent to the system print service.'); }
    finally { setPrintingReceipt(false); }
  };

  // ===== FILTERS =====
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
    return matchSearch;
  });

  // ========== RENDER HELPERS ==========
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
          onPress={() => { setSelectedViolation(item); setModalVisible(true); }}
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
      <TouchableOpacity style={styles.receiptButton} onPress={() => setSelectedPayment(item)}>
        <Ionicons name="receipt-outline" size={14} color={COLORS.primary} />
        <Text style={styles.receiptButtonText}>View Receipt</Text>
      </TouchableOpacity>
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
          {renderSummaryCell(<Ionicons name="calendar-outline" size={16} color={COLORS.primary} />, 'This Week', shortCurrency(stats.collectedThisWeek), false)}
          {renderSummaryCell(<Ionicons name="stats-chart-outline" size={16} color={COLORS.primary} />, 'This Month', shortCurrency(stats.collectedThisMonth), false)}
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
                  <View style={[styles.methodOption, styles.methodOptionActive]}>
                    <Ionicons name="cash-outline" size={17} color={COLORS.primary} />
                    <Text style={[styles.methodOptionText, styles.methodOptionTextActive]}>Cash</Text>
                    <Ionicons name="checkmark-circle" size={17} color={COLORS.success} style={{ marginLeft: 'auto' }} />
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
      <Modal animationType="slide" transparent visible={!!selectedPayment} onRequestClose={() => setSelectedPayment(null)}>
        <View style={styles.modalOverlay}>
          <View style={styles.receiptSheet}>
            <View style={styles.modalHeader}>
              <View style={{ flex: 1 }}><Text style={styles.modalTitle}>Official Payment Receipt</Text><Text style={styles.modalSub}>{selectedPayment?.receipt_reference || `PAY-${String(selectedPayment?.payment_id || '').padStart(6, '0')}`}</Text></View>
              <TouchableOpacity onPress={() => setSelectedPayment(null)}><Ionicons name="close" size={22} color={COLORS.textTertiary} /></TouchableOpacity>
            </View>
            {selectedPayment && <ScrollView contentContainerStyle={styles.receiptBody}>
              {[['Ticket Number', selectedPayment.ticket_number], ['Driver', selectedPayment.driver_name], ['Plate Number', selectedPayment.plate_number], ['Violation', selectedPayment.violation_type], ['Payment Method', methodLabel(selectedPayment.payment_method)], ['Payment Date', selectedPayment.payment_date], ['Collecting Officer', selectedPayment.received_by_name || 'Not recorded']].map(([label, value]) => <View key={label} style={styles.receiptRow}><Text style={styles.receiptLabel}>{label}</Text><Text style={styles.receiptValue}>{value}</Text></View>)}
              <View style={styles.receiptTotal}><Text style={styles.receiptTotalLabel}>TOTAL AMOUNT PAID</Text><Text style={styles.receiptTotalValue}>{formatCurrency(selectedPayment.amount_paid)}</Text></View>
              <Text style={styles.receiptNote}>This receipt is subject to verification in the official TRAVIS payment ledger.</Text>
              <TouchableOpacity style={styles.printReceiptButton} onPress={printReceipt} disabled={printingReceipt}>{printingReceipt ? <ActivityIndicator color="#FFF" /> : <Ionicons name="print-outline" size={18} color="#FFF" />}<Text style={styles.printReceiptText}>Print Receipt</Text></TouchableOpacity>
            </ScrollView>}
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

  summaryPanel: {
    flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 18,
    borderWidth: 1, borderColor: COLORS.border, paddingVertical: 16, marginBottom: 18, ...softShadow,
  },
  summaryCell: { flex: 1, alignItems: 'center', paddingHorizontal: 4 },
  summaryCellDivider: { borderRightWidth: 1, borderRightColor: COLORS.border },
  summaryValue: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 9, color: COLORS.textTertiary, textAlign: 'center' },

  sectionCard: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 16, marginBottom: 18,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  sectionHeader: { marginBottom: 14 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 2 },
  sectionSub: { fontSize: 12, color: COLORS.textTertiary },

  searchWrap: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.bg,
    borderRadius: 10, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 42, marginBottom: 12,
  },
  searchIcon: { marginRight: 8 },
  searchInput: { flex: 1, fontSize: 14, color: COLORS.textPrimary },

  methodFilterRow: { marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },

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
  receiptButton: { alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 5, borderWidth: 1, borderColor: COLORS.border, borderRadius: 9, paddingHorizontal: 10, paddingVertical: 7, marginTop: 10 },
  receiptButtonText: { color: COLORS.primary, fontSize: 11, fontWeight: '800' },

  emptyState: { alignItems: 'center', paddingVertical: 30 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 8, lineHeight: 18 },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.66)', justifyContent: 'center', alignItems: 'center', paddingHorizontal: 16 },
  receiptSheet: { backgroundColor: COLORS.surface, borderRadius: 22, width: '92%', maxHeight: '88%', borderWidth: 1, borderColor: COLORS.border, overflow: 'hidden' },
  receiptBody: { padding: 20 },
  receiptRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 14, paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  receiptLabel: { color: COLORS.textTertiary, fontSize: 11 },
  receiptValue: { flex: 1, color: COLORS.textPrimary, fontSize: 11, fontWeight: '800', textAlign: 'right' },
  receiptTotal: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', backgroundColor: COLORS.header, borderRadius: 12, padding: 14, marginTop: 16 },
  receiptTotalLabel: { color: '#C5D5E0', fontSize: 10, fontWeight: '800' },
  receiptTotalValue: { color: '#FFF', fontSize: 18, fontWeight: '900' },
  receiptNote: { color: COLORS.textTertiary, fontSize: 10, lineHeight: 15, textAlign: 'center', marginVertical: 14 },
  printReceiptButton: { height: 46, backgroundColor: COLORS.primary, borderRadius: 11, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
  printReceiptText: { color: '#FFF', fontWeight: '900', fontSize: 12 },
  modalContent: { backgroundColor: COLORS.surface, borderRadius: 22, width: '92%', maxHeight: '85%', borderWidth: 1, borderColor: COLORS.border, overflow: 'hidden' },
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
