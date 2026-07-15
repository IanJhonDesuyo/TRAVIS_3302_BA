import React from 'react';
import { View, Text, StyleSheet, Modal, TouchableOpacity } from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';

interface LoginSuccessModalProps {
  visible: boolean;
  userName: string;
  onContinue: () => void;
}

export default function LoginSuccessModal({
  visible,
  userName,
  onContinue,
}: LoginSuccessModalProps) {
  return (
    <Modal visible={visible} animationType="fade" transparent statusBarTranslucent>
      <View style={styles.overlay}>
        <View style={styles.card}>
          {/* Brand */}
          <View style={styles.brandRow}>
            <View style={styles.brandIcon}>
              <Text style={styles.brandIconText}>T</Text>
            </View>
            <Text style={styles.brandText}>TRAVIS COMMAND CENTER</Text>
          </View>

          {/* Check icon */}
          <View style={styles.checkCircle}>
            <Ionicons name="checkmark" size={32} color="#10b981" />
          </View>

          <Text style={styles.verifiedLabel}>IDENTITY VERIFIED</Text>
          <Text style={styles.welcomeText}>Welcome back, {userName}!</Text>
          <Text style={styles.description}>
            You have signed in successfully. Your secure dashboard and live
            traffic intelligence are ready.
          </Text>

          <TouchableOpacity onPress={onContinue} activeOpacity={0.85} style={{ width: '100%' }}>
            <LinearGradient
              colors={['#2563eb', '#0891b2']}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.button}
            >
              <Text style={styles.buttonText}>Open dashboard</Text>
              <Ionicons name="arrow-forward" size={18} color="#fff" style={{ marginLeft: 8 }} />
            </LinearGradient>
          </TouchableOpacity>

          <View style={styles.footerRow}>
            <Ionicons name="shield-checkmark-outline" size={12} color="#94a3b8" />
            <Text style={styles.footerText}>  Secure administrator session active.</Text>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.55)',
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
  },
  card: {
    width: '100%',
    maxWidth: 380,
    backgroundColor: '#ffffff',
    borderRadius: 24,
    paddingVertical: 32,
    paddingHorizontal: 24,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 10 },
    shadowOpacity: 0.2,
    shadowRadius: 20,
    elevation: 10,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 24,
  },
  brandIcon: {
    width: 24,
    height: 24,
    borderRadius: 6,
    backgroundColor: '#2563eb',
    alignItems: 'center',
    justifyContent: 'center',
    marginRight: 8,
  },
  brandIconText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  brandText: { color: '#1e293b', fontWeight: '700', fontSize: 12, letterSpacing: 0.5 },
  checkCircle: {
    width: 72,
    height: 72,
    borderRadius: 20,
    backgroundColor: '#d1fae5',
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 20,
  },
  verifiedLabel: {
    color: '#2563eb',
    fontWeight: '700',
    fontSize: 12,
    letterSpacing: 1,
    marginBottom: 8,
  },
  welcomeText: {
    fontSize: 20,
    fontWeight: '800',
    color: '#0f172a',
    textAlign: 'center',
    marginBottom: 10,
  },
  description: {
    fontSize: 13,
    color: '#64748b',
    textAlign: 'center',
    lineHeight: 19,
    marginBottom: 24,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 12,
    paddingVertical: 14,
    paddingHorizontal: 28,
  },
  buttonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 20,
  },
  footerText: { color: '#94a3b8', fontSize: 11 },
});