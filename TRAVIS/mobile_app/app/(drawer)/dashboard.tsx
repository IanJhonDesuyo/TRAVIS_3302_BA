import React, { useState, useEffect, useRef } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ActivityIndicator,
  StatusBar,
  Animated,
  Platform,
  RefreshControl,
  useWindowDimensions,
} from 'react-native';
import { LineChart, BarChart } from 'react-native-chart-kit';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import Svg, { Circle } from 'react-native-svg';

// ========== COLOR TOKENS ==========
// Hybrid theme: dark navy hero/header, light body, white cards.
// Chosen for outdoor readability (traffic enforcers / LGU field use) while
// keeping the "command center" feel via the hero, monospace data, and status
// indicators. See: Power BI, Apple Health, Stripe Dashboard, Azure Portal.
const COLORS = {
  bg: '#F8FAFC',
  header: '#0F172A',
  headerAccent: '#1E293B',
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
interface Zone { name: string; status: string; vehicles: number; congestion: string; }

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
const mockVehicleDetectionRate = 98;
const mockSystemHealth = 99.8;

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
  { id: 3, type: 'Officer', severity: 'Low', message: 'Officer presence needed at Roxas Blvd', time: '09:15 AM' },
  { id: 4, type: 'Weather', severity: 'Medium', message: 'Heavy rain expected, reduce speed', time: '08:00 AM' },
];

const mockZones: Zone[] = [
  { name: 'EDSA - Ortigas', status: 'Online', vehicles: 156, congestion: 'High' },
  { name: 'C5 - Tiendesitas', status: 'Online', vehicles: 132, congestion: 'High' },
  { name: 'Commonwealth - Fairview', status: 'Online', vehicles: 98, congestion: 'Medium' },
  { name: 'Taft - Vito Cruz', status: 'Online', vehicles: 67, congestion: 'Medium' },
  { name: 'Roxas Blvd - MOA', status: 'Offline', vehicles: 0, congestion: 'None' },
  { name: 'BGC - 32nd St', status: 'Online', vehicles: 18, congestion: 'Low' },
];

