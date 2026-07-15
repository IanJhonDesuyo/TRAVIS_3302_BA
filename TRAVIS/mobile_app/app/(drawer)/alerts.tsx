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
} from 'react-native';
import { useRouter } from 'expo-router';

// ========== TYPES ==========
interface Alert {
  id: number;
  type: string;
  message: string;
  severity: 'critical' | 'warning' | 'info' | 'resolved';
  status: string;
  generatedAt: string;
}

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
    case 'critical':
      return '#dc2626';
    case 'warning':
      return '#f59e0b';
    case 'info':
      return '#2563eb';
    case 'resolved':
      return '#16a34a';
    default:
      return '#6b7280';
  }
};

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'active' || s === 'critical') return '#dc2626';
  if (s === 'acknowledged') return '#f59e0b';
  if (s === 'resolved') return '#16a34a';
  return '#6b7280';
};

// ========== SCREEN ==========
export default function AlertsScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  // Mock summary counts
  const counts = {
    critical: 2,
    warning: 2,
    info: 1,
    resolved: 1,
  };

  // Simulate data fetch
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

  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <View style={styles.statHeader}>
        <Text style={[styles.statIcon, { color }]}>●</Text>
        <Text style={styles.statLabel}>{label}</Text>
      </View>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );

  const renderAlertItem = ({ item }: { item: Alert }) => (
    <View style={styles.alertItem}>
      <View style={styles.alertRow}>
        <Text style={styles.alertType}>{item.type.toUpperCase()}</Text>
        <View style={[styles.severityBadge, { backgroundColor: severityColor(item.severity) + '20' }]}>
          <Text style={[styles.severityText, { color: severityColor(item.severity) }]}>
            {item.severity.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.alertMessage}>{item.message}</Text>
      <View style={styles.alertFooter}>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status}
          </Text>
        </View>
        <Text style={styles.alertTime}>{item.generatedAt}</Text>
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading alerts...</Text>
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
          <Text style={styles.pageTitle}>Alerts & Notifications</Text>
          <Text style={styles.pageSub}>Real-time computer vision and system event stream</Text>
        </View>

        {/* Stat Cards */}
        <View style={styles.statsRow}>
          {renderStatCard('Critical', counts.critical, '#dc2626')}
          {renderStatCard('Warning', counts.warning, '#f59e0b')}
          {renderStatCard('Info', counts.info, '#2563eb')}
          {renderStatCard('Resolved', counts.resolved, '#16a34a')}
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

          {alerts.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyText}>No alerts found. Computer vision alerts will appear here after records are inserted into monitoring_alerts.</Text>
            </View>
          ) : (
            <FlatList
              data={alerts}
              renderItem={renderAlertItem}
              keyExtractor={item => item.id.toString()}
              scrollEnabled={false}
            />
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
    width: '23%',
    borderLeftWidth: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  statHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 4 },
  statIcon: { fontSize: 16, marginRight: 4 },
  statLabel: { fontSize: 12, color: '#64748b', fontWeight: '500' },
  statValue: { fontSize: 20, fontWeight: '700' },

  sectionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78' },
  onlineBadge: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#e6f7e6', paddingHorizontal: 10, paddingVertical: 4, borderRadius: 16 },
  onlineDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#16a34a', marginRight: 4 },
  onlineText: { fontSize: 11, fontWeight: '600', color: '#16a34a' },

  emptyState: { padding: 20, alignItems: 'center' },
  emptyText: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },

  alertItem: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  alertRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  alertType: { fontSize: 13, fontWeight: '600', color: '#0b3d78' },
  severityBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12 },
  severityText: { fontSize: 11, fontWeight: '600' },
  alertMessage: { fontSize: 14, color: '#1e293b', marginBottom: 4 },
  alertFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12 },
  statusText: { fontSize: 11, fontWeight: '500', textTransform: 'capitalize' },
  alertTime: { fontSize: 11, color: '#94a3b8' },
});