import { Ionicons } from '@expo/vector-icons';
import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { mlApi } from '../../api/axiosConfig';

type Hotspot = Record<string, any>;
type Prediction = { risk_level?: string; confidence?: number; month_name?: string; year?: number; recommendations?: string[] };
const C = { bg: '#F3F6F5', navy: '#102F49', teal: '#087D78', green: '#15966F', amber: '#EB941F', red: '#C84B45', text: '#10202C', muted: '#64748B', border: '#DCE5E3', white: '#FFFFFF' };

const riskKey = (value?: string) => String(value || 'low').toLowerCase().replace(' risk', '');
const riskColor = (value?: string) => riskKey(value) === 'high' ? C.red : riskKey(value) === 'medium' ? C.amber : C.green;
const locationName = (item: Hotspot) => item.Location || item.location || item.violation_location || 'Unnamed location';
const totalViolations = (item: Hotspot) => Number(item['Total Violations'] ?? item.Total_Violations ?? item.total ?? 0);

export default function DecisionSupportScreen() {
  const nextMonth = useMemo(() => { const value = new Date(); value.setMonth(value.getMonth() + 1, 1); return value; }, []);
  const [period, setPeriod] = useState(nextMonth);
  const [prediction, setPrediction] = useState<Prediction>({});
  const [locations, setLocations] = useState<Hotspot[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setError('');
    try {
      const [monthly, hotspots] = await Promise.all([
        mlApi.get('predict_monthly.php', { params: { year: period.getFullYear(), month: period.getMonth() + 1 } }),
        mlApi.get('predict_hotspot.php'),
      ]);
      if (!monthly.data?.success) throw new Error(monthly.data?.message || 'Monthly prediction is unavailable.');
      if (!hotspots.data?.success) throw new Error(hotspots.data?.message || 'Location prediction is unavailable.');
      setPrediction(monthly.data.data || monthly.data.prediction || {});
      setLocations(hotspots.data?.data?.locations || hotspots.data?.locations || []);
    } catch (requestError: any) {
      setError(requestError.response?.data?.message || requestError.message || 'Unable to load decision support information.');
    } finally {
      setLoading(false); setRefreshing(false);
    }
  }, [period]);

  useEffect(() => { setLoading(true); load(); }, [load]);
  const grouped = useMemo(() => ({
    high: locations.filter(item => riskKey(item['Risk Level'] || item.risk_level) === 'high'),
    medium: locations.filter(item => riskKey(item['Risk Level'] || item.risk_level) === 'medium'),
    low: locations.filter(item => riskKey(item['Risk Level'] || item.risk_level) === 'low'),
  }), [locations]);
  const recommendations = prediction.recommendations || (riskKey(prediction.risk_level) === 'high'
    ? ['Prioritize high-risk intersections for field deployment.', 'Increase visible enforcement during peak hours.', 'Review live congestion and alert feeds.']
    : riskKey(prediction.risk_level) === 'medium'
      ? ['Review recurring violation types and locations.', 'Prepare personnel for known hotspot areas.', 'Continue targeted monitoring.']
      : ['Maintain routine monitoring.', 'Inspect historical hotspots periodically.', 'Keep standard personnel deployment.']);
  const moveMonth = (amount: number) => setPeriod(current => new Date(current.getFullYear(), current.getMonth() + amount, 1));

  const hotspotGroup = (title: string, items: Hotspot[], color: string, icon: any) => <View style={styles.card}>
    <View style={styles.groupHeader}><View style={[styles.groupIcon, { backgroundColor: `${color}18` }]}><Ionicons name={icon} size={18} color={color} /></View><View style={{ flex: 1 }}><Text style={styles.groupTitle}>{title}</Text><Text style={styles.groupSub}>{items.length} predicted location{items.length === 1 ? '' : 's'}</Text></View><Text style={[styles.groupCount, { color }]}>{items.length}</Text></View>
    {items.length === 0 ? <Text style={styles.empty}>No locations in this risk group.</Text> : items.map((item, index) => <View style={styles.locationRow} key={`${locationName(item)}-${index}`}><View style={[styles.rank, { borderColor: color }]}><Text style={[styles.rankText, { color }]}>{index + 1}</Text></View><View style={{ flex: 1 }}><Text style={styles.location}>{locationName(item)}</Text><Text style={styles.locationMeta}>{totalViolations(item)} historical violations</Text></View><Ionicons name="chevron-forward" size={16} color={C.muted} /></View>)}
  </View>;

  return <ScrollView style={styles.screen} contentContainerStyle={styles.page} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}>
    <View style={styles.hero}><Text style={styles.eyebrow}>AI DECISION INTELLIGENCE</Text><Text style={styles.heroTitle}>Location Prediction</Text><Text style={styles.heroText}>Monthly risk forecasting and historical violation-hotspot clustering for deployment planning.</Text></View>
    <View style={styles.periodCard}><TouchableOpacity style={styles.periodButton} onPress={() => moveMonth(-1)}><Ionicons name="chevron-back" size={20} color={C.navy} /></TouchableOpacity><View><Text style={styles.periodLabel}>PREDICTION PERIOD</Text><Text style={styles.periodValue}>{period.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}</Text></View><TouchableOpacity style={styles.periodButton} onPress={() => moveMonth(1)}><Ionicons name="chevron-forward" size={20} color={C.navy} /></TouchableOpacity></View>
    {loading ? <View style={styles.loading}><ActivityIndicator size="large" color={C.teal} /><Text style={styles.loadingText}>Preparing prediction modelsâ€¦</Text></View> : error ? <View style={styles.error}><Ionicons name="cloud-offline-outline" size={28} color={C.red} /><Text style={styles.errorTitle}>Prediction unavailable</Text><Text style={styles.errorText}>{error}</Text><TouchableOpacity style={styles.retry} onPress={() => { setLoading(true); load(); }}><Text style={styles.retryText}>Try again</Text></TouchableOpacity></View> : <>
      <View style={[styles.riskCard, { borderLeftColor: riskColor(prediction.risk_level) }]}><View><Text style={styles.riskLabel}>PREDICTED MONTHLY RISK</Text><Text style={[styles.riskValue, { color: riskColor(prediction.risk_level) }]}>{String(prediction.risk_level || 'Unknown').toUpperCase()}</Text><Text style={styles.riskPeriod}>{prediction.month_name || period.toLocaleDateString('en-US', { month: 'long' })} {prediction.year || period.getFullYear()}</Text></View><View style={[styles.confidence, { borderColor: riskColor(prediction.risk_level) }]}><Text style={[styles.confidenceValue, { color: riskColor(prediction.risk_level) }]}>{Math.round(Number(prediction.confidence || 0))}%</Text><Text style={styles.confidenceLabel}>CONFIDENCE</Text></View></View>
      <Text style={styles.section}>PREDICTED LOCATION PRIORITIES</Text>
      {hotspotGroup('High Risk Locations', grouped.high, C.red, 'warning-outline')}
      {hotspotGroup('Medium Risk Locations', grouped.medium, C.amber, 'alert-circle-outline')}
      {hotspotGroup('Low Risk Locations', grouped.low, C.green, 'shield-checkmark-outline')}
      <Text style={styles.section}>RECOMMENDED ACTIONS</Text><View style={styles.card}>{recommendations.map((item, index) => <View style={styles.recommendation} key={`${item}-${index}`}><View style={styles.check}><Ionicons name="checkmark" size={14} color={C.white} /></View><Text style={styles.recommendationText}>{item}</Text></View>)}</View>
      <Text style={styles.disclaimer}>Predictions support operational planning and do not guarantee future conditions. Review them alongside live monitoring and field reports.</Text>
    </>}
  </ScrollView>;
}

