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
import { useRouter } from 'expo-router';

// ========== TYPES ==========
interface StatData {
  vehiclesToday: number;
  inboundToday: number;
  outboundToday: number;
  violationsToday: number;
  paidViolations: number;
  unpaidViolations: number;
  collectedToday: number;
  completedPayments: number;
  activeAlerts: number;
  alertsToday: number;
}

interface MonitoringData {
  cameraName: string;
  location: string;
  vehicleCount: number;
  inbound: number;
  outbound: number;
  congestion: string;
  officerPresence: string;
  potentialCollision: string;
  recordedAt: string;
  cameraStatus: string;
}

interface HotspotLocation {
  location: string;
  total: number;
}

interface Hotspots {
  high: HotspotLocation[];
  medium: HotspotLocation[];
  low: HotspotLocation[];
}

interface Alert {
  id: number;
  type: string;
  severity: string;
  message: string;
  time: string;
}

interface Violation {
  id: number;
  ticket: string;
  plate: string;
  type: string;
  location: string;
  penalty: number;
  status: string;
}

interface Prediction {
  riskLevel: string;
  confidence: number;
  month: string;
  recommendations: string[];
}

// ========== MOCK DATA (replace with API calls) ==========
const mockStats: StatData = {
  vehiclesToday: 1243,
  inboundToday: 720,
  outboundToday: 523,
  violationsToday: 87,
  paidViolations: 34,
  unpaidViolations: 53,
  collectedToday: 124500,
  completedPayments: 42,
  activeAlerts: 5,
  alertsToday: 3,
};

const mockPendingViolations = 142;
const mockCongestionEvents = 18;
const mockCollisionEvents = 2;
const mockOnlineCameras = 12;
const mockTotalCameras = 20;

const mockLatestMonitoring: MonitoringData = {
  cameraName: 'Main Intersection',
  location: 'EDSA Cor. Ayala',
  vehicleCount: 34,
  inbound: 12,
  outbound: 22,
  congestion: 'Moderate',
  officerPresence: 'Present',
  potentialCollision: 'Low',
  recordedAt: '2026-07-16 12:12:18',
  cameraStatus: 'Online',
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
  { id: 1, type: 'Congestion', severity: 'High', message: 'Heavy traffic at EDSA-Ortigas', time: '2026-07-16 11:45' },
  { id: 2, type: 'Collision', severity: 'Medium', message: 'Potential collision detected near Taft Ave', time: '2026-07-16 10:30' },
  { id: 3, type: 'Officer', severity: 'Low', message: 'Officer presence needed at Roxas Blvd', time: '2026-07-16 09:15' },
  { id: 4, type: 'Weather', severity: 'Medium', message: 'Heavy rain expected, reduce speed', time: '2026-07-16 08:00' },
];

const mockRecentViolations: Violation[] = [
  { id: 1, ticket: 'T-2026-001', plate: 'ABC-1234', type: 'Speeding', location: 'EDSA Ayala', penalty: 1200, status: 'Unpaid' },
  { id: 2, ticket: 'T-2026-002', plate: 'XYZ-5678', type: 'Illegal Parking', location: 'BGC 32nd', penalty: 800, status: 'Paid' },
  { id: 3, ticket: 'T-2026-003', plate: 'DEF-9012', type: 'Disregarded Signal', location: 'Commonwealth', penalty: 600, status: 'Pending' },
  { id: 4, ticket: 'T-2026-004', plate: 'GHI-3456', type: 'Overloading', location: 'C5', penalty: 1500, status: 'Unpaid' },
  { id: 5, ticket: 'T-2026-005', plate: 'JKL-7890', type: 'No Helmet', location: 'Taft', penalty: 300, status: 'Paid' },
];

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'online' || s === 'paid') return '#16a34a';
  if (s === 'high' || s === 'critical') return '#dc2626';
  if (s === 'medium' || s === 'pending') return '#f59e0b';
  if (s === 'low' || s === 'none') return '#6b7280';
  return '#6b7280';
};

