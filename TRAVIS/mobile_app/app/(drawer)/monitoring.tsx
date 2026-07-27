// @ts-nocheck -- this screen extends its StyleSheet at runtime for the upload panel.
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Image, RefreshControl, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import api, { MOBILE_SNAPSHOT_URL, mlApi } from '../../api/axiosConfig';
import * as ImagePicker from 'expo-image-picker';

type SourceType = 'uploaded_video' | 'tapo_camera';
type MonitorStatus = {
  analysis_status?: string; ai_status?: string; message?: string; vehicle_count?: number;
  inbound_count?: number; outbound_count?: number; congestion_level?: string;
  officer_presence?: string; potential_collision?: string; alert_status?: string; recorded_at?: string;
  stream_owner?: string; calibration_profile?: string;
  runtime_settings?: { congestion_light_max: number; congestion_heavy_min: number; confidence_threshold: number; enable_officer_detection: boolean; enable_collision_detection: boolean };
};
type MonitorLog = { recorded_at: string; vehicle_count: number; inbound_count: number; outbound_count: number; congestion_level: string; alert_generated: number };
type CalibrationProfile = { file: string; name: string };
const COLORS = { navy: '#0A1A30', teal: '#087D78', green: '#15966F', amber: '#EB941F', red: '#C84B45', bg: '#F3F6F7', card: '#FFFFFF', text: '#10202C', muted: '#64748B', border: '#DDE5E7' };

