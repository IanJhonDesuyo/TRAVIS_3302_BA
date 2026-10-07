// @ts-nocheck -- this screen extends its StyleSheet at runtime for the upload panel.
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, Image, Modal, RefreshControl, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Picker } from '@react-native-picker/picker';
import { Image as ExpoImage } from 'expo-image';
import Svg, { Circle, Line, Polygon } from 'react-native-svg';
import api, { MOBILE_SNAPSHOT_URL, mlApi } from '../../api/axiosConfig';
import * as ImagePicker from 'expo-image-picker';
import AsyncStorage from '@react-native-async-storage/async-storage';

type SourceType = 'uploaded_video' | 'tapo_camera';
type MonitorStatus = {
  analysis_status?: string; ai_status?: string; message?: string; vehicle_count?: number;
  inbound_count?: number; outbound_count?: number; congestion_level?: string;
  officer_presence?: string; potential_collision?: string; alert_status?: string; recorded_at?: string;
  stream_owner?: string; calibration_profile?: string;
  runtime_settings?: { congestion_light_max: number; congestion_heavy_min: number; confidence_threshold: number; enable_officer_detection: boolean; enable_collision_detection: boolean; notify_congestion: boolean; notify_collision: boolean; notify_officer_absence: boolean; officer_absence_seconds: number; alert_cooldown_seconds: number; enforcer_schedule_enabled: boolean; enforcer_duty_start: string; enforcer_duty_end: string; enforcer_break_start: string; enforcer_break_end: string; enforcer_duty_active: boolean };
};
type MonitorLog = { recorded_at: string; vehicle_count: number; inbound_count: number; outbound_count: number; congestion_level: string; alert_generated: number };
type CalibrationProfile = { file: string; name: string };
type CalibrationPoint = [number, number];
type CalibrationTool = 'inbound' | 'outbound' | 'officer' | null;
const COLORS = { navy: '#0A1A30', teal: '#087D78', green: '#15966F', amber: '#EB941F', red: '#C84B45', bg: '#F3F6F7', card: '#FFFFFF', text: '#10202C', muted: '#64748B', border: '#DDE5E7' };
const JOINED_FEED_KEY = 'travis_mobile_feed_joined';

