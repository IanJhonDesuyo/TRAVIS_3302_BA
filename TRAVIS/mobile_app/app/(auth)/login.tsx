import React, { useState } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  Modal,
  Pressable,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { Ionicons } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import LoginSuccessModal from '../../components/LoginSuccessModal';
// ^ Adjust the relative path kung saan mo ilalagay ang LoginSuccessModal.tsx.
// Kung gagawa ka ng /components folder sa root (kasabay ng /app), tama na ito.

type OrgType = 'LGU' | 'BSU';

interface LoginPayload {
  org: OrgType;
  email: string;
  password: string;
  rememberMe: boolean;
}

interface StatItemProps {
  value: string;
  label: string;
}

function StatItem({ value, label }: StatItemProps) {
  return (
    <View style={styles.statBox}>
      <Text style={styles.statValue}>{value}</Text>
      <Text style={styles.statLabel}>{label}</Text>
    </View>
  );
}

export default function LoginScreen() {
  const router = useRouter();

  const [modalVisible, setModalVisible] = useState<boolean>(false);
  const [selectedOrg, setSelectedOrg] = useState<OrgType>('LGU');
  const [email, setEmail] = useState<string>('');
  const [password, setPassword] = useState<string>('');
  const [showPassword, setShowPassword] = useState<boolean>(false);
  const [rememberMe, setRememberMe] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string>('');

  // Bago: state para sa success modal
  const [successVisible, setSuccessVisible] = useState<boolean>(false);
  const [loggedInName, setLoggedInName] = useState<string>('');

  const handleSignIn = (): void => {
  if (!email || !password) {
    setErrorMsg("Please enter both email/username and password.");
    return;
  }

  setErrorMsg("");
  setIsLoading(true);

  setTimeout(() => {
    setIsLoading(false);
    setModalVisible(false);

    router.replace("/(drawer)/dashboard");
  }, 1000);
};

const handleContinueToDashboard = (): void => {
  setSuccessVisible(false);
  router.replace("/(drawer)/dashboard");
};

  return (
    <LinearGradient
      colors={['#3b4652', '#5c6b78', '#7c8a96']}
      style={styles.background}
    >
      <SafeAreaView style={styles.safeArea}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
        >
          {/* Info card */}
          <View style={styles.infoCard}>
            <View style={styles.badge}>
              <Text style={styles.badgeText}>AI Smart Traffic Command Center</Text>
            </View>

            <Text style={styles.title}>TRAVIS</Text>
            <Text style={styles.subtitle}>
              Traffic Violation Recognition and AI Surveillance
            </Text>

            <Text style={styles.description}>
              An AI-powered intelligent traffic monitoring platform designed to
              assist Local Government Units in monitoring traffic violations,
              congestion, collisions, and road conditions using Computer Vision
              and Machine Learning.
            </Text>

            <View style={styles.statsRow}>
              <StatItem value="24" label="Active Cameras" />
              <StatItem value="AI" label="Monitoring Online" />
              <StatItem value="24/7" label="Traffic Surveillance" />
            </View>

            <TouchableOpacity
              style={styles.loginButton}
              onPress={() => setModalVisible(true)}
            >
              <Ionicons name="log-in-outline" size={18} color="#fff" style={{ marginRight: 8 }} />
              <Text style={styles.loginButtonText}>Login</Text>
            </TouchableOpacity>

            <View style={styles.footerRow}>
              <Ionicons name="shield-checkmark-outline" size={13} color="#cbd5e1" />
              <Text style={styles.copyright}>
                {'  '}Municipality of Nasugbu • Batangas State University • TRAVIS v1.0
              </Text>
            </View>
          </View>
        </ScrollView>

        {/* Login modal */}
        <Modal
          visible={modalVisible}
          animationType="slide"
          transparent
          onRequestClose={() => setModalVisible(false)}
        >
          <View style={styles.modalOverlay}>
            <Pressable style={styles.modalBackdrop} onPress={() => setModalVisible(false)} />

            <KeyboardAvoidingView
              behavior={Platform.OS === 'ios' ? 'padding' : undefined}
              style={styles.modalWrapper}
            >
              <ScrollView
                contentContainerStyle={styles.modalScrollContent}
                keyboardShouldPersistTaps="handled"
              >
                <View style={styles.card}>
                  <TouchableOpacity
                    style={styles.closeButton}
                    onPress={() => setModalVisible(false)}
                  >
                    <Ionicons name="close" size={22} color="#e2e8f0" />
                  </TouchableOpacity>

                  <View style={styles.orgRow}>
                    <TouchableOpacity
                      style={[styles.orgCircle, selectedOrg === 'LGU' && styles.orgCircleActive]}
                      onPress={() => setSelectedOrg('LGU')}
                    >
                      <Text style={[styles.orgText, selectedOrg === 'LGU' && styles.orgTextActive]}>
                        LGU
                      </Text>
                    </TouchableOpacity>

                    <TouchableOpacity
                      style={[styles.orgCircle, selectedOrg === 'BSU' && styles.orgCircleActive]}
                      onPress={() => setSelectedOrg('BSU')}
                    >
                      <Text style={[styles.orgText, selectedOrg === 'BSU' && styles.orgTextActive]}>
                        BSU
                      </Text>
                    </TouchableOpacity>
                  </View>

                  <Text style={styles.welcomeText}>Welcome Back</Text>
                  <Text style={styles.welcomeSub}>Authorized Personnel Only</Text>

                  <Text style={styles.label}>Email / Username</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="mail-outline" size={18} color="#8a96a3" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="Enter email address"
                      placeholderTextColor="#8a96a3"
                      value={email}
                      onChangeText={setEmail}
                      autoCapitalize="none"
                      keyboardType="email-address"
                    />
                  </View>

                  <Text style={styles.label}>Password</Text>
                  <View style={styles.inputWrapper}>
                    <Ionicons name="lock-closed-outline" size={18} color="#8a96a3" style={styles.inputIcon} />
                    <TextInput
                      style={styles.input}
                      placeholder="Enter password"
                      placeholderTextColor="#8a96a3"
                      value={password}
                      onChangeText={setPassword}
                      secureTextEntry={!showPassword}
                    />
                    <TouchableOpacity onPress={() => setShowPassword(!showPassword)}>
                      <Ionicons
                        name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                        size={18}
                        color="#8a96a3"
                      />
                    </TouchableOpacity>
                  </View>

                  {errorMsg ? <Text style={styles.errorText}>{errorMsg}</Text> : null}

                  <View style={styles.optionsRow}>
                    <TouchableOpacity
                      style={styles.rememberRow}
                      onPress={() => setRememberMe(!rememberMe)}
                    >
                      <View style={[styles.checkbox, rememberMe && styles.checkboxChecked]}>
                        {rememberMe && <Ionicons name="checkmark" size={12} color="#fff" />}
                      </View>
                      <Text style={styles.rememberText}>Remember me</Text>
                    </TouchableOpacity>

                    <TouchableOpacity>
                      <Text style={styles.forgotText}>Forgot Password?</Text>
                    </TouchableOpacity>
                  </View>

                  <TouchableOpacity
                    style={[styles.signInButton, isLoading && styles.signInButtonDisabled]}
                    onPress={handleSignIn}
                    disabled={isLoading}
                  >
                    {!isLoading && (
                      <Ionicons name="log-in-outline" size={18} color="#fff" style={{ marginRight: 8 }} />
                    )}
                    <Text style={styles.signInText}>{isLoading ? 'Signing In...' : 'Sign In'}</Text>
                  </TouchableOpacity>

                  <View style={styles.divider} />

                  <Text style={styles.footerText}>
                    Traffic Violation Recognition and AI Surveillance
                  </Text>
                  <Text style={styles.footerSub}>Powered by Artificial Intelligence</Text>
                </View>
              </ScrollView>
            </KeyboardAvoidingView>
          </View>
        </Modal>

        {/* Success modal - lumalabas pagkatapos ng matagumpay na login */}
        <LoginSuccessModal
          visible={successVisible}
          userName={loggedInName}
          onContinue={handleContinueToDashboard}
        />
      </SafeAreaView>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  background: { flex: 1 },
  safeArea: { flex: 1 },
  scrollContent: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 24,
    paddingVertical: 40,
  },

  infoCard: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: 'rgba(59, 70, 82, 0.55)',
    borderRadius: 20,
    padding: 24,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.12)',
  },
  badge: {
    alignSelf: 'flex-start',
    backgroundColor: '#22d3ee',
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 6,
    marginBottom: 14,
  },
  badgeText: { color: '#0b3b45', fontWeight: '600', fontSize: 11 },
  title: {
    fontSize: 36,
    fontWeight: '800',
    color: '#ffffff',
    letterSpacing: 1,
  },
  subtitle: {
    fontSize: 15,
    color: '#e2e8f0',
    marginTop: 4,
    marginBottom: 14,
    fontWeight: '600',
  },
  description: {
    fontSize: 13,
    color: '#cbd5e1',
    lineHeight: 20,
    marginBottom: 20,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 24,
  },
  statBox: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginHorizontal: 4,
  },
  statValue: { color: '#fff', fontSize: 18, fontWeight: '800' },
  statLabel: {
    color: '#cbd5e1',
    fontSize: 10,
    textAlign: 'center',
    marginTop: 4,
  },
  loginButton: {
    flexDirection: 'row',
    backgroundColor: '#2563eb',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 18,
  },
  loginButtonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  copyright: {
    color: '#cbd5e1',
    fontSize: 10,
    textAlign: 'center',
  },

  modalOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  modalBackdrop: {
    ...StyleSheet.absoluteFillObject,
  },
  modalWrapper: {
    maxHeight: '90%',
  },
  modalScrollContent: {
    paddingHorizontal: 20,
    paddingBottom: 30,
  },

  card: {
    width: '100%',
    backgroundColor: '#4b5866',
    borderRadius: 20,
    padding: 24,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.15)',
    marginTop: 12,
  },
  closeButton: {
    position: 'absolute',
    top: 14,
    right: 14,
    zIndex: 10,
    padding: 4,
  },
  orgRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    marginBottom: 16,
  },
  orgCircle: {
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: 'rgba(255,255,255,0.15)',
    alignItems: 'center',
    justifyContent: 'center',
    marginHorizontal: 8,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.25)',
  },
  orgCircleActive: {
    backgroundColor: '#2563eb',
    borderColor: '#2563eb',
  },
  orgText: { color: '#e2e8f0', fontWeight: '700', fontSize: 13 },
  orgTextActive: { color: '#fff' },
  welcomeText: {
    fontSize: 22,
    fontWeight: '800',
    color: '#fff',
    textAlign: 'center',
    marginBottom: 2,
  },
  welcomeSub: {
    fontSize: 12,
    color: '#e2e8f0',
    textAlign: 'center',
    marginBottom: 20,
  },
  label: { color: '#e2e8f0', fontSize: 13, marginBottom: 6, marginTop: 10 },
  inputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderRadius: 10,
    paddingHorizontal: 12,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
  },
  inputIcon: { marginRight: 8 },
  input: {
    flex: 1,
    color: '#fff',
    paddingVertical: 12,
    fontSize: 14,
  },
  errorText: {
    color: '#fca5a5',
    fontSize: 12,
    marginTop: 10,
  },
  optionsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 14,
    marginBottom: 20,
  },
  rememberRow: { flexDirection: 'row', alignItems: 'center' },
  checkbox: {
    width: 16,
    height: 16,
    borderRadius: 3,
    borderWidth: 1,
    borderColor: '#cbd5e1',
    marginRight: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkboxChecked: { backgroundColor: '#2563eb', borderColor: '#2563eb' },
  rememberText: { color: '#e2e8f0', fontSize: 12 },
  forgotText: { color: '#67e8f9', fontSize: 12, fontWeight: '600' },
  signInButton: {
    flexDirection: 'row',
    backgroundColor: '#2563eb',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  signInButtonDisabled: {
    opacity: 0.7,
  },
  signInText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  divider: {
    height: 1,
    backgroundColor: 'rgba(255,255,255,0.15)',
    marginVertical: 18,
  },
  footerText: {
    color: '#e2e8f0',
    fontSize: 11,
    textAlign: 'center',
  },
  footerSub: {
    color: '#94a3b8',
    fontSize: 11,
    textAlign: 'center',
    marginTop: 2,
  },
});