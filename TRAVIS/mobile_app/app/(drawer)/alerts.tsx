import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  FlatList,
  ActivityIndicator,
  StatusBar,
  RefreshControl,
  TouchableOpacity,
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
interface Alert {
  id: number;
  type: string;
  message: string;
  severity: 'critical' | 'warning' | 'info' | 'resolved';
  status: string;
  generatedAt: string;
}

type SeverityFilter = '' | 'critical' | 'warning' | 'info' | 'resolved';

// ========== MOCK DATA (replace with API calls) ==========
const mockAlerts: Alert[] = [
  {
    id: 1,
    type: 'congestion',
    message: 'Heavy traffic at EDSA-Ortigas',
    severity: 'critical',
    status: 'active',
    generatedAt: '2026-07-16 11:45:00',
  },
  {
    id: 2,
    type: 'collision',
    message: 'Potential collision detected near Taft Ave',
    severity: 'warning',
    status: 'active',
    generatedAt: '2026-07-16 10:30:00',
  },
  {
    id: 3,
    type: 'officer',
    message: 'Officer presence needed at Roxas Blvd',
    severity: 'info',
    status: 'acknowledged',
    generatedAt: '2026-07-16 09:15:00',
  },
  {
    id: 4,
    type: 'weather',
    message: 'Heavy rain expected, reduce speed',
    severity: 'warning',
    status: 'resolved',
    generatedAt: '2026-07-16 08:00:00',
  },
  {
    id: 5,
    type: 'system',
    message: 'Camera offline: Main Intersection',
    severity: 'critical',
    status: 'active',
    generatedAt: '2026-07-16 07:20:00',
  },
];

// ========== HELPERS ==========
const severityColor = (severity: string): string => {
  switch (severity.toLowerCase()) {
    case 'critical': return COLORS.danger;
    case 'warning': return COLORS.warning;
    case 'info': return COLORS.primary;
    case 'resolved': return COLORS.success;
    default: return COLORS.neutral;
  }
};

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'active' || s === 'critical') return COLORS.danger;
  if (s === 'acknowledged') return COLORS.warning;
  if (s === 'resolved') return COLORS.success;
  return COLORS.neutral;
};

const alertTypeIcon = (type: string): keyof typeof Ionicons.glyphMap => {
  switch (type.toLowerCase()) {
    case 'congestion': return 'car-outline';
    case 'collision': return 'warning-outline';
    case 'officer': return 'person-outline';
    case 'weather': return 'rainy-outline';
    case 'system': return 'hardware-chip-outline';
    default: return 'notifications-outline';
  }
};

const SEVERITY_FILTERS: { label: string; value: SeverityFilter }[] = [
  { label: 'All', value: '' },
  { label: 'Critical', value: 'critical' },
  { label: 'Warning', value: 'warning' },
  { label: 'Info', value: 'info' },
  { label: 'Resolved', value: 'resolved' },
];

