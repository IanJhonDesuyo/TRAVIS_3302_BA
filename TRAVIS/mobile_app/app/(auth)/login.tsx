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
  Image,
  ImageBackground,
  Alert,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import LoginSuccessModal from '../../components/LoginSuccessModal';
import api, { APP_ROOT_URL } from '../../api/axiosConfig';
import AsyncStorage from '@react-native-async-storage/async-storage';

type OrgType = 'LGU' | 'BSU';

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
  const [successVisible, setSuccessVisible] = useState<boolean>(false);
  const [loggedInName, setLoggedInName] = useState<string>('');
  const [loggedInRole, setLoggedInRole] = useState<string>('');

  const handleSignIn = async (): Promise<void> => {
    if (!email || !password) {
      setErrorMsg("Please enter both email/username and password.");
      return;
    }

    setErrorMsg("");
    setIsLoading(true);

    try {
      const response = await api.post('login.php', { email, password });

      if (response.data.success) {
        const user = response.data.user;
        await AsyncStorage.setItem('travis_user', JSON.stringify(user));
        setLoggedInName(user.full_name);
        setLoggedInRole(user.role || '');
        setModalVisible(false);
        setSuccessVisible(true);
      } else {
        setErrorMsg(response.data.error || 'Invalid credentials');
      }
    } catch (error: any) {
      console.error('Login error:', error);
      setErrorMsg(error.response?.data?.error || 'Network error. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleContinueToDashboard = (): void => {
    setSuccessVisible(false);
    const normalizedRole = loggedInRole.trim().toLowerCase();
    const isTreasurer = normalizedRole === 'treasurer' || normalizedRole === 'treasury personnel';
    router.replace((isTreasurer ? "/(treasurer)/dashboard" : "/(drawer)/dashboard") as Href);
  };

  return (
    <ImageBackground source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-municipal-hall.jpg` }} style={styles.background} resizeMode="cover">
      <View pointerEvents="none" style={styles.backgroundWash} />
      <View pointerEvents="none" style={styles.backgroundShade} />
      <SafeAreaView style={styles.safeArea}>
        <ScrollView
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled"
        >
          {/* Info card */}
          <View style={styles.infoCard}>
            <View style={styles.brandRow}>
              <Image source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-seal.jpg` }} style={styles.seal} />
              <View style={{ flex: 1 }}><Text style={styles.brandName}>NASUGBU · TMO</Text><Text style={styles.brandSub}>Traffic Management Office</Text></View>
              <View style={styles.officialDot}><Ionicons name="checkmark" size={12} color="#FFFFFF" /></View>
            </View>
            <View style={styles.eyebrowRow}><View style={styles.eyebrowLine} /><Text style={styles.eyebrow}>AI SMART TRAFFIC COMMAND CENTER</Text></View>
            <Text style={styles.title}>Plan ahead.</Text>
            <Text style={styles.titleAccent}>Travel safer.</Text>
            <Text style={styles.subtitle}>TRAVIS · Traffic Violation Recognition and AI Surveillance</Text>

            <Text style={styles.description}>
              Official intelligent traffic monitoring for violations, congestion,
              collisions, and safer road conditions across Nasugbu.
            </Text>

            <View style={styles.statsRow}>
              <StatItem value="Live" label="Traffic outlook" />
              <StatItem value="AI" label="Monitoring" />
              <StatItem value="24/7" label="Operations" />
            </View>

            <TouchableOpacity
              style={styles.loginButton}
              onPress={() => setModalVisible(true)}
            >
              <Ionicons name="log-in-outline" size={18} color="#fff" style={{ marginRight: 8 }} />
              <Text style={styles.loginButtonText}>Personnel Login</Text>
              <Ionicons name="arrow-forward" size={17} color="#fff" style={{ marginLeft: 8 }} />
            </TouchableOpacity>

            <View style={styles.footerRow}>
              <Ionicons name="shield-checkmark-outline" size={13} color="#137B70" />
              <Text style={styles.copyright}>
                {'  '}Official municipal traffic information system
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

                    <TouchableOpacity onPress={() => Alert.alert('Forgot Password', 'Contact a TRAVIS administrator to verify your account and reset your password.')}>
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
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  background: { flex: 1 },
  backgroundWash: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(247,244,235,0.82)' },
  backgroundShade: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(247,244,235,0.10)' },
  safeArea: { flex: 1 },
  scrollContent: {
    flexGrow: 1,
    alignItems: 'center',
    justifyContent: 'center', paddingHorizontal: 18, paddingVertical: 28,
  },

  infoCard: {
    width: '100%',
    maxWidth: 420,
    backgroundColor: 'rgba(247,244,235,0.90)',
    borderRadius: 22, padding: 22,
    borderWidth: 1,
    borderColor: 'rgba(11,39,66,0.14)',
    shadowColor: '#071B2E', shadowOffset: { width: 0, height: 16 }, shadowOpacity: 0.20, shadowRadius: 28, elevation: 10,
  },
  brandRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 34 },
  seal: { width: 52, height: 52, borderRadius: 26, borderWidth: 3, borderColor: '#FFFFFF', marginRight: 11 },
  brandName: { color: '#0B2742', fontSize: 14, fontWeight: '900', letterSpacing: .4 },
  brandSub: { color: '#60716B', fontSize: 10, marginTop: 3 },
  officialDot: { width: 25, height: 25, borderRadius: 13, backgroundColor: '#137B70', alignItems: 'center', justifyContent: 'center' },
  eyebrowRow: { flexDirection: 'row', alignItems: 'center', gap: 9, marginBottom: 12 },
  eyebrowLine: { width: 28, height: 3, backgroundColor: '#EA9625' },
  eyebrow: { color: '#137B70', fontSize: 9, fontWeight: '900', letterSpacing: 1.1, flexShrink: 1 },
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
    fontSize: 42, fontWeight: '900', color: '#0B2742', letterSpacing: -1.8, lineHeight: 44,
  },
  titleAccent: { fontSize: 42, fontWeight: '900', color: '#137B70', letterSpacing: -1.8, lineHeight: 44 },
  subtitle: {
    fontSize: 10, color: '#137B70', marginTop: 14, marginBottom: 10, fontWeight: '800', letterSpacing: .2,
  },
  description: {
    fontSize: 13,
    color: '#475D56', lineHeight: 20,
    marginBottom: 20,
  },
  statsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 24,
  },
  statBox: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.58)',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
    marginHorizontal: 4,
  },
  statValue: { color: '#0B2742', fontSize: 17, fontWeight: '900' },
  statLabel: {
    color: '#60716B',
    fontSize: 10,
    textAlign: 'center',
    marginTop: 4,
  },
  loginButton: {
    flexDirection: 'row',
    backgroundColor: '#0B2742', borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 18, borderBottomWidth: 5, borderBottomColor: '#EA9625',
  },
  loginButtonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  footerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
  },
  copyright: {
    color: '#60716B',
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
