import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import React, { useState } from 'react';
import { Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import api from '../api/axiosConfig';
import { clearStoredUser } from '../utils/session';

type Props = { portal: string; notificationRoute: Href; userName: string };

export function MunicipalHeaderTitle({ portal }: Pick<Props, 'portal'>) {
  return <View style={styles.titleWrap}><Text style={styles.title} numberOfLines={1}>Municipality of Nasugbu</Text><Text style={styles.subtitle} numberOfLines={1}>Traffic Management Office · {portal}</Text></View>;
}

export function MunicipalHeaderActions({ notificationRoute, userName }: Omit<Props, 'portal'>) {
  const router = useRouter();
  const [logoutVisible, setLogoutVisible] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const initials = userName.split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase() || 'TR';
  const logout = async () => {
    setLoggingOut(true);
    try { await api.post('logout.php'); } catch { /* Local session is still cleared. */ }
    finally {
      await clearStoredUser();
      setLoggingOut(false);
      setLogoutVisible(false);
      router.replace('/');
    }
  };
  return <>
    <View style={styles.actions}>
      <TouchableOpacity style={styles.iconButton} onPress={() => router.push(notificationRoute)} accessibilityLabel="Open notifications"><Ionicons name="notifications-outline" size={18} color="#087D78" /></TouchableOpacity>
      <TouchableOpacity style={styles.avatar} onPress={() => setLogoutVisible(true)} accessibilityLabel="Open logout confirmation" activeOpacity={0.75}><Text style={styles.avatarText}>{initials}</Text></TouchableOpacity>
    </View>
    <Modal transparent animationType="fade" visible={logoutVisible} onRequestClose={() => { if (!loggingOut) setLogoutVisible(false); }}>
      <View style={styles.modalOverlay}>
        <View style={styles.modalCard} accessibilityRole="alert">
          <View style={styles.logoutIcon}><Ionicons name="log-out-outline" size={23} color="#C84B45" /></View>
          <Text style={styles.modalTitle}>Log out of TRAVIS?</Text>
          <Text style={styles.modalMessage}>You will need to sign in again to access the mobile system.</Text>
          <View style={styles.modalActions}>
            <TouchableOpacity style={[styles.modalButton, styles.cancelButton]} onPress={() => setLogoutVisible(false)} disabled={loggingOut}><Text style={styles.cancelText}>Cancel</Text></TouchableOpacity>
            <TouchableOpacity style={[styles.modalButton, styles.logoutButton]} onPress={logout} disabled={loggingOut}><Ionicons name="log-out-outline" size={16} color="#FFF" /><Text style={styles.logoutText}>{loggingOut ? 'Logging out...' : 'Log Out'}</Text></TouchableOpacity>
          </View>
        </View>
      </View>
    </Modal>
  </>;
}

const styles = StyleSheet.create({
  titleWrap: { minWidth: 0, paddingLeft: 2 },
  title: { color: '#10202C', fontSize: 13, fontWeight: '800' },
  subtitle: { color: '#526B64', fontSize: 8.5, fontWeight: '600', marginTop: 2 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 8, marginRight: 12 },
  iconButton: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 11, backgroundColor: 'rgba(255,255,255,.94)', borderWidth: 1, borderColor: 'rgba(16,47,73,.16)' },
  avatar: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 19, backgroundColor: '#087D78', borderWidth: 2, borderColor: 'rgba(255,255,255,.9)' },
  avatarText: { color: '#fff', fontSize: 11, fontWeight: '900' },
  modalOverlay: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: 'rgba(15,23,42,.68)' },
  modalCard: { width: '100%', maxWidth: 380, padding: 22, borderRadius: 20, backgroundColor: '#FFFDF7', borderWidth: 1, borderColor: 'rgba(16,47,73,.20)', alignItems: 'center', shadowColor: '#0F172A', shadowOpacity: .18, shadowRadius: 18, elevation: 12 },
  logoutIcon: { width: 48, height: 48, borderRadius: 15, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(200,75,69,.10)' },
  modalTitle: { marginTop: 13, color: '#10202C', fontSize: 19, fontWeight: '900' },
  modalMessage: { marginTop: 7, color: '#526B64', fontSize: 12, lineHeight: 18, textAlign: 'center' },
  modalActions: { width: '100%', flexDirection: 'row', gap: 10, marginTop: 20 },
  modalButton: { flex: 1, minHeight: 44, borderRadius: 11, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 6 },
  cancelButton: { borderWidth: 1, borderColor: 'rgba(16,47,73,.18)', backgroundColor: '#FFF' },
  logoutButton: { backgroundColor: '#C84B45' },
  cancelText: { color: '#526B64', fontSize: 12, fontWeight: '800' },
  logoutText: { color: '#FFF', fontSize: 12, fontWeight: '900' },
});