// ========== SCREEN ==========
export default function AlertsScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [severityFilter, setSeverityFilter] = useState<SeverityFilter>('');

  // Mock summary counts
  const counts = {
    critical: 2,
    warning: 2,
    info: 1,
    resolved: 1,
  };

  useEffect(() => {
    fetchAlerts();
  }, []);

  const fetchAlerts = async () => {
    // Replace with actual API call: fetch('/api/alerts')
    await new Promise(resolve => setTimeout(resolve, 800));
    setAlerts(mockAlerts);
    setLoading(false);
    setRefreshing(false);
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchAlerts();
  };

  const filteredAlerts = severityFilter
    ? alerts.filter(a => a.severity === severityFilter)
    : alerts;

  // ---------- RENDER HELPERS ----------
  const renderSummaryCell = (icon: React.ReactNode, label: string, value: number, isLast: boolean) => (
    <View style={[styles.summaryCell, !isLast && styles.summaryCellDivider]}>
      {icon}
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );

  const renderAlertItem = ({ item }: { item: Alert }) => (
    <View style={[styles.alertItem, { backgroundColor: severityColor(item.severity) + '0D', borderColor: severityColor(item.severity) + '33' }]}>
      <View style={styles.alertRow}>
        <View style={styles.alertTypeRow}>
          <Ionicons name={alertTypeIcon(item.type)} size={15} color={severityColor(item.severity)} />
          <Text style={styles.alertType}>{item.type.toUpperCase()}</Text>
        </View>
        <View style={[styles.severityBadge, { backgroundColor: severityColor(item.severity) }]}>
          <Text style={styles.severityText}>{item.severity.toUpperCase()}</Text>
        </View>
      </View>

      <Text style={styles.alertMessage}>{item.message}</Text>

      <View style={styles.alertFooter}>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>{item.status}</Text>
        </View>
        <Text style={styles.alertTime}>{item.generatedAt}</Text>
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading alerts…</Text>
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
          <Text style={styles.eyebrow}>SYSTEM MONITORING</Text>
          <Text style={styles.pageTitle}>Alerts & Notifications</Text>
          <Text style={styles.pageSub}>Real-time computer vision and system event stream.</Text>
        </View>

        {/* Summary panel */}
        <View style={styles.summaryPanel}>
          {renderSummaryCell(<Ionicons name="alert-circle" size={16} color={COLORS.danger} />, 'Critical', counts.critical, false)}
          {renderSummaryCell(<Ionicons name="warning" size={16} color={COLORS.warning} />, 'Warning', counts.warning, false)}
          {renderSummaryCell(<Ionicons name="information-circle" size={16} color={COLORS.primary} />, 'Info', counts.info, false)}
          {renderSummaryCell(<Ionicons name="checkmark-circle" size={16} color={COLORS.success} />, 'Resolved', counts.resolved, true)}
        </View>

        {/* Live Event Stream */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Live Event Stream</Text>
            <View style={styles.onlineBadge}>
              <View style={styles.onlineDot} />
              <Text style={styles.onlineText}>Database Connected</Text>
            </View>
          </View>

          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.filterRow} contentContainerStyle={{ paddingRight: 4 }}>
            {SEVERITY_FILTERS.map(f => {
              const active = severityFilter === f.value;
              return (
                <TouchableOpacity
                  key={f.label}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => setSeverityFilter(f.value)}
                  activeOpacity={0.7}
                >
                  <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{f.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>

          {filteredAlerts.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="checkmark-done-circle-outline" size={26} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>
                {alerts.length === 0
                  ? 'No alerts found. Computer vision alerts will appear here after records are inserted into monitoring_alerts.'
                  : 'No alerts match this filter.'}
              </Text>
            </View>
          ) : (
            <FlatList
              data={filteredAlerts}
              renderItem={renderAlertItem}
              keyExtractor={item => item.id.toString()}
              scrollEnabled={false}
              ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
            />
          )}
        </View>
      </ScrollView>
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
  summaryValue: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 10, color: COLORS.textTertiary, textAlign: 'center' },

  // Section card
  sectionCard: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 16,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  sectionHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: COLORS.textPrimary },
  onlineBadge: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.success + '14',
    paddingHorizontal: 10, paddingVertical: 5, borderRadius: 16,
  },
  onlineDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: COLORS.success, marginRight: 6 },
  onlineText: { fontSize: 11, fontWeight: '700', color: COLORS.success },

  // Filter chips
  filterRow: { marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },

  // Empty state
  emptyState: { alignItems: 'center', paddingVertical: 30 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 8, lineHeight: 18, paddingHorizontal: 12 },

  // Alert card — tinted background per severity (same pattern as the dashboard's Alert Feed)
  alertItem: { borderRadius: 14, borderWidth: 1, padding: 14 },
  alertRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  alertTypeRow: { flexDirection: 'row', alignItems: 'center' },
  alertType: { fontSize: 12, fontWeight: '700', color: COLORS.textPrimary, letterSpacing: 0.4, marginLeft: 6 },
  severityBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  severityText: { fontSize: 10, fontWeight: '800', color: '#FFFFFF', letterSpacing: 0.3 },
  alertMessage: { fontSize: 14, color: COLORS.textPrimary, marginBottom: 10, lineHeight: 19 },
  alertFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  statusBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  statusText: { fontSize: 11, fontWeight: '600', textTransform: 'capitalize' },
  alertTime: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
});