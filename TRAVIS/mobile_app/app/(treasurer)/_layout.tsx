import React, { useEffect, useState } from 'react';
import { Drawer } from 'expo-router/drawer';
import { DrawerContentScrollView, DrawerItem } from '@react-navigation/drawer';
import { ActivityIndicator, Dimensions, Image, ImageBackground, Modal, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Href, Redirect, usePathname, useRouter } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import api, { APP_ROOT_URL } from '../../api/axiosConfig';
import { clearStoredUser, getStoredUser, isTreasurerRole, TravisUser } from '../../utils/session';
import { MunicipalHeaderActions, MunicipalHeaderTitle } from '../../components/MunicipalMobileHeader';

const { width } = Dimensions.get('window');
const COLORS = {
  header: '#102F49', surface: 'rgba(255,253,247,.94)', text: '#10202C',
  secondary: '#526B64', tertiary: '#82928C', primary: '#087D78', warning: '#EB941F', danger: '#C84B45',
};

const groups = [
  { title: 'OVERVIEW', items: [['dashboard', 'Dashboard', 'grid-outline']] },
  { title: 'COLLECTIONS', items: [['violations', 'Violations', 'alert-circle-outline'], ['payments', 'Payments', 'cash-outline']] },
  { title: 'ACCOUNT', items: [['notifications', 'Notifications', 'notifications-outline'], ['profile', 'Profile', 'person-outline']] },
];

function TreasuryDrawer({ user, ...props }: { user: TravisUser; [key: string]: any }) {
  const router = useRouter();
  const pathname = usePathname();
  const [logoutVisible, setLogoutVisible] = useState(false);
  const logout = async () => { setLogoutVisible(false); try { await api.post('logout.php'); } finally { await clearStoredUser(); router.replace('/'); } };

  return <>
    <DrawerContentScrollView {...props} contentContainerStyle={styles.drawer} showsVerticalScrollIndicator={false}>
      <View style={styles.drawerHeader}><View style={styles.brandRow}><Image source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-seal.jpg` }} style={styles.seal} /><View><Text style={styles.logo}>NASUGBU · TMO</Text><Text style={styles.logoSub}>Treasury & Collection Portal</Text></View></View></View>
      {groups.map(group => <View key={group.title}><View style={styles.sectionHeaderWrap}><Text style={styles.sectionHeader}>{group.title}</Text></View>{group.items.map(([route, label, icon]) => { const active = pathname.endsWith(`/${route}`); return <DrawerItem key={route} label={label} icon={({ size }) => <Ionicons name={icon as any} size={Math.min(size, 18)} color={active ? '#FFD18C' : '#8BC9C2'} />} onPress={() => router.push(`/(treasurer)/${route}` as Href)} style={[styles.drawerItem, active && styles.activeItem]} labelStyle={[styles.drawerLabel, active && styles.activeLabel]} />; })}</View>)}
      <View style={styles.bottomSpacer}><TouchableOpacity style={styles.userBadge} onPress={() => setLogoutVisible(true)} activeOpacity={.7}><View style={styles.avatar}><Text style={styles.avatarText}>{user.full_name.split(/\s+/).map(part => part[0]).slice(0, 2).join('').toUpperCase()}</Text></View><View style={{ flex: 1 }}><Text style={styles.userName} numberOfLines={1}>{user.full_name}</Text><Text style={styles.userRole}>{user.role}</Text></View><Ionicons name="log-out-outline" size={17} color={COLORS.tertiary} /></TouchableOpacity></View>
    </DrawerContentScrollView>
    <Modal transparent animationType="fade" visible={logoutVisible} onRequestClose={() => setLogoutVisible(false)}><View style={styles.modalOverlay}><View style={styles.modal}><View style={styles.modalIcon}><Ionicons name="log-out-outline" size={22} color={COLORS.danger} /></View><Text style={styles.modalTitle}>Log Out</Text><Text style={styles.modalText}>Are you sure you want to log out of TRAVIS?</Text><View style={styles.modalActions}><TouchableOpacity style={[styles.modalButton, styles.cancel]} onPress={() => setLogoutVisible(false)}><Text style={styles.cancelText}>Cancel</Text></TouchableOpacity><TouchableOpacity style={[styles.modalButton, styles.logout]} onPress={logout}><Text style={styles.logoutText}>Log Out</Text></TouchableOpacity></View></View></View></Modal>
  </>;
}

export default function TreasurerLayout() {
  const [user, setUser] = useState<TravisUser | null | undefined>(undefined);
  useEffect(() => { getStoredUser().then(setUser); }, []);
  if (user === undefined) return <View style={styles.loading}><ActivityIndicator color={COLORS.warning} /></View>;
  if (!user) return <Redirect href="/" />;
  if (!isTreasurerRole(user.role)) return <Redirect href="/(drawer)/dashboard" />;

  return <ImageBackground source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-municipal-hall.jpg` }} style={styles.background} resizeMode="cover"><View pointerEvents="none" style={styles.overlay} /><Drawer drawerContent={props => <TreasuryDrawer {...props} user={user} />} screenOptions={{ headerShown: true, headerStyle: { backgroundColor: '#F3F1EA' }, headerTintColor: '#102F49', headerTitle: () => <MunicipalHeaderTitle portal="Treasury Portal" />, headerRight: () => <MunicipalHeaderActions notificationRoute={'/(treasurer)/notifications' as Href} userName={user.full_name} />, headerShadowVisible: false, drawerType: 'slide', drawerStyle: { width: width * .8, maxWidth: 300, backgroundColor: '#073643' }, sceneStyle: { backgroundColor: 'transparent' }, overlayColor: 'rgba(15,23,42,.5)' }}>
    <Drawer.Screen name="dashboard" options={{ title: 'Dashboard' }} />
    <Drawer.Screen name="violations" options={{ title: 'Violations' }} />
    <Drawer.Screen name="payments" options={{ title: 'Payments' }} />
    <Drawer.Screen name="notifications" options={{ title: 'Notifications' }} />
    <Drawer.Screen name="profile" options={{ title: 'Profile' }} />
  </Drawer></ImageBackground>;
}

