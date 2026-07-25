import React, { useEffect, useState } from 'react';
import { Drawer } from 'expo-router/drawer';
import { DrawerContentScrollView, DrawerItem } from '@react-navigation/drawer';
import { ActivityIndicator, Alert, StyleSheet, Text, View } from 'react-native';
import { Href, Redirect, usePathname, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import api from '../../api/axiosConfig';
import { clearStoredUser, getStoredUser, isTreasurerRole, TravisUser } from '../../utils/session';

const NAVY = '#0A1A30';
const CYAN = '#4FC3F7';

function TreasuryDrawer({ user }: { user: TravisUser }) {
  const router = useRouter();
  const pathname = usePathname();
  const items = [
    ['dashboard', 'Dashboard', 'speedometer-outline'],
    ['violations', 'Traffic Violations', 'alert-circle-outline'],
    ['payments', 'Payment Management', 'cash-outline'],
    ['reports', 'Collection Reports', 'bar-chart-outline'],
    ['history', 'Payment History', 'time-outline'],
    ['notifications', 'Notifications', 'notifications-outline'],
    ['profile', 'My Profile', 'person-outline'],
  ];

  const logout = () => Alert.alert('Log Out', 'Are you sure you want to log out?', [
    { text: 'Cancel', style: 'cancel' },
    { text: 'Log Out', style: 'destructive', onPress: async () => {
      try { await api.post('logout.php'); } finally {
        await clearStoredUser();
        router.replace('/(auth)/login');
      }
    } },
  ]);

  return (
    <DrawerContentScrollView contentContainerStyle={styles.drawer}>
      <View style={styles.brand}><Text style={styles.brandTitle}>TRAVIS</Text><Text style={styles.brandSub}>Treasurer Portal</Text></View>
      {items.map(([route, label, icon]) => {
        const href = `/(treasurer)/${route}`;
        const active = pathname.endsWith(`/${route}`);
        return <DrawerItem key={route} label={label} icon={() => <Ionicons name={icon as any} size={19} color={active ? NAVY : '#C9D8EA'} />} onPress={() => router.push(href as Href)} style={active ? styles.active : undefined} labelStyle={[styles.label, active && styles.activeLabel]} />;
      })}
      <View style={styles.account}><Text style={styles.name}>{user.full_name}</Text><Text style={styles.role}>{user.role}</Text></View>
      <DrawerItem label="Log Out" icon={() => <Ionicons name="log-out-outline" size={19} color="#FCA5A5" />} onPress={logout} labelStyle={[styles.label, { color: '#FCA5A5' }]} />
    </DrawerContentScrollView>
  );
}

export default function TreasurerLayout() {
  const [user, setUser] = useState<TravisUser | null | undefined>(undefined);
  useEffect(() => { getStoredUser().then(setUser); }, []);
  if (user === undefined) return <View style={styles.loading}><ActivityIndicator color={CYAN} /></View>;
  if (!user) return <Redirect href="/(auth)/login" />;
  if (!isTreasurerRole(user.role)) return <Redirect href="/(drawer)/dashboard" />;

  return <Drawer drawerContent={() => <TreasuryDrawer user={user} />} screenOptions={{ headerStyle: { backgroundColor: NAVY }, headerTintColor: '#FFF', drawerStyle: { backgroundColor: NAVY }, sceneStyle: { backgroundColor: '#F3F6FA' } }}>
    <Drawer.Screen name="dashboard" options={{ title: 'Treasurer Dashboard' }} />
    <Drawer.Screen name="violations" options={{ title: 'Traffic Violations' }} />
    <Drawer.Screen name="payments" options={{ title: 'Payment Management' }} />
    <Drawer.Screen name="reports" options={{ title: 'Collection Reports' }} />
    <Drawer.Screen name="history" options={{ title: 'Payment History' }} />
    <Drawer.Screen name="notifications" options={{ title: 'Notifications' }} />
    <Drawer.Screen name="profile" options={{ title: 'My Profile' }} />
  </Drawer>;
}

const styles = StyleSheet.create({
  drawer: { flexGrow: 1, backgroundColor: NAVY, paddingBottom: 20 }, loading: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: NAVY },
  brand: { padding: 24, borderBottomWidth: 1, borderBottomColor: 'rgba(255,255,255,.1)', marginBottom: 12 }, brandTitle: { color: '#FFF', fontSize: 25, fontWeight: '900' }, brandSub: { color: '#94A3B8', marginTop: 3 },
  label: { color: '#C9D8EA', fontWeight: '600' }, active: { backgroundColor: CYAN }, activeLabel: { color: NAVY, fontWeight: '800' }, account: { marginTop: 'auto', marginHorizontal: 18, padding: 14, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,.1)' }, name: { color: '#FFF', fontWeight: '700' }, role: { color: '#94A3B8', fontSize: 12, marginTop: 3 },
});
