import React, { useState, useEffect, useRef, useCallback } from 'react';
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
  Image,
} from 'react-native';
import { LineChart, BarChart } from 'react-native-chart-kit';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import Svg, { Circle } from 'react-native-svg';
import { useFocusEffect } from '@react-navigation/native';
import { useRouter } from 'expo-router';
import api, { mlApi } from '../../api/axiosConfig';

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: 'rgba(247, 245, 238, 0.74)',
  header: '#102F49',
  headerAccent: '#16445D',
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

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `\u20b1${amount.toLocaleString()}`;
const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'online' || s === 'paid' || s === 'low' || s === 'active' || s === 'published') return COLORS.success;
  if (s === 'high' || s === 'critical' || s === 'danger' || s === 'severe') return COLORS.danger;
  if (s === 'medium' || s === 'moderate' || s === 'pending' || s === 'warning' || s === 'draft') return COLORS.warning;
  if (s === 'offline' || s === 'archived' || s === 'none') return COLORS.neutral;
  return COLORS.textTertiary;
};
const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });

// ========== COUNT-UP ANIMATION ==========
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

// ========== PROGRESS RING ==========
function ProgressRing({ percentage, size = 108, strokeWidth = 10, color, trackColor }: any) {
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

// ========== MAIN SCREEN ==========
export default function DashboardScreen() {
  const router = useRouter();
  const { width } = useWindowDimensions();
  const chartWidth = Math.max(width - 40, 200);
  const isTablet = width >= 700;

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'monitoring' | 'analytics'>('overview');
  const [aiExpanded, setAiExpanded] = useState(false);
  const [dateRange, setDateRange] = useState<'Today' | 'Week' | 'Month' | 'Year'>('Month');
  const [now, setNow] = useState(new Date());

  // ===== REAL DATA STATES =====
  const [stats, setStats] = useState({
    vehiclesToday: 0,
    violationsToday: 0,
    activeAlerts: 0,
    collectedToday: 0,
    pendingViolations: 0,
    onlineCameras: 0,
    totalCameras: 0,
  });
  const [recentAlerts, setRecentAlerts] = useState<any[]>([]);

  // Chart data
  const [monthlyTrend, setMonthlyTrend] = useState<{ labels: string[]; data: number[] }>({
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    data: [],
  });
  const [topViolations, setTopViolations] = useState<{ labels: string[]; data: number[] }>({
    labels: [],
    data: [],
  });

  // Hotspots
  const [hotspots, setHotspots] = useState<{ high: any[]; medium: any[]; low: any[] }>({
    high: [],
    medium: [],
    low: [],
  });

  // Zones
  const [zones, setZones] = useState<any[]>([]);

  // ===== DYNAMIC AI RISK ASSESSMENT =====
  const [aiPrediction, setAiPrediction] = useState({
    riskLevel: 'Low',
    confidence: 0,
    month: '',
    recommendations: ['Loading risk assessment...'],
  });

  // ===== LIVE CAMERA FEED =====
  const [cameraFeed, setCameraFeed] = useState<any>(null);
  const [cameraLoading, setCameraLoading] = useState(false);
  const [cameraSnapshot, setCameraSnapshot] = useState<string | null>(null);

  const pulse = useRef(new Animated.Value(1)).current;

  // ========== FETCH ALL DATA ==========
  const fetchDashboardData = async () => {
    try {
      // 1. Stats
      const statsRes = await api.get('get_dashboard_stats.php');
      if (statsRes.data.success) {
        const d = statsRes.data.data;
        setStats({
          vehiclesToday: d.vehicles_today || 0,
          violationsToday: d.violations_today || 0,
          activeAlerts: d.active_alerts || 0,
          collectedToday: d.collected_today || 0,
          pendingViolations: d.pending_violations || 0,
          onlineCameras: d.online_cameras || 0,
          totalCameras: d.total_cameras || 0,
        });
      }

      // 2. Alerts
      const alertsRes = await api.get('get_alerts.php', { params: { limit: 5 } });
      if (alertsRes.data.success) {
        setRecentAlerts(alertsRes.data.data);
      }

      // 3. Monthly trends
      const trendRes = await api.get('get_monthly_trends.php');
      if (trendRes.data.success) {
        setMonthlyTrend({
          labels: trendRes.data.labels,
          data: trendRes.data.data,
        });
      }

      // 4. Top violations
      const topRes = await api.get('get_top_violations.php');
      if (topRes.data.success) {
        setTopViolations({
          labels: topRes.data.labels,
          data: topRes.data.data,
        });
      }

      // 5. ML location/hotspot prediction
      const hotspotRes = await mlApi.get('predict_hotspot.php');
      if (hotspotRes.data.success && Array.isArray(hotspotRes.data.data?.locations)) {
        const locations = hotspotRes.data.data.locations.map((location: any) => ({
          location: location.Location,
          total: Number(location['Total Violations']) || 0,
          riskLevel: location['Risk Level'] || 'Low Risk',
          recommendation: location.Recommendation || '',
        }));
        setHotspots({
          high: locations.filter((location: any) => String(location.riskLevel).toLowerCase().startsWith('high')),
          medium: locations.filter((location: any) => String(location.riskLevel).toLowerCase().startsWith('medium')),
          low: locations.filter((location: any) => String(location.riskLevel).toLowerCase().startsWith('low')),
        });
      }

      // 6. Zones
      const zonesRes = await api.get('get_zones.php');
      if (zonesRes.data.success) {
        setZones(zonesRes.data.data || []);
      }

      // 7. ML monthly prediction (forecast the next calendar month)
      const forecastDate = new Date();
      forecastDate.setMonth(forecastDate.getMonth() + 1, 1);
      const aiRes = await mlApi.get('predict_monthly.php', {
        params: {
          year: forecastDate.getFullYear(),
          month: forecastDate.getMonth() + 1,
        },
      });
      if (aiRes.data.success && aiRes.data.data) {
        const prediction = aiRes.data.data;
        setAiPrediction({
          riskLevel: prediction.risk_level || 'Low',
          confidence: Number(prediction.confidence) || 0,
          month: `${prediction.month_name || ''} ${prediction.year || ''}`.trim(),
          recommendations: Array.isArray(prediction.recommendations)
            ? prediction.recommendations
            : ['Review the prediction with current traffic conditions.'],
        });
      }

      // 8. Camera Feed
      await fetchCameraFeed();

    } catch (error) {
      console.error('Dashboard fetch error:', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const fetchCameraFeed = async () => {
    try {
      setCameraLoading(true);
      const res = await api.get('get_camera_feed.php');
      if (res.data.success) {
        setCameraFeed(res.data.data);
        // Kung may snapshot URL, i-set ito (optional)
        if (res.data.data.snapshot_url) {
          setCameraSnapshot(res.data.data.snapshot_url);
        } else {
          // Kung walang snapshot, gumamit ng placeholder
          setCameraSnapshot(null);
        }
      }
    } catch (error) {
      console.error('Camera feed error:', error);
    } finally {
      setCameraLoading(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchDashboardData();
    }, [])
  );

  const refresh = () => {
    setRefreshing(true);
    fetchDashboardData();
  };

  // ===== EFFECTS =====
  useEffect(() => {
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

  // ===== COUNT-UP =====
  const vehiclesCount = useCountUp(stats.vehiclesToday, !loading);
  const violationsCount = useCountUp(stats.violationsToday, !loading);
  const alertsCount = useCountUp(stats.activeAlerts, !loading);
  const revenueCount = useCountUp(stats.collectedToday, !loading);
  const confidenceCount = useCountUp(aiPrediction.confidence, !loading);

  const timeStr = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
  const dateStr = now.toLocaleDateString('en-PH', { month: 'long', day: '2-digit', year: 'numeric' });
  const hour = now.getHours();
  const greeting = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';

  const hasHotspots = hotspots.high.length + hotspots.medium.length + hotspots.low.length > 0;

  // ========== RENDER HELPERS ==========
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

  const renderHotspotRow = (loc: any, color: string, key: number) => (
    <View key={key} style={styles.hotspotRowItem}>
      <View style={[styles.hotspotDot, { backgroundColor: color }]} />
      <Text style={styles.hotspotRowName}>{loc.location}</Text>
      <Text style={styles.hotspotRowCount}>{loc.total}</Text>
    </View>
  );

  const renderZoneCard = (zone: any, idx: number) => (
    <View key={idx} style={[styles.zoneCard, { width: isTablet ? '31.5%' : '48%' }]}>
      <View style={styles.zoneCardTop}>
        <View style={[styles.zoneStatusDot, { backgroundColor: statusColor(zone.status) }]} />
        <Text style={styles.zoneStatus}>{zone.status.toUpperCase()}</Text>
      </View>
      <Text style={styles.zoneName} numberOfLines={1}>{zone.name}</Text>
      <Text style={styles.zoneVehicles}>{zone.vehicles || 0} Vehicles</Text>
      {zone.congestion && zone.congestion !== 'None' ? (
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
          {/* ===== HERO ===== */}
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

            <Text style={styles.heroSummaryLabel}>TODAY’S SUMMARY</Text>
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
            {renderLiveChip('CAMERAS', `${stats.onlineCameras}/${stats.totalCameras}`, stats.onlineCameras > 0 ? 'online' : 'offline')}
            {renderLiveChip('DETECTION', '98%', 'online')}
            {renderLiveChip('SYSTEM HEALTH', '99.8%', 'online')}
          </ScrollView>

          {activeTab === 'overview' && (
            <>
              {/* Quick Actions */}
              <Text style={styles.sectionLabel}>QUICK ACTIONS</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.quickActionsRow} contentContainerStyle={{ paddingRight: 20 }}>
                {renderQuickAction(<Ionicons name="videocam-outline" size={17} color={COLORS.textPrimary} />, 'Cameras', () => router.push('/(drawer)/monitoring'))}
                {renderQuickAction(<MaterialCommunityIcons name="robot-outline" size={17} color={COLORS.textPrimary} />, 'AI', () => setActiveTab('analytics'))}
                {renderQuickAction(<Ionicons name="document-text-outline" size={17} color={COLORS.textPrimary} />, 'Reports', () => router.push('/(drawer)/reports'))}
                {renderQuickAction(<Ionicons name="map-outline" size={17} color={COLORS.textPrimary} />, 'Zones', () => setActiveTab('monitoring'))}
                {renderQuickAction(<Ionicons name="alert-circle-outline" size={17} color={COLORS.textPrimary} />, 'Alerts', () => router.push('/(drawer)/alerts'))}
              </ScrollView>

              {/* AI Risk Assessment - DYNAMIC */}
              <View style={styles.panel}>
                <View style={styles.panelHeader}>
                  <View style={styles.eyebrowRow}>
                    <MaterialCommunityIcons name="radar" size={13} color={COLORS.primary} style={{ marginRight: 6 }} />
                    <Text style={styles.panelTitle}>AI RISK ASSESSMENT</Text>
                  </View>
                </View>

                <View style={styles.aiReadoutRow}>
                  <View style={styles.ringWrap}>
                    <ProgressRing
                      percentage={confidenceCount}
                      color={statusColor(aiPrediction.riskLevel)}
                      trackColor={COLORS.border}
                    />
                    <View style={styles.ringCenter}>
                      <Text style={styles.ringPercent}>{confidenceCount}<Text style={styles.ringPercentSign}>%</Text></Text>
                    </View>
                  </View>
                  <View style={styles.aiMetaCol}>
                    <Text style={[styles.riskLabel, { color: statusColor(aiPrediction.riskLevel) }]}>
                      {aiPrediction.riskLevel.toUpperCase()} RISK
                    </Text>
                    <Text style={styles.aiPeriod}>Forecast · {aiPrediction.month}</Text>
                    <Text style={styles.aiRecommendationLead}>{aiPrediction.recommendations[0]}</Text>
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
                    {aiPrediction.recommendations.map((action, idx) => (
                      <View key={idx} style={styles.commandRow}>
                        <Text style={styles.commandPrefix}>{'>'}</Text>
                        <Text style={styles.commandText}>{action}</Text>
                      </View>
                    ))}

                    <View style={styles.panelDivider} />
                    <Text style={styles.subsectionLabel}>DEPLOYMENT GUIDANCE</Text>
                    <View style={styles.readoutRow}>
                      <Text style={styles.readoutLabel}>PERSONNEL</Text>
                      <Text style={styles.readoutValue}>
                        {aiPrediction.riskLevel === 'Critical' ? '8–10 ENFORCERS' :
                         aiPrediction.riskLevel === 'High' ? '5–6 ENFORCERS' :
                         aiPrediction.riskLevel === 'Medium' ? '3–4 ENFORCERS' : '1–2 ENFORCERS'}
                      </Text>
                    </View>
                    <View style={styles.readoutRow}>
                      <Text style={styles.readoutLabel}>MONITORING</Text>
                      <Text style={styles.readoutValue}>
                        {aiPrediction.riskLevel === 'Critical' ? '24/7 INTENSIVE' :
                         aiPrediction.riskLevel === 'High' ? 'INTENSIVE' :
                         aiPrediction.riskLevel === 'Medium' ? 'STANDARD' : 'ROUTINE'}
                      </Text>
                    </View>

                    <View style={styles.panelDivider} />
                    <Text style={styles.subsectionLabel}>HOTSPOTS BY RISK</Text>
                    {hasHotspots ? (
                      <>
                        {hotspots.high.slice(0, 3).map((loc: any, idx: number) => renderHotspotRow(loc, COLORS.danger, idx))}
                        {hotspots.medium.slice(0, 2).map((loc: any, idx: number) => renderHotspotRow(loc, COLORS.warning, idx + 10))}
                        {hotspots.low.slice(0, 2).map((loc: any, idx: number) => renderHotspotRow(loc, COLORS.success, idx + 20))}
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

              {/* Zone preview */}
              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionLabel}>ZONE STATUS</Text>
                <TouchableOpacity onPress={() => setActiveTab('monitoring')}>
                  <Text style={styles.viewAllLink}>Open Zone Dashboard →</Text>
                </TouchableOpacity>
              </View>
              <View style={styles.zoneGrid}>
                {zones.length > 0 ? (
                  zones.slice(0, 4).map((zone, idx) => renderZoneCard(zone, idx))
                ) : (
                  <Text style={styles.emptyStateText}>No zones available.</Text>
                )}
              </View>
            </>
          )}

          {activeTab === 'monitoring' && (
            <>
              {/* Live Camera Preview - DYNAMIC */}
              <Text style={styles.sectionLabel}>PRIMARY FEED</Text>
              <View style={styles.cameraCard}>
                <View style={styles.cameraPreview}>
                  {cameraLoading ? (
                    <ActivityIndicator size="large" color={COLORS.primary} />
                  ) : cameraSnapshot ? (
                    <Image
                      source={{ uri: cameraSnapshot }}
                      style={{ width: '100%', height: '100%', borderRadius: 14 }}
                      resizeMode="cover"
                    />
                  ) : (
                    <>
                      <Ionicons name="videocam" size={32} color={COLORS.textTertiary} />
                      <Text style={styles.cameraPreviewNote}>
                        {cameraFeed ? `Camera: ${cameraFeed.camera_name}` : 'No camera feed available'}
                      </Text>
                    </>
                  )}
                  <View style={styles.liveBadge}>
                    <View style={styles.liveBadgeDot} />
                    <Text style={styles.liveBadgeText}>LIVE</Text>
                  </View>
                </View>

                <View style={styles.cameraInfoRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.feedTitle}>
                      {cameraFeed ? cameraFeed.camera_name : 'No Camera Selected'}
                    </Text>
                    <Text style={styles.feedSubtitle}>
                      {cameraFeed ? cameraFeed.location : 'No location data'}
                    </Text>
                  </View>
                  <View style={styles.panelStatusPill}>
                    <View style={[styles.zoneStatusDot, { backgroundColor: statusColor(cameraFeed?.status || 'offline') }]} />
                    <Text style={[styles.panelStatusText, { color: statusColor(cameraFeed?.status || 'offline') }]}>
                      {(cameraFeed?.status || 'OFFLINE').toUpperCase()}
                    </Text>
                  </View>
                </View>

                <View style={styles.monitoringGrid}>
                  {(['VEHICLES', 'INBOUND', 'OUTBOUND', 'CONGESTION', 'OFFICER', 'COLLISION RISK'] as const).map((label, i) => {
                    const vals = cameraFeed ? [
                      cameraFeed.vehicle_count || 0,
                      cameraFeed.inbound_count || 0,
                      cameraFeed.outbound_count || 0,
                      cameraFeed.congestion_level_display || 'None',
                      cameraFeed.officer_presence || 'Unknown',
                      cameraFeed.potential_collision || 'None',
                    ] : ['--', '--', '--', '--', '--', '--'];
                    return (
                      <View key={label} style={[styles.monitoringItem, { width: isTablet ? '23%' : '48%' }]}>
                        <Text style={styles.readoutLabel}>{label}</Text>
                        <Text style={styles.monitoringValue}>{String(vals[i])}</Text>
                      </View>
                    );
                  })}
                </View>
                <Text style={styles.feedTimestamp}>
                  LAST SYNC {cameraFeed?.recorded_at ? new Date(cameraFeed.recorded_at).toLocaleTimeString() : timeStr}
                </Text>
              </View>

              <View style={styles.sectionHeaderRow}>
                <Text style={styles.sectionLabel}>ZONE STATUS · {zones.length} MONITORED</Text>
              </View>
              <View style={styles.zoneGrid}>
                {zones.length > 0 ? (
                  zones.map((zone, idx) => renderZoneCard(zone, idx))
                ) : (
                  <Text style={styles.emptyStateText}>No zones available.</Text>
                )}
              </View>

              <Text style={styles.sectionLabel}>ALERT FEED</Text>
              <View style={{ gap: 10 }}>
                {recentAlerts.length === 0 ? (
                  <View style={styles.emptyState}>
                    <Ionicons name="checkmark-circle" size={16} color={COLORS.success} />
                    <Text style={styles.emptyStateText}>No active alerts.</Text>
                  </View>
                ) : (
                  recentAlerts.slice(0, 4).map((alert: any) => (
                    <View key={alert.alert_id} style={[styles.alertCard, { backgroundColor: statusColor(alert.severity) + '14', borderColor: statusColor(alert.severity) + '33' }]}>
                      <View style={styles.alertRow}>
                        <View style={[styles.alertSeverityBadge, { backgroundColor: statusColor(alert.severity) }]}>
                          <Text style={styles.alertSeverityText}>{alert.severity.toUpperCase()}</Text>
                        </View>
                        <Text style={styles.alertTime}>{alert.generated_at}</Text>
                      </View>
                      <Text style={styles.alertType}>{alert.alert_type}</Text>
                      <Text style={styles.alertMessage}>{alert.message}</Text>
                    </View>
                  ))
                )}
              </View>
            </>
          )}

          {activeTab === 'analytics' && (
            <>
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
              <View style={[styles.panel, styles.analyticsCard]}>
                <View style={styles.chartHeader}>
                  <View style={styles.chartTitleGroup}>
                    <View style={[styles.chartIcon, { backgroundColor: '#E6F5F2' }]}><Ionicons name="trending-up" size={18} color={COLORS.primary} /></View>
                    <View><Text style={styles.chartTitle}>Violation activity</Text><Text style={styles.chartSubtitle}>Monthly records · {new Date().getFullYear()}</Text></View>
                  </View>
                  {monthlyTrend.data.length > 0 && <View style={styles.chartMetricPill}><Text style={styles.chartMetricValue}>{monthlyTrend.data.reduce((sum, value) => sum + value, 0)}</Text><Text style={styles.chartMetricLabel}>TOTAL</Text></View>}
                </View>
                {monthlyTrend.data.length > 0 ? (
                  <View style={styles.chartViewport}><LineChart
                      data={{ labels: monthlyTrend.labels, datasets: [{ data: monthlyTrend.data, strokeWidth: 3 }] }}
                      width={chartWidth - 30}
                      height={220}
                      chartConfig={{
                        backgroundColor: '#FFFDF7', backgroundGradientFrom: '#FFFDF7', backgroundGradientTo: '#FFFDF7',
                        decimalPlaces: 0,
                        color: (opacity = 1) => `rgba(8, 125, 120, ${opacity})`,
                        labelColor: (opacity = 1) => `rgba(82, 107, 100, ${opacity})`,
                        fillShadowGradientFrom: COLORS.primary, fillShadowGradientFromOpacity: .24,
                        fillShadowGradientTo: '#FFFDF7', fillShadowGradientToOpacity: .02,
                        propsForDots: { r: '4', strokeWidth: '3', stroke: '#FFFDF7' },
                        propsForBackgroundLines: { stroke: 'rgba(16,47,73,.10)', strokeDasharray: '4 6' },
                      }}
                      bezier withShadow segments={4} style={styles.chart} withOuterLines={false}
                    /></View>
                ) : (
                  <View style={styles.chartEmpty}><Ionicons name="analytics-outline" size={28} color={COLORS.neutral} /><Text style={styles.chartEmptyTitle}>No trend data yet</Text><Text style={styles.chartEmptyText}>Monthly violations will appear after records are added.</Text></View>
                )}
                {monthlyTrend.data.length > 0 && <View style={styles.chartInsight}><Ionicons name="sparkles-outline" size={15} color={COLORS.primary} /><Text style={styles.chartInterpretation}>Peak activity reached <Text style={styles.chartInsightStrong}>{Math.max(...monthlyTrend.data)} violations</Text> in {monthlyTrend.labels[monthlyTrend.data.indexOf(Math.max(...monthlyTrend.data))]}.</Text></View>}
              </View>

              <Text style={[styles.sectionLabel, { marginTop: 24 }]}>TOP VIOLATION TYPES</Text>
              <View style={[styles.panel, styles.analyticsCard]}>
                <View style={styles.chartHeader}>
                  <View style={styles.chartTitleGroup}>
                    <View style={[styles.chartIcon, { backgroundColor: '#FFF2DF' }]}><Ionicons name="podium-outline" size={18} color={COLORS.warning} /></View>
                    <View><Text style={styles.chartTitle}>Most recorded offenses</Text><Text style={styles.chartSubtitle}>Ranked by total citations</Text></View>
                  </View>
                </View>
                {topViolations.data.length > 0 ? (
                  <View style={styles.chartViewport}><BarChart
                    data={{ labels: topViolations.labels.map(label => label.length > 10 ? `${label.slice(0, 9)}…` : label), datasets: [{ data: topViolations.data }] }}
                    width={chartWidth - 30}
                    height={230}
                    yAxisLabel=""
                    yAxisSuffix=""
                    fromZero
                    chartConfig={{
                      backgroundColor: '#FFFDF7', backgroundGradientFrom: '#FFFDF7', backgroundGradientTo: '#FFFDF7',
                      decimalPlaces: 0,
                      color: (opacity = 1) => `rgba(235, 148, 31, ${opacity})`,
                      labelColor: (opacity = 1) => `rgba(82, 107, 100, ${opacity})`,
                      barPercentage: .62,
                      propsForBackgroundLines: { stroke: 'rgba(16,47,73,.10)', strokeDasharray: '4 6' },
                    }}
                    style={styles.chart} verticalLabelRotation={0} showValuesOnTopOfBars withInnerLines segments={4}
                  /></View>
                ) : (
                  <View style={styles.chartEmpty}><Ionicons name="bar-chart-outline" size={28} color={COLORS.neutral} /><Text style={styles.chartEmptyTitle}>No ranking data yet</Text><Text style={styles.chartEmptyText}>Top violation types will appear as citations are recorded.</Text></View>
                )}
                {topViolations.data.length > 0 && <View style={[styles.chartInsight, styles.chartInsightAmber]}><Ionicons name="ribbon-outline" size={15} color={COLORS.warning} /><Text style={styles.chartInterpretation}><Text style={styles.chartInsightStrong}>{topViolations.labels[0]}</Text> currently ranks first with {topViolations.data[0]} records.</Text></View>}
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

  statusStrip: { maxHeight: 56, marginBottom: 20 },
  statusChip: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    borderWidth: 1, borderColor: COLORS.border, borderRadius: 12,
    paddingHorizontal: 12, paddingVertical: 8, marginRight: 10, ...softShadow,
  },
  statusChipDot: { width: 6, height: 6, borderRadius: 3, marginRight: 8 },
  statusChipLabel: { fontSize: 9, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6 },
  statusChipValue: { fontSize: 12, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono, marginTop: 1 },

  quickActionsRow: { marginBottom: 20 },
  quickAction: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    paddingHorizontal: 14, paddingVertical: 10, borderRadius: 12, marginRight: 10,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  quickActionLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginLeft: 7 },

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

  feedTitle: { fontSize: 18, fontWeight: '600', color: COLORS.textPrimary, marginBottom: 2 },
  feedSubtitle: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 16 },
  monitoringGrid: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', rowGap: 16, marginTop: 12 },
  monitoringItem: {},
  monitoringValue: { fontSize: 14, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono, marginTop: 4 },
  feedTimestamp: { fontSize: 10, color: COLORS.textTertiary, marginTop: 16, letterSpacing: 0.5, fontFamily: mono },

  alertCard: { borderRadius: 14, borderWidth: 1, padding: 14 },
  alertRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  alertSeverityBadge: { paddingHorizontal: 8, paddingVertical: 3, borderRadius: 6 },
  alertSeverityText: { fontSize: 10, fontWeight: '800', color: '#FFFFFF', letterSpacing: 0.5 },
  alertType: { fontSize: 13, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 3 },
  alertTime: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
  alertMessage: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  dateFilterRow: { flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 12, padding: 4, marginBottom: 20, borderWidth: 1, borderColor: COLORS.border },
  dateFilterChip: { flex: 1, paddingVertical: 8, borderRadius: 9, alignItems: 'center' },
  dateFilterChipActive: { backgroundColor: '#EFF6FF' },
  dateFilterText: { fontSize: 12, fontWeight: '600', color: COLORS.textTertiary },
  dateFilterTextActive: { color: COLORS.primary },

  analyticsCard: { padding: 15, overflow: 'hidden' },
  chartHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 },
  chartTitleGroup: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  chartIcon: { width: 38, height: 38, borderRadius: 12, alignItems: 'center', justifyContent: 'center', marginRight: 10 },
  chartTitle: { fontSize: 14, fontWeight: '800', color: COLORS.textPrimary },
  chartSubtitle: { fontSize: 10, color: COLORS.textTertiary, marginTop: 2 },
  chartMetricPill: { minWidth: 54, paddingHorizontal: 10, paddingVertical: 6, borderRadius: 10, backgroundColor: '#E6F5F2', alignItems: 'center' },
  chartMetricValue: { color: COLORS.primary, fontSize: 15, fontWeight: '900', fontFamily: mono },
  chartMetricLabel: { color: COLORS.textTertiary, fontSize: 7, fontWeight: '800', letterSpacing: .7, marginTop: 1 },
  chartViewport: { marginHorizontal: -15, overflow: 'hidden' },
  chart: { marginVertical: 2, marginLeft: -8, borderRadius: 16 },
  chartInsight: { flexDirection: 'row', alignItems: 'flex-start', gap: 7, backgroundColor: '#EAF6F3', borderRadius: 11, padding: 10, marginTop: 8 },
  chartInsightAmber: { backgroundColor: '#FFF5E7' },
  chartInterpretation: { flex: 1, fontSize: 11, color: COLORS.textSecondary, lineHeight: 16 },
  chartInsightStrong: { color: COLORS.textPrimary, fontWeight: '800' },
  chartEmpty: { alignItems: 'center', justifyContent: 'center', minHeight: 180, padding: 24 },
  chartEmptyTitle: { color: COLORS.textPrimary, fontSize: 13, fontWeight: '800', marginTop: 9 },
  chartEmptyText: { color: COLORS.textTertiary, fontSize: 10, textAlign: 'center', lineHeight: 15, marginTop: 4 },

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
