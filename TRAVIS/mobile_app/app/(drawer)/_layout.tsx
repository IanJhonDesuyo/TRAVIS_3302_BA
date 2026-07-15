import React from 'react';
import { Drawer } from 'expo-router/drawer';
import {
  DrawerContentScrollView,
  DrawerItem,
} from '@react-navigation/drawer';
import { View, Text, StyleSheet, Dimensions } from 'react-native';
import { useRouter, usePathname, Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

const { width } = Dimensions.get('window');

// ========== CUSTOM DRAWER CONTENT ==========
function CustomDrawerContent(props: any) {
  const router = useRouter();
  const pathname = usePathname();

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
            size={size}
            color={active ? '#1e3a5f' : '#6b7f8f'}
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

  return (
    <DrawerContentScrollView
      {...props}
      contentContainerStyle={styles.drawerContent}
      showsVerticalScrollIndicator={false}
    >
      {/* Header */}
      <View style={styles.drawerHeader}>
        <View style={styles.logoContainer}>
          <Text style={styles.logoText}>TRAVIS</Text>
          <Text style={styles.logoSub}>Traffic Violation Analytics</Text>
        </View>
      </View>

      <View style={styles.divider} />

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
        <View style={styles.userBadge}>
          <View style={styles.userAvatar}>
            <Text style={styles.userAvatarText}>ZR</Text>
          </View>
          <View>
            <Text style={styles.userName}>Zeth Ramzy</Text>
            <Text style={styles.userRole}>Administrator</Text>
          </View>
        </View>
      </View>
    </DrawerContentScrollView>
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
          backgroundColor: '#eef2f6',
        },
        headerTintColor: '#1e3a5f',
        headerTitleStyle: {
          fontWeight: '600',
          color: '#1e3a5f',
        },
        drawerActiveBackgroundColor: '#d6e0ea',
        drawerActiveTintColor: '#1e3a5f',
        drawerInactiveTintColor: '#6b7f8f',
        drawerType: 'slide',
        drawerStyle: {
          width: width * 0.8,
          maxWidth: 300,
          backgroundColor: '#f5f7fa',
        },
        overlayColor: 'rgba(30, 58, 95, 0.3)',
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

// ========== STYLES - GRAY BLUE THEME ==========
const styles = StyleSheet.create({
  drawerContent: {
    paddingTop: 0,
    paddingBottom: 20,
    flexGrow: 1,
    backgroundColor: '#f5f7fa',
  },

  drawerHeader: {
    paddingVertical: 24,
    paddingHorizontal: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
    marginBottom: 4,
    backgroundColor: '#eef2f6',
  },

  logoContainer: {
    alignItems: 'center',
  },

  logoText: {
    fontSize: 22,
    fontWeight: '700',
    color: '#1e3a5f',
    letterSpacing: 0.5,
  },

  logoSub: {
    fontSize: 12,
    color: '#6b7f8f',
    marginTop: 2,
    fontWeight: '400',
  },

  divider: {
    height: 1,
    backgroundColor: '#e2e8f0',
    marginHorizontal: 16,
    marginVertical: 4,
  },

  sectionHeaderContainer: {
    paddingHorizontal: 20,
    paddingVertical: 8,
    marginTop: 4,
  },

  sectionHeader: {
    fontSize: 11,
    fontWeight: '600',
    color: '#8a9baa',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
  },

  drawerItem: {
    borderRadius: 8,
    marginHorizontal: 12,
    marginVertical: 1,
  },

  activeDrawerItem: {
    backgroundColor: '#d6e0ea',
    borderRadius: 8,
  },

  drawerLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#6b7f8f',
  },

  activeDrawerLabel: {
    color: '#1e3a5f',
    fontWeight: '600',
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
    paddingHorizontal: 16,
    borderRadius: 12,
    backgroundColor: '#eef2f6',
    borderWidth: 1,
    borderColor: '#dce3ea',
  },

  userAvatar: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#d6e0ea',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },

  userAvatarText: {
    fontSize: 14,
    fontWeight: '700',
    color: '#1e3a5f',
  },

  userName: {
    fontSize: 14,
    fontWeight: '600',
    color: '#1e3a5f',
  },

  userRole: {
    fontSize: 11,
    color: '#8a9baa',
    fontWeight: '400',
  },
});