const styles = StyleSheet.create({ screen:{flex:1,backgroundColor:C.bg},page:{padding:16,paddingBottom:40},hero:{backgroundColor:C.navy,borderRadius:20,padding:20,marginBottom:14},eyebrow:{color:'#55C7C0',fontSize:9,fontWeight:'900',letterSpacing:1.2},heroTitle:{color:C.white,fontSize:25,fontWeight:'900',marginTop:6},heroText:{color:'#B9CAD3',fontSize:11,lineHeight:17,marginTop:7},periodCard:{backgroundColor:C.white,borderWidth:1,borderColor:C.border,borderRadius:16,padding:12,flexDirection:'row',alignItems:'center',justifyContent:'space-between',marginBottom:14},periodButton:{width:38,height:38,borderRadius:12,backgroundColor:'#EDF3F2',alignItems:'center',justifyContent:'center'},periodLabel:{textAlign:'center',color:C.muted,fontSize:8,fontWeight:'900',letterSpacing:1},periodValue:{textAlign:'center',color:C.text,fontSize:15,fontWeight:'800',marginTop:3},loading:{minHeight:260,alignItems:'center',justifyContent:'center'},loadingText:{color:C.muted,fontSize:12,marginTop:12},error:{backgroundColor:C.white,borderRadius:18,padding:28,alignItems:'center',borderWidth:1,borderColor:'#F0C9C6'},errorTitle:{color:C.text,fontSize:16,fontWeight:'900',marginTop:10},errorText:{color:C.muted,fontSize:11,lineHeight:17,textAlign:'center',marginTop:6},retry:{backgroundColor:C.teal,borderRadius:10,paddingHorizontal:18,paddingVertical:10,marginTop:15},retryText:{color:C.white,fontWeight:'800'},riskCard:{backgroundColor:C.white,borderRadius:18,borderWidth:1,borderColor:C.border,borderLeftWidth:5,padding:18,flexDirection:'row',justifyContent:'space-between',alignItems:'center'},riskLabel:{color:C.muted,fontSize:9,fontWeight:'900',letterSpacing:.8},riskValue:{fontSize:22,fontWeight:'900',marginTop:5},riskPeriod:{color:C.muted,fontSize:11,marginTop:3},confidence:{width:74,height:74,borderRadius:37,borderWidth:5,alignItems:'center',justifyContent:'center'},confidenceValue:{fontSize:17,fontWeight:'900'},confidenceLabel:{color:C.muted,fontSize:6,fontWeight:'900',marginTop:1},section:{color:C.muted,fontSize:10,fontWeight:'900',letterSpacing:1.1,marginTop:22,marginBottom:9},card:{backgroundColor:C.white,borderRadius:16,borderWidth:1,borderColor:C.border,padding:15,marginBottom:10},groupHeader:{flexDirection:'row',alignItems:'center',paddingBottom:10,borderBottomWidth:1,borderBottomColor:'#EDF1F0'},groupIcon:{width:38,height:38,borderRadius:12,alignItems:'center',justifyContent:'center',marginRight:10},groupTitle:{color:C.text,fontSize:13,fontWeight:'800'},groupSub:{color:C.muted,fontSize:9,marginTop:2},groupCount:{fontSize:20,fontWeight:'900'},locationRow:{flexDirection:'row',alignItems:'center',paddingVertical:11,borderBottomWidth:1,borderBottomColor:'#F0F3F2'},rank:{width:28,height:28,borderRadius:9,borderWidth:1,alignItems:'center',justifyContent:'center',marginRight:10},rankText:{fontSize:11,fontWeight:'900'},location:{color:C.text,fontSize:12,fontWeight:'700'},locationMeta:{color:C.muted,fontSize:9,marginTop:3},empty:{color:C.muted,fontSize:11,textAlign:'center',paddingVertical:16},recommendation:{flexDirection:'row',alignItems:'flex-start',paddingVertical:9},check:{width:24,height:24,borderRadius:8,backgroundColor:C.teal,alignItems:'center',justifyContent:'center',marginRight:10},recommendationText:{flex:1,color:C.text,fontSize:11,lineHeight:17},disclaimer:{color:C.muted,fontSize:9,lineHeight:14,textAlign:'center',marginTop:8,paddingHorizontal:12} });