const formatCurrency = (amount: number): string => `\u20b1${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'online' || s === 'paid' || s === 'low') return COLORS.success;
  if (s === 'high' || s === 'critical') return COLORS.danger;
  if (s === 'medium' || s === 'moderate' || s === 'pending') return COLORS.warning;
  if (s === 'offline') return COLORS.neutral;
  if (s === 'none') return COLORS.textTertiary;
  return COLORS.textTertiary;
};

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });

// ========== SMALL REUSABLE PIECES ==========

// Pure-JS count-up tween — no extra dependency, runs once `active` flips true.
function useCountUp(target: number, active: boolean, duration = 900) {
  const [value, setValue] = useState(0);
  useEffect(() => {
    if (!active) return;
    let start: number | null = null;
    let raf: number;
    const step = (ts: number) => {
      if (start === null) start = ts;
      const progress = Math.min((ts - start) / duration, 1);
      setValue(Math.round(progress * target));
      if (progress < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf);
  }, [active, target, duration]);
  return value;
}

// Circular confidence ring, built on react-native-svg (already a transitive
// dependency of react-native-chart-kit, so no new package should be needed).
function ProgressRing({ percentage, size = 108, strokeWidth = 10, color, trackColor }:
  { percentage: number; size?: number; strokeWidth?: number; color: string; trackColor: string }) {
  const radius = (size - strokeWidth) / 2;
  const circumference = 2 * Math.PI * radius;
  const dashOffset = circumference - (Math.min(percentage, 100) / 100) * circumference;
  return (
    <Svg width={size} height={size}>
      <Circle cx={size / 2} cy={size / 2} r={radius} stroke={trackColor} strokeWidth={strokeWidth} fill="none" />
      <Circle
        cx={size / 2} cy={size / 2} r={radius}
        stroke={color} strokeWidth={strokeWidth} fill="none"
        strokeDasharray={`${circumference}, ${circumference}`}
        strokeDashoffset={dashOffset}
        strokeLinecap="round"
        rotation={-90}
        origin={`${size / 2}, ${size / 2}`}
      />
    </Svg>
  );
}

// ========== SCREEN COMPONENT ==========
export default function DashboardScreen() {
  const { width } = useWindowDimensions();
  const chartWidth = Math.max(width - 40, 200);
  const isTablet = width >= 700;

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'monitoring' | 'analytics'>('overview');
  const [aiExpanded, setAiExpanded] = useState(false);
  const [dateRange, setDateRange] = useState<'Today' | 'Week' | 'Month' | 'Year'>('Month');
  const [now, setNow] = useState(new Date());

  const pulse = useRef(new Animated.Value(1)).current;

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
    const clock = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(clock);
  }, []);

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(pulse, { toValue: 0.3, duration: 900, useNativeDriver: true }),
        Animated.timing(pulse, { toValue: 1, duration: 900, useNativeDriver: true }),
      ])
    );
    loop.start();
    return () => loop.stop();
  }, [pulse]);

  // Both the floating button and pull-to-refresh use the same lightweight
  // `refreshing` flag — neither should re-trigger the full-screen "loading"
  // gate, which is reserved for the initial mount only.
  const refresh = () => { setRefreshing(true); setTimeout(() => setRefreshing(false), 800); };

  const vehiclesCount = useCountUp(mockStats.vehiclesToday, !loading);
  const violationsCount = useCountUp(mockStats.violationsToday, !loading);
  const alertsCount = useCountUp(mockStats.activeAlerts, !loading);
  const revenueCount = useCountUp(mockStats.collectedToday, !loading);
  const confidenceCount = useCountUp(prediction.confidence, !loading);

  const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
  const dateStr = now.toLocaleDateString('en-PH', { month: 'long', day: '2-digit', year: 'numeric' });
  const hour = now.getHours();
  const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';

  const hasHotspots = mockHotspots.high.length + mockHotspots.medium.length + mockHotspots.low.length > 0;

  // ---------- RENDER HELPERS ----------
  const renderHeroStat = (icon: React.ReactNode, value: string, label: string) => (
    <View style={styles.heroStatItem}>
      {icon}
      <Text style={styles.heroStatValue}>{value}</Text>
      <Text style={styles.heroStatLabel}>{label}</Text>
    </View>
  );

  const renderLiveChip = (label: string, value: string, status: string) => (
    <View style={styles.statusChip}>
      <View style={[styles.statusChipDot, { backgroundColor: statusColor(status) }]} />
      <View>
        <Text style={styles.statusChipLabel}>{label}</Text>
        <Text style={styles.statusChipValue}>{value}</Text>
      </View>
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

  const renderZoneCard = (zone: Zone, idx: number) => (
    <View key={idx} style={[styles.zoneCard, { width: isTablet ? '31.5%' : '48%' }]}>
      <View style={styles.zoneCardTop}>
        <View style={[styles.zoneStatusDot, { backgroundColor: statusColor(zone.status) }]} />
        <Text style={styles.zoneStatus}>{zone.status.toUpperCase()}</Text>
      </View>
      <Text style={styles.zoneName} numberOfLines={1}>{zone.name}</Text>
      <Text style={styles.zoneVehicles}>{zone.vehicles} Vehicles</Text>
      {zone.congestion !== 'None' ? (
        <View style={[styles.zoneCongestionPill, { backgroundColor: statusColor(zone.congestion) + '1A' }]}>
          <Text style={[styles.zoneCongestionText, { color: statusColor(zone.congestion) }]}>{zone.congestion} Congestion</Text>
        </View>
      ) : (
        <Text style={styles.zoneOfflineNote}>No live data</Text>
      )}
    </View>
  );

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>INITIALIZING SYSTEM…</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.header} />
      <View style={styles.container}>

        <ScrollView
          style={styles.scrollArea}
          contentContainerStyle={styles.scrollContent}
          showsVerticalScrollIndicator={false}
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={COLORS.primary} />}
        >
          {/* ===== HERO (dark navy — the "header" of the hybrid theme) ===== */}
          <View style={styles.heroCard}>
            <View style={styles.heroTopRow}>
              <View style={styles.brandRow}>
                <View style={styles.brandBadge}>
                  <MaterialCommunityIcons name="traffic-light" size={16} color="#7DB4FF" />
                </View>
                <View>
                  <Text style={styles.brandName}>TRAVIS</Text>
                  <Text style={styles.brandSubtitle}>AI-Powered Smart Traffic Monitoring</Text>
                </View>
              </View>
              <View style={styles.clockBox}>
                <Text style={styles.clockTime}>{timeStr}</Text>
                <Text style={styles.clockDate}>{dateStr}</Text>
              </View>
            </View>

            <Text style={styles.heroGreeting}>{greeting}</Text>
            <View style={styles.heroOperationalRow}>
              <Animated.View style={[styles.liveDot, { opacity: pulse }]} />
              <Text style={styles.heroOperationalText}>All Systems Operational</Text>
            </View>

            <View style={styles.heroDivider} />

            <Text style={styles.heroSummaryLabel}>TODAY'S SUMMARY</Text>
            <View style={styles.heroStatsRow}>
              {renderHeroStat(<Ionicons name="car" size={16} color="#7DB4FF" />, vehiclesCount.toLocaleString(), 'Vehicles')}
              {renderHeroStat(<Ionicons name="warning" size={16} color="#FBBF24" />, violationsCount.toString(), 'Violations')}
              {renderHeroStat(<Ionicons name="notifications" size={16} color="#F87171" />, alertsCount.toString(), 'Alerts')}
              {renderHeroStat(<Ionicons name="cash" size={16} color="#34D399" />, formatCurrency(revenueCount), 'Revenue')}
            </View>
          </View>

          {/* Live system stats */}
          <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.statusStrip} contentContainerStyle={{ paddingRight: 4 }}>
            {renderLiveChip('AI ENGINE', 'ONLINE', 'online')}
            {renderLiveChip('CAMERAS', `${mockOnlineCameras}/${mockTotalCameras}`, 'online')}
            {renderLiveChip('DETECTION', `${mockVehicleDetectionRate}%`, 'online')}
            {renderLiveChip('SYSTEM HEALTH', `${mockSystemHealth}%`, 'online')}
          </ScrollView>

          {activeTab === 'overview' && (
            <>
              {/* Quick Actions */}
              <Text style={styles.sectionLabel}>QUICK ACTIONS</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.quickActionsRow} contentContainerStyle={{ paddingRight: 20 }}>
                {renderQuickAction(<Ionicons name="videocam-outline" size={17} color={COLORS.textPrimary} />, 'Cameras')}
                {renderQuickAction(<MaterialCommunityIcons name="robot-outline" size={17} color={COLORS.textPrimary} />, 'AI')}
                {renderQuickAction(<Ionicons name="document-text-outline" size={17} color={COLORS.textPrimary} />, 'Reports')}
                {renderQuickAction(<Ionicons name="map-outline" size={17} color={COLORS.textPrimary} />, 'Live Map')}
                {renderQuickAction(<Ionicons name="alert-circle-outline" size={17} color={COLORS.textPrimary} />, 'Alerts')}
              </ScrollView>

              {/* AI Risk Assessment */}
              <View style={styles.panel}>
                <View style={styles.panelHeader}>
                  <View style={styles.eyebrowRow}>
                    <MaterialCommunityIcons name="radar" size={13} color={COLORS.primary} style={{ marginRight: 6 }} />
                    <Text style={styles.panelTitle}>AI RISK ASSESSMENT</Text>
                  </View>
                </View>

                <View style={styles.aiReadoutRow}>
                  <View style={styles.ringWrap}>
                    <ProgressRing percentage={confidenceCount} color={statusColor(prediction.riskLevel)} trackColor={COLORS.border} />
                    <View style={styles.ringCenter}>
                      <Text style={styles.ringPercent}>{confidenceCount}<Text style={styles.ringPercentSign}>%</Text></Text>
                    </View>
                  </View>
                  <View style={styles.aiMetaCol}>
                    <Text style={[styles.riskLabel, { color: statusColor(prediction.riskLevel) }]}>{prediction.riskLevel.toUpperCase()} RISK</Text>
                    <Text style={styles.aiPeriod}>Forecast · {prediction.month}</Text>
                    <Text style={styles.aiRecommendationLead}>{prediction.recommendations[0]}</Text>
                  </View>
                </View>

                <TouchableOpacity style={styles.expandTrigger} onPress={() => setAiExpanded(v => !v)} activeOpacity={0.6}>
                  <Text style={styles.expandLabel}>{aiExpanded ? 'Close AI Report' : 'Open AI Report'}</Text>
                  <Ionicons name={aiExpanded ? 'chevron-up' : 'chevron-forward'} size={13} color={COLORS.primary} />
                </TouchableOpacity>

                {aiExpanded && (
                  <View style={styles.expandedContent}>
                    <View style={styles.panelDivider} />
                    <Text style={styles.subsectionLabel}>RECOMMENDED ACTIONS</Text>
                    {prediction.recommendations.map((action, idx) => (
                      <View key={idx} style={styles.commandRow}>
                        <Text style={styles.commandPrefix}>{'>'}</Text>
                        <Text style={styles.commandText}>{action}</Text>
                      </View>
                    ))}

                    <View style={styles.panelDivider} />
                    <Text style={styles.subsectionLabel}>DEPLOYMENT GUIDANCE</Text>
                    <View style={styles.readoutRow}>
                      <Text style={styles.readoutLabel}>PERSONNEL</Text>
                      <Text style={styles.readoutValue}>5–6 ENFORCERS</Text>
                    </View>
                    <View style={styles.readoutRow}>
                      <Text style={styles.readoutLabel}>MONITORING</Text>
                      <Text style={styles.readoutValue}>INTENSIVE</Text>
                    </View>

                    <View style={styles.panelDivider} />
                    <Text style={styles.subsectionLabel}>HOTSPOTS BY RISK</Text>
                    {hasHotspots ? (
                      <>
                        {mockHotspots.high.slice(0, 3).map((loc, idx) => renderHotspotRow(loc, COLORS.danger, idx))}
                        {mockHotspots.medium.slice(0, 2).map((loc, idx) => renderHotspotRow(loc, COLORS.warning, idx + 10))}
                        {mockHotspots.low.slice(0, 2).map((loc, idx) => renderHotspotRow(loc, COLORS.success, idx + 20))}
                      </>
                    ) : (
                      <View style={styles.emptyState}>
                        <Ionicons name="checkmark-circle" size={16} color={COLORS.success} />
                        <Text style={styles.emptyStateText}>No hotspots detected. Traffic is operating normally.</Text>
                      </View>
                    )}
                  </View>
                )}
              </View>

              {/* Zone preview teaser */}
              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionLabel}>ZONE STATUS</Text>
                <TouchableOpacity onPress={() => setActiveTab('monitoring')}>
                  <Text style={styles.viewAllLink}>Open Zone Dashboard →</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.zoneGrid}>
                {mockZones.slice(0, 4).map(renderZoneCard)}
              </View>
            </>
          )}

          {activeTab === 'monitoring' && (
            <>
              {/* Live camera preview — CCTV-style */}
              <Text style={styles.sectionLabel}>PRIMARY FEED</Text>
              <View style={styles.cameraCard}>
                <View style={styles.cameraPreview}>
                  <Ionicons name="videocam" size={32} color={COLORS.textTertiary} />
                  <Text style={styles.cameraPreviewNote}>Live thumbnail coming soon</Text>
                  <View style={styles.liveBadge}>
                    <View style={styles.liveBadgeDot} />
                    <Text style={styles.liveBadgeText}>LIVE</Text>
                  </View>
                </View>
                <View style={styles.cameraInfoRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.feedTitle}>{mockLatestMonitoring.cameraName}</Text>
                    <Text style={styles.feedSubtitle}>{mockLatestMonitoring.location}</Text>
                  </View>
                  <View style={styles.panelStatusPill}>
                    <View style={[styles.zoneStatusDot, { backgroundColor: statusColor(mockLatestMonitoring.cameraStatus) }]} />
                    <Text style={[styles.panelStatusText, { color: statusColor(mockLatestMonitoring.cameraStatus) }]}>{mockLatestMonitoring.cameraStatus.toUpperCase()}</Text>
                  </View>
                </View>

                <View style={styles.monitoringGrid}>
                  {(['VEHICLES', 'INBOUND', 'OUTBOUND', 'CONGESTION', 'OFFICER', 'COLLISION RISK'] as const).map((label, i) => {
                    const vals = [mockLatestMonitoring.vehicleCount, mockLatestMonitoring.inbound, mockLatestMonitoring.outbound, mockLatestMonitoring.congestion, mockLatestMonitoring.officerPresence, mockLatestMonitoring.potentialCollision];
                    return (
                      <View key={label} style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
                        <Text style={styles.readoutLabel}>{label}</Text>
                        <Text style={styles.monitoringValue}>{vals[i]}</Text>
                      </View>
                    );
                  })}
                </View>
                <Text style={styles.feedTimestamp}>LAST SYNC {timeStr}</Text>
              </View>

              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionLabel}>ZONE STATUS · {mockZones.length} MONITORED</Text>
              </View>
              <View style={styles.zoneGrid}>
                {mockZones.map(renderZoneCard)}
              </View>

              <Text style={styles.sectionLabel}>ALERT FEED</Text>
              <View style={{ gap: 10 }}>
                {mockRecentAlerts.map(alert => (
                  <View key={alert.id} style={[styles.alertCard, { backgroundColor: statusColor(alert.severity) + '14', borderColor: statusColor(alert.severity) + '33' }]}>
                    <View style={styles.alertRow}>
                      <View style={[styles.alertSeverityBadge, { backgroundColor: statusColor(alert.severity) }]}>
                        <Text style={styles.alertSeverityText}>{alert.severity.toUpperCase()}</Text>
                      </View>
                      <Text style={styles.alertTime}>{alert.time}</Text>
                    </View>
                    <Text style={styles.alertType}>{alert.type}</Text>
                    <Text style={styles.alertMessage}>{alert.message}</Text>
                  </View>
                ))}
              </View>
            </>
          )}

          {activeTab === 'analytics' && (
            <>
              {/* Interactive date filter — UI only for now; not wired to alternate
                  datasets since only one mock dataset exists. Swap `dateRange`
                  into a real query once historical data is available. */}
              <View style={styles.dateFilterRow}>
                {(['Today', 'Week', 'Month', 'Year'] as const).map(r => (
                  <TouchableOpacity
                    key={r}
                    style={[styles.dateFilterChip, dateRange === r && styles.dateFilterChipActive]}
                    onPress={() => setDateRange(r)}
                  >
                    <Text style={[styles.dateFilterText, dateRange === r && styles.dateFilterTextActive]}>{r}</Text>
                  </TouchableOpacity>
                ))}
              </View>

              <Text style={styles.sectionLabel}>MONTHLY VIOLATION TRENDS</Text>
              <View style={styles.panel}>
                <Text style={styles.feedSubtitle}>Current year</Text>
                <LineChart
                  data={{ labels: mockMonthlyTrend.labels, datasets: [{ data: mockMonthlyTrend.data }] }}
                  width={chartWidth - 32}
                  height={150}
                  chartConfig={{
                    backgroundColor: COLORS.surface,
                    backgroundGradientFrom: COLORS.surface,
                    backgroundGradientTo: COLORS.surface,
                    decimalPlaces: 0,
                    color: (opacity = 1) => `rgba(37, 99, 235, ${opacity})`,
                    labelColor: (opacity = 1) => `rgba(100, 116, 139, ${opacity})`,
                    style: { borderRadius: 16 },
                    propsForDots: { r: '3', strokeWidth: '2', stroke: COLORS.primary },
                    propsForBackgroundLines: { stroke: COLORS.border },
                  }}
                  bezier
                  style={styles.chart}
                  withInnerLines
                  withOuterLines={false}
                />
                <Text style={styles.chartInterpretation}>July recorded the highest monthly count at 124.</Text>
              </View>

              <Text style={[styles.sectionLabel, { marginTop: 24 }]}>TOP VIOLATION TYPES</Text>
              <View style={styles.panel}>
                <Text style={styles.feedSubtitle}>All time</Text>
                <BarChart
                  data={{ labels: mockTopViolations.labels, datasets: [{ data: mockTopViolations.data }] }}
                  width={chartWidth - 32}
                  height={200}
                  yAxisLabel=""
                  yAxisSuffix=""
                  fromZero
                  chartConfig={{
                    backgroundColor: COLORS.surface,
                    backgroundGradientFrom: COLORS.surface,
                    backgroundGradientTo: COLORS.surface,
                    decimalPlaces: 0,
                    color: (opacity = 1) => `rgba(37, 99, 235, ${opacity})`,
                    labelColor: (opacity = 1) => `rgba(100, 116, 139, ${opacity})`,
                    style: { borderRadius: 16 },
                    barPercentage: 0.55,
                    propsForBackgroundLines: { stroke: COLORS.border },
                  }}
                  style={styles.chart}
                  verticalLabelRotation={30}
                />
                <Text style={styles.chartInterpretation}>Speeding is the leading violation type with 45 records.</Text>
              </View>
            </>
          )}

          <View style={{ height: 100 }} />
        </ScrollView>

        {/* Floating bottom nav */}
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
                color={activeTab === tab ? COLORS.primary : COLORS.textTertiary}
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
// Type scale: 32 hero / 24 page / 18 section / 16 card number / 14 body / 12 caption

const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.bg },
  loadingText: { marginTop: 14, fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, fontFamily: mono },

  scrollArea: { flex: 1 },
  scrollContent: { paddingHorizontal: 20, paddingTop: 18 },

  // Hero clock

  // Hero — dark navy header/summary slab
  heroCard: {
    backgroundColor: COLORS.header, borderRadius: 22, padding: 20, marginBottom: 16,
    ...softShadow, shadowOpacity: 0.18,
  },
  heroTopRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 18 },
  brandRow: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  brandBadge: {
    width: 32, height: 32, borderRadius: 10, backgroundColor: COLORS.headerAccent,
    justifyContent: 'center', alignItems: 'center', marginRight: 10,
  },
  brandName: { fontSize: 18, fontWeight: '800', color: '#FFFFFF', letterSpacing: 1 },
  brandSubtitle: { fontSize: 11, color: '#94A3B8', marginTop: 1 },
  clockBox: { alignItems: 'flex-end' },
  clockTime: { fontSize: 16, fontWeight: '600', color: '#FFFFFF', fontFamily: mono, letterSpacing: 0.5 },
  clockDate: { fontSize: 10, color: '#94A3B8', marginTop: 2, fontFamily: mono },

  heroGreeting: { fontSize: 24, fontWeight: '700', color: '#FFFFFF', marginBottom: 8, letterSpacing: -0.3 },
  heroOperationalRow: { flexDirection: 'row', alignItems: 'center' },
  liveDot: { width: 7, height: 7, borderRadius: 3.5, backgroundColor: '#34D399', marginRight: 8 },
  heroOperationalText: { fontSize: 13, fontWeight: '600', color: '#CBD5E1' },

  heroDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.1)', marginVertical: 18 },
  heroSummaryLabel: { fontSize: 11, fontWeight: '700', color: '#94A3B8', letterSpacing: 1, marginBottom: 14 },
  heroStatsRow: { flexDirection: 'row', justifyContent: 'space-between' },
  heroStatItem: { alignItems: 'flex-start' },
  heroStatValue: { fontSize: 16, fontWeight: '700', color: '#FFFFFF', fontFamily: mono, marginTop: 8, marginBottom: 2 },
  heroStatLabel: { fontSize: 11, color: '#94A3B8' },

  // Live status strip
  statusStrip: { maxHeight: 56, marginBottom: 20 },
  statusChip: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 12,
    paddingHorizontal: 12, paddingVertical: 8, marginRight: 10, ...softShadow,
  },
  statusChipDot: { width: 6, height: 6, borderRadius: 3, marginRight: 8 },
  statusChipLabel: { fontSize: 9, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6 },
  statusChipValue: { fontSize: 12, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono, marginTop: 1 },

  // Quick actions
  quickActionsRow: { marginBottom: 20 },
  quickAction: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    paddingHorizontal: 14, paddingVertical: 10, borderRadius: 12, marginRight: 10,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  quickActionLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginLeft: 7 },

  // Panels
  panel: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 20,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  panelHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 18 },
  eyebrowRow: { flexDirection: 'row', alignItems: 'center' },
  panelTitle: { fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1 },
  panelStatusPill: { flexDirection: 'row', alignItems: 'center' },
  panelStatusText: { fontSize: 11, fontWeight: '700', letterSpacing: 0.5, marginLeft: 6, fontFamily: mono },
  panelDivider: { height: 1, backgroundColor: COLORS.border, marginVertical: 16 },

  sectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12 },
  subsectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12 },
  sectionHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  viewAllLink: { fontSize: 11, fontWeight: '700', color: COLORS.primary, letterSpacing: 0.3 },

  // AI ring
  aiReadoutRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 4 },
  ringWrap: { width: 108, height: 108, justifyContent: 'center', alignItems: 'center', marginRight: 18 },
  ringCenter: { position: 'absolute', justifyContent: 'center', alignItems: 'center' },
  ringPercent: { fontSize: 26, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },
  ringPercentSign: { fontSize: 14, color: COLORS.textTertiary },
  aiMetaCol: { flex: 1 },
  riskLabel: { fontSize: 20, fontWeight: '800', letterSpacing: 0.3, marginBottom: 4 },
  aiPeriod: { fontSize: 12, color: COLORS.textTertiary, fontFamily: mono, marginBottom: 10 },
  aiRecommendationLead: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  expandTrigger: { flexDirection: 'row', alignItems: 'center', marginTop: 16 },
  expandLabel: { fontSize: 12, fontWeight: '700', color: COLORS.primary, letterSpacing: 0.3, marginRight: 4 },
  expandedContent: {},

  commandRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 9 },
  commandPrefix: { fontSize: 13, color: COLORS.primary, fontFamily: mono, marginRight: 8, fontWeight: '700' },
  commandText: { fontSize: 13, color: COLORS.textSecondary, flex: 1, lineHeight: 19, fontFamily: mono },

  readoutRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 },
  readoutLabel: { fontSize: 10, fontWeight: '600', color: COLORS.textTertiary, letterSpacing: 0.5 },
  readoutValue: { fontSize: 12, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },

  hotspotRowItem: { flexDirection: 'row', alignItems: 'center', paddingVertical: 7 },
  hotspotDot: { width: 6, height: 6, borderRadius: 3, marginRight: 10 },
  hotspotRowName: { fontSize: 13, color: COLORS.textSecondary, flex: 1 },
  hotspotRowCount: { fontSize: 12, color: COLORS.textTertiary, fontFamily: mono },

  emptyState: { flexDirection: 'row', alignItems: 'center', paddingVertical: 6 },
  emptyStateText: { fontSize: 13, color: COLORS.textSecondary, marginLeft: 8, flex: 1, lineHeight: 18 },

  // Zone grid
  zoneGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 12, marginBottom: 24 },
  zoneCard: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 16,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  zoneCardTop: { flexDirection: 'row', alignItems: 'center', marginBottom: 10 },
  zoneStatusDot: { width: 7, height: 7, borderRadius: 3.5, marginRight: 6 },
  zoneStatus: { fontSize: 9, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6 },
  zoneName: { fontSize: 14, fontWeight: '600', color: COLORS.textPrimary, marginBottom: 8 },
  zoneVehicles: { fontSize: 16, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginBottom: 8 },
  zoneCongestionPill: { alignSelf: 'flex-start', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8 },
  zoneCongestionText: { fontSize: 10, fontWeight: '700' },
  zoneOfflineNote: { fontSize: 11, color: COLORS.textTertiary, fontStyle: 'italic' },

  // Camera card
  cameraCard: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 24,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  cameraPreview: {
    height: 140, borderRadius: 14, backgroundColor: '#F1F5F9', marginBottom: 16,
    justifyContent: 'center', alignItems: 'center', borderWidth: 1, borderColor: COLORS.border,
  },
  cameraPreviewNote: { fontSize: 11, color: COLORS.textTertiary, marginTop: 8 },
  liveBadge: {
    position: 'absolute', top: 10, left: 10, flexDirection: 'row', alignItems: 'center',
    backgroundColor: 'rgba(239,68,68,0.1)', borderWidth: 1, borderColor: COLORS.danger,
    paddingHorizontal: 8, paddingVertical: 4, borderRadius: 8,
  },
  liveBadgeDot: { width: 5, height: 5, borderRadius: 2.5, backgroundColor: COLORS.danger, marginRight: 5 },
  liveBadgeText: { fontSize: 10, fontWeight: '800', color: COLORS.danger, letterSpacing: 0.5 },
  cameraInfoRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 4 },

  // Monitoring detail
  feedTitle: { fontSize: 18, fontWeight: '600', color: COLORS.textPrimary, marginBottom: 2 },
  feedSubtitle: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 16 },
  monitoringGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 16, marginTop: 12 },
  monitoringItem: {},
  monitoringValue: { fontSize: 14, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono, marginTop: 4 },
  feedTimestamp: { fontSize: 10, color: COLORS.textTertiary, marginTop: 16, letterSpacing: 0.5, fontFamily: mono },

  // Alerts — tinted background per severity
  alertCard: { borderRadius: 14, borderWidth: 1, padding: 14 },
  alertRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  alertSeverityBadge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 },
  alertSeverityText: { fontSize: 10, fontWeight: '800', color: '#FFFFFF', letterSpacing: 0.5 },
  alertType: { fontSize: 13, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 3 },
  alertTime: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
  alertMessage: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  // Date filter
  dateFilterRow: { flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 12, padding: 4, marginBottom: 20, borderWidth: 1, borderColor: COLORS.border },
  dateFilterChip: { flex: 1, paddingVertical: 8, borderRadius: 9, alignItems: 'center' },
  dateFilterChipActive: { backgroundColor: '#EFF6FF' },
  dateFilterText: { fontSize: 12, fontWeight: '600', color: COLORS.textTertiary },
  dateFilterTextActive: { color: COLORS.primary },

  chart: { marginVertical: 8, marginLeft: -16, borderRadius: 16 },
  chartInterpretation: { fontSize: 12, color: COLORS.textSecondary, marginTop: 10, lineHeight: 17 },

  // Floating bottom nav
  bottomTabBar: {
    position: 'absolute', left: 20, right: 20, bottom: 20,
    flexDirection: 'row', backgroundColor: COLORS.surface,
    borderRadius: 22, paddingVertical: 14,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  bottomTabItem: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingVertical: 4 },
  bottomTabLabel: { fontSize: 11, fontWeight: '600', color: COLORS.textTertiary, marginTop: 4 },
  bottomTabLabelActive: { color: COLORS.primary },
});