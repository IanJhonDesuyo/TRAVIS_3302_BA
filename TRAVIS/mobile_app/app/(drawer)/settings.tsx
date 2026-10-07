import React, { useState, useEffect } from 'react';
import {
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
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import DateTimePicker from '@react-native-community/datetimepicker';
import api from '../../api/axiosConfig';

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

const timeValue = (value: string) => {
  const match = /^(\d{2}):([0-5]\d)$/.exec(value);
  const date = new Date();
  date.setSeconds(0, 0);
  date.setHours(match ? Number(match[1]) : 0, match ? Number(match[2]) : 0);
  return date;
};

const formatTime12 = (value: string) => timeValue(value).toLocaleTimeString('en-PH', {
  hour: 'numeric', minute: '2-digit', hour12: true,
});

const TimeSettingInput = ({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) => {
  const [open, setOpen] = useState(false);
  return <View style={styles.settingGroup}>
    <Text style={styles.settingLabel}>{label}</Text>
    <TouchableOpacity style={styles.timeSelect} onPress={() => setOpen(true)} activeOpacity={0.75} accessibilityRole="button" accessibilityLabel={`${label}, ${formatTime12(value)}`}>
      <Ionicons name="time-outline" size={18} color={COLORS.primary} />
      <Text style={styles.timeSelectText}>{formatTime12(value)}</Text>
      <Ionicons name="chevron-down" size={16} color={COLORS.textTertiary} />
    </TouchableOpacity>
    {open && <DateTimePicker
      value={timeValue(value)}
      mode="time"
      display="default"
      is24Hour={false}
      onChange={(_, date) => {
        setOpen(false);
        if (date) onChange(`${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`);
      }}
    />}
  </View>;
};

const SettingInput = ({ label, value, onChangeText, disabled = false, keyboardType = 'default' }: any) => (
  <View style={styles.settingGroup}>
    <Text style={styles.settingLabel}>{label}</Text>
    <View style={[styles.settingInputWrap, disabled && styles.disabledInput]}>
      <TextInput
        style={styles.settingInput}
        value={value}
        onChangeText={onChangeText}
        editable={!disabled}
        keyboardType={keyboardType}
      />
    </View>
  </View>
);

// ========== SCREEN ==========
export default function SettingsScreen() {
  const [loading, setLoading] = useState(true);
  const [congestionLightMax, setCongestionLightMax] = useState('5');
  const [congestionHeavyMin, setCongestionHeavyMin] = useState('13');
  const [alertCooldownSeconds, setAlertCooldownSeconds] = useState('300');
  const [confidenceThreshold, setConfidenceThreshold] = useState('0.50');
  const [officerDetection, setOfficerDetection] = useState(true);
  const [collisionDetection, setCollisionDetection] = useState(false);
  const [notifyCongestion, setNotifyCongestion] = useState(true);
  const [notifyCollision, setNotifyCollision] = useState(true);
  const [notifyOfficerAbsence, setNotifyOfficerAbsence] = useState(true);
  const [officerAbsenceSeconds, setOfficerAbsenceSeconds] = useState('180');
  const [scheduleEnabled, setScheduleEnabled] = useState(false);
  const [dutyStart, setDutyStart] = useState('06:00');
  const [dutyEnd, setDutyEnd] = useState('18:00');
  const [breakStart, setBreakStart] = useState('12:00');
  const [breakEnd, setBreakEnd] = useState('13:00');
  const [saving, setSaving] = useState(false);

  // ===== FETCH SETTINGS =====
  const fetchSettings = async () => {
    try {
      setLoading(true);
      const res = await api.get('get_settings.php');
      if (res.data.success) {
        const data = res.data.data;
        setCongestionLightMax(String(data.congestion_light_max));
        setCongestionHeavyMin(String(data.congestion_heavy_min));
        setAlertCooldownSeconds(String(data.alert_cooldown_seconds));
        setConfidenceThreshold(Number(data.confidence_threshold).toFixed(2));
        setOfficerDetection(Boolean(data.enable_officer_detection));
        setCollisionDetection(Boolean(data.enable_collision_detection));
        setNotifyCongestion(Boolean(data.notify_congestion));
        setNotifyCollision(Boolean(data.notify_collision));
        setNotifyOfficerAbsence(Boolean(data.notify_officer_absence));
        setOfficerAbsenceSeconds(String(data.officer_absence_seconds || 180));
        setScheduleEnabled(Boolean(data.enforcer_schedule_enabled));
        setDutyStart(data.enforcer_duty_start || '06:00');
        setDutyEnd(data.enforcer_duty_end || '18:00');
        setBreakStart(data.enforcer_break_start || '12:00');
        setBreakEnd(data.enforcer_break_end || '13:00');
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
    const lightMax = Number(congestionLightMax);
    const heavyMin = Number(congestionHeavyMin);
    const cooldown = Number(alertCooldownSeconds);
    const confidence = Number(confidenceThreshold);
    const absenceSeconds = Number(officerAbsenceSeconds);
    if (!Number.isInteger(lightMax) || lightMax < 0 || lightMax > 100) {
      Alert.alert('Invalid setting', 'Light congestion maximum must be a whole number from 0 to 100.');
      return;
    }
    if (!Number.isInteger(heavyMin) || heavyMin < 1 || heavyMin > 200 || heavyMin <= lightMax) {
      Alert.alert('Invalid setting', 'Heavy congestion must be a whole number above the light maximum, up to 200.');
      return;
    }
    if (!Number.isInteger(cooldown) || cooldown < 0 || cooldown > 86400) {
      Alert.alert('Invalid setting', 'Alert cooldown must be a whole number from 0 to 86,400 seconds.');
      return;
    }
    if (!Number.isFinite(confidence) || confidence < 0.1 || confidence > 1) {
      Alert.alert('Invalid setting', 'Minimum detection confidence must be from 0.10 (10%) to 1.00 (100%). Use 0.50 for balanced detection.');
      return;
    }
    if (![dutyStart, dutyEnd, breakStart, breakEnd].every(value => /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value))) {
      Alert.alert('Invalid schedule', 'Choose valid duty and break times.');
      return;
    }
    if (!Number.isInteger(absenceSeconds) || absenceSeconds < 60 || absenceSeconds > 3600) {
      Alert.alert('Invalid setting', 'Officer absence delay must be from 60 to 3,600 seconds.');
      return;
    }
    setSaving(true);
    try {
      const payload = {
        congestion_light_max: lightMax,
        congestion_heavy_min: heavyMin,
        alert_cooldown_seconds: cooldown,
        confidence_threshold: confidence,
        enable_officer_detection: officerDetection,
        enable_collision_detection: collisionDetection,
        notify_congestion: notifyCongestion,
        notify_collision: notifyCollision,
        notify_officer_absence: notifyOfficerAbsence,
        officer_absence_seconds: absenceSeconds,
        enforcer_schedule_enabled: scheduleEnabled,
        enforcer_duty_start: dutyStart,
        enforcer_duty_end: dutyEnd,
        enforcer_break_start: breakStart,
        enforcer_break_end: breakEnd,
      };
      const res = await api.post('update_settings.php', payload);
      if (res.data.success) {
        Alert.alert('Settings saved', res.data.message || 'Your changes were saved.');
      } else {
        Alert.alert('Error', res.data.error || 'Failed to save.');
      }
    } catch (error: any) {
      Alert.alert('Unable to save', error?.response?.data?.error || 'Check your connection and try again.');
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
              <Text style={styles.brandSubtitle}>Detection thresholds and notification preferences</Text>
            </View>
          </View>
        </View>

        {/* Traffic Thresholds */}
        <SectionCard title="Traffic Thresholds">
          <SettingInput label="Light Congestion Maximum (visible vehicles)" value={congestionLightMax} onChangeText={setCongestionLightMax} keyboardType="number-pad" />
          <SettingInput label="Heavy Congestion Starts At (visible vehicles)" value={congestionHeavyMin} onChangeText={setCongestionHeavyMin} keyboardType="number-pad" />
          <SettingInput label="Alert Cooldown (seconds)" value={alertCooldownSeconds} onChangeText={setAlertCooldownSeconds} keyboardType="number-pad" />
        </SectionCard>

        {/* Computer Vision Integration */}
        <SectionCard title="Computer Vision Integration">
          <SettingInput label="Minimum Detection Confidence (0.50 = 50%)" value={confidenceThreshold} onChangeText={setConfidenceThreshold} keyboardType="decimal-pad" />
          <View style={styles.helperRow}>
            <Ionicons name="information-circle-outline" size={14} color={COLORS.primary} style={{ marginRight: 6 }} />
            <Text style={styles.helperText}>Current level: {Math.round((Number(confidenceThreshold) || 0) * 100)}%. Use 0.50 for balanced detection. Lower values detect more but may be less accurate; higher values are stricter.</Text>
          </View>
          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Officer presence detection</Text>
            <Switch value={officerDetection} onValueChange={setOfficerDetection} trackColor={{ false: COLORS.border, true: COLORS.primary }} thumbColor="#fff" />
          </View>
          <View style={[styles.switchRow, { borderBottomWidth: 0 }]}>
            <Text style={styles.switchLabel}>Collision detection</Text>
            <Switch value={collisionDetection} onValueChange={setCollisionDetection} trackColor={{ false: COLORS.border, true: COLORS.primary }} thumbColor="#fff" />
          </View>
          <View style={styles.helperRow}>
            <Ionicons name="hardware-chip-outline" size={13} color={COLORS.textTertiary} style={{ marginRight: 6 }} />
            <Text style={styles.helperText}>
              Detection changes apply the next time analysis starts. Camera source remains in Live Monitoring.
            </Text>
          </View>
        </SectionCard>

        <SectionCard title="Enforcer Duty Schedule">
          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Limit detection to duty hours</Text>
            <Switch value={scheduleEnabled} onValueChange={setScheduleEnabled} trackColor={{ false: COLORS.border, true: COLORS.primary }} thumbColor="#fff" />
          </View>
          <TimeSettingInput label="Duty starts" value={dutyStart} onChange={setDutyStart} />
          <TimeSettingInput label="Duty ends" value={dutyEnd} onChange={setDutyEnd} />
          <TimeSettingInput label="Break starts" value={breakStart} onChange={setBreakStart} />
          <TimeSettingInput label="Break ends" value={breakEnd} onChange={setBreakEnd} />
          <View style={styles.helperRow}>
            <Ionicons name="time-outline" size={13} color={COLORS.textTertiary} style={{ marginRight: 6 }} />
            <Text style={styles.helperText}>Officer detection is inactive outside duty hours and during break.</Text>
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
              value={notifyCongestion}
              onValueChange={setNotifyCongestion}
              trackColor={{ false: COLORS.border, true: COLORS.primary }}
              thumbColor="#fff"
            />
          </View>
          <View style={[styles.switchRow, { borderBottomWidth: 0 }]}>
            <View style={styles.switchLabelCol}>
              <View style={[styles.switchDot, { backgroundColor: COLORS.primary }]} />
              <Text style={styles.switchLabel}>Confirmed collision alerts</Text>
            </View>
            <Switch
              value={notifyCollision}
              onValueChange={setNotifyCollision}
              trackColor={{ false: COLORS.border, true: COLORS.primary }}
              thumbColor="#fff"
            />
          </View>
          <View style={styles.switchRow}>
            <View style={styles.switchLabelCol}><View style={[styles.switchDot, { backgroundColor: COLORS.warning }]} /><Text style={styles.switchLabel}>Officer absence alerts</Text></View>
            <Switch value={notifyOfficerAbsence} onValueChange={setNotifyOfficerAbsence} trackColor={{ false: COLORS.border, true: COLORS.primary }} thumbColor="#fff" />
          </View>
          <SettingInput label="Officer absence delay (seconds)" value={officerAbsenceSeconds} onChangeText={setOfficerAbsenceSeconds} keyboardType="number-pad" />
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
  settingInputWrap: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 46, flexDirection: 'row', alignItems: 'center',
  },
  settingInput: { flex: 1, height: 44, paddingVertical: 0, fontSize: 14, color: COLORS.textPrimary, fontFamily: mono },
  timeSelect: { height: 48, paddingHorizontal: 12, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.bg, flexDirection: 'row', alignItems: 'center', gap: 10 },
  timeSelectText: { flex: 1, color: COLORS.textPrimary, fontSize: 14, fontWeight: '800' },
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