// ========== SCREEN COMPONENT ==========
export default function DashboardScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  // Safety: ensure width is at least 200px to avoid rendering issues
  const chartWidth = Math.max(width - 40, 200);

  // Simple responsive breakpoint. Below this we stack everything in a
  // single column so cards get room to breathe on phones.
  const isTablet = width >= 700;

  const [loading, setLoading] = useState<boolean>(true);
  const [prediction, setPrediction] = useState<Prediction>({
    riskLevel: 'High',
    confidence: 78,
    month: 'July 2026',
    recommendations: [
      'Deploy additional enforcers to EDSA',
      'Monitor inbound traffic at Ayala',
      'Activate congestion alert system',
    ],
  });

  // Simulate data fetching – replace with actual API calls
  useEffect(() => {
    const fetchData = async () => {
      // In reality: fetch stats, prediction, hotspots, alerts, violations, etc.
      await new Promise(resolve => setTimeout(resolve, 1000));
      setLoading(false);
    };
    fetchData();
  }, []);

  // ========== RENDER FUNCTIONS ==========
  const renderStatCard = (
    icon: string,
    label: string,
    value: string | number,
    subtext: string,
    tone: 'primary' | 'warning' | 'success' | 'danger'
  ) => {
    const toneColors = {
      primary: '#2563eb',
      warning: '#f59e0b',
      success: '#16a34a',
      danger: '#dc2626',
    };
    const color = toneColors[tone];
    return (
      <View style={[styles.statCard, { width: isTablet ? '48%' : '100%' }]}>
        <View style={[styles.iconCircle, { backgroundColor: color + '15' }]}>
          <Text style={styles.iconCircleText}>{icon}</Text>
        </View>
        <Text style={styles.statLabel}>{label}</Text>
        <Text style={styles.statValue}>{value}</Text>
        <Text style={styles.statSubtext}>{subtext}</Text>
      </View>
    );
  };

  const renderHotspotCard = (
    title: string,
    locations: HotspotLocation[],
    borderColor: string
  ) => (
    <View style={[styles.hotspotCard, { borderTopColor: borderColor, width: isTablet ? '31.5%' : '100%' }]}>
      <View style={styles.hotspotHeader}>
        <Text style={styles.hotspotTitle}>{title}</Text>
        <View style={[styles.hotspotCountPill, { backgroundColor: borderColor + '15' }]}>
          <Text style={[styles.hotspotCount, { color: borderColor }]}>{locations.length}</Text>
        </View>
      </View>
      {locations.map((loc, idx) => (
        <View key={idx} style={styles.hotspotItem}>
          <View style={[styles.hotspotRank, { backgroundColor: borderColor }]}>
            <Text style={styles.hotspotRankText}>{idx + 1}</Text>
          </View>
          <View style={styles.hotspotLocation}>
            <Text style={styles.hotspotName}>{loc.location}</Text>
            <Text style={styles.hotspotDetails}>{loc.total} historical records</Text>
          </View>
        </View>
      ))}
      {locations.length === 0 && (
        <Text style={styles.hotspotEmpty}>No locations</Text>
      )}
    </View>
  );

  const renderAlertItem = (alert: Alert) => (
    <View key={alert.id} style={styles.alertItem}>
      <View style={styles.alertRow}>
        <Text style={styles.alertType}>{alert.type}</Text>
        <View style={[styles.severityBadge, { backgroundColor: statusColor(alert.severity) + '20' }]}>
          <Text style={[styles.severityText, { color: statusColor(alert.severity) }]}>
            {alert.severity}
          </Text>
        </View>
      </View>
      <Text style={styles.alertMessage}>{alert.message}</Text>
      <Text style={styles.alertTime}>{alert.time}</Text>
    </View>
  );

  const renderViolationItem = (violation: Violation) => (
    <View key={violation.id} style={styles.violationItem}>
      <View style={styles.violationRow}>
        <Text style={styles.violationTicket}>{violation.ticket}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(violation.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(violation.status) }]}>
            {violation.status}
          </Text>
        </View>
      </View>
      <Text style={styles.violationDetail}>
        {violation.plate} • {violation.type}
      </Text>
      <Text style={styles.violationDetail}>
        {violation.location} • {formatCurrency(violation.penalty)}
      </Text>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading TRAVIS Dashboard...</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.scrollContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={[styles.header, !isTablet && styles.headerStacked]}>
          <View style={styles.headerText}>
            <Text style={styles.eyebrow}>TRAVIS COMMAND CENTER</Text>
            <Text style={styles.pageTitle}>Operations Dashboard</Text>
            <Text style={styles.pageSub}>
              Real-time traffic monitoring, predictive insights, and hotspot intelligence.
            </Text>
          </View>
          <View style={[styles.onlineBadge, !isTablet && { marginTop: 12 }]}>
            <View style={styles.onlineDot} />
            <Text style={styles.onlineText}>AI Services Online</Text>
          </View>
        </View>

        {/* Stats Cards */}
        <View style={styles.statsRow}>
          {renderStatCard(
            '🚗',
            'Vehicles Counted Today',
            mockStats.vehiclesToday,
            `${mockStats.inboundToday} inbound • ${mockStats.outboundToday} outbound`,
            'primary'
          )}
          {renderStatCard(
            '🚦',
            'Violations Today',
            mockStats.violationsToday,
            `${mockStats.paidViolations} paid • ${mockStats.unpaidViolations} unpaid`,
            'warning'
          )}
          {renderStatCard(
            '💰',
            'Collected Today',
            formatCurrency(mockStats.collectedToday),
            `${mockStats.completedPayments} completed payments`,
            'success'
          )}
          {renderStatCard(
            '⚠️',
            'Active Alerts',
            mockStats.activeAlerts,
            `${mockStats.alertsToday} generated today`,
            'danger'
          )}
        </View>

        {/* AI Decision Support */}
        <View style={styles.aiCard}>
          <View style={[styles.aiHeader, !isTablet && styles.aiHeaderStacked]}>
            <View style={styles.headerText}>
              <Text style={styles.aiKicker}>🤖 TRAVIS AI ENGINE</Text>
              <Text style={styles.aiTitle}>AI Decision Support Center</Text>
              <Text style={styles.aiSub}>
                Random Forest monthly risk prediction combined with K-Means hotspot intelligence
              </Text>
            </View>
            <TouchableOpacity
              style={[styles.refreshButton, !isTablet && { marginTop: 14, alignSelf: 'flex-start' }]}
              onPress={() => setLoading(true)}
            >
              <Text style={styles.refreshButtonText}>⟳ Refresh</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.aiContent}>
            {/* Monthly Risk Prediction */}
            <View style={[styles.aiPredictionCard, { width: isTablet ? '48.5%' : '100%' }]}>
              <View style={styles.aiPredictionHeader}>
                <View style={styles.aiIconBox}>
                  <Text style={styles.aiIcon}>📈</Text>
                </View>
                <View style={styles.aiPredictionHeaderText}>
                  <Text style={styles.aiPredictionTitle}>Monthly Risk Prediction</Text>
                  <Text style={styles.aiPredictionSub}>Random Forest risk forecast</Text>
                </View>
              </View>
              <View style={styles.aiPredictionBody}>
                <View>
                  <Text style={styles.aiLabel}>Expected monthly risk</Text>
                  <View style={[styles.riskBadge, { backgroundColor: statusColor(prediction.riskLevel) + '20' }]}>
                    <Text style={[styles.riskBadgeText, { color: statusColor(prediction.riskLevel) }]}>
                      {prediction.riskLevel.toUpperCase()}
                    </Text>
                  </View>
                  <Text style={styles.aiPeriod}>{prediction.month}</Text>
                </View>
                <View style={styles.aiConfidence}>
                  <View style={styles.confidenceRow}>
                    <Text style={styles.confidenceLabel}>Prediction confidence</Text>
                    <Text style={styles.confidenceValue}>{prediction.confidence}%</Text>
                  </View>
                  <View style={styles.progressBar}>
                    <View style={[styles.progressFill, { width: `${prediction.confidence}%`, backgroundColor: statusColor(prediction.riskLevel) }]} />
                  </View>
                </View>
              </View>
              <Text style={styles.aiNote}>
                This prediction is based on historical TMO records and should be reviewed together with current traffic conditions.
              </Text>
            </View>

            {/* Deployment Guidance */}
            <View style={[styles.aiDeploymentCard, { width: isTablet ? '48.5%' : '100%' }]}>
              <View style={styles.aiPredictionHeader}>
                <View style={[styles.aiIconBox, { backgroundColor: '#d1fae5' }]}>
                  <Text style={styles.aiIcon}>👥</Text>
                </View>
                <View style={styles.aiPredictionHeaderText}>
                  <Text style={styles.aiPredictionTitle}>Deployment Guidance</Text>
                  <Text style={styles.aiPredictionSub}>Recommended operational resources</Text>
                </View>
              </View>
              <View style={styles.deploymentItems}>
                <View style={styles.deploymentItem}>
                  <Text style={styles.deploymentLabel}>Priority</Text>
                  <Text style={styles.deploymentValue}>{prediction.riskLevel}</Text>
                </View>
                <View style={styles.deploymentItem}>
                  <Text style={styles.deploymentLabel}>Personnel</Text>
                  <Text style={styles.deploymentValue}>5–6 enforcers</Text>
                </View>
                <View style={[styles.deploymentItem, { borderBottomWidth: 0 }]}>
                  <Text style={styles.deploymentLabel}>Monitoring</Text>
                  <Text style={styles.deploymentValue}>Intensive monitoring</Text>
                </View>
              </View>
            </View>
          </View>

          {/* Hotspots */}
          <View style={styles.hotspotSection}>
            <View style={[styles.hotspotSectionHeader, !isTablet && { flexDirection: 'column', alignItems: 'flex-start' }]}>
              <View style={styles.headerText}>
                <Text style={styles.hotspotSectionTitle}>Historical Hotspot Classification</Text>
                <Text style={styles.hotspotSectionSub}>
                  K-Means clustering groups all monitored locations by historical risk level.
                </Text>
              </View>
              <Text style={[styles.hotspotHighlight, !isTablet && { marginTop: 8 }]}>
                Monthly forecast: <Text style={styles.hotspotHighlightBold}>{prediction.riskLevel} Risk</Text>
              </Text>
            </View>

            <View style={styles.hotspotRow}>
              {renderHotspotCard('High Risk', mockHotspots.high, '#dc2626')}
              {renderHotspotCard('Medium Risk', mockHotspots.medium, '#f59e0b')}
              {renderHotspotCard('Low Risk', mockHotspots.low, '#16a34a')}
            </View>
          </View>

          {/* Recommended Actions */}
          <View style={styles.aiActionsCard}>
            <View style={styles.aiPredictionHeader}>
              <View style={[styles.aiIconBox, { backgroundColor: '#fef3c7' }]}>
                <Text style={styles.aiIcon}>💡</Text>
              </View>
              <View style={styles.aiPredictionHeaderText}>
                <Text style={styles.aiPredictionTitle}>Recommended Actions</Text>
                <Text style={styles.aiPredictionSub}>Suggested operational response based on the monthly forecast</Text>
              </View>
            </View>
            <View style={styles.actionGrid}>
              {prediction.recommendations.map((action, idx) => (
                <View key={idx} style={[styles.actionItem, { width: isTablet ? '48%' : '100%' }]}>
                  <Text style={styles.actionIcon}>✅</Text>
                  <Text style={styles.actionText}>{action}</Text>
                </View>
              ))}
            </View>
          </View>
        </View>

        {/* Compact Metrics */}
        <View style={styles.compactRow}>
          <View style={[styles.compactMetric, { width: isTablet ? '23%' : '48%' }]}>
            <Text style={styles.compactLabel}>Pending Violations</Text>
            <Text style={styles.compactValue}>{mockPendingViolations}</Text>
          </View>
          <View style={[styles.compactMetric, { width: isTablet ? '23%' : '48%' }]}>
            <Text style={styles.compactLabel}>Congestion Events Today</Text>
            <Text style={styles.compactValue}>{mockCongestionEvents}</Text>
          </View>
          <View style={[styles.compactMetric, { width: isTablet ? '23%' : '48%' }]}>
            <Text style={styles.compactLabel}>Potential Collisions Today</Text>
            <Text style={styles.compactValue}>{mockCollisionEvents}</Text>
          </View>
          <View style={[styles.compactMetric, { width: isTablet ? '23%' : '48%' }]}>
            <Text style={styles.compactLabel}>Online Cameras</Text>
            <Text style={styles.compactValue}>{mockOnlineCameras} / {mockTotalCameras}</Text>
          </View>
        </View>

        {/* Current Monitoring */}
        <View style={styles.monitoringCard}>
          <View style={styles.monitoringHeader}>
            <View>
              <Text style={styles.monitoringTitle}>Current Monitoring Status</Text>
              <Text style={styles.monitoringSub}>Latest computer-vision monitoring record</Text>
            </View>
            <View style={[styles.statusBadge, { backgroundColor: statusColor(mockLatestMonitoring.cameraStatus) + '20' }]}>
              <Text style={[styles.statusText, { color: statusColor(mockLatestMonitoring.cameraStatus) }]}>
                {mockLatestMonitoring.cameraStatus}
              </Text>
            </View>
          </View>
          <View style={styles.monitoringGrid}>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Camera</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.cameraName}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Location</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.location}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Visible Vehicles</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.vehicleCount}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Inbound</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.inbound}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Outbound</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.outbound}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Congestion</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.congestion}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Traffic Officer</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.officerPresence}</Text>
            </View>
            <View style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
              <Text style={styles.monitoringLabel}>Potential Collision</Text>
              <Text style={styles.monitoringValue}>{mockLatestMonitoring.potentialCollision}</Text>
            </View>
          </View>
          <Text style={styles.monitoringTimestamp}>Last updated: {mockLatestMonitoring.recordedAt}</Text>
        </View>

        {/* Charts */}
        <View style={styles.chartsRow}>
          <View style={[styles.chartCard, { width: isTablet ? '48.5%' : '100%' }]}>
            <Text style={styles.chartTitle}>Monthly Violation Trends</Text>
            <Text style={styles.chartSub}>Current year</Text>
            <LineChart
              data={{
                labels: mockMonthlyTrend.labels,
                datasets: [{ data: mockMonthlyTrend.data }],
              }}
              width={isTablet ? chartWidth / 2 - 32 : chartWidth - 32}
              height={180}
              chartConfig={{
                backgroundColor: '#fff',
                backgroundGradientFrom: '#fff',
                backgroundGradientTo: '#fff',
                decimalPlaces: 0,
                color: (opacity = 1) => `rgba(25, 118, 210, ${opacity})`,
                labelColor: (opacity = 1) => `rgba(73, 101, 127, ${opacity})`,
                style: { borderRadius: 16 },
                propsForDots: { r: '4', strokeWidth: '2', stroke: '#0b3d78' },
              }}
              bezier
              style={styles.chart}
            />
            <Text style={styles.chartInterpretation}>
              Interpretation: July recorded the highest monthly count at 124.
            </Text>
          </View>

          <View style={[styles.chartCard, { width: isTablet ? '48.5%' : '100%' }]}>
            <Text style={styles.chartTitle}>Top Violation Types</Text>
            <Text style={styles.chartSub}>All-time</Text>
            <BarChart
              data={{
                labels: mockTopViolations.labels,
                datasets: [{ data: mockTopViolations.data }],
              }}
              width={isTablet ? chartWidth / 2 - 32 : chartWidth - 32}
              height={180}
              yAxisLabel=""
              yAxisSuffix=""
              fromZero={true}
              chartConfig={{
                backgroundColor: '#fff',
                backgroundGradientFrom: '#fff',
                backgroundGradientTo: '#fff',
                decimalPlaces: 0,
                color: (opacity = 1) => `rgba(25, 118, 210, ${opacity})`,
                labelColor: (opacity = 1) => `rgba(73, 101, 127, ${opacity})`,
                style: { borderRadius: 16 },
              }}
              style={styles.chart}
              verticalLabelRotation={30}
            />
            <Text style={styles.chartInterpretation}>
              Interpretation: Speeding is the leading violation type with 45 records.
            </Text>
          </View>
        </View>

        {/* Recent Alerts and Violations */}
        <View style={styles.recentRow}>
          <View style={[styles.recentCard, { width: isTablet ? '48.5%' : '100%' }]}>
            <View style={styles.recentHeader}>
              <Text style={styles.recentTitle}>Recent Alerts</Text>
              <TouchableOpacity onPress={() => router.push('./alerts')}>
                <Text style={styles.recentViewAll}>View all</Text>
              </TouchableOpacity>
            </View>
            {mockRecentAlerts.map(renderAlertItem)}
          </View>

          <View style={[styles.recentCard, { width: isTablet ? '48.5%' : '100%' }]}>
            <View style={styles.recentHeader}>
              <Text style={styles.recentTitle}>Recent Violations</Text>
              <TouchableOpacity onPress={() => router.push('./violations')}>
                <Text style={styles.recentViewAll}>View all</Text>
              </TouchableOpacity>
            </View>
            {mockRecentViolations.map(renderViolationItem)}
          </View>
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
// General spacing scale used throughout: 8 / 12 / 16 / 20 / 24
const SECTION_GAP = 20;
const CARD_RADIUS = 18;

