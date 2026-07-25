import React, { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';
import { getStoredUser, TravisUser } from '../../utils/session';

export default function TreasurerProfile() {
  const [user, setUser] = useState<TravisUser | null>(null);
  useEffect(() => { getStoredUser().then(setUser); }, []);
  if (!user) return <View style={styles.center}><ActivityIndicator color="#087D78" /></View>;
  const field = (label: string, value: string) => <View style={styles.field}><Text style={styles.label}>{label}</Text><Text style={styles.value}>{value}</Text></View>;
  return <View style={styles.page}><View style={styles.hero}><View style={styles.avatar}><Text style={styles.initials}>{user.full_name.split(/\s+/).map(v => v[0]).slice(0, 2).join('').toUpperCase()}</Text></View><Text style={styles.name}>{user.full_name}</Text><Text style={styles.role}>{user.role} · TRAVIS Treasurer Portal</Text></View><View style={styles.card}>{field('Email address', user.email)}{field('Access role', user.role)}{field('Account access', 'Active session')}<Text style={styles.note}>Treasurer profiles are view-only. Contact an administrator to update account details or reset your password.</Text></View></View>;
}
const styles = StyleSheet.create({ page: { flex: 1, padding: 20, backgroundColor: '#F3F6FA' }, center: { flex: 1, alignItems: 'center', justifyContent: 'center' }, hero: { backgroundColor: '#0A1A30', padding: 25, borderRadius: 18, alignItems: 'center' }, avatar: { width: 70, height: 70, borderRadius: 35, backgroundColor: '#4FC3F7', alignItems: 'center', justifyContent: 'center' }, initials: { color: '#0A1A30', fontSize: 23, fontWeight: '900' }, name: { color: '#FFF', fontSize: 21, fontWeight: '800', marginTop: 12 }, role: { color: '#C9D8EA', marginTop: 4, fontSize: 12 }, card: { backgroundColor: '#FFF', borderRadius: 18, padding: 18, marginTop: 16 }, field: { paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: '#E2E8F0' }, label: { color: '#64748B', fontSize: 11, textTransform: 'uppercase' }, value: { color: '#10202C', fontWeight: '700', marginTop: 5 }, note: { color: '#64748B', lineHeight: 19, fontSize: 12, marginTop: 18 } });