const styles = StyleSheet.create({
  background: { flex: 1, backgroundColor: '#E9E8E1' }, overlay: { ...StyleSheet.absoluteFillObject, backgroundColor: 'rgba(247,245,238,.62)' }, loading: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: COLORS.header }, drawer: { paddingTop: 0, paddingBottom: 20, flexGrow: 1, backgroundColor: '#073643' }, drawerHeader: { paddingVertical: 22, paddingHorizontal: 20, backgroundColor: '#082E3B', borderBottomWidth: 1, borderBottomColor: 'rgba(139,201,194,.14)', marginBottom: 8 }, brandRow: { flexDirection: 'row', alignItems: 'center' }, seal: { width: 42, height: 42, borderRadius: 21, borderWidth: 2, borderColor: 'rgba(255,255,255,.86)', marginRight: 11 }, logo: { fontSize: 18, fontWeight: '800', color: '#FFF', letterSpacing: 1 }, logoSub: { fontSize: 11, color: '#C6D6CF', marginTop: 1 }, sectionHeaderWrap: { paddingHorizontal: 20, paddingVertical: 8, marginTop: 10 }, sectionHeader: { color: '#7FB4AF', fontSize: 11, fontWeight: '700', letterSpacing: 1, textTransform: 'uppercase' }, drawerItem: { marginHorizontal: 12, marginVertical: 1, borderRadius: 11, borderWidth: 1, borderColor: 'transparent' }, activeItem: { backgroundColor: 'rgba(235,148,31,.25)', borderColor: 'rgba(244,174,70,.30)', borderRadius: 11 }, drawerLabel: { color: '#D5E6E4', fontSize: 14, fontWeight: '600', marginLeft: -8 }, activeLabel: { color: '#FFF', fontWeight: '700' }, bottomSpacer: { marginTop: 'auto', paddingHorizontal: 14, paddingTop: 20 }, userBadge: { flexDirection: 'row', alignItems: 'center', padding: 11, borderRadius: 13, backgroundColor: 'rgba(255,255,255,.08)', borderWidth: 1, borderColor: 'rgba(139,201,194,.16)' }, avatar: { width: 36, height: 36, borderRadius: 10, backgroundColor: COLORS.warning, alignItems: 'center', justifyContent: 'center', marginRight: 10 }, avatarText: { color: COLORS.header, fontWeight: '900', fontSize: 11 }, userName: { color: '#FFF', fontWeight: '700', fontSize: 12 }, userRole: { color: '#9FC1BD', fontSize: 10, marginTop: 2 }, modalOverlay: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24, backgroundColor: 'rgba(15,23,42,.66)' }, modal: { width: '100%', maxWidth: 380, padding: 22, borderRadius: 20, backgroundColor: COLORS.surface, alignItems: 'center', borderWidth: 1, borderColor: 'rgba(16,47,73,.22)' }, modalIcon: { width: 46, height: 46, borderRadius: 14, backgroundColor: 'rgba(200,75,69,.1)', alignItems: 'center', justifyContent: 'center' }, modalTitle: { color: COLORS.text, fontSize: 19, fontWeight: '800', marginTop: 12 }, modalText: { color: COLORS.secondary, textAlign: 'center', marginTop: 6 }, modalActions: { flexDirection: 'row', gap: 10, marginTop: 20 }, modalButton: { flex: 1, minHeight: 44, borderRadius: 11, alignItems: 'center', justifyContent: 'center' }, cancel: { borderWidth: 1, borderColor: 'rgba(16,47,73,.18)' }, logout: { backgroundColor: COLORS.danger }, cancelText: { color: COLORS.secondary, fontWeight: '800' }, logoutText: { color: '#FFF', fontWeight: '800' },
});
