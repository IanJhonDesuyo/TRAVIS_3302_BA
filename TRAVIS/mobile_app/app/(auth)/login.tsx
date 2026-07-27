import AsyncStorage from '@react-native-async-storage/async-storage';
import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import React, { useState } from 'react';
import {
  Image,
  ImageBackground,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import api, { APP_ROOT_URL } from '../../api/axiosConfig';
import LoginSuccessModal from '../../components/LoginSuccessModal';

export default function LoginScreen() {
  const router = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');
  const [successVisible, setSuccessVisible] = useState(false);
  const [loggedInName, setLoggedInName] = useState('');
  const [loggedInRole, setLoggedInRole] = useState('');

  const handleSignIn = async () => {
    if (!email.trim() || !password) { setErrorMsg('Please enter both email/username and password.'); return; }
    setErrorMsg('');
    setIsLoading(true);
    try {
      const response = await api.post('login.php', { email: email.trim(), password });
      if (response.data.success) {
        const user = response.data.user;
        await AsyncStorage.setItem('travis_user', JSON.stringify(user));
        setLoggedInName(user.full_name);
        setLoggedInRole(user.role || '');
        setSuccessVisible(true);
      } else setErrorMsg(response.data.error || 'Invalid credentials');
    } catch (error: any) {
      setErrorMsg(error.response?.data?.error || 'Network error. Please try again.');
    } finally { setIsLoading(false); }
  };

  const continueToDashboard = () => {
    setSuccessVisible(false);
    const role = loggedInRole.trim().toLowerCase();
    const route = role === 'treasurer' || role === 'treasury personnel' ? '/(treasurer)/dashboard' : '/(drawer)/dashboard';
    router.replace(route as Href);
  };

  return (
    <ImageBackground source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-municipal-hall.jpg` }} style={styles.background} resizeMode="cover">
      <View pointerEvents="none" style={styles.backgroundWash} />
      <View pointerEvents="none" style={styles.backgroundShade} />
      <SafeAreaView style={styles.safeArea}>
        <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.safeArea}>
          <ScrollView contentContainerStyle={styles.scrollContent} keyboardShouldPersistTaps="handled" showsVerticalScrollIndicator={false}>
            <TouchableOpacity style={styles.backLink} onPress={() => router.replace('/' as Href)}><Ionicons name="arrow-back" size={17} color="#17304B" /><Text style={styles.backText}>Back to TRAVIS</Text></TouchableOpacity>

            <View style={styles.card}>
              <View style={styles.brandRow}>
                <Image source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-seal.jpg` }} style={styles.seal} />
                <View style={styles.brandCopy}><Text style={styles.brandName}>NASUGBU · TMO</Text><Text style={styles.brandSub}>Traffic Management Office</Text></View>
                <View style={styles.officialDot}><Ionicons name="checkmark" size={12} color="#fff" /></View>
              </View>
              <Text style={styles.welcomeText}>Welcome Back</Text>
              <Text style={styles.welcomeSub}>Authorized Personnel Only</Text>

              <Text style={styles.label}>EMAIL / USERNAME</Text>
              <View style={styles.inputWrapper}>
                <Ionicons name="mail-outline" size={18} color="#cbd6dc" style={styles.inputIcon} />
                <TextInput style={styles.input} placeholder="Enter email address" placeholderTextColor="#b4c0c7" value={email} onChangeText={setEmail} autoCapitalize="none" keyboardType="email-address" />
              </View>
              <Text style={styles.label}>PASSWORD</Text>
              <View style={styles.inputWrapper}>
                <Ionicons name="lock-closed-outline" size={18} color="#cbd6dc" style={styles.inputIcon} />
                <TextInput style={styles.input} placeholder="Enter password" placeholderTextColor="#b4c0c7" value={password} onChangeText={setPassword} secureTextEntry={!showPassword} />
                <TouchableOpacity onPress={() => setShowPassword(!showPassword)} accessibilityLabel={showPassword ? 'Hide password' : 'Show password'}><Ionicons name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={19} color="#cbd6dc" /></TouchableOpacity>
              </View>
              {errorMsg ? <Text style={styles.errorText}>{errorMsg}</Text> : null}

              <View style={styles.optionsRow}>
                <TouchableOpacity style={styles.rememberRow} onPress={() => setRememberMe(!rememberMe)}><View style={[styles.checkbox, rememberMe && styles.checkboxChecked]}>{rememberMe && <Ionicons name="checkmark" size={12} color="#fff" />}</View><Text style={styles.rememberText}>Remember me</Text></TouchableOpacity>
              </View>
              <TouchableOpacity style={[styles.signInButton, isLoading && styles.disabled]} onPress={handleSignIn} disabled={isLoading}>{!isLoading && <Ionicons name="log-in-outline" size={18} color="#fff" style={styles.signInIcon} />}<Text style={styles.signInText}>{isLoading ? 'Signing In...' : 'Sign In'}</Text></TouchableOpacity>
              <View style={styles.divider} />
              <Text style={styles.footerText}>Traffic Violation Recognition and AI Surveillance</Text><Text style={styles.footerSub}>Powered by Artificial Intelligence</Text>
            </View>

            <View style={styles.footerRow}><Ionicons name="shield-checkmark-outline" size={13} color="#17304b" /><Text style={styles.copyright}>  Official municipal traffic information system</Text></View>
          </ScrollView>
        </KeyboardAvoidingView>
        <LoginSuccessModal visible={successVisible} userName={loggedInName} onContinue={continueToDashboard} />
      </SafeAreaView>
    </ImageBackground>
  );
}