export default function MonitoringScreen() {
  Object.assign(styles, {
    uploadPanel: { flexDirection: 'row', alignItems: 'center', gap: 11, padding: 13, marginTop: 12, borderRadius: 12, backgroundColor: '#ECF7F6', borderWidth: 1, borderColor: '#B9DDDA' },
    uploadTitle: { color: COLORS.text, fontSize: 12, fontWeight: '800' }, uploadSub: { color: COLORS.muted, fontSize: 9, marginTop: 3 },
    uploadButton: { backgroundColor: COLORS.teal, borderRadius: 9, paddingHorizontal: 14, paddingVertical: 9 }, uploadButtonText: { color: '#FFF', fontSize: 11, fontWeight: '900' },
    progressTrack: { height: 4, borderRadius: 2, backgroundColor: '#CFE5E3', overflow: 'hidden', marginTop: 6 }, progressFill: { height: '100%', backgroundColor: COLORS.teal },
    calibrationRow: { flexDirection: 'row', justifyContent: 'space-between', gap: 12, paddingVertical: 9, borderBottomWidth: 1, borderBottomColor: '#EDF1F2' },
    calibrationLabel: { color: COLORS.muted, fontSize: 10 },
    calibrationValue: { flex: 1, color: COLORS.text, fontSize: 10, fontWeight: '800', textAlign: 'right' },
    configActionRow: { flexDirection: 'row', gap: 8, marginTop: 9 },
    editConfigButton: { flex: 1, minHeight: 42, borderWidth: 1, borderColor: '#9CCECB', borderRadius: 10, backgroundColor: '#F1FAF9', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
    editConfigText: { color: COLORS.teal, fontSize: 11, fontWeight: '800' },
    deleteConfigButton: { flex: 1, minHeight: 42, borderWidth: 1, borderColor: '#E8B6B3', borderRadius: 10, backgroundColor: '#FFF7F6', flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
    deleteConfigText: { color: COLORS.red, fontSize: 11, fontWeight: '800' },
    editorScreen: { flex: 1, backgroundColor: COLORS.bg },
    editorHeader: { minHeight: 64, paddingHorizontal: 16, paddingTop: 12, paddingBottom: 10, backgroundColor: COLORS.navy, flexDirection: 'row', alignItems: 'center', gap: 12 },
    editorClose: { width: 38, height: 38, borderRadius: 19, backgroundColor: 'rgba(255,255,255,.12)', alignItems: 'center', justifyContent: 'center' },
    editorHeaderTitle: { color: '#FFF', fontSize: 17, fontWeight: '900' },
    editorHeaderSub: { color: '#B9CAD8', fontSize: 10, marginTop: 2 },
    editorBody: { padding: 16, paddingBottom: 36 },
    editorCanvas: { width: '100%', aspectRatio: 16 / 9, overflow: 'hidden', borderRadius: 12, backgroundColor: '#142433', borderWidth: 1, borderColor: '#496273', marginTop: 8 },
    editorCanvasHint: { position: 'absolute', left: 10, bottom: 8, color: '#E6F0F4', fontSize: 9, backgroundColor: 'rgba(0,0,0,.58)', paddingHorizontal: 7, paddingVertical: 4, borderRadius: 6 },
    editorInstruction: { color: COLORS.muted, fontSize: 11, lineHeight: 16, marginTop: 10 },
    toolRow: { flexDirection: 'row', gap: 7, marginTop: 12 },
    toolButton: { flex: 1, minHeight: 42, paddingHorizontal: 6, borderWidth: 1, borderColor: COLORS.border, borderRadius: 9, backgroundColor: '#FFF', alignItems: 'center', justifyContent: 'center' },
    toolButtonActive: { borderColor: COLORS.teal, backgroundColor: '#E8F6F5' },
    toolText: { color: COLORS.text, fontSize: 9, fontWeight: '800', textAlign: 'center' },
    removeZoneButton: { minHeight: 40, marginTop: 8, borderRadius: 9, borderWidth: 1, borderColor: '#D8DEE2', alignItems: 'center', justifyContent: 'center' },
    removeZoneText: { color: COLORS.muted, fontSize: 10, fontWeight: '800' },
    editorActions: { flexDirection: 'row', gap: 9, marginTop: 16 },
    editorCancelButton: { width: 105, height: 46, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, backgroundColor: '#FFF', alignItems: 'center', justifyContent: 'center' },
    editorCancelText: { color: COLORS.text, fontSize: 12, fontWeight: '800' },
    editorSaveButton: { flex: 1, height: 46, borderRadius: 10, backgroundColor: COLORS.teal, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 },
    page: { padding: 12, paddingBottom: 40 },
    videoCard: { backgroundColor: COLORS.card, borderRadius: 18, padding: 5, borderWidth: 1, borderColor: COLORS.border },
    expandButton: { position: 'absolute', top: 10, right: 10, width: 38, height: 38, borderRadius: 19, backgroundColor: 'rgba(0,0,0,.72)', alignItems: 'center', justifyContent: 'center' },
    largeScreen: { flex: 1, backgroundColor: '#000', alignItems: 'center', justifyContent: 'center' },
    largeScreenVideo: { width: '100%', height: '100%' },
    largeLiveBadge: { position: 'absolute', top: 48, left: 16, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(0,0,0,.7)', borderRadius: 8, paddingHorizontal: 9, paddingVertical: 6 },
    largeScreenClose: { position: 'absolute', top: 42, right: 16, width: 46, height: 46, borderRadius: 23, backgroundColor: 'rgba(0,0,0,.7)', alignItems: 'center', justifyContent: 'center' },
    rotateHint: { position: 'absolute', bottom: 34, color: '#D5E1E8', fontSize: 11, backgroundColor: 'rgba(0,0,0,.65)', paddingHorizontal: 12, paddingVertical: 7, borderRadius: 12 },
  });
  const [status, setStatus] = useState<MonitorStatus>({});
  const [statusLoaded, setStatusLoaded] = useState(false);
  const [logs, setLogs] = useState<MonitorLog[]>([]);
  const [source, setSource] = useState<SourceType>('uploaded_video');
  const [profiles, setProfiles] = useState<CalibrationProfile[]>([]);
  const [calibrationProfile, setCalibrationProfile] = useState('');
  const [deletingProfile, setDeletingProfile] = useState(false);
  const [loadingProfile, setLoadingProfile] = useState(false);
  const [savingProfile, setSavingProfile] = useState(false);
  const [editVisible, setEditVisible] = useState(false);
  const [editName, setEditName] = useState('');
  const [editInbound, setEditInbound] = useState<CalibrationPoint[]>([]);
  const [editOutbound, setEditOutbound] = useState<CalibrationPoint[]>([]);
  const [editOfficerZone, setEditOfficerZone] = useState<CalibrationPoint[]>([]);
  const [editTool, setEditTool] = useState<CalibrationTool>(null);
  const [editorSize, setEditorSize] = useState({ width: 1, height: 1 });
  const [editorSnapshot, setEditorSnapshot] = useState('');
  const [host, setHost] = useState(''); const [username, setUsername] = useState(''); const [password, setPassword] = useState(''); const [stream, setStream] = useState('stream2');
  const [hasSavedCameraPassword, setHasSavedCameraPassword] = useState(false);
  const [busy, setBusy] = useState(false); const [refreshing, setRefreshing] = useState(false); const [frameAvailable, setFrameAvailable] = useState(false);
  const [largeScreenVisible, setLargeScreenVisible] = useState(false);
  const [viewerJoined, setViewerJoined] = useState(false);
  const [frameUrl, setFrameUrl] = useState('');
  const [uploading, setUploading] = useState(false); const [uploadProgress, setUploadProgress] = useState(0); const [uploadedName, setUploadedName] = useState('');
  const mounted = useRef(true);
  const analysisActive = ['running', 'starting'].includes(String(status.analysis_status || status.ai_status || '').toLowerCase());
  const running = analysisActive && viewerJoined;
  const selectedProfileIsDefault = calibrationProfile.toLowerCase() === 'example.json';
  const profileActionBusy = deletingProfile || loadingProfile || savingProfile;
  const editProfileDisabled = analysisActive || busy || profileActionBusy || !calibrationProfile;
  const deleteProfileDisabled = editProfileDisabled || selectedProfileIsDefault;

  const load = useCallback(async () => {
    try {
      const [statusRes, logsRes] = await Promise.all([mlApi.get('get_status.php'), mlApi.get('get_monitoring_logs.php')]);
      if (mounted.current) { setStatus(statusRes.data || {}); setLogs(logsRes.data?.logs || []); setStatusLoaded(true); }
    } catch { if (mounted.current) { setStatus(current => ({ ...current, ai_status: 'Offline', message: 'Monitoring service is unreachable.' })); setStatusLoaded(true); } }
    finally { if (mounted.current) setRefreshing(false); }
  }, []);

  useEffect(() => { mounted.current = true; load(); const statusTimer = setInterval(load, 3000); return () => { mounted.current = false; clearInterval(statusTimer); }; }, [load]);
  useEffect(() => {
    AsyncStorage.getItem(JOINED_FEED_KEY)
      .then(value => { if (mounted.current) setViewerJoined(value === '1'); })
      .catch(() => undefined);
  }, []);
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
    if (statusLoaded && !analysisActive) {
      setViewerJoined(false);
      AsyncStorage.removeItem(JOINED_FEED_KEY).catch(() => undefined);
      setFrameAvailable(false);
      setFrameUrl('');
      setLargeScreenVisible(false);
    }
  }, [analysisActive, statusLoaded]);

  useEffect(() => {
    if (!running) {
      setFrameAvailable(false);
      setFrameUrl('');
      return;
    }

    let cancelled = false;
    let timer: ReturnType<typeof setTimeout> | null = null;

    const loadFrame = async () => {
      const nextUrl = `${MOBILE_SNAPSHOT_URL}&t=${Date.now()}`;
      try {
        const loaded = await ExpoImage.prefetch(nextUrl, { cachePolicy: 'memory' });
        if (!cancelled && loaded) {
          setFrameUrl(nextUrl);
          setFrameAvailable(true);
        }
      } catch {
        // Keep the last complete frame visible during a temporary network miss.
      } finally {
        if (!cancelled) timer = setTimeout(loadFrame, 30);
      }
    };

    loadFrame();
    return () => {
      cancelled = true;
      if (timer) clearTimeout(timer);
    };
  }, [running]);

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
    if (analysisActive) {
      setViewerJoined(true);
      await AsyncStorage.setItem(JOINED_FEED_KEY, '1');
      setFrameAvailable(false);
      return;
    }
    if (source === 'tapo_camera' && (!host.trim() || !username.trim() || (!password && !hasSavedCameraPassword))) { Alert.alert('Camera details required', 'Enter the Tapo camera IP, camera username, and password.'); return; }
    setBusy(true);
    try {
      const payload: any = { source_type: source, client: 'mobile', calibration_profile: calibrationProfile };
      if (source === 'tapo_camera') Object.assign(payload, { tapo_host: host.trim(), tapo_username: username.trim(), tapo_password: password, tapo_stream: stream });
      const response = await mlApi.post('start_analysis.php', payload);
      if (!response.data.success) throw new Error(response.data.message || 'Unable to start analysis.');
      setViewerJoined(true);
      await AsyncStorage.setItem(JOINED_FEED_KEY, '1');
      setStatus(current => ({ ...current, analysis_status: 'Starting', ai_status: 'Starting', message: response.data.message }));
      setTimeout(load, 1800);
    } catch (error: any) { Alert.alert('Start failed', error.response?.data?.message || error.message || 'Unable to start monitoring.'); }
    finally { setBusy(false); }
  };
  const stop = () => Alert.alert(
    'Leave Live Feed',
    'Stop viewing on this mobile device? The shared analysis and web feed will continue running.',
    [
      { text: 'Keep Viewing', style: 'cancel' },
      {
        text: 'Leave Feed',
        onPress: () => {
          setViewerJoined(false);
          AsyncStorage.removeItem(JOINED_FEED_KEY).catch(() => undefined);
          setFrameAvailable(false);
          setFrameUrl('');
          setLargeScreenVisible(false);
        },
      },
    ]
  );
  const editProfile = async () => {
    if (!calibrationProfile || analysisActive) return;
    setLoadingProfile(true);
    try {
      const response = await api.get('get_calibration_profiles.php', { params: { file: calibrationProfile } });
      const profile = response.data?.data;
      const validPoints = (points: unknown, expected: number) => Array.isArray(points)
        && points.length === expected
        && points.every(point => Array.isArray(point) && point.length === 2 && point.every(value => Number.isFinite(Number(value))));
      if (!profile || !validPoints(profile.inbound_line, 2) || !validPoints(profile.outbound_line, 2)) {
        throw new Error('This configuration does not contain valid counting lines.');
      }
      setEditName(String(profile.name || ''));
      setEditInbound(profile.inbound_line.map((point: unknown[]) => point.map(Number) as CalibrationPoint));
      setEditOutbound(profile.outbound_line.map((point: unknown[]) => point.map(Number) as CalibrationPoint));
      setEditOfficerZone(validPoints(profile.officer_zone, 4)
        ? profile.officer_zone.map((point: unknown[]) => point.map(Number) as CalibrationPoint)
        : []);
      setEditTool(null);
      setEditorSnapshot(`${MOBILE_SNAPSHOT_URL}&t=${Date.now()}`);
      setEditVisible(true);
    } catch (error: any) {
      Alert.alert('Edit unavailable', error.response?.data?.error || error.message || 'The configuration could not be loaded.');
    } finally {
      setLoadingProfile(false);
    }
  };
  const chooseEditTool = (tool: Exclude<CalibrationTool, null>) => {
    setEditTool(tool);
    if (tool === 'inbound') setEditInbound([]);
    if (tool === 'outbound') setEditOutbound([]);
    if (tool === 'officer') setEditOfficerZone([]);
  };
  const addEditorPoint = (event: any) => {
    if (!editTool || editorSize.width <= 1 || editorSize.height <= 1) return;
    const point: CalibrationPoint = [
      Math.max(0, Math.min(1, event.nativeEvent.locationX / editorSize.width)),
      Math.max(0, Math.min(1, event.nativeEvent.locationY / editorSize.height)),
    ];
    if (editTool === 'inbound' && editInbound.length < 2) {
      const next = [...editInbound, point]; setEditInbound(next); if (next.length === 2) setEditTool(null);
    } else if (editTool === 'outbound' && editOutbound.length < 2) {
      const next = [...editOutbound, point]; setEditOutbound(next); if (next.length === 2) setEditTool(null);
    } else if (editTool === 'officer' && editOfficerZone.length < 4) {
      const next = [...editOfficerZone, point]; setEditOfficerZone(next); if (next.length === 4) setEditTool(null);
    }
  };
  const saveEditedProfile = async () => {
    if (!editName.trim() || editInbound.length !== 2 || editOutbound.length !== 2) {
      Alert.alert('Incomplete configuration', 'Enter a name and draw both counting lines.');
      return;
    }
    setSavingProfile(true);
    try {
      const response = await api.put('get_calibration_profiles.php', {
        file: calibrationProfile,
        profile_name: editName.trim(),
        inbound_line: editInbound,
        outbound_line: editOutbound,
        officer_zone: editOfficerZone,
      });
      setProfiles(current => current.map(profile => profile.file === calibrationProfile ? { ...profile, name: editName.trim() } : profile));
      setEditVisible(false);
      Alert.alert('Configuration updated', response.data?.message || 'Your changes were saved.');
    } catch (error: any) {
      Alert.alert('Save failed', error.response?.data?.error || 'The configuration could not be updated.');
    } finally {
      setSavingProfile(false);
    }
  };
  const deleteProfile = () => {
    const selected = profiles.find(profile => profile.file === calibrationProfile);
    if (!selected || selectedProfileIsDefault) return;
    if (analysisActive) {
      Alert.alert('Stop monitoring first', 'Stop the active analysis before deleting an intersection configuration.');
      return;
    }

    Alert.alert(
      'Delete configuration?',
      `Remove "${selected.name}" from the available configurations? A recovery copy will be archived.`,
      [
        { text: 'Cancel', style: 'cancel' },
        { text: 'Delete', style: 'destructive', onPress: async () => {
          setDeletingProfile(true);
          try {
            const response = await api.delete('get_calibration_profiles.php', { data: { file: selected.file } });
            const remaining = profiles.filter(profile => profile.file !== selected.file);
            setProfiles(remaining);
            setCalibrationProfile(remaining.find(profile => profile.file.toLowerCase() === 'example.json')?.file || remaining[0]?.file || '');
            Alert.alert('Configuration deleted', response.data?.message || `${selected.name} was removed.`);
          } catch (error: any) {
            Alert.alert('Delete failed', error.response?.data?.error || 'The configuration could not be deleted.');
          } finally {
            setDeletingProfile(false);
          }
        } },
      ]
    );
  };

  const tone = (value?: string) => { const v = String(value || '').toLowerCase(); if (['running', 'online', 'low', 'none', 'normal', 'present'].includes(v)) return COLORS.green; if (['heavy', 'severe', 'critical', 'alert', 'yes'].includes(v)) return COLORS.red; if (['moderate', 'starting', 'warning'].includes(v)) return COLORS.amber; return COLORS.muted; };
  const metric = (label: string, value: string | number, icon: any, color = COLORS.teal) => <View style={styles.metric}><Ionicons name={icon} size={19} color={color} /><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricLabel}>{label}</Text></View>;
  const editorInstruction = editTool === 'inbound'
    ? `Tap ${2 - editInbound.length} more point${2 - editInbound.length === 1 ? '' : 's'} for the inbound line.`
    : editTool === 'outbound'
      ? `Tap ${2 - editOutbound.length} more point${2 - editOutbound.length === 1 ? '' : 's'} for the outbound line.`
      : editTool === 'officer'
        ? `Tap ${4 - editOfficerZone.length} more corner${4 - editOfficerZone.length === 1 ? '' : 's'} for the enforcer zone.`
        : 'Choose a tool to redraw it, or save the currently displayed lines.';
  const calibrationCard = <View style={styles.card}>
    <Text style={styles.label}>Intersection configuration</Text>
    <View style={styles.pickerWrap}><Picker selectedValue={calibrationProfile} onValueChange={setCalibrationProfile} enabled={!analysisActive && !busy && profiles.length > 0}>{profiles.length === 0 ? <Picker.Item label="No configurations available" value="" /> : profiles.map(profile => <Picker.Item key={profile.file} label={profile.name} value={profile.file} />)}</Picker></View>
    <View style={styles.configActionRow}>
      <TouchableOpacity style={[styles.editConfigButton, editProfileDisabled && styles.disabled]} onPress={editProfile} disabled={editProfileDisabled} accessibilityRole="button" accessibilityLabel="Edit selected intersection configuration"><Ionicons name="create-outline" size={16} color={COLORS.teal} />{loadingProfile ? <ActivityIndicator size="small" color={COLORS.teal} /> : <Text style={styles.editConfigText}>Edit</Text>}</TouchableOpacity>
      <TouchableOpacity style={[styles.deleteConfigButton, deleteProfileDisabled && styles.disabled]} onPress={deleteProfile} disabled={deleteProfileDisabled} accessibilityRole="button" accessibilityLabel="Delete selected intersection configuration"><Ionicons name={selectedProfileIsDefault ? 'lock-closed-outline' : 'trash-outline'} size={16} color={COLORS.red} />{deletingProfile ? <ActivityIndicator size="small" color={COLORS.red} /> : <Text style={styles.deleteConfigText}>{selectedProfileIsDefault ? 'Protected' : 'Delete'}</Text>}</TouchableOpacity>
    </View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Congestion bands</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `Light ≤ ${status.runtime_settings.congestion_light_max} · Heavy ≥ ${status.runtime_settings.congestion_heavy_min}` : 'Loaded when analysis starts'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Confidence</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `${Math.round(status.runtime_settings.confidence_threshold * 100)}%` : '—'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Officer / Collision</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `${status.runtime_settings.enable_officer_detection ? 'On' : 'Off'} / ${status.runtime_settings.enable_collision_detection ? 'On' : 'Off'}` : '—'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Duty status</Text><Text style={[styles.calibrationValue, { color: status.runtime_settings?.enforcer_duty_active ? COLORS.green : COLORS.muted }]}>{status.runtime_settings ? (status.runtime_settings.enforcer_schedule_enabled ? (status.runtime_settings.enforcer_duty_active ? 'Active duty' : 'Off duty / break') : 'Schedule unrestricted') : '—'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Duty / Break</Text><Text style={styles.calibrationValue}>{status.runtime_settings?.enforcer_schedule_enabled ? `${status.runtime_settings.enforcer_duty_start}–${status.runtime_settings.enforcer_duty_end} · ${status.runtime_settings.enforcer_break_start}–${status.runtime_settings.enforcer_break_end}` : 'Not restricted'}</Text></View>
    <View style={styles.calibrationRow}><Text style={styles.calibrationLabel}>Alert notifications</Text><Text style={styles.calibrationValue}>{status.runtime_settings ? `${status.runtime_settings.notify_congestion ? 'Congestion' : '—'} / ${status.runtime_settings.notify_collision ? 'Collision' : '—'} / ${status.runtime_settings.notify_officer_absence ? 'Officer' : '—'}` : '—'}</Text></View>
  </View>;

  return <ScrollView style={styles.screen} contentContainerStyle={styles.page} refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => { setRefreshing(true); load(); }} />}>
    <View style={styles.hero}><View style={{ flex: 1 }}><Text style={styles.eyebrow}>AI TRAFFIC OPERATIONS</Text><Text style={styles.title}>Live Monitoring</Text><Text style={styles.heroSub}>{status.message || 'Start a source to begin AI analysis.'}</Text></View><View style={[styles.statusPill, { borderColor: tone(status.analysis_status || status.ai_status) }]}><View style={[styles.dot, { backgroundColor: tone(status.analysis_status || status.ai_status) }]} /><Text style={styles.statusText}>{status.analysis_status || status.ai_status || 'Idle'}</Text></View></View>
    <Text style={styles.sectionTitle}>ACTIVE CALIBRATION</Text>{calibrationCard}

    <View style={styles.videoCard}><View style={styles.videoStage}>{running && frameUrl ? <ExpoImage source={{ uri: frameUrl }} style={styles.video} contentFit="cover" cachePolicy="memory" transition={0} recyclingKey="travis-live-feed" /> : null}{(!running || !frameAvailable) && <View pointerEvents="none" style={styles.videoEmpty}><Ionicons name="videocam-outline" size={38} color="#78909C" /><Text style={styles.emptyTitle}>{running ? 'Connecting to AI stream…' : (analysisActive ? 'Live session available' : 'Stream is offline')}</Text><Text style={styles.emptySub}>{analysisActive ? 'Tap Join Live Feed below to watch this shared session.' : 'Start an analysis to activate the processed camera feed.'}</Text></View>}<View pointerEvents="none" style={styles.liveBadge}><View style={[styles.dot, { backgroundColor: running && frameAvailable ? COLORS.red : COLORS.muted }]} /><Text style={styles.liveText}>{running && frameAvailable ? 'LIVE AI' : (analysisActive ? 'AVAILABLE' : 'OFFLINE')}</Text></View>{running && frameAvailable ? <TouchableOpacity style={styles.expandButton} onPress={() => setLargeScreenVisible(true)} accessibilityRole="button" accessibilityLabel="Open camera in large screen"><Ionicons name="expand-outline" size={21} color="#FFF" /></TouchableOpacity> : null}</View></View>

    <View style={styles.metricGrid}>{metric('Vehicles', status.vehicle_count || 0, 'car-outline')}{metric('Inbound', status.inbound_count || 0, 'arrow-down-outline', COLORS.green)}{metric('Outbound', status.outbound_count || 0, 'arrow-up-outline', '#2563EB')}{metric('Congestion', status.congestion_level || 'Unknown', 'speedometer-outline', tone(status.congestion_level))}</View>
    <View style={styles.signalRow}><View style={styles.signal}><Text style={styles.signalLabel}>Officer presence</Text><Text style={[styles.signalValue, { color: tone(status.officer_presence) }]}>{status.officer_presence || 'Unknown'}</Text></View><View style={styles.signal}><Text style={styles.signalLabel}>Collision risk</Text><Text style={[styles.signalValue, { color: tone(status.potential_collision) }]}>{status.potential_collision || 'None'}</Text></View></View>

    <Text style={styles.sectionTitle}>MONITORING SOURCE</Text><View style={styles.card}><Text style={styles.label}>Video source</Text><View style={styles.pickerWrap}><Picker selectedValue={source} onValueChange={setSource} enabled={!running && !busy}><Picker.Item label="Uploaded CCTV video" value="uploaded_video" /><Picker.Item label="Tapo camera (RTSP)" value="tapo_camera" /></Picker></View>{source === 'uploaded_video' && <View style={styles.uploadPanel}><Ionicons name="cloud-upload-outline" size={25} color={COLORS.teal} /><View style={{ flex: 1 }}><Text style={styles.uploadTitle}>{uploadedName || 'Select CCTV footage'}</Text><Text style={styles.uploadSub}>{uploading ? `Uploading… ${uploadProgress}%` : 'MP4, AVI, MOV, or MKV · up to 500 MB'}</Text>{uploading && <View style={styles.progressTrack}><View style={[styles.progressFill, { width: `${uploadProgress}%` }]} /></View>}</View><TouchableOpacity style={styles.uploadButton} onPress={uploadVideo} disabled={uploading || running}><Text style={styles.uploadButtonText}>{uploading ? 'Wait' : 'Upload'}</Text></TouchableOpacity></View>}{source === 'tapo_camera' && <><Text style={styles.label}>Camera IP address</Text><TextInput value={host} onChangeText={setHost} style={styles.input} placeholder="192.168.1.100" keyboardType="numeric" editable={!running} /><Text style={styles.label}>Camera username</Text><TextInput value={username} onChangeText={setUsername} style={styles.input} placeholder="Camera account username" autoCapitalize="none" editable={!running} /><Text style={styles.label}>Camera password</Text><TextInput value={password} onChangeText={setPassword} style={styles.input} placeholder="Camera password" secureTextEntry editable={!running} /><Text style={styles.label}>Stream quality</Text><View style={styles.pickerWrap}><Picker selectedValue={stream} onValueChange={setStream} enabled={!running}><Picker.Item label="Standard quality" value="stream2" /><Picker.Item label="High quality" value="stream1" /></Picker></View></>}
      <View style={styles.actions}><TouchableOpacity style={[styles.startButton, (running || busy) && styles.disabled]} disabled={running || busy} onPress={start}>{busy && !running ? <ActivityIndicator color="#FFF" /> : <><Ionicons name={analysisActive ? 'enter-outline' : 'play'} size={17} color="#FFF" /><Text style={styles.buttonText}>{analysisActive ? (running ? 'Feed Joined' : 'Join Live Feed') : 'Start Analysis'}</Text></>}</TouchableOpacity><TouchableOpacity style={[styles.stopButton, (!running || busy) && styles.disabled]} disabled={!running || busy} onPress={stop}><Ionicons name="exit-outline" size={17} color="#FFF" /><Text style={styles.buttonText}>Leave</Text></TouchableOpacity></View>
    </View>

    <Text style={styles.sectionTitle}>RECENT MONITORING EVENTS</Text><View style={styles.card}>{logs.length === 0 ? <Text style={styles.noLogs}>No monitoring events recorded.</Text> : logs.map((log, index) => <View key={`${log.recorded_at}-${index}`} style={styles.logRow}><View style={[styles.logIcon, { backgroundColor: tone(log.congestion_level) + '1A' }]}><Ionicons name={log.alert_generated ? 'warning-outline' : 'pulse-outline'} size={17} color={tone(log.alert_generated ? 'alert' : log.congestion_level)} /></View><View style={{ flex: 1 }}><Text style={styles.logTitle}>{log.vehicle_count} vehicles · {log.congestion_level} congestion</Text><Text style={styles.logMeta}>{log.inbound_count} inbound · {log.outbound_count} outbound · {log.recorded_at}</Text></View></View>)}</View>
    <Modal visible={largeScreenVisible} animationType="fade" presentationStyle="fullScreen" statusBarTranslucent onRequestClose={() => setLargeScreenVisible(false)}>
      <View style={styles.largeScreen}>
        {running && frameUrl ? <ExpoImage source={{ uri: frameUrl }} style={styles.largeScreenVideo} contentFit="contain" cachePolicy="memory" transition={0} recyclingKey="travis-fullscreen-feed" /> : null}
        <View pointerEvents="none" style={styles.largeLiveBadge}><View style={[styles.dot, { backgroundColor: COLORS.red }]} /><Text style={styles.liveText}>LIVE AI</Text></View>
        <TouchableOpacity style={styles.largeScreenClose} onPress={() => setLargeScreenVisible(false)} accessibilityRole="button" accessibilityLabel="Close large screen"><Ionicons name="contract-outline" size={25} color="#FFF" /></TouchableOpacity>
        <Text pointerEvents="none" style={styles.rotateHint}>Rotate your phone for the widest camera view</Text>
      </View>
    </Modal>
    <Modal visible={editVisible} animationType="slide" onRequestClose={() => { if (!savingProfile) setEditVisible(false); }}>
      <View style={styles.editorScreen}>
        <View style={styles.editorHeader}>
          <TouchableOpacity style={styles.editorClose} onPress={() => setEditVisible(false)} disabled={savingProfile} accessibilityLabel="Close configuration editor"><Ionicons name="close" size={23} color="#FFF" /></TouchableOpacity>
          <View><Text style={styles.editorHeaderTitle}>Edit Configuration</Text><Text style={styles.editorHeaderSub}>Previous version will be archived when saved</Text></View>
        </View>
        <ScrollView contentContainerStyle={styles.editorBody} keyboardShouldPersistTaps="handled">
          <Text style={[styles.label, { marginTop: 0 }]}>Configuration name</Text>
          <TextInput value={editName} onChangeText={setEditName} style={styles.input} maxLength={100} placeholder="Intersection configuration name" editable={!savingProfile} />
          <Text style={styles.label}>Line and zone placement</Text>
          <View
            style={styles.editorCanvas}
            onLayout={event => setEditorSize({ width: event.nativeEvent.layout.width, height: event.nativeEvent.layout.height })}
            onTouchEnd={addEditorPoint}
          >
            {editorSnapshot ? <Image pointerEvents="none" source={{ uri: editorSnapshot }} style={StyleSheet.absoluteFillObject} resizeMode="cover" /> : null}
            <Svg pointerEvents="none" style={StyleSheet.absoluteFillObject} width="100%" height="100%">
              {[0.25, 0.5, 0.75].map(ratio => <Line key={`vertical-${ratio}`} x1={editorSize.width * ratio} y1={0} x2={editorSize.width * ratio} y2={editorSize.height} stroke="rgba(255,255,255,.16)" strokeWidth={1} />)}
              {[0.25, 0.5, 0.75].map(ratio => <Line key={`horizontal-${ratio}`} x1={0} y1={editorSize.height * ratio} x2={editorSize.width} y2={editorSize.height * ratio} stroke="rgba(255,255,255,.16)" strokeWidth={1} />)}
              {editOfficerZone.length > 1 ? <Polygon points={editOfficerZone.map(point => `${point[0] * editorSize.width},${point[1] * editorSize.height}`).join(' ')} fill="rgba(34,211,238,.20)" stroke="#22D3EE" strokeWidth={3} /> : null}
              {editInbound.length === 2 ? <Line x1={editInbound[0][0] * editorSize.width} y1={editInbound[0][1] * editorSize.height} x2={editInbound[1][0] * editorSize.width} y2={editInbound[1][1] * editorSize.height} stroke="#22C55E" strokeWidth={4} /> : null}
              {editOutbound.length === 2 ? <Line x1={editOutbound[0][0] * editorSize.width} y1={editOutbound[0][1] * editorSize.height} x2={editOutbound[1][0] * editorSize.width} y2={editOutbound[1][1] * editorSize.height} stroke="#EF4444" strokeWidth={4} /> : null}
              {editInbound.map((point, index) => <Circle key={`inbound-${index}`} cx={point[0] * editorSize.width} cy={point[1] * editorSize.height} r={6} fill="#22C55E" />)}
              {editOutbound.map((point, index) => <Circle key={`outbound-${index}`} cx={point[0] * editorSize.width} cy={point[1] * editorSize.height} r={6} fill="#EF4444" />)}
              {editOfficerZone.map((point, index) => <Circle key={`officer-${index}`} cx={point[0] * editorSize.width} cy={point[1] * editorSize.height} r={5} fill="#22D3EE" />)}
            </Svg>
            <Text pointerEvents="none" style={styles.editorCanvasHint}>Green: inbound · Red: outbound · Blue: enforcer</Text>
          </View>
          <Text style={styles.editorInstruction}>{editorInstruction}</Text>
          <View style={styles.toolRow}>
            <TouchableOpacity style={[styles.toolButton, editTool === 'inbound' && styles.toolButtonActive]} onPress={() => chooseEditTool('inbound')} disabled={savingProfile}><Text style={styles.toolText}>Redraw Inbound</Text></TouchableOpacity>
            <TouchableOpacity style={[styles.toolButton, editTool === 'outbound' && styles.toolButtonActive]} onPress={() => chooseEditTool('outbound')} disabled={savingProfile}><Text style={styles.toolText}>Redraw Outbound</Text></TouchableOpacity>
            <TouchableOpacity style={[styles.toolButton, editTool === 'officer' && styles.toolButtonActive]} onPress={() => chooseEditTool('officer')} disabled={savingProfile}><Text style={styles.toolText}>Draw Enforcer Zone</Text></TouchableOpacity>
          </View>
          {editOfficerZone.length > 0 ? <TouchableOpacity style={styles.removeZoneButton} onPress={() => { setEditOfficerZone([]); setEditTool(null); }} disabled={savingProfile}><Text style={styles.removeZoneText}>Remove Enforcer Zone</Text></TouchableOpacity> : null}
          <View style={styles.editorActions}>
            <TouchableOpacity style={styles.editorCancelButton} onPress={() => setEditVisible(false)} disabled={savingProfile}><Text style={styles.editorCancelText}>Cancel</Text></TouchableOpacity>
            <TouchableOpacity style={[styles.editorSaveButton, (savingProfile || !editName.trim() || editInbound.length !== 2 || editOutbound.length !== 2) && styles.disabled]} onPress={saveEditedProfile} disabled={savingProfile || !editName.trim() || editInbound.length !== 2 || editOutbound.length !== 2}>{savingProfile ? <ActivityIndicator color="#FFF" /> : <><Ionicons name="save-outline" size={17} color="#FFF" /><Text style={styles.buttonText}>Save Changes</Text></>}</TouchableOpacity>
          </View>
        </ScrollView>
      </View>
    </Modal>
  </ScrollView>;
}

