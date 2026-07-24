import React, { useState } from 'react';
import { Drawer } from 'expo-router/drawer';
import {
  DrawerContentScrollView,
  DrawerItem,
} from '@react-navigation/drawer';
import { View, Text, StyleSheet, Dimensions, TouchableOpacity, Modal, Platform, Alert } from 'react-native';
import { useRouter, usePathname, Href } from 'expo-router';
import { Ionicons, MaterialCommunityIcons } from '@expo/vector-icons';
import api from '../../api/axiosConfig';

const { width } = Dimensions.get('window');

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: '#F8FAFC',
  header: '#0F172A',
  headerAccent: '#1E293B',
  surface: '#FFFFFF',
  border: '#E2E8F0',
  textPrimary: '#0F172A',
  textSecondary: '#64748B',
  textTertiary: '#94A3B8',
  primary: '#2563EB',
  success: '#10B981',
  warning: '#F59E0B',
  danger: '#EF4444',
  neutral: '#94A3B8',
};

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });

const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

// ========== CUSTOM DRAWER CONTENT ==========
function CustomDrawerContent(props: any) {
  const router = useRouter();
  const pathname = usePathname();
  const [logoutModalVisible, setLogoutModalVisible] = useState(false);

  const isActive = (route: string) => {
    return pathname === route || pathname.startsWith(route + '/');
  };

  const DrawerItemWithIcon = ({
    label,
    icon,
    route,
  }: {
    label: string;
    icon: string;
    route: string;
  }) => {
    const active = isActive(route);
    return (
      <DrawerItem
        label={label}
        icon={({ size }) => (
          <Ionicons
            name={icon as any}
            size={18}
            color={active ? COLORS.primary : COLORS.textTertiary}
          />
        )}
        onPress={() => router.push(route as Href)}
        style={[
          styles.drawerItem,
          active && styles.activeDrawerItem,
        ]}
        labelStyle={[
          styles.drawerLabel,
          active && styles.activeDrawerLabel,
        ]}
      />
    );
  };

  const SectionHeader = ({ title }: { title: string }) => (
    <View style={styles.sectionHeaderContainer}>
      <Text style={styles.sectionHeader}>{title}</Text>
    </View>
  );

  // ===== UPDATED LOGOUT =====
  const handleLogout = async () => {
    setLogoutModalVisible(false);
    try {
      await api.post('logout.php');
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      router.replace('/login' as Href);
    }
  };

  return (
    <>
      <DrawerContentScrollView
        {...props}
        contentContainerStyle={styles.drawerContent}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.drawerHeader}>
          <View style={styles.brandRow}>
            <View style={styles.brandBadge}>
              <MaterialCommunityIcons name="traffic-light" size={16} color="#7DB4FF" />
            </View>
            <View>
              <Text style={styles.logoText}>TRAVIS</Text>
              <Text style={styles.logoSub}>Traffic Violation Analytics</Text>
            </View>
          </View>
        </View>

        {/* ===== OVERVIEW ===== */}
        <SectionHeader title="OVERVIEW" />
        <DrawerItemWithIcon
          label="Dashboard"
          icon="grid-outline"
          route="/dashboard"
        />

        {/* ===== ENFORCEMENT ===== */}
        <SectionHeader title="ENFORCEMENT" />
        <DrawerItemWithIcon
          label="Violations"
          icon="alert-circle-outline"
          route="/violations"
        />
        <DrawerItemWithIcon
          label="Payments"
          icon="cash-outline"
          route="/payments"
        />
        <DrawerItemWithIcon
          label="Alerts"
          icon="notifications-outline"
          route="/alerts"
        />

        {/* ===== ADMINISTRATION ===== */}
        <SectionHeader title="ADMINISTRATION" />
        <DrawerItemWithIcon
          label="Reports"
          icon="bar-chart-outline"
          route="/reports"
        />
        <DrawerItemWithIcon
          label="User Management"
          icon="people-outline"
          route="/users"
        />
        <DrawerItemWithIcon
          label="Public Website"
          icon="globe-outline"
          route="/public-website"
        />
        <DrawerItemWithIcon
          label="Settings"
          icon="settings-outline"
          route="/settings"
        />

        {/* Bottom spacer with user badge */}
        <View style={styles.bottomSpacer}>
          <TouchableOpacity
            style={styles.userBadge}
            onPress={() => setLogoutModalVisible(true)}
            activeOpacity={0.7}
          >
            <View style={styles.userAvatar}>
              <Text style={styles.userAvatarText}>ZR</Text>
            </View>
            <View style={styles.userInfo}>
              <Text style={styles.userName}>Zeth Ramzy</Text>
              <Text style={styles.userRole}>Administrator</Text>
            </View>
            <Ionicons name="log-out-outline" size={17} color={COLORS.textTertiary} />
          </TouchableOpacity>
        </View>
      </DrawerContentScrollView>

      {/* ===== LOGOUT CONFIRMATION MODAL ===== */}
      <Modal
        animationType="fade"
        transparent={true}
        visible={logoutModalVisible}
        onRequestClose={() => setLogoutModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalIconWrap}>
              <Ionicons name="log-out-outline" size={22} color={COLORS.danger} />
            </View>
            <Text style={styles.modalTitle}>Log Out</Text>
            <Text style={styles.modalMessage}>Are you sure you want to log out of TRAVIS?</Text>
            <View style={styles.modalActions}>
              <TouchableOpacity
                style={[styles.modalButton, styles.cancelButton]}
                onPress={() => setLogoutModalVisible(false)}
                activeOpacity={0.7}
              >
                <Text style={styles.cancelButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.logoutButton]}
                onPress={handleLogout}
                activeOpacity={0.85}
              >
                <Text style={styles.logoutButtonText}>Log Out</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </>
  );
}