const styles = StyleSheet.create({
  background: { flex: 1 }, safeArea: { flex: 1 },
  backgroundWash: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(232,241,246,.68)' },
  backgroundShade: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(12,31,52,.08)' },
  scrollContent: { flexGrow: 1, paddingHorizontal: 18, paddingTop: 18, paddingBottom: 28 },
  backLink: { alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 18, paddingHorizontal: 12, height: 38, borderRadius: 11, backgroundColor: 'rgba(255,255,255,.88)', borderWidth: 1, borderColor: 'rgba(23,48,75,.14)' },
  backText: { color: '#17304B', fontSize: 11, fontWeight: '800' },
  livePill: { alignSelf: 'flex-end', flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 11, height: 29, borderRadius: 15, backgroundColor: 'rgba(19,45,68,.62)', borderWidth: 1, borderColor: 'rgba(255,255,255,.24)' },
  liveDot: { width: 6, height: 6, borderRadius: 3, backgroundColor: '#2ed66f' }, liveText: { color: '#fff', fontSize: 9, fontWeight: '800', letterSpacing: .7 },
  heroPanel: { marginTop: 18, marginBottom: 18, padding: 20, borderRadius: 22, backgroundColor: 'rgba(247,250,251,.78)', borderWidth: 1, borderColor: 'rgba(23,48,75,.13)' },
  eyebrowRow: { alignSelf: 'flex-start', flexDirection: 'row', alignItems: 'center', gap: 7, paddingHorizontal: 11, paddingVertical: 7, borderRadius: 18, backgroundColor: 'rgba(232,238,244,.86)', borderWidth: 1, borderColor: 'rgba(23,35,79,.16)' },
  eyebrow: { color: '#17234f', fontSize: 9, fontWeight: '900', letterSpacing: .8 },
  title: { marginTop: 14, color: '#101a43', fontSize: 48, lineHeight: 52, fontWeight: '900', letterSpacing: -2 },
  heroSubtitle: { marginTop: 2, color: '#17234f', fontSize: 16, lineHeight: 21, fontWeight: '800' },
  description: { marginTop: 12, color: '#405877', fontSize: 12.5, lineHeight: 19 },
  statsRow: { flexDirection: 'row', gap: 8, marginTop: 18 }, statBox: { flex: 1, minHeight: 68, alignItems: 'center', justifyContent: 'center', padding: 8, borderRadius: 13, backgroundColor: 'rgba(255,255,255,.78)', borderWidth: 1, borderColor: 'rgba(23,48,75,.09)' },
  statValue: { color: '#17234f', fontSize: 16, fontWeight: '900' }, statLabel: { marginTop: 4, color: '#5d7090', fontSize: 8.5, textAlign: 'center', textTransform: 'uppercase' },
  card: { padding: 22, borderRadius: 24, backgroundColor: 'rgba(28,49,70,.72)', borderWidth: 1, borderColor: 'rgba(255,255,255,.32)', shadowColor: '#071728', shadowOffset: { width: 0, height: 18 }, shadowOpacity: .28, shadowRadius: 28, elevation: 12 },
  brandRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 22 }, seal: { width: 48, height: 48, borderRadius: 24, borderWidth: 3, borderColor: 'rgba(255,255,255,.9)', marginRight: 11 }, brandCopy: { flex: 1 },
  brandName: { color: '#fff', fontSize: 15, fontWeight: '900', letterSpacing: .2 }, brandSub: { color: '#d4dde2', fontSize: 10, marginTop: 3 }, officialDot: { width: 25, height: 25, borderRadius: 13, backgroundColor: '#16897f', alignItems: 'center', justifyContent: 'center' },
  welcomeText: { color: '#fff', fontSize: 26, fontWeight: '900', textAlign: 'center' }, welcomeSub: { color: '#d7dfe4', fontSize: 12, textAlign: 'center', marginTop: 3, marginBottom: 20 },
  label: { color: '#eef3f5', fontSize: 10, fontWeight: '800', letterSpacing: .5, marginTop: 12, marginBottom: 7 },
  inputWrapper: { minHeight: 52, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 13, borderRadius: 12, backgroundColor: 'rgba(255,255,255,.10)', borderWidth: 1.5, borderColor: 'rgba(14,30,78,.78)' }, inputIcon: { marginRight: 9 }, input: { flex: 1, color: '#fff', fontSize: 14, paddingVertical: 12 },
  errorText: { color: '#ffd0d0', fontSize: 12, marginTop: 10 }, optionsRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 15, marginBottom: 20 }, rememberRow: { flexDirection: 'row', alignItems: 'center' },
  checkbox: { width: 17, height: 17, marginRight: 7, alignItems: 'center', justifyContent: 'center', borderRadius: 4, borderWidth: 1, borderColor: '#d7e0e5' }, checkboxChecked: { backgroundColor: '#16897f', borderColor: '#16897f' }, rememberText: { color: '#eef3f5', fontSize: 12 },
  signInButton: { minHeight: 50, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: '#25377c' }, disabled: { opacity: .65 }, signInIcon: { marginRight: 8 }, signInText: { color: '#fff', fontSize: 15, fontWeight: '800' },
  divider: { height: 1, marginVertical: 20, backgroundColor: 'rgba(255,255,255,.16)' }, footerText: { color: '#e5ecef', fontSize: 10.5, textAlign: 'center' }, footerSub: { color: '#b9c5cc', fontSize: 10, textAlign: 'center', marginTop: 3 },
  footerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', marginTop: 17 }, copyright: { color: '#17304b', fontSize: 10, fontWeight: '600' },
});
