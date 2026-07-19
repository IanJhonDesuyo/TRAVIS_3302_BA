import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  StatusBar,
  useWindowDimensions,
} from 'react-native';
import { LineChart, BarChart } from 'react-native-chart-kit';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';

// ========== DATA & HELPERS (unchanged) ==========
interface StatData {
  vehiclesToday: number; inboundToday: number; outboundToday: number;
  violationsToday: number; paidViolations: number; unpaidViolations: number;
  collectedToday: number; completedPayments: number; activeAlerts: number; alertsToday: number;
}
interface MonitoringData {
  cameraName: string; location: string; vehicleCount: number; inbound: number; outbound: number;
  congestion: string; officerPresence: string; potentialCollision: string; recordedAt: string; cameraStatus: string;
}
interface HotspotLocation { location: string; total: number; }
interface Hotspots { high: HotspotLocation[]; medium: HotspotLocation[]; low: HotspotLocation[]; }
interface Alert { id: number; type: string; severity: string; message: string; time: string; }
interface Prediction { riskLevel: string; confidence: number; month: string; recommendations: string[]; }

const mockStats: StatData = {
  vehiclesToday: 1243, inboundToday: 720, outboundToday: 523,
  violationsToday: 87, paidViolations: 34, unpaidViolations: 53,
  collectedToday: 124500, completedPayments: 42, activeAlerts: 5, alertsToday: 3,
};
const mockPendingViolations = 142;
const mockCongestionEvents = 18;
const mockCollisionEvents = 2;
const mockOnlineCameras = 12;
const mockTotalCameras = 20;

const mockLatestMonitoring: MonitoringData = {
  cameraName: 'Main Intersection', location: 'EDSA Cor. Ayala',
  vehicleCount: 34, inbound: 12, outbound: 22, congestion: 'Moderate',
  officerPresence: 'Present', potentialCollision: 'Low',
  recordedAt: '2026-07-16 12:12:18', cameraStatus: 'Online',
};

const mockMonthlyTrend = {
  labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  data: [65, 72, 88, 94, 82, 110, 124, 0, 0, 0, 0, 0],
};

const mockTopViolations = {
  labels: ['Speeding', 'Illegal Parking', 'Disregarded Signal', 'Overloading', 'No Helmet', 'Other'],
  data: [45, 38, 27, 19, 12, 8],
};

const mockHotspots: Hotspots = {
  high: [
    { location: 'EDSA - Ortigas', total: 156 },
    { location: 'C5 - Tiendesitas', total: 132 },
    { location: 'Commonwealth - Fairview', total: 98 },
  ],
  medium: [
    { location: 'Taft - Vito Cruz', total: 67 },
    { location: 'Roxas Blvd - MOA', total: 55 },
  ],
  low: [
    { location: 'Macapagal - Seaside', total: 23 },
    { location: 'BGC - 32nd St', total: 18 },
  ],
};

const mockRecentAlerts: Alert[] = [
  { id: 1, type: 'Congestion', severity: 'High', message: 'Heavy traffic at EDSA-Ortigas', time: '11:45 AM' },
  { id: 2, type: 'Collision', severity: 'Medium', message: 'Potential collision detected near Taft Ave', time: '10:30 AM' },
  { id: 3, type: 'Officer', severity: 'Low', message: 'Officer presence needed at Roxas Blvd', time: '9:15 AM' },
  { id: 4, type: 'Weather', severity: 'Medium', message: 'Heavy rain expected, reduce speed', time: '8:00 AM' },
];

const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'online' || s === 'paid' || s === 'low') return '#1E9E5A';
  if (s === 'high' || s === 'critical') return '#E0392C';
  if (s === 'medium' || s === 'moderate' || s === 'pending') return '#D9822B';
  if (s === 'none') return '#8A8F98';
  return '#8A8F98';
};