const cardShadow = {
  shadowColor: '#0b3d78',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.06,
  shadowRadius: 12,
  elevation: 2,
};

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#f8fafc' },
  container: { flex: 1 },
  scrollContent: { padding: 20, paddingBottom: 40 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f8fafc' },
  loadingText: { marginTop: 12, fontSize: 16, color: '#1e293b' },

  // Header
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: SECTION_GAP,
  },
  headerStacked: { flexDirection: 'column', alignItems: 'flex-start' },
  headerText: { flex: 1, paddingRight: 12 },
  eyebrow: { fontSize: 12, fontWeight: '700', color: '#2563eb', letterSpacing: 0.6, marginBottom: 6 },
  pageTitle: { fontSize: 26, fontWeight: '800', color: '#0b3d78', marginBottom: 6 },
  pageSub: { fontSize: 14, color: '#64748b', lineHeight: 20 },
  onlineBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#e6f7ea',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 20,
  },
  onlineDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#16a34a', marginRight: 8 },
  onlineText: { fontSize: 12, fontWeight: '700', color: '#16a34a' },

  // Stat cards
  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: 14,
    marginBottom: SECTION_GAP,
  },
  statCard: {
    backgroundColor: '#fff',
    borderRadius: CARD_RADIUS,
    padding: 18,
    ...cardShadow,
  },
  iconCircle: {
    width: 44,
    height: 44,
    borderRadius: 14,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  iconCircleText: { fontSize: 20 },
  statLabel: { fontSize: 13, color: '#64748b', fontWeight: '600', marginBottom: 6 },
  statValue: { fontSize: 24, fontWeight: '800', color: '#0b3d78', marginBottom: 6 },
  statSubtext: { fontSize: 12, color: '#94a3b8' },

  // AI card
  aiCard: {
    backgroundColor: '#fff',
    borderRadius: 24,
    padding: 22,
    marginBottom: SECTION_GAP,
    ...cardShadow,
  },
  aiHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 20,
  },
  aiHeaderStacked: { flexDirection: 'column', alignItems: 'flex-start' },
  aiKicker: { fontSize: 12, fontWeight: '700', color: '#2563eb', letterSpacing: 0.4 },
  aiTitle: { fontSize: 19, fontWeight: '800', color: '#0b3d78', marginTop: 6, marginBottom: 4 },
  aiSub: { fontSize: 13, color: '#64748b', lineHeight: 19 },
  refreshButton: { backgroundColor: '#f1f5f9', paddingHorizontal: 16, paddingVertical: 9, borderRadius: 20 },
  refreshButtonText: { fontSize: 13, fontWeight: '700', color: '#0b3d78' },

  aiContent: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: 14,
    marginBottom: 20,
  },
  aiPredictionCard: { backgroundColor: '#f8fafc', borderRadius: 18, padding: 18 },
  aiDeploymentCard: { backgroundColor: '#f8fafc', borderRadius: 18, padding: 18 },
  aiPredictionHeader: { flexDirection: 'row', alignItems: 'center', marginBottom: 16 },
  aiPredictionHeaderText: { flex: 1 },
  aiIconBox: {
    width: 42,
    height: 42,
    borderRadius: 13,
    backgroundColor: '#dbeafe',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  aiIcon: { fontSize: 18 },
  aiPredictionTitle: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  aiPredictionSub: { fontSize: 12, color: '#64748b', marginTop: 2 },
  aiPredictionBody: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 10,
  },
  aiLabel: { fontSize: 12, color: '#64748b', marginBottom: 6 },
  riskBadge: { alignSelf: 'flex-start', paddingHorizontal: 14, paddingVertical: 5, borderRadius: 20 },
  riskBadgeText: { fontWeight: '800', fontSize: 15 },
  aiPeriod: { fontSize: 12, color: '#64748b', marginTop: 8 },
  aiConfidence: { minWidth: 110 },
  confidenceRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 6 },
  confidenceLabel: { fontSize: 12, color: '#64748b' },
  confidenceValue: { fontSize: 12, fontWeight: '700', color: '#0b3d78' },
  progressBar: { height: 7, backgroundColor: '#e2e8f0', borderRadius: 4, overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: 4 },
  aiNote: { fontSize: 11.5, color: '#94a3b8', marginTop: 12, fontStyle: 'italic', lineHeight: 16 },

  deploymentItems: { marginTop: 4 },
  deploymentItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: 9,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  deploymentLabel: { fontSize: 13, color: '#64748b' },
  deploymentValue: { fontSize: 13, fontWeight: '700', color: '#0b3d78' },

  // Hotspots
  hotspotSection: { marginTop: 4 },
  hotspotSectionHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  hotspotSectionTitle: { fontSize: 16, fontWeight: '700', color: '#0b3d78', marginBottom: 4 },
  hotspotSectionSub: { fontSize: 12, color: '#64748b', lineHeight: 17 },
  hotspotHighlight: { fontSize: 13, color: '#64748b' },
  hotspotHighlightBold: { fontWeight: '700', color: '#0b3d78' },
  hotspotRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 14 },
  hotspotCard: { backgroundColor: '#f8fafc', borderRadius: 18, padding: 16, borderTopWidth: 5 },
  hotspotHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  hotspotTitle: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  hotspotCountPill: { paddingHorizontal: 10, paddingVertical: 3, borderRadius: 12 },
  hotspotCount: { fontSize: 13, fontWeight: '800' },
  hotspotItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  hotspotRank: { width: 26, height: 26, borderRadius: 9, justifyContent: 'center', alignItems: 'center', marginRight: 10 },
  hotspotRankText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  hotspotLocation: { flex: 1 },
  hotspotName: { fontSize: 13, fontWeight: '600', color: '#0b3d78' },
  hotspotDetails: { fontSize: 11.5, color: '#64748b', marginTop: 1 },
  hotspotEmpty: { fontSize: 13, color: '#94a3b8', textAlign: 'center', paddingVertical: 16 },

  aiActionsCard: { marginTop: 20, backgroundColor: '#f8fafc', borderRadius: 18, padding: 18 },
  actionGrid: { flexDirection: 'row', flexWrap: 'wrap', rowGap: 10, columnGap: 10, marginTop: 6 },
  actionItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: 14,
    padding: 14,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  actionIcon: { marginRight: 10, fontSize: 14 },
  actionText: { fontSize: 13, color: '#0b3d78', flex: 1, lineHeight: 18 },

  // Compact metrics
  compactRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: 14,
    marginBottom: SECTION_GAP,
  },
  compactMetric: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    ...cardShadow,
  },
  compactLabel: { fontSize: 12, color: '#64748b', marginBottom: 8, lineHeight: 16 },
  compactValue: { fontSize: 22, fontWeight: '800', color: '#0b3d78' },

  // Current monitoring
  monitoringCard: {
    backgroundColor: '#fff',
    borderRadius: 18,
    padding: 20,
    marginBottom: SECTION_GAP,
    ...cardShadow,
  },
  monitoringHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: 18 },
  monitoringTitle: { fontSize: 16, fontWeight: '700', color: '#0b3d78', marginBottom: 4 },
  monitoringSub: { fontSize: 12, color: '#64748b' },
  statusBadge: { paddingHorizontal: 12, paddingVertical: 5, borderRadius: 20 },
  statusText: { fontSize: 12, fontWeight: '700', textTransform: 'capitalize' },
  monitoringGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 16 },
  monitoringItem: {},
  monitoringLabel: { fontSize: 11, color: '#94a3b8', marginBottom: 4 },
  monitoringValue: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  monitoringTimestamp: { fontSize: 11, color: '#94a3b8', marginTop: 14 },

  // Charts
  chartsRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 14, marginBottom: SECTION_GAP },
  chartCard: { backgroundColor: '#fff', borderRadius: 18, padding: 18, ...cardShadow },
  chartTitle: { fontSize: 14, fontWeight: '700', color: '#0b3d78', marginBottom: 2 },
  chartSub: { fontSize: 12, color: '#64748b', marginBottom: 10 },
  chart: { marginVertical: 8, borderRadius: 16 },
  chartInterpretation: { fontSize: 11.5, color: '#64748b', marginTop: 10, backgroundColor: '#f1f5f9', padding: 10, borderRadius: 10, lineHeight: 16 },

  // Recent alerts / violations
  recentRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 14 },
  recentCard: { backgroundColor: '#fff', borderRadius: 18, padding: 18, ...cardShadow },
  recentHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  recentTitle: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  recentViewAll: { fontSize: 12, color: '#2563eb', fontWeight: '700' },
  alertItem: { paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#e2e8f0' },
  alertRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  alertType: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  severityBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 12 },
  severityText: { fontSize: 11, fontWeight: '700', textTransform: 'capitalize' },
  alertMessage: { fontSize: 13, color: '#1e293b', marginBottom: 3, lineHeight: 18 },
  alertTime: { fontSize: 11, color: '#94a3b8' },
  violationItem: { paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: '#e2e8f0' },
  violationRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  violationTicket: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  violationDetail: { fontSize: 13, color: '#1e293b', lineHeight: 18 },
});