export default function MonitoringScreen() {
  Object.assign(styles, {
    uploadPanel: { flexDirection: 'row', alignItems: 'center', gap: 11, padding: 13, marginTop: 12, borderRadius: 12, backgroundColor: '#ECF7F6', borderWidth: 1, borderColor: '#B9DDDA' },
    uploadTitle: { color: COLORS.text, fontSize: 12, fontWeight: '800' }, uploadSub: { color: COLORS.muted, fontSize: 9, marginTop: 3 },
    uploadButton: { backgroundColor: COLORS.teal, borderRadius: 9, paddingHorizontal: 14, paddingVertical: 9 }, uploadButtonText: { color: '#FFF', fontSize: 11, fontWeight: '900' },
    progressTrack: { height: 4, borderRadius: 2, backgroundColor: '#CFE5E3', overflow: 'hidden', marginTop: 6 }, progressFill: { height: '100%', backgroundColor: COLORS.teal },
    calibrationRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: '#EDF1F2' },
    calibrationLabel: { color: COLORS.muted, fontSize: 10 },
    calibrationValue: { flex: 1, color: COLORS.text, fontSize: 10, fontWeight: '800', textAlign: 'right' },
  });
  const [status, setStatus] = useState<MonitorStatus>({});
  const [logs, setLogs] = useState<MonitorLog[]>([]);
  const [source, setSource] = useState<SourceType>('uploaded_video');
  const [profiles, setProfiles] = useState<CalibrationProfile[]>([]);
  const [calibrationProfile, setCalibrationProfile] = useState('');
  const [host, setHost] = useState(''); const [username, setUsername] = useState(''); const [password, setPassword] = useState(''); const [stream, setStream] = useState('stream2');
  const [hasSavedCameraPassword, setHasSavedCameraPassword] = useState(false);
  const [busy, setBusy] = useState(false); const [refreshing, setRefreshing] = useState(false); const [frameAvailable, setFrameAvailable] = useState(false);
  const [frameSlots, setFrameSlots] = useState<[string, string]>(['', '']);
  const [activeFrameSlot, setActiveFrameSlot] = useState(0);
  const activeFrameSlotRef = useRef(0);
  const [uploading, setUploading] = useState(false); const [uploadProgress, setUploadProgress] = useState(0); const [uploadedName, setUploadedName] = useState('');
  const mounted = useRef(true);
  const running = ['running', 'starting'].includes(String(status.analysis_status || status.ai_status || '').toLowerCase()) && status.stream_owner === 'mobile';

  const load = useCallback(async () => {
    try {
      const [statusRes, logsRes] = await Promise.all([mlApi.get('get_status.php'), mlApi.get('get_monitoring_logs.php')]);
      if (mounted.current) { setStatus(statusRes.data || {}); setLogs(logsRes.data?.logs || []); }
    } catch { if (mounted.current) setStatus(current => ({ ...current, ai_status: 'Offline', message: 'Monitoring service is unreachable.' })); }
    finally { if (mounted.current) setRefreshing(false); }
  }, []);

  useEffect(() => { mounted.current = true; load(); const statusTimer = setInterval(load, 3000); return () => { mounted.current = false; clearInterval(statusTimer); }; }, [load]);
  useEffect(() => {
    api.get('get_calibration_profiles.php').then(response => {
      const available = response.data?.data || [];
      if (!mounted.current) return;
      setProfiles(available);
      setCalibrationProfile(current => current || available[0]?.file || '');
    }).catch(() => Alert.alert('Calibration unavailable', 'Intersection configurations could not be loaded.'));
  }, []);
  useEffect(() => {
    mlApi.get('get_camera_config.php').then(response => {
      const saved = response.data?.data;
      if (!saved || !mounted.current) return;
      setHost(saved.host || '');
      setUsername(saved.username || '');
      setStream(saved.stream === 'stream1' ? 'stream1' : 'stream2');
      setHasSavedCameraPassword(Boolean(saved.has_saved_password));
    }).catch(() => undefined);
  }, []);
  useEffect(() => {
    if (!running) {
      setFrameAvailable(false);
      setFrameSlots(['', '']);
      activeFrameSlotRef.current = 0;
      setActiveFrameSlot(0);
      return;
    }

    const requestFrame = () => {
      const nextSlot = activeFrameSlotRef.current === 0 ? 1 : 0;
      const nextUrl = `${MOBILE_SNAPSHOT_URL}&t=${Date.now()}`;
      setFrameSlots(current => {
        const updated: [string, string] = [...current];
        updated[nextSlot] = nextUrl;
        return updated;
      });
    };

    requestFrame();
    const frameTimer = setInterval(requestFrame, 200);
    return () => clearInterval(frameTimer);
  }, [running]);

  const showLoadedFrame = (slot: number) => {
    if (slot === activeFrameSlotRef.current && frameAvailable) return;
    activeFrameSlotRef.current = slot;
    setActiveFrameSlot(slot);
    setFrameAvailable(true);
  };

  const uploadVideo = async () => {
    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) { Alert.alert('Permission required', 'Allow photo and video access to select CCTV footage.'); return; }
    const result = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['videos'], quality: 1 });
    if (result.canceled || !result.assets[0]) return;
    const asset = result.assets[0];
    if (asset.fileSize && asset.fileSize > 500 * 1024 * 1024) { Alert.alert('Video too large', 'Select a video that is 500 MB or smaller.'); return; }
    const body = new FormData();
    body.append('video', { uri: asset.uri, name: asset.fileName || 'monitoring.mp4', type: asset.mimeType || 'video/mp4' } as any);
    setUploading(true); setUploadProgress(0);
    try {
      const response = await api.post('upload_monitoring_video.php', body, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 10 * 60 * 1000, onUploadProgress: event => { if (event.total) setUploadProgress(Math.round(event.loaded / event.total * 100)); } });
      setUploadedName(response.data.filename || asset.fileName || 'Selected video');
      Alert.alert('Upload complete', 'The video is ready for AI analysis.');
    } catch (error: any) { Alert.alert('Upload failed', error.response?.data?.error || 'The video could not be uploaded.'); }
    finally { setUploading(false); }
  };

  const start = async () => {
    if (source === 'tapo_camera' && (!host.trim() || !username.trim() || (!password && !hasSavedCameraPassword))) { Alert.alert('Camera details required', 'Enter the Tapo camera IP, camera username, and password.'); return; }
    setBusy(true);
    try {
      const payload: any = { source_type: source, client: 'mobile', calibration_profile: calibrationProfile };
      if (source === 'tapo_camera') Object.assign(payload, { tapo_host: host.trim(), tapo_username: username.trim(), tapo_password: password, tapo_stream: stream });
      const response = await mlApi.post('start_analysis.php', payload);
      if (!response.data.success) throw new Error(response.data.message || 'Unable to start analysis.');
      setStatus(current => ({ ...current, analysis_status: 'Starting', ai_status: 'Starting', message: response.data.message }));
      setTimeout(load, 1800);
    } catch (error: any) { Alert.alert('Start failed', error.response?.data?.message || error.message || 'Unable to start monitoring.'); }
    finally { setBusy(false); }
  };
  const stop = () => Alert.alert('Stop Monitoring', 'Stop the active AI analysis?', [{ text: 'Keep Running', style: 'cancel' }, { text: 'Stop', style: 'destructive', onPress: async () => { setBusy(true); try { await mlApi.post('stop_analysis.php', { client: 'mobile' }); setFrameAvailable(false); await load(); } catch { Alert.alert('Stop failed', 'The monitoring service could not be stopped.'); } finally { setBusy(false); } } }]);

  const tone = (value?: string) => { const v = String(value || '').toLowerCase(); if (['running', 'online', 'low', 'none', 'normal', 'present'].includes(v)) return COLORS.green; if (['heavy', 'severe', 'critical', 'alert', 'yes'].includes(v)) return COLORS.red; if (['moderate', 'starting', 'warning'].includes(v)) return COLORS.amber; return COLORS.muted; };
  const metric = (label: string, value: string | number, icon: any, color = COLORS.teal) => <View style={styles.metric}><Ionicons name={icon} size={19} color={color} /><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricLabel}>{label}</Text></View>;
  const calibrationCard = <View style={styles.card}>
    <Text style={styles.label}>Intersection configuration</Text>
    <View style={styles.pickerWrap}><Picker selectedValue={calibrationProfile} onValueChange={setCalibrationProfile} enabled={!running && !busy && profiles.length > 0}>{profiles.length === 0 ? <Picker.Item label="No configurations available" value="" /> : profiles.map(profile => <Picker.Item key={profile.file} label={profile.name} value={profile.file} />)}</Picker></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Congestion bands</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `Light ≤ ${status.runtime_settings.congestion_light_max} · Heavy ≥ ${status.runtime_settings.congestion_heavy_min}` : 'Loaded when analysis starts'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Confidence</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `${Math.round(status.runtime_settings.confidence_threshold * 100)}%` : '—'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Officer / Collision</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `${status.runtime_settings.enable_officer_detection ? 'On' : 'Off'} / ${status.runtime_settings.enable_collision_detection ? 'On' : 'Off'}` : '—'}</Text></View>
  </View>;

  return <ScrollView style={styles.screen} contentContainerStyle={styles.page} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}>
    <View style={styles.hero}><View style={{ flex: 1 }}><Text style={styles.eyebrow}>AI TRAFFIC OPERATIONS</Text><Text style={styles.title}>Live Monitoring</Text><Text style={styles.heroSub}>{status.message || 'Start a source to begin AI analysis.'}</Text></View><View style={[styles.statusPill, { borderColor: tone(status.analysis_status || status.ai_status) }]}><View style={[styles.dot, { backgroundColor: tone(status.analysis_status || status.ai_status) }]} /><Text style={styles.statusText}>{status.analysis_status || status.ai_status || 'Idle'}</Text></View></View>
    <Text style={styles.sectionTitle}>ACTIVE CALIBRATION</Text>{calibrationCard}

    <View style={styles.videoCard}><View style={styles.videoStage}>{running && frameSlots.map((uri, slot) => uri ? <Image key={slot} source={{ uri }} style={[styles.video, { opacity: activeFrameSlot === slot ? 1 : 0 }]} resizeMode="contain" fadeDuration={0} onLoad={() => showLoadedFrame(slot)} /> : null)}{(!running || !frameAvailable) && <View pointerEvents="none" style={styles.videoEmpty}><Ionicons name="videocam-outline" size={38} color="#78909C" /><Text style={styles.emptyTitle}>{running ? 'Connecting to AI stream…' : 'Stream is offline'}</Text><Text style={styles.emptySub}>The processed camera feed appears after analysis begins.</Text></View>}<View pointerEvents="none" style={styles.liveBadge}><View style={[styles.dot, { backgroundColor: running && frameAvailable ? COLORS.red : COLORS.muted }]} /><Text style={styles.liveText}>{running && frameAvailable ? 'LIVE AI' : 'OFFLINE'}</Text></View></View></View>

    <View style={styles.metricGrid}>{metric('Vehicles', status.vehicle_count || 0, 'car-outline')}{metric('Inbound', status.inbound_count || 0, 'arrow-down-outline', COLORS.green)}{metric('Outbound', status.outbound_count || 0, 'arrow-up-outline', '#2563EB')}{metric('Congestion', status.congestion_level || 'Unknown', 'speedometer-outline', tone(status.congestion_level))}</View>
    <View style={styles.signalRow}><View style={styles.signal}><Text style={styles.signalLabel}>Officer presence</Text><Text style={[styles.signalValue, { color: tone(status.officer_presence) }]}>{status.officer_presence || 'Unknown'}</Text></View><View style={styles.signal}><Text style={styles.signalLabel}>Collision risk</Text><Text style={[styles.signalValue, { color: tone(status.potential_collision) }]}>{status.potential_collision || 'None'}</Text></View></View>

    <Text style={styles.sectionTitle}>MONITORING SOURCE</Text><View style={styles.card}><Text style={styles.label}>Video source</Text><View style={styles.pickerWrap}><Picker selectedValue={source} onValueChange={setSource} enabled={!running && !busy}><Picker.Item label="Uploaded CCTV video" value="uploaded_video" /><Picker.Item label="Tapo camera (RTSP)" value="tapo_camera" /></Picker></View>{source === 'uploaded_video' && <View style={styles.uploadPanel}><Ionicons name="cloud-upload-outline" size={25} color={COLORS.teal} /><View style={{ flex: 1 }}><Text style={styles.uploadTitle}>{uploadedName || 'Select CCTV footage'}</Text><Text style={styles.uploadSub}>{uploading ? `Uploading… ${uploadProgress}%` : 'MP4, AVI, MOV, or MKV · up to 500 MB'}</Text>{uploading && <View style={styles.progressTrack}><View style={[styles.progressFill, { width: `${uploadProgress}%` }]} /></View>}</View><TouchableOpacity style={styles.uploadButton} onPress={uploadVideo} disabled={uploading || running}><Text style={styles.uploadButtonText}>{uploading ? 'Wait' : 'Upload'}</Text></TouchableOpacity></View>}{source === 'tapo_camera' && <><Text style={styles.label}>Camera IP address</Text><TextInput value={host} onChangeText={setHost} style={styles.input} placeholder="192.168.1.100" keyboardType="numeric" editable={!running} /><Text style={styles.label}>Camera username</Text><TextInput value={username} onChangeText={setUsername} style={styles.input} placeholder="Camera account username" autoCapitalize="none" editable={!running} /><Text style={styles.label}>Camera password</Text><TextInput value={password} onChangeText={setPassword} style={styles.input} placeholder="Camera password" secureTextEntry editable={!running} /><Text style={styles.label}>Stream quality</Text><View style={styles.pickerWrap}><Picker selectedValue={stream} onValueChange={setStream} enabled={!running}><Picker.Item label="Standard quality" value="stream2" /><Picker.Item label="High quality" value="stream1" /></Picker></View></>}
      <View style={styles.actions}><TouchableOpacity style={[styles.startButton, (running || busy) && styles.disabled]} disabled={running || busy} onPress={start}>{busy && !running ? <ActivityIndicator color="#FFF" /> : <><Ionicons name="play" size={17} color="#FFF" /><Text style={styles.buttonText}>Start Analysis</Text></>}</TouchableOpacity><TouchableOpacity style={[styles.stopButton, (!running || busy) && styles.disabled]} disabled={!running || busy} onPress={stop}><Ionicons name="stop" size={17} color="#FFF" /><Text style={styles.buttonText}>Stop</Text></TouchableOpacity></View>
    </View>

    <Text style={styles.sectionTitle}>RECENT MONITORING EVENTS</Text><View style={styles.card}>{logs.length === 0 ? <Text style={styles.noLogs}>No monitoring events recorded.</Text> : logs.map((log, index) => <View key={`${log.recorded_at}-${index}`} style={styles.logRow}><View style={[styles.logIcon, { backgroundColor: tone(log.congestion_level) + '1A' }]}><Ionicons name={log.alert_generated ? 'warning-outline' : 'pulse-outline'} size={17} color={tone(log.alert_generated ? 'alert' : log.congestion_level)} /></View><View style={{ flex: 1 }}><Text style={styles.logTitle}>{log.vehicle_count} vehicles · {log.congestion_level} congestion</Text><Text style={styles.logMeta}>{log.inbound_count} inbound · {log.outbound_count} outbound · {log.recorded_at}</Text></View></View>)}</View>
  </ScrollView>;
}