const styles = StyleSheet.create({ screen: { flex: 1, backgroundColor: COLORS.bg }, page: { padding: 16, paddingBottom: 40 }, hero: { flexDirection: 'row', alignItems: 'flex-start', backgroundColor: COLORS.navy, borderRadius: 18, padding: 18, marginBottom: 14 }, eyebrow: { color: '#4FC3F7', fontSize: 9, fontWeight: '900', letterSpacing: 1 }, title: { color: '#FFF', fontSize: 24, fontWeight: '900', marginTop: 4 }, heroSub: { color: '#B9CAD8', fontSize: 11, lineHeight: 16, marginTop: 6, paddingRight: 8 }, statusPill: { flexDirection: 'row', alignItems: 'center', gap: 6, borderWidth: 1, borderRadius: 20, paddingHorizontal: 10, paddingVertical: 6 }, dot: { width: 7, height: 7, borderRadius: 4 }, statusText: { color: '#FFF', fontSize: 10, fontWeight: '800' }, videoCard: { backgroundColor: COLORS.card, borderRadius: 18, padding: 8, borderWidth: 1, borderColor: COLORS.border }, videoStage: { aspectRatio: 16 / 9, backgroundColor: '#07131F', borderRadius: 13, overflow: 'hidden', alignItems: 'center', justifyContent: 'center' }, video: { ...StyleSheet.absoluteFillObject, width: '100%', height: '100%' }, videoEmpty: { alignItems: 'center', padding: 20 }, emptyTitle: { color: '#D5E1E8', fontWeight: '800', marginTop: 8 }, emptySub: { color: '#78909C', fontSize: 10, textAlign: 'center', marginTop: 4 }, liveBadge: { position: 'absolute', top: 10, left: 10, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: 'rgba(0,0,0,.7)', borderRadius: 8, paddingHorizontal: 8, paddingVertical: 5 }, liveText: { color: '#FFF', fontSize: 9, fontWeight: '900' }, metricGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 9, marginTop: 12 }, metric: { width: '48.5%', backgroundColor: COLORS.card, borderRadius: 14, padding: 13, borderWidth: 1, borderColor: COLORS.border }, metricValue: { color: COLORS.text, fontSize: 18, fontWeight: '900', marginTop: 8, textTransform: 'capitalize' }, metricLabel: { color: COLORS.muted, fontSize: 10, marginTop: 3 }, signalRow: { flexDirection: 'row', gap: 9, marginTop: 9 }, signal: { flex: 1, backgroundColor: COLORS.card, borderRadius: 12, padding: 12, borderWidth: 1, borderColor: COLORS.border }, signalLabel: { color: COLORS.muted, fontSize: 10 }, signalValue: { fontWeight: '900', marginTop: 4, textTransform: 'capitalize' }, sectionTitle: { color: COLORS.muted, fontSize: 10, fontWeight: '900', letterSpacing: 1, marginTop: 20, marginBottom: 9 }, card: { backgroundColor: COLORS.card, borderRadius: 16, padding: 15, borderWidth: 1, borderColor: COLORS.border }, label: { color: COLORS.muted, fontSize: 10, fontWeight: '800', marginBottom: 5, marginTop: 10 }, pickerWrap: { height: 48, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, overflow: 'hidden', justifyContent: 'center', backgroundColor: '#F8FAFB' }, input: { height: 45, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, paddingHorizontal: 12, color: COLORS.text, backgroundColor: '#F8FAFB' }, actions: { flexDirection: 'row', gap: 9, marginTop: 16 }, startButton: { flex: 1, height: 46, borderRadius: 10, backgroundColor: COLORS.teal, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 }, stopButton: { width: 105, height: 46, borderRadius: 10, backgroundColor: COLORS.red, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7 }, disabled: { opacity: .42 }, buttonText: { color: '#FFF', fontWeight: '900', fontSize: 12 }, noLogs: { color: COLORS.muted, textAlign: 'center', padding: 18 }, logRow: { flexDirection: 'row', gap: 10, alignItems: 'center', paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: '#EDF1F2' }, logIcon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' }, logTitle: { color: COLORS.text, fontSize: 12, fontWeight: '800', textTransform: 'capitalize' }, logMeta: { color: COLORS.muted, fontSize: 9, marginTop: 4 } });
