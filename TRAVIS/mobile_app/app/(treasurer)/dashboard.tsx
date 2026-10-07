import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Href, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import DateTimePicker from '@react-native-community/datetimepicker';
import { Picker } from '@react-native-picker/picker';
import api from '../../api/axiosConfig';
import { formatTreasuryDate, peso, TREASURY } from '../../constants/treasury-theme';
import { getStoredUser } from '../../utils/session';

type Stats = { violations_today: number; pending_violations: number; pending_amount: number; paid_today: number; collected_today: number; collected_this_week: number; collected_this_month: number; period_label?: string };
type Payment = { payment_id: number; receipt_reference?: string; official_receipt_number?: string; ticket_number: string; driver_name: string; amount_paid: number; payment_date: string };
type Period = 'day' | 'month' | 'year' | 'all';

const PERIODS: { value: Period; label: string }[] = [
  { value: 'day', label: 'Specific day' },
  { value: 'month', label: 'Specific month' },
  { value: 'year', label: 'Specific year' },
  { value: 'all', label: 'All records' },
];
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const localDay = (value: Date) => `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;

const emptyStats: Stats = { violations_today: 0, pending_violations: 0, pending_amount: 0, paid_today: 0, collected_today: 0, collected_this_week: 0, collected_this_month: 0 };

export default function TreasurerDashboard() {
  const router = useRouter();
  const [name, setName] = useState('Treasury Personnel');
  const [stats, setStats] = useState<Stats>(emptyStats);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [period, setPeriod] = useState<Period>('day');
  const [filterOpen, setFilterOpen] = useState(false);
  const [selectedDate, setSelectedDate] = useState(new Date());
  const [showDayPicker, setShowDayPicker] = useState(false);

  const load = useCallback(async () => {
    try {
      const params = { period, dashboard_day: localDay(selectedDate), dashboard_month: `${selectedDate.getFullYear()}-${String(selectedDate.getMonth() + 1).padStart(2, '0')}`, dashboard_year: selectedDate.getFullYear() };
      const [user, statsRes, paymentsRes] = await Promise.all([getStoredUser(), api.get('get_dashboard_stats.php', { params }), api.get('get_payments.php', { params })]);
      if (user) setName(user.full_name.split(' ')[0]);
      if (statsRes.data.success) setStats({ ...emptyStats, ...statsRes.data.data });
      if (paymentsRes.data.success) setPayments(paymentsRes.data.data.slice(0, 5));
    } finally { setLoading(false); setRefreshing(false); }
  }, [period, selectedDate]);

  useEffect(() => { load(); }, [load]);
  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color={TREASURY.cyan} /></View>;

  const metric = (label: string, value: string, icon: keyof typeof Ionicons.glyphMap, color: string) => (
    <View style={styles.metric}><View style={[styles.metricIcon, { backgroundColor: `${color}18` }]}><Ionicons name={icon} size={20} color={color} /></View><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricLabel}>{label}</Text></View>
  );

  return <ScrollView style={styles.screen} contentContainerStyle={styles.page} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} tintColor={TREASURY.teal} />}>
    <View style={styles.filterWrap}>
      <TouchableOpacity style={styles.filterButton} onPress={() => setFilterOpen(value => !value)}>
        <Ionicons name="calendar-outline" size={17} color={TREASURY.teal} />
        <Text style={styles.filterText}>{PERIODS.find(item => item.value === period)?.label}</Text>
        <Ionicons name={filterOpen ? 'chevron-up' : 'chevron-down'} size={16} color={TREASURY.muted} />
      </TouchableOpacity>
      {filterOpen && <View style={styles.filterMenu}>{PERIODS.map(item => <TouchableOpacity key={item.value} style={[styles.filterOption, item.value === period && styles.filterOptionActive]} onPress={() => { setPeriod(item.value); setFilterOpen(false); }}><Text style={[styles.filterOptionText, item.value === period && styles.filterOptionTextActive]}>{item.label}</Text>{item.value === period && <Ionicons name="checkmark" size={17} color={TREASURY.teal} />}</TouchableOpacity>)}</View>}
    </View>
    {period === 'day' && <><TouchableOpacity style={styles.dateControl} onPress={() => setShowDayPicker(true)}><Ionicons name="calendar-outline" size={17} color={TREASURY.teal} /><Text style={styles.dateControlText}>{selectedDate.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })}</Text><Ionicons name="chevron-down" size={16} color={TREASURY.muted} /></TouchableOpacity>{showDayPicker && <DateTimePicker value={selectedDate} mode="date" maximumDate={new Date()} display="default" onChange={(_, date) => { setShowDayPicker(false); if (date) setSelectedDate(date); }} />}</>}
    {period === 'month' && <View style={styles.filterPair}><View style={styles.pickerWrap}><Picker selectedValue={selectedDate.getMonth()} onValueChange={month => setSelectedDate(current => new Date(current.getFullYear(), Number(month), 1))} style={styles.picker} dropdownIconColor={TREASURY.teal}>{MONTHS.map((month, index) => <Picker.Item key={month} label={month} value={index} />)}</Picker></View><View style={styles.pickerWrap}><Picker selectedValue={selectedDate.getFullYear()} onValueChange={year => setSelectedDate(current => new Date(Number(year), current.getMonth(), 1))} style={styles.picker} dropdownIconColor={TREASURY.teal}>{Array.from({ length: 15 }, (_, index) => new Date().getFullYear() - 10 + index).map(year => <Picker.Item key={year} label={String(year)} value={year} />)}</Picker></View></View>}
    {period === 'year' && <View style={[styles.pickerWrap, { marginBottom: 10 }]}><Picker selectedValue={selectedDate.getFullYear()} onValueChange={year => setSelectedDate(current => new Date(Number(year), current.getMonth(), 1))} style={styles.picker} dropdownIconColor={TREASURY.teal}>{Array.from({ length: 15 }, (_, index) => new Date().getFullYear() - 10 + index).map(year => <Picker.Item key={year} label={String(year)} value={year} />)}</Picker></View>}
    <View style={styles.hero}>
      <View style={styles.heroGlow} />
      <Text style={styles.eyebrow}>TREASURY & COLLECTION</Text>
      <Text style={styles.greeting}>Good day, {name}</Text>
      <Text style={styles.heroSub}>Collection position for the selected period.</Text>
      <View style={styles.totalRow}><View><Text style={styles.totalLabel}>Collected · {stats.period_label || PERIODS.find(item => item.value === period)?.label}</Text><Text style={styles.total}>{peso(stats.collected_today)}</Text></View><View style={styles.paidPill}><Ionicons name="checkmark-circle" size={15} color="#A7F3D0" /><Text style={styles.paidPillText}>{stats.paid_today} paid</Text></View></View>
    </View>

    <View style={styles.grid}>
      {metric('Total violations', String(stats.violations_today), 'alert-circle-outline', TREASURY.blue)}
      {metric('Paid violations', String(stats.paid_today), 'checkmark-circle-outline', TREASURY.green)}
      {metric('This week', peso(stats.collected_this_week, 0), 'calendar-outline', TREASURY.blue)}
      {metric('This month', peso(stats.collected_this_month, 0), 'stats-chart-outline', TREASURY.teal)}
      {metric('Unpaid cases', String(stats.pending_violations), 'time-outline', TREASURY.amber)}
      {metric('Pending value', peso(stats.pending_amount, 0), 'receipt-outline', TREASURY.red)}
    </View>

    <Text style={styles.sectionLabel}>QUICK ACTIONS</Text>
    <View style={styles.actions}>
      <TouchableOpacity style={styles.primaryAction} onPress={() => router.push('/(treasurer)/payments' as Href)}><View style={styles.actionIcon}><Ionicons name="cash-outline" size={23} color="#FFF" /></View><View style={{ flex: 1 }}><Text style={styles.primaryActionTitle}>Process payment</Text><Text style={styles.primaryActionSub}>Find an unpaid violation</Text></View><Ionicons name="chevron-forward" size={20} color="#FFF" /></TouchableOpacity>
      <View style={styles.secondaryActions}>
        <TouchableOpacity style={styles.smallAction} onPress={() => router.push('/(treasurer)/violations' as Href)}><Ionicons name="alert-circle-outline" size={21} color={TREASURY.blue} /><Text style={styles.smallActionText}>Violations</Text></TouchableOpacity>
        <TouchableOpacity style={styles.smallAction} onPress={() => router.push('/(treasurer)/notifications' as Href)}><Ionicons name="notifications-outline" size={21} color={TREASURY.teal} /><Text style={styles.smallActionText}>Updates</Text></TouchableOpacity>
      </View>
    </View>

    <View style={styles.sectionHead}><View><Text style={styles.sectionTitle}>Recent collections</Text><Text style={styles.sectionSub}>Latest completed transactions</Text></View><TouchableOpacity onPress={() => router.push('/(treasurer)/payments' as Href)}><Text style={styles.viewAll}>View all</Text></TouchableOpacity></View>
    <View style={styles.panel}>{payments.length === 0 ? <View style={styles.empty}><Ionicons name="receipt-outline" size={28} color={TREASURY.muted} /><Text style={styles.emptyText}>No payments recorded yet.</Text></View> : payments.map((payment, index) => <View key={payment.payment_id} style={[styles.row, index === payments.length - 1 && { borderBottomWidth: 0 }]}><View style={styles.receiptIcon}><Ionicons name="receipt-outline" size={18} color={TREASURY.teal} /></View><View style={{ flex: 1 }}><Text style={styles.ticket}>{payment.official_receipt_number || payment.receipt_reference || `PAY-${String(payment.payment_id).padStart(6, '0')}`}</Text><Text style={styles.meta}>{payment.driver_name} · {formatTreasuryDate(payment.payment_date)}</Text></View><Text style={styles.amount}>{peso(payment.amount_paid)}</Text></View>)}</View>
  </ScrollView>;
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: TREASURY.bg }, page: { padding: 16, paddingBottom: 40 }, center: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: TREASURY.navy },
  filterWrap: { alignItems: 'flex-end', marginBottom: 10, zIndex: 20 }, filterButton: { minWidth: 164, height: 42, paddingHorizontal: 13, borderRadius: 13, borderWidth: 1, borderColor: TREASURY.line, backgroundColor: '#FFF', flexDirection: 'row', alignItems: 'center', gap: 8 }, filterText: { flex: 1, color: TREASURY.text, fontSize: 12, fontWeight: '800' }, filterMenu: { position: 'absolute', top: 47, right: 0, width: 190, padding: 6, borderRadius: 14, borderWidth: 1, borderColor: TREASURY.line, backgroundColor: '#FFF', elevation: 12, shadowColor: '#0F172A', shadowOpacity: .14, shadowRadius: 12 }, filterOption: { minHeight: 40, paddingHorizontal: 11, borderRadius: 9, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, filterOptionActive: { backgroundColor: '#E8F7F5' }, filterOptionText: { color: TREASURY.text, fontSize: 12, fontWeight: '700' }, filterOptionTextActive: { color: TREASURY.teal, fontWeight: '900' },
  dateControl: { height: 48, marginBottom: 10, paddingHorizontal: 13, borderRadius: 13, borderWidth: 1, borderColor: TREASURY.line, backgroundColor: '#FFF', flexDirection: 'row', alignItems: 'center', gap: 10 }, dateControlText: { flex: 1, color: TREASURY.text, fontSize: 13, fontWeight: '800' }, filterPair: { flexDirection: 'row', gap: 8, marginBottom: 10 }, pickerWrap: { flex: 1, height: 50, borderRadius: 13, borderWidth: 1, borderColor: TREASURY.line, backgroundColor: '#FFF', overflow: 'hidden', justifyContent: 'center' }, picker: { height: 50, color: TREASURY.text },
  hero: { minHeight: 190, borderRadius: 24, backgroundColor: TREASURY.navy, padding: 22, overflow: 'hidden' }, heroGlow: { position: 'absolute', width: 190, height: 190, borderRadius: 95, backgroundColor: 'rgba(56,189,248,.13)', right: -55, top: -70 }, eyebrow: { color: TREASURY.cyan, fontSize: 10, fontWeight: '900', letterSpacing: 1.5 }, greeting: { color: '#FFF', fontSize: 25, fontWeight: '900', marginTop: 9 }, heroSub: { color: '#B9CBE0', marginTop: 4 }, totalRow: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'space-between', marginTop: 25 }, totalLabel: { color: '#AFC3D8', fontSize: 11 }, total: { color: '#FFF', fontSize: 28, fontWeight: '900', marginTop: 3 }, paidPill: { flexDirection: 'row', gap: 5, backgroundColor: 'rgba(22,163,106,.22)', borderRadius: 20, paddingHorizontal: 10, paddingVertical: 7 }, paidPillText: { color: '#D1FAE5', fontWeight: '800', fontSize: 11 },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 12 }, metric: { width: '48.5%', backgroundColor: TREASURY.surface, borderRadius: 18, borderWidth: 1, borderColor: TREASURY.line, padding: 14 }, metricIcon: { width: 36, height: 36, borderRadius: 11, alignItems: 'center', justifyContent: 'center' }, metricValue: { color: TREASURY.text, fontSize: 18, fontWeight: '900', marginTop: 11 }, metricLabel: { color: TREASURY.muted, fontSize: 11, marginTop: 3 },
  sectionLabel: { color: TREASURY.muted, fontSize: 10, fontWeight: '900', letterSpacing: 1.3, marginTop: 24, marginBottom: 9 }, actions: { gap: 10 }, primaryAction: { flexDirection: 'row', alignItems: 'center', gap: 12, padding: 15, borderRadius: 18, backgroundColor: TREASURY.teal }, actionIcon: { width: 42, height: 42, borderRadius: 13, backgroundColor: 'rgba(255,255,255,.15)', alignItems: 'center', justifyContent: 'center' }, primaryActionTitle: { color: '#FFF', fontWeight: '900', fontSize: 15 }, primaryActionSub: { color: '#D5F5F1', fontSize: 11, marginTop: 2 }, secondaryActions: { flexDirection: 'row', gap: 10 }, smallAction: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 9, backgroundColor: '#FFF', borderWidth: 1, borderColor: TREASURY.line, borderRadius: 15, padding: 14 }, smallActionText: { color: TREASURY.text, fontWeight: '800' },
  sectionHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 25, marginBottom: 10 }, sectionTitle: { color: TREASURY.text, fontSize: 17, fontWeight: '900' }, sectionSub: { color: TREASURY.muted, fontSize: 11, marginTop: 2 }, viewAll: { color: TREASURY.teal, fontWeight: '800', fontSize: 12 }, panel: { backgroundColor: '#FFF', borderRadius: 18, borderWidth: 1, borderColor: TREASURY.line, paddingHorizontal: 14 }, row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#EEF2F7' }, receiptIcon: { width: 36, height: 36, borderRadius: 11, backgroundColor: '#E8F7F5', alignItems: 'center', justifyContent: 'center' }, ticket: { color: TREASURY.text, fontWeight: '800', fontSize: 12 }, meta: { color: TREASURY.muted, fontSize: 10, marginTop: 3 }, amount: { color: TREASURY.green, fontWeight: '900', fontSize: 13 }, empty: { padding: 28, alignItems: 'center', gap: 8 }, emptyText: { color: TREASURY.muted },
});
