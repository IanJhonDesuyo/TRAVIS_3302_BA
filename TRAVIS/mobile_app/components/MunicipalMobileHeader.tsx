import { Ionicons } from '@expo/vector-icons';
import { Href, useRouter } from 'expo-router';
import React from 'react';
import { StyleSheet, Text, TouchableOpacity, View } from 'react-native';

type Props = { portal: string; notificationRoute: Href; userName: string };

export function MunicipalHeaderTitle({ portal }: Pick<Props, 'portal'>) {
  return <View style={styles.titleWrap}><Text style={styles.title} numberOfLines={1}>Municipality of Nasugbu</Text><Text style={styles.subtitle} numberOfLines={1}>Traffic Management Office · {portal}</Text></View>;
}

export function MunicipalHeaderActions({ notificationRoute, userName }: Omit<Props, 'portal'>) {
  const router = useRouter();
  const initials = userName.split(/\s+/).filter(Boolean).map(part => part[0]).slice(0, 2).join('').toUpperCase() || 'TR';
  return <View style={styles.actions}>
    <TouchableOpacity style={styles.iconButton} onPress={() => router.push(notificationRoute)} accessibilityLabel="Open notifications"><Ionicons name="notifications-outline" size={18} color="#087D78" /></TouchableOpacity>
    <View style={styles.avatar}><Text style={styles.avatarText}>{initials}</Text></View>
  </View>;
}

const styles = StyleSheet.create({
  titleWrap: { minWidth: 0, paddingLeft: 2 },
  title: { color: '#10202C', fontSize: 13, fontWeight: '800' },
  subtitle: { color: '#526B64', fontSize: 8.5, fontWeight: '600', marginTop: 2 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 8, marginRight: 12 },
  iconButton: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 11, backgroundColor: 'rgba(255,255,255,.94)', borderWidth: 1, borderColor: 'rgba(16,47,73,.16)' },
  avatar: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 19, backgroundColor: '#087D78', borderWidth: 2, borderColor: 'rgba(255,255,255,.9)' },
  avatarText: { color: '#fff', fontSize: 11, fontWeight: '900' },
});
