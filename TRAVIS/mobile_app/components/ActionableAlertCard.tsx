import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  AppState,
  Dimensions,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import api from '../api/axiosConfig';

type ActionableAlert = {
  alert_id: number;
  alert_type: string;
  severity: string;
  message: string;
  status: string;
  generated_at: string;
};

type SeenMap = Record<string, number>;

const STORAGE_KEY = 'travis_actionable_alert_seen';
const POLL_MS = 5000;

const isOfficerAbsence = (alert: ActionableAlert) =>
  alert.alert_type.toLowerCase() === 'officer_absence';

const formatTimestamp = (value: string) => {
  const parsed = new Date(value.replace(' ', 'T'));
  return Number.isNaN(parsed.getTime()) ? value : parsed.toLocaleString();
};

export default function ActionableAlertCard() {
  const router = useRouter();
  const [current, setCurrent] = useState<ActionableAlert | null>(null);
  const [acknowledging, setAcknowledging] = useState(false);
  const [cooldownSeconds, setCooldownSeconds] = useState(300);
  const seenRef = useRef<SeenMap>({});
  const pollingRef = useRef(false);

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY)
      .then(value => {
        if (value) seenRef.current = JSON.parse(value);
      })
      .catch(() => undefined);
  }, []);

  const rememberShown = useCallback((alertId: number) => {
    seenRef.current[String(alertId)] = Date.now();
    AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(seenRef.current)).catch(() => undefined);
  }, []);

  const fetchActionable = useCallback(async () => {
    if (pollingRef.current || current) return;
    pollingRef.current = true;
    try {
      const response = await api.get('get_actionable_alerts.php');
      if (!response.data?.success) return;

      const cooldown = Math.max(60, Number(response.data.cooldown_seconds) || 300);
      setCooldownSeconds(cooldown);
      const now = Date.now();
      const alerts: ActionableAlert[] = response.data.data || [];
      const next = alerts.find(alert => {
        const lastShown = seenRef.current[String(alert.alert_id)];
        if (!lastShown) return true;
        return isOfficerAbsence(alert) && now - lastShown >= cooldown * 1000;
      });

      if (next) {
        rememberShown(next.alert_id);
        setCurrent(next);
      }
    } catch {
      // Background polling stays silent; the regular Alerts screen remains available.
    } finally {
      pollingRef.current = false;
    }
  }, [current, rememberShown]);

  useEffect(() => {
    const initial = setTimeout(fetchActionable, 1200);
    const timer = setInterval(fetchActionable, POLL_MS);
    const subscription = AppState.addEventListener('change', state => {
      if (state === 'active') fetchActionable();
    });
    return () => {
      clearTimeout(initial);
      clearInterval(timer);
      subscription.remove();
    };
  }, [fetchActionable]);

  const acknowledge = async () => {
    if (!current || acknowledging) return;
    setAcknowledging(true);
    try {
      const response = await api.post('acknowledge_alert.php', { alert_id: current.alert_id });
      if (response.data?.success) setCurrent(null);
    } finally {
      setAcknowledging(false);
    }
  };

  const viewLive = () => {
    setCurrent(null);
    router.push('/(drawer)/monitoring' as Href);
  };

  if (!current) return null;

  const officerAlert = isOfficerAbsence(current);
  const accent = officerAlert ? '#EB941F' : '#D92D2D';

  return (
    <View pointerEvents="box-none" style={styles.layer}>
      <View style={[styles.card, { borderTopColor: accent }]} accessibilityRole="alert">
        <TouchableOpacity
          style={styles.closeButton}
          onPress={() => setCurrent(null)}
          accessibilityLabel="Dismiss alert"
        >
          <Ionicons name="close" size={19} color="#60736D" />
        </TouchableOpacity>

        <View style={styles.headingRow}>
          <View style={[styles.iconTile, { backgroundColor: `${accent}16` }]}>
            <Ionicons
              name={officerAlert ? 'person-remove-outline' : 'warning-outline'}
              size={23}
              color={accent}
            />
          </View>
          <View style={styles.headingCopy}>
            <Text style={styles.eyebrow}>TRAVIS OPERATIONAL ALERT</Text>
            <Text style={styles.title}>
              {officerAlert ? 'No enforcer detected' : 'Critical traffic alert'}
            </Text>
          </View>
        </View>

        <Text style={styles.message}>{current.message}</Text>
        <Text style={styles.timestamp}>Detected {formatTimestamp(current.generated_at)}</Text>

        <View style={styles.actions}>
          <TouchableOpacity style={styles.secondaryButton} onPress={viewLive}>
            <Ionicons name="videocam-outline" size={15} color="#087D78" />
            <Text style={styles.secondaryText}>View live</Text>
          </TouchableOpacity>
          <TouchableOpacity
            style={[styles.primaryButton, { backgroundColor: accent }]}
            onPress={acknowledge}
            disabled={acknowledging}
          >
            {acknowledging
              ? <ActivityIndicator size="small" color="#FFFFFF" />
              : <Ionicons name="checkmark-circle-outline" size={15} color="#FFFFFF" />}
            <Text style={styles.primaryText}>{acknowledging ? 'Saving…' : 'Acknowledge'}</Text>
          </TouchableOpacity>
        </View>

        {officerAlert && (
          <Text style={styles.cooldownNote}>
            Reminder repeats after {Math.ceil(cooldownSeconds / 60)} minute(s) if no officer is detected.
          </Text>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  layer: {
    position: 'absolute',
    left: 12,
    right: 12,
    bottom: 14,
    zIndex: 1000,
    alignItems: 'flex-end',
  },
  card: {
    width: Math.min(Dimensions.get('window').width - 24, 380),
    backgroundColor: 'rgba(255, 253, 247, 0.98)',
    borderRadius: 18,
    borderWidth: 1,
    borderColor: 'rgba(16, 47, 73, 0.18)',
    borderTopWidth: 5,
    padding: 16,
    shadowColor: '#0F172A',
    shadowOffset: { width: 0, height: 7 },
    shadowOpacity: 0.2,
    shadowRadius: 18,
    elevation: 14,
  },
  closeButton: {
    position: 'absolute',
    right: 9,
    top: 8,
    zIndex: 2,
    width: 32,
    height: 32,
    borderRadius: 10,
    backgroundColor: '#EFF2EE',
    alignItems: 'center',
    justifyContent: 'center',
  },
  headingRow: { flexDirection: 'row', alignItems: 'center', paddingRight: 30 },
  iconTile: { width: 45, height: 45, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },
  headingCopy: { flex: 1, marginLeft: 11 },
  eyebrow: { color: '#63748C', fontSize: 10, fontWeight: '800', letterSpacing: 1 },
  title: { color: '#10202C', fontSize: 17, fontWeight: '800', marginTop: 3 },
  message: { color: '#526B64', fontSize: 13, lineHeight: 18, marginTop: 12 },
  timestamp: { color: '#7B8B85', fontSize: 10, marginTop: 5 },
  actions: { flexDirection: 'row', gap: 8, marginTop: 14 },
  secondaryButton: {
    flex: 1,
    height: 40,
    borderRadius: 11,
    borderWidth: 1,
    borderColor: 'rgba(8, 125, 120, 0.38)',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  secondaryText: { color: '#087D78', fontSize: 12, fontWeight: '800' },
  primaryButton: {
    flex: 1.2,
    height: 40,
    borderRadius: 11,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
  },
  primaryText: { color: '#FFFFFF', fontSize: 12, fontWeight: '800' },
  cooldownNote: { color: '#7B8B85', fontSize: 9.5, lineHeight: 14, marginTop: 10 },
});