const styles = StyleSheet.create({ screen: { flex: 1, backgroundColor: COLORS.bg }, page: { padding: 16, paddingBottom: 40 }, hero: { flexDirection: 'row', alignItems: 'flex-start', backgroundColor: COLORS.navy, borderRadius: 18, padding: 18, marginBottom: 14 }, eyebrow: { color: '#4FC3F7', fontSize: 9, fontWeight: '900', letterSpacing: 1 }, title: { color: '#FFF', fontSize: 24, fontWeight: '900', marginTop: 4 }, heroSub: { color: '#B9CAD8', fontSize: 11, lineHeight: 16, marginTop: 6, paddingRight: 8 }, statusPill: { flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 6 }, dot: { width: 7, height: 7, borderRadius: 4 }, statusText: { color: '#FFF', fontSize: 10, fontWeight: '800' }, videoCard: { backgroundColor: COLORS.card, borderRadius: 18, padding: 8, borderWidth: 1, borderColor: COLORS.border }, videoStage: { aspectRatio: 16 / 9, backgroundColor: '#07131F', borderRadius: 13, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' }, video: { ...StyleSheet.absoluteFillObject, width: '100%', height: '100%' }, videoEmpty: { alignItems: 'center', padding: 20 }, emptyTitle: { color: '#D5E1E8', fontWeight: '800', marginTop: 8 }, emptySub: { color: '#78909C', fontSize: 10, textAlign: 'center', marginTop: 4 }, liveBadge: { position: 'absolute', top: 10, left: 10, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(0,0,0,.7)', borderRadius: 8, paddingHorizontal: 8, paddingVertical: 5 }, liveText: { color: '#FFF', fontSize: 9, fontWeight: '900' }, metricGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9, marginTop: 12 }, metric: { width: '48.5%', backgroundColor: COLORS.card, borderRadius: 14, padding: 13, borderWidth: 1, borderColor: COLORS.border }, metricValue: { color: COLORS.text, fontSize: 18, fontWeight: '900', marginTop: 8, textTransform: 'capitalize' }, metricLabel: { color: COLORS.muted, fontSize: 10, marginTop: 3 }, signalRow: { flexDirection: 'row', gap: 9, marginTop: 9 }, signal: { flex: 1, backgroundColor: COLORS.card, borderRadius: 12, padding: 12, borderWidth: 1, borderColor: COLORS.border }, signalLabel: { color: COLORS.muted, fontSize: 10 }, signalValue: { fontWeight: '900', marginTop: 4, textTransform: 'capitalize' }, sectionTitle: { color: COLORS.muted, fontSize: 10, fontWeight: '900', letterSpacing: 1, marginTop: 20, marginBottom: 9 }, card: { backgroundColor: COLORS.card, borderRadius: 16, padding: 15, borderWidth: 1, borderColor: COLORS.border }, label: { color: COLORS.muted, fontSize: 10, fontWeight: '800', marginBottom: 5, marginTop: 10 }, pickerWrap: { height: 48, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, overflow: 'hidden', justifyContent: 'center', backgroundColor: '#F8FAFB' }, input: { height: 45, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, paddingHorizontal: 12, color: COLORS.text, backgroundColor: '#F8FAFB' }, actions: { flexDirection: 'row', gap: 9, marginTop: 16 }, startButton: { flex: 1, height: 46, borderRadius: 10, backgroundColor: COLORS.teal, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 }, stopButton: { width: 105, height: 46, borderRadius: 10, backgroundColor: COLORS.red, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 }, disabled: { opacity: .42 }, buttonText: { color: '#FFF', fontWeight: '900', fontSize: 12 }, noLogs: { color: COLORS.muted, textAlign: 'center', padding: 18 }, logRow: { flexDirection: 'row', gap: 10, alignItems: 'center', paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: '#EDF1F2' }, logIcon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' }, logTitle: { color: COLORS.text, fontSize: 12, fontWeight: '800', textTransform: 'capitalize' }, logMeta: { color: COLORS.muted, fontSize: 9, marginTop: 4 } });