// ========== MAIN DRAWER LAYOUT ==========
export default function DrawerLayout() {
  return (
    <Drawer
      drawerContent={(props) => <CustomDrawerContent {...props} />}
      screenOptions={{
        headerShown: true,
        headerStyle: {
          backgroundColor: COLORS.header,
        },
        headerTintColor: '#FFFFFF',
        headerTitleStyle: {
          fontWeight: '700',
          color: '#FFFFFF',
          letterSpacing: 0.3,
        },
        drawerActiveBackgroundColor: '#EFF6FF',
        drawerActiveTintColor: COLORS.primary,
        drawerInactiveTintColor: COLORS.textTertiary,
        drawerType: 'slide',
        drawerStyle: {
          width: width * 0.8,
          maxWidth: 300,
          backgroundColor: COLORS.bg,
        },
        overlayColor: 'rgba(15, 23, 42, 0.5)',
        headerShadowVisible: false,
      }}
    >
      {/* OVERVIEW */}
      <Drawer.Screen
        name="dashboard"
        options={{
          title: 'Dashboard',
          headerTitle: 'Dashboard',
        }}
      />

      {/* ENFORCEMENT */}
      <Drawer.Screen
        name="violations"
        options={{
          title: 'Violations',
          headerTitle: 'Violations',
        }}
      />
      <Drawer.Screen
        name="payments"
        options={{
          title: 'Payments',
          headerTitle: 'Payments',
        }}
      />
      <Drawer.Screen
        name="alerts"
        options={{
          title: 'Alerts',
          headerTitle: 'Alerts',
        }}
      />

      {/* ADMINISTRATION */}
      <Drawer.Screen
        name="reports"
        options={{
          title: 'Reports',
          headerTitle: 'Reports',
        }}
      />
      <Drawer.Screen
        name="users"
        options={{
          title: 'User Management',
          headerTitle: 'User Management',
        }}
      />
      <Drawer.Screen
        name="public-website"
        options={{
          title: 'Public Website',
          headerTitle: 'Public Website',
        }}
      />
      <Drawer.Screen
        name="settings"
        options={{
          title: 'Settings',
          headerTitle: 'Settings',
        }}
      />
    </Drawer>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  drawerContent: {
    paddingTop: 0,
    paddingBottom: 20,
    flexGrow: 1,
    backgroundColor: COLORS.bg,
  },

  drawerHeader: {
    paddingVertical: 22,
    paddingHorizontal: 20,
    backgroundColor: COLORS.header,
    marginBottom: 8,
  },
  brandRow: { flexDirection: 'row', alignItems: 'center' },
  brandBadge: {
    width: 32, height: 32, borderRadius: 10, backgroundColor: COLORS.headerAccent,
    justifyContent: 'center', alignItems: 'center', marginRight: 10,
  },
  logoText: {
    fontSize: 18,
    fontWeight: '800',
    color: '#FFFFFF',
    letterSpacing: 1,
  },
  logoSub: {
    fontSize: 11,
    color: '#94A3B8',
    marginTop: 1,
  },

  sectionHeaderContainer: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    marginTop: 10,
  },

  sectionHeader: {
    fontSize: 11,
    fontWeight: '700',
    color: COLORS.textTertiary,
    letterSpacing: 1,
    textTransform: 'uppercase',
  },

  drawerItem: {
    borderRadius: 12,
    marginHorizontal: 12,
    marginVertical: 1,
  },

  activeDrawerItem: {
    backgroundColor: '#EFF6FF',
    borderRadius: 12,
  },

  drawerLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.textSecondary,
    marginLeft: -8,
  },

  activeDrawerLabel: {
    color: COLORS.primary,
    fontWeight: '700',
  },

  bottomSpacer: {
    flex: 1,
    justifyContent: 'flex-end',
    paddingHorizontal: 16,
    paddingBottom: 8,
    marginTop: 16,
  },

  userBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 14,
    backgroundColor: COLORS.surface,
    borderWidth: 1,
    borderColor: COLORS.border,
    ...softShadow,
  },

  userAvatar: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#EFF6FF',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },

  userAvatarText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.primary,
    fontFamily: mono,
  },

  userInfo: { flex: 1 },

  userName: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textPrimary,
  },

  userRole: {
    fontSize: 11,
    color: COLORS.textTertiary,
    marginTop: 1,
  },

  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.6)',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 30,
  },
  modalContent: {
    backgroundColor: COLORS.surface,
    borderRadius: 20,
    padding: 22,
    width: '100%',
    alignItems: 'center',
    ...softShadow,
  },
  modalIconWrap: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.danger + '1A',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  modalTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: COLORS.textPrimary,
    marginBottom: 6,
  },
  modalMessage: {
    fontSize: 13,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 19,
    marginBottom: 20,
  },
  modalActions: {
    flexDirection: 'row',
    gap: 10,
    width: '100%',
  },
  modalButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
  },
  cancelButton: {
    backgroundColor: COLORS.bg,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  cancelButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: COLORS.textSecondary,
  },
  logoutButton: {
    backgroundColor: COLORS.danger,
  },
  logoutButtonText: {
    fontSize: 13,
    fontWeight: '700',
    color: '#fff',
  },
});