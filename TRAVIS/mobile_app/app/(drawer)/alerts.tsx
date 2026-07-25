import React, { useState, useCallback } from 'react';
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
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../../api/axiosConfig';

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
interface AlertItem {
  alert_id: number;
  alert_type: string;
  severity: string;
  message: string;
  status: string;
  generated_at: string;
}

type SeverityFilter = '' | 'critical' | 'warning' | 'info' | 'resolved';

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
  const [loading, setLoading] = useState(true);
  const [alerts, setAlerts] = useState<AlertItem[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [severityFilter, setSeverityFilter] = useState<SeverityFilter>('');

  // ===== FETCH ALERTS =====
  const fetchAlerts = useCallback(async (showSpinner = true) => {
    try {
      if (showSpinner) setLoading(true);
      const params: any = { limit: 100 };
      if (severityFilter === 'resolved') {
        params.status = 'resolved';
      } else {
        params.status = 'active,acknowledged';
      }
      const response = await api.get('get_alerts.php', { params });
      if (response.data.success) {
        setAlerts(response.data.data);
      }
    } catch (error) {
      console.error('Fetch alerts error:', error);
      Alert.alert('Error', 'Failed to load alerts.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [severityFilter]);

  useFocusEffect(
    useCallback(() => {
      fetchAlerts();
      const timer = setInterval(() => fetchAlerts(false), 5000);
      return () => clearInterval(timer);
    }, [fetchAlerts])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchAlerts();
  };

  // ===== ACKNOWLEDGE ALERT =====
  const acknowledgeAlert = async (alertId: number) => {
    try {
      const response = await api.post('acknowledge_alert.php', { alert_id: alertId });
      if (response.data.success) {
        Alert.alert('Success', 'Alert acknowledged.');
        fetchAlerts();
      } else {
        Alert.alert('Error', response.data.error || 'Failed to acknowledge.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.error || 'Network error.');
    }
  };

  // ===== FILTER =====
  const filteredAlerts = severityFilter
    ? alerts.filter(a => severityFilter === 'resolved' ? a.status === 'resolved' : a.severity === severityFilter)
    : alerts;

  // ===== COUNTS =====
  const counts = {
    critical: alerts.filter(a => a.severity === 'critical').length,
    warning: alerts.filter(a => a.severity === 'warning').length,
    info: alerts.filter(a => a.severity === 'info').length,
    resolved: alerts.filter(a => a.status === 'resolved').length,
  };

  // ========== RENDER HELPERS ==========
  const renderSummaryCell = (icon: React.ReactNode, label: string, value: number, isLast: boolean) => (
    <View style={[styles.summaryCell, !isLast && styles.summaryCellDivider]}>
      {icon}
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );

  const renderAlertItem = ({ item }: { item: AlertItem }) => (
    <View style={[styles.alertItem, { backgroundColor: severityColor(item.severity) + '0D', borderColor: severityColor(item.severity) + '33' }]}>
      <View style={styles.alertRow}>
        <View style={styles.alertTypeRow}>
          <Ionicons name={alertTypeIcon(item.alert_type)} size={15} color={severityColor(item.severity)} />
          <Text style={styles.alertType}>{item.alert_type.toUpperCase()}</Text>
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
        <Text style={styles.alertTime}>{item.generated_at}</Text>
      </View>

      {item.status === 'active' && (
        <TouchableOpacity style={styles.acknowledgeButton} onPress={() => acknowledgeAlert(item.alert_id)}>
          <Text style={styles.acknowledgeText}>Acknowledge</Text>
        </TouchableOpacity>
      )}
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
              keyExtractor={item => item.alert_id.toString()}
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

  summaryPanel: {
    flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 18,
    borderWidth: 1, borderColor: COLORS.border, paddingVertical: 16, marginBottom: 18, ...softShadow,
  },
  summaryCell: { flex: 1, alignItems: 'center', paddingHorizontal: 4 },
  summaryCellDivider: { borderRightWidth: 1, borderRightColor: COLORS.border },
  summaryValue: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 10, color: COLORS.textTertiary, textAlign: 'center' },

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

  filterRow: { marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },

  emptyState: { alignItems: 'center', paddingVertical: 30 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 8, lineHeight: 18, paddingHorizontal: 12 },

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

  acknowledgeButton: { marginTop: 10, backgroundColor: COLORS.primary, paddingVertical: 6, borderRadius: 8, alignItems: 'center' },
  acknowledgeText: { color: '#fff', fontWeight: '700', fontSize: 12 },
});
