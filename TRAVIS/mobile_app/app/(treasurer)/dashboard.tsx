import React, { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Href, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import api from '../../api/axiosConfig';

type Stats = { violations_today: number; pending_violations: number; paid_today: number; collected_today: number };
type Payment = { payment_id: number; ticket_number: string; driver_name: string; amount_paid: number; payment_date: string };
const money = (value: number) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`;

export default function TreasurerDashboard() {
  const router = useRouter();
  const [stats, setStats] = useState<Stats>({ violations_today: 0, pending_violations: 0, paid_today: 0, collected_today: 0 });
  const [payments, setPayments] = useState<Payment[]>([]);
  const [loading, setLoading] = useState(true);
  const load = useCallback(async () => {
    try {
      const [statsRes, paymentsRes] = await Promise.all([api.get('get_dashboard_stats.php'), api.get('get_payments.php')]);
      if (statsRes.data.success) setStats(statsRes.data.data);
      if (paymentsRes.data.success) setPayments(paymentsRes.data.data.slice(0, 5));
    } finally { setLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);
  if (loading) return <View style={styles.center}><ActivityIndicator size="large" color="#087D78" /></View>;
  const card = (label: string, value: string | number, icon: any, color: string) => <View style={styles.card}><Ionicons name={icon} size={22} color={color} /><Text style={styles.cardValue}>{value}</Text><Text style={styles.cardLabel}>{label}</Text></View>;
  return <ScrollView contentContainerStyle={styles.page} refreshControl={<RefreshControl refreshing={false} onRefresh={load} />}>
    <Text style={styles.title}>Collections Overview</Text><Text style={styles.sub}>Today’s violation payments and collection activity.</Text>
    <View style={styles.grid}>
      {card('Total Collected Today', money(stats.collected_today), 'wallet-outline', '#087D78')}
      {card('Paid Today', stats.paid_today, 'checkmark-circle-outline', '#15966F')}
      {card('Pending Payments', stats.pending_violations, 'time-outline', '#EB941F')}
      {card('Violations Today', stats.violations_today, 'alert-circle-outline', '#C84B45')}
    </View>
    <View style={styles.actions}><TouchableOpacity style={styles.primary} onPress={() => router.push('/(treasurer)/payments' as Href)}><Text style={styles.primaryText}>Process Payment</Text></TouchableOpacity><TouchableOpacity style={styles.secondary} onPress={() => router.push('/(treasurer)/reports' as Href)}><Text style={styles.secondaryText}>Collection Reports</Text></TouchableOpacity></View>
    <Text style={styles.heading}>Recent Payments</Text>
    <View style={styles.panel}>{payments.length === 0 ? <Text style={styles.empty}>No payments recorded yet.</Text> : payments.map(p => <View key={p.payment_id} style={styles.row}><View style={{ flex: 1 }}><Text style={styles.ticket}>{p.ticket_number}</Text><Text style={styles.meta}>{p.driver_name} · {p.payment_date}</Text></View><Text style={styles.amount}>{money(p.amount_paid)}</Text></View>)}</View>
  </ScrollView>;
}

const styles = StyleSheet.create({ page: { padding: 18, paddingBottom: 36 }, center: { flex: 1, alignItems: 'center', justifyContent: 'center' }, title: { fontSize: 25, fontWeight: '800', color: '#10202C' }, sub: { color: '#64748B', marginTop: 4, marginBottom: 18 }, grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 }, card: { width: '48%', minHeight: 125, backgroundColor: '#FFF', padding: 16, borderRadius: 16, borderWidth: 1, borderColor: '#E2E8F0' }, cardValue: { fontSize: 21, fontWeight: '800', color: '#10202C', marginTop: 12 }, cardLabel: { color: '#64748B', fontSize: 12, marginTop: 4 }, actions: { flexDirection: 'row', gap: 10, marginVertical: 20 }, primary: { flex: 1, backgroundColor: '#087D78', padding: 14, borderRadius: 12, alignItems: 'center' }, primaryText: { color: '#FFF', fontWeight: '800' }, secondary: { flex: 1, backgroundColor: '#E6F4F3', padding: 14, borderRadius: 12, alignItems: 'center' }, secondaryText: { color: '#087D78', fontWeight: '800' }, heading: { fontSize: 17, fontWeight: '800', color: '#10202C', marginBottom: 10 }, panel: { backgroundColor: '#FFF', borderRadius: 16, paddingHorizontal: 15, borderWidth: 1, borderColor: '#E2E8F0' }, row: { flexDirection: 'row', paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: '#EEF2F7', alignItems: 'center' }, ticket: { fontWeight: '800', color: '#10202C' }, meta: { color: '#64748B', fontSize: 11, marginTop: 4 }, amount: { color: '#15966F', fontWeight: '800' }, empty: { padding: 25, color: '#64748B', textAlign: 'center' } });
