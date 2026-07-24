import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Switch,
  StatusBar,
  TextInput,
  Platform,
  Alert,
  ActivityIndicator,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import { Ionicons } from '@expo/vector-icons';
import api from '../../api/axiosConfig';

// ========== COLOR TOKENS ==========
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

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });
const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

// ========== HELPERS ==========
const SectionCard = ({ title, children }: { title: string; children: React.ReactNode }) => (
  <>
    <Text style={styles.sectionLabel}>{title.toUpperCase()}</Text>
    <View style={styles.panel}>{children}</View>
  </>
);

const SettingInput = ({ label, value, onChangeText, disabled = false, keyboardType = 'default' }: any) => (
  <View style={styles.settingGroup}>
    <Text style={styles.settingLabel}>{label}</Text>
    <TextInput
      style={[styles.settingInput, disabled && styles.disabledInput]}
      value={value}
      onChangeText={onChangeText}
      editable={!disabled}
      keyboardType={keyboardType}
    />
  </View>
);

// ========== SCREEN ==========
export default function SettingsScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);

  const [congestionTrigger, setCongestionTrigger] = useState('');
  const [alertCooldown, setAlertCooldown] = useState('');
  const [officerAbsence, setOfficerAbsence] = useState('');
  const [collisionStationary, setCollisionStationary] = useState('');
  const [flaskApiUrl, setFlaskApiUrl] = useState('');
  const [rtspSource, setRtspSource] = useState('');
  const [sessionTimeout, setSessionTimeout] = useState('');
  const [passwordPolicy, setPasswordPolicy] = useState('');
  const [notifications, setNotifications] = useState({ congestion: true, officer: true, collision: true });
  const [saving, setSaving] = useState(false);

  // ===== FETCH SETTINGS =====
  const fetchSettings = async () => {
    try {
      setLoading(true);
      const res = await api.get('get_settings.php');
      if (res.data.success) {
        const data = res.data.data;
        setCongestionTrigger(data.congestion_trigger || '1500');
        setAlertCooldown(data.alert_cooldown || '15');
        setOfficerAbsence(data.officer_absence || '30');
        setCollisionStationary(data.collision_stationary || '10');
        setFlaskApiUrl(data.flask_api_url || 'http://localhost:5000');
        setRtspSource(data.rtsp_source || 'rtsp://username:password@camera-ip:554/stream1');
        setSessionTimeout(data.session_timeout || '30');
        setPasswordPolicy(data.password_policy || 'Strong (12+ chars)');
        if (data.notifications) setNotifications(data.notifications);
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to load settings.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchSettings();
  }, []);

  // ===== SAVE SETTINGS =====
  const saveSettings = async () => {
    setSaving(true);
    try {
      const payload = {
        congestion_trigger: congestionTrigger,
        alert_cooldown: alertCooldown,
        officer_absence: officerAbsence,
        collision_stationary: collisionStationary,
        flask_api_url: flaskApiUrl,
        rtsp_source: rtspSource,
        session_timeout: sessionTimeout,
        password_policy: passwordPolicy,
        notifications,
      };
      const res = await api.post('update_settings.php', payload);
      if (res.data.success) {
        Alert.alert('Success', 'Settings saved.');
      } else {
        Alert.alert('Error', res.data.error || 'Failed to save.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center' }}>
          <ActivityIndicator size="large" color={COLORS.primary} />
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.header} />
      <ScrollView style={styles.container} contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        {/* Hero */}
        <View style={styles.heroCard}>
          <View style={styles.brandRow}>
            <View style={styles.brandBadge}>
              <Ionicons name="settings-outline" size={16} color="#7DB4FF" />
            </View>
            <View>
              <Text style={styles.brandName}>SYSTEM SETTINGS</Text>
              <Text style={styles.brandSubtitle}>CV thresholds, notifications, integrations, and security</Text>
            </View>
          </View>
        </View>

        {/* Traffic Thresholds */}
        <SectionCard title="Traffic Thresholds">
          <SettingInput label="Congestion Trigger (vehicles/hr)" value={congestionTrigger} onChangeText={setCongestionTrigger} keyboardType="numeric" />
          <SettingInput label="Alert Cooldown (minutes)" value={alertCooldown} onChangeText={setAlertCooldown} keyboardType="numeric" />
          <SettingInput label="Officer Absence Threshold (minutes)" value={officerAbsence} onChangeText={setOfficerAbsence} keyboardType="numeric" />
          <SettingInput label="Potential Collision Stationary Threshold (seconds)" value={collisionStationary} onChangeText={setCollisionStationary} keyboardType="numeric" />
        </SectionCard>

        {/* Computer Vision Integration */}
        <SectionCard title="Computer Vision Integration">
          <SettingInput label="Flask API URL" value={flaskApiUrl} onChangeText={setFlaskApiUrl} />
          <SettingInput label="RTSP Camera Source" value={rtspSource} onChangeText={setRtspSource} />
          <View style={styles.helperRow}>
            <Ionicons name="hardware-chip-outline" size={13} color={COLORS.textTertiary} style={{ marginRight: 6 }} />
            <Text style={styles.helperText}>
              These fields are placeholders for later integration with Tapo C210, YOLOv8, and OpenCV.
            </Text>
          </View>
        </SectionCard>

        {/* Notifications */}
        <SectionCard title="Notifications">
          <View style={styles.switchRow}>
            <View style={styles.switchLabelCol}>
              <View style={[styles.switchDot, { backgroundColor: COLORS.danger }]} />
              <Text style={styles.switchLabel}>Critical congestion alerts</Text>
            </View>
            <Switch
              value={notifications.congestion}
              onValueChange={(val) => setNotifications({ ...notifications, congestion: val })}
              trackColor={{ false: COLORS.border, true: COLORS.primary }}
              thumbColor="#fff"
            />
          </View>
          <View style={styles.switchRow}>
            <View style={styles.switchLabelCol}>
              <View style={[styles.switchDot, { backgroundColor: COLORS.warning }]} />
              <Text style={styles.switchLabel}>Officer absence alerts</Text>
            </View>
            <Switch
              value={notifications.officer}
              onValueChange={(val) => setNotifications({ ...notifications, officer: val })}
              trackColor={{ false: COLORS.border, true: COLORS.primary }}
              thumbColor="#fff"
            />
          </View>
          <View style={[styles.switchRow, { borderBottomWidth: 0 }]}>
            <View style={styles.switchLabelCol}>
              <View style={[styles.switchDot, { backgroundColor: COLORS.primary }]} />
              <Text style={styles.switchLabel}>Potential collision alerts</Text>
            </View>
            <Switch
              value={notifications.collision}
              onValueChange={(val) => setNotifications({ ...notifications, collision: val })}
              trackColor={{ false: COLORS.border, true: COLORS.primary }}
              thumbColor="#fff"
            />
          </View>
        </SectionCard>

        {/* Security */}
        <SectionCard title="Security">
          <SettingInput label="Session Timeout (minutes)" value={sessionTimeout} onChangeText={setSessionTimeout} keyboardType="numeric" />
          <View style={styles.settingGroup}>
            <Text style={styles.settingLabel}>Password Policy</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={passwordPolicy}
                onValueChange={setPasswordPolicy}
                style={styles.picker}
                dropdownIconColor={COLORS.primary}
                enabled={true}
              >
                <Picker.Item label="Strong (12+ chars)" value="Strong (12+ chars)" />
                <Picker.Item label="Standard (8+ chars)" value="Standard (8+ chars)" />
              </Picker>
            </View>
          </View>
        </SectionCard>

        {/* Save button */}
        <TouchableOpacity style={[styles.saveButton, saving && { opacity: 0.6 }]} onPress={saveSettings} disabled={saving} activeOpacity={0.85}>
          <Ionicons name="save-outline" size={16} color="#fff" style={{ marginRight: 8 }} />
          <Text style={styles.saveButtonText}>{saving ? 'Saving...' : 'Save Changes'}</Text>
        </TouchableOpacity>

        <View style={{ height: 20 }} />
      </ScrollView>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1 },
  scrollContent: { paddingHorizontal: 20, paddingTop: 18 },

  heroCard: {
    backgroundColor: COLORS.header, borderRadius: 22, padding: 20, marginBottom: 16,
    ...softShadow, shadowOpacity: 0.18,
  },
  brandRow: { flexDirection: 'row', alignItems: 'center' },
  brandBadge: {
    width: 32, height: 32, borderRadius: 10, backgroundColor: COLORS.headerAccent,
    justifyContent: 'center', alignItems: 'center', marginRight: 10,
  },
  brandName: { fontSize: 16, fontWeight: '800', color: '#FFFFFF', letterSpacing: 1 },
  brandSubtitle: { fontSize: 11, color: '#94A3B8', marginTop: 2, maxWidth: 260 },

  sectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12 },
  panel: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 20,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },

  settingGroup: { marginBottom: 14 },
  settingLabel: { fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 0.2, marginBottom: 6 },
  settingInput: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, fontSize: 14, height: 46, color: COLORS.textPrimary, fontFamily: mono,
  },
  disabledInput: { backgroundColor: '#F1F5F9', color: COLORS.textTertiary },

  helperRow: { flexDirection: 'row', alignItems: 'flex-start', marginTop: 4 },
  helperText: { flex: 1, fontSize: 12, color: COLORS.textTertiary, lineHeight: 17 },

  switchRow: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center',
    paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: COLORS.border,
  },
  switchLabelCol: { flexDirection: 'row', alignItems: 'center', flex: 1, marginRight: 8 },
  switchDot: { width: 6, height: 6, borderRadius: 3, marginRight: 10 },
  switchLabel: { fontSize: 13, color: COLORS.textPrimary, fontWeight: '500', flex: 1 },

  pickerWrapper: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    height: 46, justifyContent: 'center', overflow: 'hidden',
  },
  picker: { height: 46, width: '100%', color: COLORS.textPrimary },

  saveButton: {
    flexDirection: 'row',
    backgroundColor: COLORS.primary,
    paddingVertical: 15,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 12,
  },
  saveButtonText: { color: '#fff', fontSize: 14, fontWeight: '700' },
});