// ========== SCREEN COMPONENT ==========
export default function DashboardScreen() {
  const { width } = useWindowDimensions();
  const chartWidth = Math.max(width - 40, 200);
  const isTablet = width >= 700;

  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'overview' | 'monitoring' | 'analytics'>('overview');
  const [aiExpanded, setAiExpanded] = useState(false);

  const prediction: Prediction = {
    riskLevel: 'High', confidence: 78, month: 'July 2026',
    recommendations: [
      'Deploy additional enforcers to EDSA',
      'Monitor inbound traffic at Ayala',
      'Activate congestion alert system',
    ],
  };

  useEffect(() => {
    setTimeout(() => setLoading(false), 1000);
  }, []);

  const refresh = () => { setLoading(true); setTimeout(() => setLoading(false), 700); };

  // ---------- RENDER HELPERS ----------
  const renderHeroStat = (value: string | number, label: string, isFirst: boolean) => (
    <View style={[styles.heroStatItem, !isFirst && styles.heroStatDivider]}>
      <Text style={styles.heroStatValue}>{value}</Text>
      <Text style={styles.heroStatLabel}>{label}</Text>
    </View>
  );

  const renderQuickAction = (icon: React.ReactNode, label: string, onPress?: () => void) => (
    <TouchableOpacity style={styles.quickAction} activeOpacity={0.6} onPress={onPress}>
      {icon}
      <Text style={styles.quickActionLabel}>{label}</Text>
    </TouchableOpacity>
  );

  const renderHotspotRow = (loc: HotspotLocation, color: string, key: number) => (
    <View key={key} style={styles.hotspotRowItem}>
      <View style={[styles.hotspotDot, { backgroundColor: color }]} />
      <Text style={styles.hotspotRowName}>{loc.location}</Text>
      <Text style={styles.hotspotRowCount}>{loc.total}</Text>
    </View>
  );

  const renderCompactMetric = (label: string, value: string | number, isLast: boolean) => (
    <View style={[styles.compactMetric, !isLast && styles.compactMetricDivider]}>
      <Text style={styles.compactValue}>{value}</Text>
      <Text style={styles.compactLabel}>{label}</Text>
    </View>
  );

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#3A3FF0" />
        <Text style={styles.loadingText}>Loading dashboard…</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#FAFAFA" />
      <View style={styles.container}>
        {/* Header — quiet, no boxed badges */}
        <View style={styles.header}>
          <View>
            <Text style={styles.greeting}>Good morning</Text>
            <Text style={styles.pageTitle}>Traffic Operations</Text>
          </View>
          <View style={styles.liveIndicator}>
            <View style={styles.liveDot} />
            <Text style={styles.liveText}>Live</Text>
          </View>
        </View>
        <Text style={styles.headerMeta}>July 16, 2026 · Updated 2 min ago</Text>

        <ScrollView style={styles.scrollArea} contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
          {activeTab === 'overview' && (
            <>
              {/* HERO — the one loud element on the screen */}
              <View style={styles.heroCard}>
                <View style={styles.heroTopRow}>
                  <Text style={styles.heroEyebrow}>TRAFFIC STATUS</Text>
                  <View style={styles.heroStatusPill}>
                    <View style={[styles.heroStatusDot, { backgroundColor: statusColor(mockLatestMonitoring.congestion) }]} />
                    <Text style={styles.heroStatusText}>{mockLatestMonitoring.congestion}</Text>
                  </View>
                </View>

                <View style={styles.heroStatsRow}>
                  {renderHeroStat(mockStats.vehiclesToday.toLocaleString(), 'Vehicles', true)}
                  {renderHeroStat(mockStats.violationsToday, 'Violations', false)}
                  {renderHeroStat(mockStats.activeAlerts, 'Alerts', false)}
                  {renderHeroStat(formatCurrency(mockStats.collectedToday), 'Collected', false)}
                </View>
              </View>

              {/* Quick actions — flat, horizontal, low-chrome */}
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.quickActionsRow} contentContainerStyle={{ paddingRight: 20 }}>
                {renderQuickAction(<Ionicons name="refresh" size={17} color="#0A0A0B" />, 'Refresh', refresh)}
                {renderQuickAction(<Ionicons name="videocam-outline" size={17} color="#0A0A0B" />, 'Cameras')}
                {renderQuickAction(<Ionicons name="document-text-outline" size={17} color="#0A0A0B" />, 'Reports')}
                {renderQuickAction(<Ionicons name="notifications-outline" size={17} color="#0A0A0B" />, 'Alerts')}
              </ScrollView>

              {/* AI Assistant — one card, progressive disclosure */}
              <View style={styles.aiCard}>
                <View style={styles.aiTopRow}>
                  <View style={styles.aiKickerRow}>
                    <MaterialCommunityIcons name="cpu-64-bit" size={13} color="#3A3FF0" style={{ marginRight: 6 }} />
                    <Text style={styles.aiEyebrow}>AI ASSISTANT</Text>
                  </View>
                  <TouchableOpacity onPress={refresh} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                    <Ionicons name="refresh-outline" size={16} color="#8A8F98" />
                  </TouchableOpacity>
                </View>

                <View style={styles.aiMainRow}>
                  <Text style={styles.aiConfidenceValue}>{prediction.confidence}%</Text>
                  <View style={styles.aiMainTextCol}>
                    <View style={styles.riskBadge}>
                      <View style={[styles.riskDot, { backgroundColor: statusColor(prediction.riskLevel) }]} />
                      <Text style={[styles.riskBadgeText, { color: statusColor(prediction.riskLevel) }]}>{prediction.riskLevel} risk</Text>
                    </View>
                    <Text style={styles.aiPeriod}>Forecast confidence · {prediction.month}</Text>
                  </View>
                </View>

                <Text style={styles.aiRecommendationLead}>{prediction.recommendations[0]}</Text>

                <TouchableOpacity style={styles.aiExpandTrigger} onPress={() => setAiExpanded(v => !v)} activeOpacity={0.6}>
                  <Text style={styles.aiExpandLabel}>{aiExpanded ? 'Hide details' : 'View details'}</Text>
                  <Ionicons name={aiExpanded ? 'chevron-up' : 'chevron-down'} size={14} color="#3A3FF0" />
                </TouchableOpacity>

                {aiExpanded && (
                  <View style={styles.aiExpandedContent}>
                    <View style={styles.aiDivider} />

                    <Text style={styles.aiSectionLabel}>Recommended actions</Text>
                    {prediction.recommendations.map((action, idx) => (
                      <View key={idx} style={styles.aiActionRow}>
                        <View style={styles.aiActionBullet} />
                        <Text style={styles.aiActionText}>{action}</Text>
                      </View>
                    ))}

                    <View style={styles.aiDivider} />

                    <Text style={styles.aiSectionLabel}>Deployment guidance</Text>
                    <View style={styles.deploymentRow}>
                      <Text style={styles.deploymentLabel}>Personnel</Text>
                      <Text style={styles.deploymentValue}>5–6 enforcers</Text>
                    </View>
                    <View style={styles.deploymentRow}>
                      <Text style={styles.deploymentLabel}>Monitoring</Text>
                      <Text style={styles.deploymentValue}>Intensive</Text>
                    </View>

                    <View style={styles.aiDivider} />

                    <Text style={styles.aiSectionLabel}>Hotspots by risk</Text>
                    {mockHotspots.high.slice(0, 3).map((loc, idx) => renderHotspotRow(loc, '#E0392C', idx))}
                    {mockHotspots.medium.slice(0, 2).map((loc, idx) => renderHotspotRow(loc, '#D9822B', idx + 10))}
                    {mockHotspots.low.slice(0, 2).map((loc, idx) => renderHotspotRow(loc, '#1E9E5A', idx + 20))}
                  </View>
                )}
              </View>

              {/* Secondary metrics — quiet, text-first, no card shadows stacked on borders */}
              <Text style={styles.groupLabel}>This month</Text>
              <View style={styles.compactRow}>
                {renderCompactMetric('Pending violations', mockPendingViolations, false)}
                {renderCompactMetric('Congestion events', mockCongestionEvents, false)}
                {renderCompactMetric('Collisions', mockCollisionEvents, false)}
                {renderCompactMetric('Cameras online', `${mockOnlineCameras}/${mockTotalCameras}`, true)}
              </View>
            </>
          )}

          {activeTab === 'monitoring' && (
            <>
              <View style={styles.card}>
                <View style={styles.monitoringHeader}>
                  <View>
                    <Text style={styles.cardTitle}>{mockLatestMonitoring.cameraName}</Text>
                    <Text style={styles.cardSubtitle}>{mockLatestMonitoring.location}</Text>
                  </View>
                  <View style={styles.statusPill}>
                    <View style={[styles.heroStatusDot, { backgroundColor: statusColor(mockLatestMonitoring.cameraStatus) }]} />
                    <Text style={[styles.statusPillText, { color: statusColor(mockLatestMonitoring.cameraStatus) }]}>{mockLatestMonitoring.cameraStatus}</Text>
                  </View>
                </View>

                <View style={styles.monitoringGrid}>
                  {(['Vehicles', 'Inbound', 'Outbound', 'Congestion', 'Officer', 'Collision risk'] as const).map((label, i) => {
                    const vals = [mockLatestMonitoring.vehicleCount, mockLatestMonitoring.inbound, mockLatestMonitoring.outbound, mockLatestMonitoring.congestion, mockLatestMonitoring.officerPresence, mockLatestMonitoring.potentialCollision];
                    return (
                      <View key={label} style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
                        <Text style={styles.monitoringLabel}>{label}</Text>
                        <Text style={styles.monitoringValue}>{vals[i]}</Text>
                      </View>
                    );
                  })}
                </View>
                <Text style={styles.monitoringTimestamp}>Last updated 12:12 PM</Text>
              </View>

              <Text style={styles.groupLabel}>Recent alerts</Text>
              <View style={styles.alertsList}>
                {mockRecentAlerts.map(alert => (
                  <View key={alert.id} style={[styles.alertItem, { borderLeftColor: statusColor(alert.severity) }]}>
                    <View style={styles.alertRow}>
                      <Text style={styles.alertType}>{alert.type}</Text>
                      <Text style={styles.alertTime}>{alert.time}</Text>
                    </View>
                    <Text style={styles.alertMessage}>{alert.message}</Text>
                  </View>
                ))}
              </View>
            </>
          )}

          {activeTab === 'analytics' && (
            <>
              <View style={styles.card}>
                <Text style={styles.cardTitle}>Monthly violation trends</Text>
                <Text style={styles.cardSubtitle}>Current year</Text>
                <LineChart
                  data={{ labels: mockMonthlyTrend.labels, datasets: [{ data: mockMonthlyTrend.data }] }}
                  width={chartWidth - 32}
                  height={180}
                  chartConfig={{
                    backgroundColor: '#ffffff',
                    backgroundGradientFrom: '#ffffff',
                    backgroundGradientTo: '#ffffff',
                    decimalPlaces: 0,
                    color: (opacity = 1) => `rgba(58, 63, 240, ${opacity})`,
                    labelColor: (opacity = 1) => `rgba(138, 143, 152, ${opacity})`,
                    style: { borderRadius: 16 },
                    propsForDots: { r: '3', strokeWidth: '2', stroke: '#3A3FF0' },
                    propsForBackgroundLines: { stroke: '#F0F0F2' },
                  }}
                  bezier
                  style={styles.chart}
                  withInnerLines
                />
                <Text style={styles.chartInterpretation}>July recorded the highest monthly count at 124.</Text>
              </View>

              <View style={[styles.card, { marginTop: 24 }]}>
                <Text style={styles.cardTitle}>Top violation types</Text>
                <Text style={styles.cardSubtitle}>All time</Text>
                <BarChart
                  data={{ labels: mockTopViolations.labels, datasets: [{ data: mockTopViolations.data }] }}
                  width={chartWidth - 32}
                  height={230}
                  yAxisLabel=""
                  yAxisSuffix=""
                  fromZero
                  chartConfig={{
                    backgroundColor: '#ffffff',
                    backgroundGradientFrom: '#ffffff',
                    backgroundGradientTo: '#ffffff',
                    decimalPlaces: 0,
                    color: (opacity = 1) => `rgba(58, 63, 240, ${opacity})`,
                    labelColor: (opacity = 1) => `rgba(138, 143, 152, ${opacity})`,
                    style: { borderRadius: 16 },
                    barPercentage: 0.55,
                    propsForBackgroundLines: { stroke: '#F0F0F2' },
                  }}
                  style={styles.chart}
                  verticalLabelRotation={30}
                  withOuterLines={false}
                />
                <Text style={styles.chartInterpretation}>Speeding is the leading violation type with 45 records.</Text>
              </View>
            </>
          )}

          <View style={{ height: 100 }} />
        </ScrollView>

        {/* Floating pill nav — inset, not edge-to-edge */}
        <View style={styles.bottomTabBar}>
          {(['overview', 'monitoring', 'analytics'] as const).map(tab => (
            <TouchableOpacity
              key={tab}
              style={styles.bottomTabItem}
              onPress={() => setActiveTab(tab)}
              activeOpacity={0.7}
            >
              <Ionicons
                name={tab === 'overview' ? 'grid' : tab === 'monitoring' ? 'eye' : 'bar-chart'}
                size={19}
                color={activeTab === tab ? '#3A3FF0' : '#8A8F98'}
              />
              <Text style={[styles.bottomTabLabel, activeTab === tab && styles.bottomTabLabelActive]}>
                {tab.charAt(0).toUpperCase() + tab.slice(1)}
              </Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
// Type scale: 34 / 28 / 20 / 17 / 15 / 13 / 11
// Palette: bg #FAFAFA, surface #FFFFFF, border #EBEBEF, text #0A0A0B / #6B7280 / #8A8F98, accent #3A3FF0

const quietShadow = {
  shadowColor: '#0A0A0B',
  shadowOffset: { width: 0, height: 2 },
  shadowOpacity: 0.04,
  shadowRadius: 10,
  elevation: 1,
};

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#FAFAFA' },
  container: { flex: 1 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#FAFAFA' },
  loadingText: { marginTop: 14, fontSize: 15, fontWeight: '500', color: '#6B7280' },

  // Header
  header: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start',
    paddingHorizontal: 20, paddingTop: 20,
  },
  greeting: { fontSize: 13, fontWeight: '500', color: '#8A8F98', marginBottom: 2 },
  pageTitle: { fontSize: 28, fontWeight: '700', color: '#0A0A0B', letterSpacing: -0.4 },
  liveIndicator: { flexDirection: 'row', alignItems: 'center', paddingTop: 6 },
  liveDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#1E9E5A', marginRight: 6 },
  liveText: { fontSize: 13, fontWeight: '600', color: '#6B7280' },
  headerMeta: { fontSize: 13, color: '#8A8F98', paddingHorizontal: 20, marginTop: 6, marginBottom: 4 },

  scrollArea: { flex: 1 },
  scrollContent: { paddingHorizontal: 20, paddingTop: 20 },

  // Hero
  heroCard: {
    backgroundColor: '#0A0A0B', borderRadius: 24, padding: 24, marginBottom: 20,
  },
  heroTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 24 },
  heroEyebrow: { fontSize: 11, fontWeight: '700', color: '#8A8F98', letterSpacing: 1 },
  heroStatusPill: { flexDirection: 'row', alignItems: 'center' },
  heroStatusDot: { width: 7, height: 7, borderRadius: 3.5, marginRight: 7 },
  heroStatusText: { fontSize: 15, fontWeight: '600', color: '#FFFFFF' },
  heroStatsRow: { flexDirection: 'row' },
  heroStatItem: { flex: 1 },
  heroStatDivider: { borderLeftWidth: 1, borderLeftColor: 'rgba(255,255,255,0.12)', paddingLeft: 14, marginLeft: 0 },
  heroStatValue: { fontSize: 22, fontWeight: '700', color: '#FFFFFF', marginBottom: 3, letterSpacing: -0.3 },
  heroStatLabel: { fontSize: 12, color: '#8A8F98', fontWeight: '500' },

  // Quick actions
  quickActionsRow: { marginBottom: 20 },
  quickAction: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: '#FFFFFF',
    paddingHorizontal: 14, paddingVertical: 10, borderRadius: 12, marginRight: 10,
    borderWidth: 1, borderColor: '#EBEBEF',
  },
  quickActionLabel: { fontSize: 13, fontWeight: '600', color: '#0A0A0B', marginLeft: 7 },

  // AI card
  aiCard: {
    backgroundColor: '#FFFFFF', borderRadius: 20, padding: 20, marginBottom: 24,
    borderWidth: 1, borderColor: '#EBEBEF', ...quietShadow,
  },
  aiTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 },
  aiKickerRow: { flexDirection: 'row', alignItems: 'center' },
  aiEyebrow: { fontSize: 11, fontWeight: '700', color: '#3A3FF0', letterSpacing: 1 },
  aiMainRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 12 },
  aiConfidenceValue: { fontSize: 34, fontWeight: '700', color: '#0A0A0B', letterSpacing: -0.6, marginRight: 16 },
  aiMainTextCol: { flex: 1 },
  riskBadge: { flexDirection: 'row', alignItems: 'center', marginBottom: 4 },
  riskDot: { width: 6, height: 6, borderRadius: 3, marginRight: 6 },
  riskBadgeText: { fontSize: 15, fontWeight: '700' },
  aiPeriod: { fontSize: 13, color: '#8A8F98' },
  aiRecommendationLead: { fontSize: 14, color: '#0A0A0B', lineHeight: 20, marginBottom: 16 },

  aiExpandTrigger: { flexDirection: 'row', alignItems: 'center' },
  aiExpandLabel: { fontSize: 13, fontWeight: '600', color: '#3A3FF0', marginRight: 4 },

  aiExpandedContent: { marginTop: 4 },
  aiDivider: { height: 1, backgroundColor: '#EBEBEF', marginVertical: 16 },
  aiSectionLabel: { fontSize: 12, fontWeight: '700', color: '#8A8F98', letterSpacing: 0.4, marginBottom: 10 },
  aiActionRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 9 },
  aiActionBullet: { width: 4, height: 4, borderRadius: 2, backgroundColor: '#3A3FF0', marginTop: 7, marginRight: 10 },
  aiActionText: { fontSize: 14, color: '#0A0A0B', flex: 1, lineHeight: 19 },

  deploymentRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 },
  deploymentLabel: { fontSize: 13, color: '#8A8F98' },
  deploymentValue: { fontSize: 13, fontWeight: '600', color: '#0A0A0B' },

  hotspotRowItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 7 },
  hotspotDot: { width: 6, height: 6, borderRadius: 3, marginRight: 10 },
  hotspotRowName: { fontSize: 13, color: '#0A0A0B', flex: 1 },
  hotspotRowCount: { fontSize: 13, color: '#8A8F98', fontWeight: '500' },

  // Group label + compact metrics
  groupLabel: { fontSize: 13, fontWeight: '600', color: '#8A8F98', marginBottom: 12 },
  compactRow: {
    flexDirection: 'row', backgroundColor: '#FFFFFF', borderRadius: 16,
    borderWidth: 1, borderColor: '#EBEBEF', paddingVertical: 16,
  },
  compactMetric: { flex: 1, alignItems: 'center' },
  compactMetricDivider: { borderRightWidth: 1, borderRightColor: '#EBEBEF' },
  compactValue: { fontSize: 18, fontWeight: '700', color: '#0A0A0B', marginBottom: 3 },
  compactLabel: { fontSize: 11, color: '#8A8F98', textAlign: 'center', paddingHorizontal: 4 },

  // Generic card (monitoring / analytics)
  card: {
    backgroundColor: '#FFFFFF', borderRadius: 20, padding: 20,
    borderWidth: 1, borderColor: '#EBEBEF', ...quietShadow,
  },
  cardTitle: { fontSize: 17, fontWeight: '600', color: '#0A0A0B', marginBottom: 2 },
  cardSubtitle: { fontSize: 13, color: '#8A8F98', marginBottom: 18 },

  monitoringHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 4 },
  statusPill: { flexDirection: 'row', alignItems: 'center', paddingTop: 4 },
  statusPillText: { fontSize: 13, fontWeight: '600', marginLeft: 6 },
  monitoringGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 18, marginTop: 4 },
  monitoringItem: {},
  monitoringLabel: { fontSize: 12, color: '#8A8F98', marginBottom: 4 },
  monitoringValue: { fontSize: 15, fontWeight: '600', color: '#0A0A0B' },
  monitoringTimestamp: { fontSize: 12, color: '#8A8F98', marginTop: 16 },

  alertsList: { backgroundColor: '#FFFFFF', borderRadius: 16, borderWidth: 1, borderColor: '#EBEBEF', overflow: 'hidden' },
  alertItem: { borderLeftWidth: 3, paddingVertical: 13, paddingHorizontal: 16, borderBottomWidth: 1, borderBottomColor: '#F0F0F2' },
  alertRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 3 },
  alertType: { fontSize: 13, fontWeight: '600', color: '#0A0A0B' },
  alertTime: { fontSize: 12, color: '#8A8F98' },
  alertMessage: { fontSize: 13, color: '#6B7280', lineHeight: 18 },

  chart: { marginVertical: 8, marginLeft: -16, borderRadius: 16 },
  chartInterpretation: { fontSize: 12, color: '#6B7280', marginTop: 10, lineHeight: 17 },

  // Floating bottom nav
  bottomTabBar: {
    position: 'absolute', left: 20, right: 20, bottom: 20,
    flexDirection: 'row', backgroundColor: '#FFFFFF',
    borderRadius: 20, paddingVertical: 10,
    borderWidth: 1, borderColor: '#EBEBEF',
    shadowColor: '#0A0A0B', shadowOffset: { width: 0, height: 8 }, shadowOpacity: 0.08, shadowRadius: 24, elevation: 6,
  },
  bottomTabItem: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingVertical: 4 },
  bottomTabLabel: { fontSize: 11, fontWeight: '600', color: '#8A8F98', marginTop: 4 },
  bottomTabLabelActive: { color: '#3A3FF0' },
});

