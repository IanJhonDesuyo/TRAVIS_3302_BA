import React, { useState } from 'react';
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
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';

// ========== HELPERS ==========
const SectionCard = ({ title, children }: { title: string; children: React.ReactNode }) => (
  <View style={styles.sectionCard}>
    <Text style={styles.sectionTitle}>{title}</Text>
    {children}
  </View>
);

const SettingInput = ({
  label,
  value,
  disabled = true,
  keyboardType = 'default',
}: {
  label: string;
  value: string;
  disabled?: boolean;
  keyboardType?: 'default' | 'numeric';
}) => (
  <View style={styles.settingGroup}>
    <Text style={styles.settingLabel}>{label}</Text>
    <TextInput
      style={[styles.settingInput, disabled && styles.disabledInput]}
      value={value}
      editable={!disabled}
      keyboardType={keyboardType}
      selectTextOnFocus={false}
    />
  </View>
);

// ========== SCREEN ==========
export default function SettingsScreen() {
  const router = useRouter();

  // Mock settings state (all disabled for UI demo)
  const [congestionTrigger, setCongestionTrigger] = useState('1500');
  const [alertCooldown, setAlertCooldown] = useState('15');
  const [officerAbsence, setOfficerAbsence] = useState('30');
  const [collisionStationary, setCollisionStationary] = useState('10');
  const [flaskApiUrl, setFlaskApiUrl] = useState('http://localhost:5000');
  const [rtspSource, setRtspSource] = useState('rtsp://username:password@camera-ip:554/stream1');
  const [notifications, setNotifications] = useState({
    congestion: true,
    officer: true,
    collision: true,
  });
  const [sessionTimeout, setSessionTimeout] = useState('30');
  const [passwordPolicy, setPasswordPolicy] = useState('Strong (12+ chars)');

  // For demonstration, we keep all fields disabled.
  // To enable editing later, set disabled={false} and add handlers.

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
      <ScrollView style={styles.container} showsVerticalScrollIndicator={false}>
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.pageTitle}>System Settings</Text>
          <Text style={styles.pageSub}>
            Prepared configuration screen for computer vision thresholds, notifications, duty schedule, and security
          </Text>
        </View>

        {/* Info Alert */}
        <View style={styles.alertBox}>
          <Text style={styles.alertText}>
            Settings are currently UI-ready only. To make these dynamic later, create a{' '}
            <Text style={styles.alertCode}>system_settings</Text> table or store values in a configuration file.
          </Text>
        </View>

        {/* Traffic Thresholds */}
        <SectionCard title="Traffic Thresholds">
          <SettingInput label="Congestion Trigger (vehicles/hr)" value={congestionTrigger} keyboardType="numeric" />
          <SettingInput label="Alert Cooldown (minutes)" value={alertCooldown} keyboardType="numeric" />
          <SettingInput label="Officer Absence Threshold (minutes)" value={officerAbsence} keyboardType="numeric" />
          <SettingInput label="Potential Collision Stationary Threshold (seconds)" value={collisionStationary} keyboardType="numeric" />
        </SectionCard>

        {/* Computer Vision Integration */}
        <SectionCard title="Computer Vision Integration">
          <SettingInput label="Flask API URL" value={flaskApiUrl} />
          <SettingInput label="RTSP Camera Source" value={rtspSource} />
          <Text style={styles.helperText}>
            These fields are placeholders for later integration with Tapo C210, YOLOv8, and OpenCV.
          </Text>
        </SectionCard>

        {/* Notifications */}
        <SectionCard title="Notifications">
          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Critical congestion alerts</Text>
            <Switch
              value={notifications.congestion}
              onValueChange={(val) => setNotifications({ ...notifications, congestion: val })}
              trackColor={{ false: '#d1d5db', true: '#2563eb' }}
              thumbColor={notifications.congestion ? '#fff' : '#fff'}
              disabled={true} // disabled for UI demo
            />
          </View>
          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Officer absence alerts</Text>
            <Switch
              value={notifications.officer}
              onValueChange={(val) => setNotifications({ ...notifications, officer: val })}
              trackColor={{ false: '#d1d5db', true: '#2563eb' }}
              thumbColor={notifications.officer ? '#fff' : '#fff'}
              disabled={true}
            />
          </View>
          <View style={styles.switchRow}>
            <Text style={styles.switchLabel}>Potential collision alerts</Text>
            <Switch
              value={notifications.collision}
              onValueChange={(val) => setNotifications({ ...notifications, collision: val })}
              trackColor={{ false: '#d1d5db', true: '#2563eb' }}
              thumbColor={notifications.collision ? '#fff' : '#fff'}
              disabled={true}
            />
          </View>
        </SectionCard>

        {/* Security */}
        <SectionCard title="Security">
          <SettingInput label="Session Timeout (minutes)" value={sessionTimeout} keyboardType="numeric" />
          <View style={styles.settingGroup}>
            <Text style={styles.settingLabel}>Password Policy</Text>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={passwordPolicy}
                onValueChange={(itemValue) => setPasswordPolicy(itemValue)}
                style={styles.picker}
                enabled={false}
                dropdownIconColor="#0b3d78"
              >
                <Picker.Item label="Strong (12+ chars)" value="Strong (12+ chars)" />
                <Picker.Item label="Standard (8+ chars)" value="Standard (8+ chars)" />
              </Picker>
            </View>
          </View>
        </SectionCard>

        {/* Save button (disabled) */}
        <TouchableOpacity style={styles.saveButton} disabled={true}>
          <Text style={styles.saveButtonText}>Save Changes</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: '#f8fafc' },
  container: { flex: 1, padding: 16 },
  header: { marginBottom: 16 },
  pageTitle: { fontSize: 24, fontWeight: '700', color: '#0b3d78', marginBottom: 4 },
  pageSub: { fontSize: 14, color: '#64748b' },

  alertBox: {
    backgroundColor: '#e6f0ff',
    borderRadius: 12,
    padding: 14,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: '#b8d4f0',
  },
  alertText: { fontSize: 14, color: '#1e293b', lineHeight: 20 },
  alertCode: {
    fontFamily: 'monospace',
    backgroundColor: '#dbeafe',
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
    fontSize: 13,
    fontWeight: '600',
    color: '#1e40af',
  },

  sectionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78', marginBottom: 12 },

  settingGroup: { marginBottom: 14 },
  settingLabel: { fontSize: 13, fontWeight: '500', color: '#0b3d78', marginBottom: 4 },
  settingInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    fontSize: 14,
    height: 44,
    color: '#0b3d78',
  },
  disabledInput: { backgroundColor: '#f1f5f9', color: '#94a3b8' },

  helperText: { fontSize: 13, color: '#94a3b8', marginTop: 4, fontStyle: 'italic' },

  switchRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 6,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  switchLabel: { fontSize: 14, color: '#1e293b', flex: 1, marginRight: 8 },

  pickerWrapper: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    height: 44,
    justifyContent: 'center',
  },
  picker: { height: 44, width: '100%' },

  saveButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 20,
    opacity: 0.5,
  },
  saveButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },
});