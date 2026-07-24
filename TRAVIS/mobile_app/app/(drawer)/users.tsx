import React, { useState, useCallback } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  ActivityIndicator,
  StatusBar,
  RefreshControl,
  Modal,
  Alert,
  FlatList,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import api from '../../api/axiosConfig';

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: 'rgba(247, 245, 238, 0.74)',
  header: '#102F49',
  headerAccent: '#16445D',
  surface: 'rgba(255, 253, 247, 0.92)',
  border: 'rgba(16, 47, 73, 0.24)',
  textPrimary: '#10202C',
  textSecondary: '#526B64',
  textTertiary: '#72847D',
  primary: '#087D78',
  success: '#15966F',
  warning: '#EB941F',
  danger: '#C84B45',
  neutral: '#8B9B96',
  purple: '#8B5CF6',
};

const mono = Platform.select({ ios: 'Courier', android: 'monospace', default: 'monospace' });
const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

// ========== TYPES ==========
interface User {
  user_id: number;
  full_name: string;
  email: string;
  role: 'Administrator' | 'Treasury Personnel';
  status: 'active' | 'inactive' | 'suspended' | 'pending';
  created_at: string;
  updated_at: string;
}

// ========== HELPERS ==========
const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map(part => part[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
};

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'active') return COLORS.success;
  if (s === 'pending') return COLORS.warning;
  if (s === 'inactive') return COLORS.neutral;
  if (s === 'suspended') return COLORS.danger;
  return COLORS.neutral;
};

const roleColor = (role: string): string => {
  return role === 'Administrator' ? COLORS.primary : COLORS.purple;
};

// ========== SCREEN ==========
export default function UsersScreen() {
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [stats, setStats] = useState({ totalUsers: 0, active: 0, inactive: 0, suspended: 0 });
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [addModalVisible, setAddModalVisible] = useState(false);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [resetModalVisible, setResetModalVisible] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  const [formName, setFormName] = useState('');
  const [formEmail, setFormEmail] = useState('');
  const [formRole, setFormRole] = useState<'Administrator' | 'Treasury Personnel'>('Administrator');
  const [formStatus, setFormStatus] = useState<'active' | 'inactive' | 'suspended' | 'pending'>('active');
  const [formPassword, setFormPassword] = useState('');
  const [formConfirmPassword, setFormConfirmPassword] = useState('');
  const [formNewPassword, setFormNewPassword] = useState('');
  const [formConfirmNewPassword, setFormConfirmNewPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // ===== FETCH USERS =====
  const fetchUsers = async () => {
    try {
      setLoading(true);
      const res = await api.get('get_users.php');
      if (res.data.success) {
        const data = res.data.data;
        setUsers(data);
        const active = data.filter((u: any) => u.status === 'active').length;
        const inactive = data.filter((u: any) => u.status === 'inactive').length;
        const suspended = data.filter((u: any) => u.status === 'suspended').length;
        setStats({ totalUsers: data.length, active, inactive, suspended });
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to load users.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchUsers();
    }, [])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchUsers();
  };

  // ===== CRUD OPERATIONS =====
  const handleAddUser = async () => {
    if (!formName || !formEmail || !formPassword || !formConfirmPassword) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    if (formPassword.length < 8) {
      Alert.alert('Error', 'Password must be at least 8 characters.');
      return;
    }
    if (formPassword !== formConfirmPassword) {
      Alert.alert('Error', 'Passwords do not match.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post('add_user.php', {
        full_name: formName,
        email: formEmail,
        role: formRole,
        status: formStatus,
        password: formPassword,
      });
      if (res.data.success) {
        Alert.alert('Success', 'User created.');
        setAddModalVisible(false);
        resetForm();
        fetchUsers();
      } else {
        Alert.alert('Error', res.data.error || 'Failed to create user.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.error || 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleEditUser = async () => {
    if (!selectedUser) return;
    if (!formName || !formEmail) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post('update_user.php', {
        user_id: selectedUser.user_id,
        full_name: formName,
        email: formEmail,
        role: formRole,
        status: formStatus,
      });
      if (res.data.success) {
        Alert.alert('Success', 'User updated.');
        setEditModalVisible(false);
        resetForm();
        fetchUsers();
      } else {
        Alert.alert('Error', res.data.error || 'Failed to update.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleResetPassword = async () => {
    if (!selectedUser) return;
    if (!formNewPassword || !formConfirmNewPassword) {
      Alert.alert('Error', 'Please fill in all password fields.');
      return;
    }
    if (formNewPassword.length < 8) {
      Alert.alert('Error', 'Password must be at least 8 characters.');
      return;
    }
    if (formNewPassword !== formConfirmNewPassword) {
      Alert.alert('Error', 'Passwords do not match.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await api.post('reset_password.php', {
        user_id: selectedUser.user_id,
        new_password: formNewPassword,
      });
      if (res.data.success) {
        Alert.alert('Success', 'Password reset.');
        setResetModalVisible(false);
        resetForm();
      } else {
        Alert.alert('Error', res.data.error || 'Failed to reset.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleStatusChange = async (user: User, newStatus: string) => {
    Alert.alert(
      'Confirm',
      `Change ${user.full_name}'s status to ${newStatus.toUpperCase()}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            try {
              const res = await api.post('update_user.php', {
                user_id: user.user_id,
                status: newStatus,
              });
              if (res.data.success) {
                Alert.alert('Success', 'Status updated.');
                fetchUsers();
              } else {
                Alert.alert('Error', res.data.error || 'Failed to update status.');
              }
            } catch (error) {
              Alert.alert('Error', 'Network error.');
            }
          },
        },
      ]
    );
  };

  const resetForm = () => {
    setFormName('');
    setFormEmail('');
    setFormRole('Administrator');
    setFormStatus('active');
    setFormPassword('');
    setFormConfirmPassword('');
    setFormNewPassword('');
    setFormConfirmNewPassword('');
  };

  const openEditModal = (user: User) => {
    setSelectedUser(user);
    setFormName(user.full_name);
    setFormEmail(user.email);
    setFormRole(user.role);
    setFormStatus(user.status);
    setEditModalVisible(true);
  };

  const openResetModal = (user: User) => {
    setSelectedUser(user);
    setFormNewPassword('');
    setFormConfirmNewPassword('');
    setResetModalVisible(true);
  };

  // ===== FILTER =====
  const filteredUsers = users.filter(user => {
    const matchSearch = search === '' ||
      user.full_name.toLowerCase().includes(search.toLowerCase()) ||
      user.email.toLowerCase().includes(search.toLowerCase());
    const matchRole = roleFilter === '' || user.role === roleFilter;
    const matchStatus = statusFilter === '' || user.status === statusFilter;
    return matchSearch && matchRole && matchStatus;
  });

  // ========== RENDER HELPERS ==========
  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={styles.statCard}>
      <View style={[styles.statAccentDot, { backgroundColor: color }]} />
      <Text style={styles.statLabel}>{label.toUpperCase()}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  const renderUserItem = ({ item }: { item: User }) => (
    <View style={styles.userItem}>
      <View style={styles.userHeader}>
        <View style={styles.userInfo}>
          <View style={[styles.avatar, { backgroundColor: roleColor(item.role) + '1A' }]}>
            <Text style={[styles.avatarText, { color: roleColor(item.role) }]}>
              {getInitials(item.full_name)}
            </Text>
          </View>
          <View>
            <Text style={styles.userName}>{item.full_name}</Text>
            <Text style={styles.userEmail}>{item.email}</Text>
          </View>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
          <View style={[styles.statusBadgeDot, { backgroundColor: statusColor(item.status) }]} />
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>

      <View style={styles.userDetails}>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>ROLE</Text>
          <Text style={[styles.detailValue, { color: roleColor(item.role) }]}>{item.role}</Text>
        </View>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>CREATED</Text>
          <Text style={styles.detailValue}>{item.created_at}</Text>
        </View>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>LAST UPDATED</Text>
          <Text style={styles.detailValue}>{item.updated_at}</Text>
        </View>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButton} onPress={() => openEditModal(item)} activeOpacity={0.7}>
          <Ionicons name="create-outline" size={13} color={COLORS.textPrimary} />
          <Text style={styles.actionText}>Edit</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionButton} onPress={() => openResetModal(item)} activeOpacity={0.7}>
          <Ionicons name="key-outline" size={13} color={COLORS.textPrimary} />
          <Text style={styles.actionText}>Reset</Text>
        </TouchableOpacity>
        {item.status === 'active' ? (
          <TouchableOpacity
            style={[styles.actionButton, { backgroundColor: COLORS.warning + '1A' }]}
            onPress={() => handleStatusChange(item, 'inactive')}
            activeOpacity={0.7}
          >
            <Ionicons name="pause-outline" size={13} color={COLORS.warning} />
            <Text style={[styles.actionText, { color: COLORS.warning }]}>Deactivate</Text>
          </TouchableOpacity>
        ) : item.status === 'inactive' ? (
          <TouchableOpacity
            style={[styles.actionButton, { backgroundColor: COLORS.success + '1A' }]}
            onPress={() => handleStatusChange(item, 'active')}
            activeOpacity={0.7}
          >
            <Ionicons name="play-outline" size={13} color={COLORS.success} />
            <Text style={[styles.actionText, { color: COLORS.success }]}>Activate</Text>
          </TouchableOpacity>
        ) : null}
        {item.status !== 'suspended' && (
          <TouchableOpacity
            style={[styles.actionButton, { backgroundColor: COLORS.danger + '1A' }]}
            onPress={() => handleStatusChange(item, 'suspended')}
            activeOpacity={0.7}
          >
            <Ionicons name="ban-outline" size={13} color={COLORS.danger} />
            <Text style={[styles.actionText, { color: COLORS.danger }]}>Suspend</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>LOADING USERS…</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor={COLORS.header} />
      <ScrollView
        style={styles.container}
        contentContainerStyle={styles.scrollContent}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Hero */}
        <View style={styles.heroCard}>
          <View style={styles.brandRow}>
            <View style={styles.brandBadge}>
              <Ionicons name="people-outline" size={16} color="#7DB4FF" />
            </View>
            <View>
              <Text style={styles.brandName}>USER MANAGEMENT</Text>
              <Text style={styles.brandSubtitle}>Manage Administrator and Treasury Personnel accounts</Text>
            </View>
          </View>
        </View>

        {/* Stats Cards */}
        <View style={styles.statsRow}>
          {renderStatCard('Total Users', stats.totalUsers, COLORS.primary)}
          {renderStatCard('Active', stats.active, COLORS.success)}
          {renderStatCard('Inactive', stats.inactive, COLORS.warning)}
          {renderStatCard('Suspended', stats.suspended, COLORS.danger)}
        </View>

        {/* Users List */}
        <Text style={styles.sectionLabel}>SYSTEM USERS</Text>
        <View style={styles.panel}>
          <Text style={styles.panelSub}>Passwords are stored as secure hashes and are never displayed.</Text>

          {/* Search and Filters */}
          <View style={styles.filterContainer}>
            <View style={styles.searchWrap}>
              <Ionicons name="search-outline" size={15} color={COLORS.textTertiary} style={{ marginRight: 8 }} />
              <TextInput
                style={styles.searchInput}
                placeholder="Search name or email..."
                placeholderTextColor={COLORS.textTertiary}
                value={search}
                onChangeText={setSearch}
              />
            </View>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={roleFilter}
                onValueChange={setRoleFilter}
                style={styles.picker}
                dropdownIconColor={COLORS.primary}
              >
                <Picker.Item label="All Roles" value="" />
                <Picker.Item label="Administrator" value="Administrator" />
                <Picker.Item label="Treasury Personnel" value="Treasury Personnel" />
              </Picker>
            </View>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={statusFilter}
                onValueChange={setStatusFilter}
                style={styles.picker}
                dropdownIconColor={COLORS.primary}
              >
                <Picker.Item label="All Statuses" value="" />
                <Picker.Item label="Active" value="active" />
                <Picker.Item label="Inactive" value="inactive" />
                <Picker.Item label="Suspended" value="suspended" />
                <Picker.Item label="Pending" value="pending" />
              </Picker>
            </View>
            <TouchableOpacity
              style={styles.clearButton}
              onPress={() => {
                setSearch('');
                setRoleFilter('');
                setStatusFilter('');
              }}
              activeOpacity={0.7}
            >
              <Text style={styles.clearText}>Clear Filters</Text>
            </TouchableOpacity>
          </View>

          {/* Add User Button */}
          <TouchableOpacity
            style={styles.addButton}
            onPress={() => {
              resetForm();
              setAddModalVisible(true);
            }}
            activeOpacity={0.85}
          >
            <Ionicons name="person-add-outline" size={16} color="#fff" />
            <Text style={styles.addButtonText}>Add User</Text>
          </TouchableOpacity>

          {/* User List */}
          {filteredUsers.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="people-outline" size={16} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>No user accounts matched your current search and filters.</Text>
            </View>
          ) : (
            <>
              <View style={styles.panelDivider} />
              <FlatList
                data={filteredUsers}
                renderItem={renderUserItem}
                keyExtractor={item => item.user_id.toString()}
                scrollEnabled={false}
                ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
              />
            </>
          )}
        </View>
      </ScrollView>

      {/* Add User Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={addModalVisible}
        onRequestClose={() => setAddModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Add User Account</Text>
                <Text style={styles.modalSub}>Create an Administrator or Treasury Personnel account.</Text>
              </View>
              <TouchableOpacity onPress={() => setAddModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Full Name</Text>
                <TextInput
                  style={styles.input}
                  value={formName}
                  onChangeText={setFormName}
                  placeholder="Enter full name"
                  placeholderTextColor={COLORS.textTertiary}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Email Address</Text>
                <TextInput
                  style={styles.input}
                  value={formEmail}
                  onChangeText={setFormEmail}
                  placeholder="name@example.gov.ph"
                  placeholderTextColor={COLORS.textTertiary}
                  keyboardType="email-address"
                  autoCapitalize="none"
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Role</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formRole}
                    onValueChange={(value) => setFormRole(value)}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Administrator" value="Administrator" />
                    <Picker.Item label="Treasury Personnel" value="Treasury Personnel" />
                  </Picker>
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Initial Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formStatus}
                    onValueChange={(value) => setFormStatus(value)}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Active" value="active" />
                    <Picker.Item label="Pending" value="pending" />
                    <Picker.Item label="Inactive" value="inactive" />
                    <Picker.Item label="Suspended" value="suspended" />
                  </Picker>
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Temporary Password</Text>
                <TextInput
                  style={styles.input}
                  value={formPassword}
                  onChangeText={setFormPassword}
                  placeholder="At least 8 characters"
                  placeholderTextColor={COLORS.textTertiary}
                  secureTextEntry
                />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Confirm Password</Text>
                <TextInput
                  style={styles.input}
                  value={formConfirmPassword}
                  onChangeText={setFormConfirmPassword}
                  placeholder="Confirm password"
                  placeholderTextColor={COLORS.textTertiary}
                  secureTextEntry
                />
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setAddModalVisible(false)}
                  disabled={submitting}
                  activeOpacity={0.7}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleAddUser}
                  disabled={submitting}
                  activeOpacity={0.85}
                >
                  {submitting ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.confirmButtonText}>Create User</Text>
                  )}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* Edit User Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={editModalVisible}
        onRequestClose={() => setEditModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Edit User Account</Text>
                <Text style={styles.modalSub}>{selectedUser?.email}</Text>
              </View>
              <TouchableOpacity onPress={() => setEditModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Full Name</Text>
                <TextInput
                  style={styles.input}
                  value={formName}
                  onChangeText={setFormName}
                  placeholder="Full name"
                  placeholderTextColor={COLORS.textTertiary}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Email Address</Text>
                <TextInput
                  style={styles.input}
                  value={formEmail}
                  onChangeText={setFormEmail}
                  placeholder="Email"
                  placeholderTextColor={COLORS.textTertiary}
                  keyboardType="email-address"
                  autoCapitalize="none"
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Role</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formRole}
                    onValueChange={(value) => setFormRole(value)}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Administrator" value="Administrator" />
                    <Picker.Item label="Treasury Personnel" value="Treasury Personnel" />
                  </Picker>
                </View>
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formStatus}
                    onValueChange={(value) => setFormStatus(value)}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Active" value="active" />
                    <Picker.Item label="Inactive" value="inactive" />
                    <Picker.Item label="Suspended" value="suspended" />
                    <Picker.Item label="Pending" value="pending" />
                  </Picker>
                </View>
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setEditModalVisible(false)}
                  disabled={submitting}
                  activeOpacity={0.7}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleEditUser}
                  disabled={submitting}
                  activeOpacity={0.85}
                >
                  {submitting ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.confirmButtonText}>Save Changes</Text>
                  )}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* Reset Password Modal */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={resetModalVisible}
        onRequestClose={() => setResetModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { maxHeight: 400 }]}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Reset Password</Text>
                <Text style={styles.modalSub}>{selectedUser?.full_name}</Text>
              </View>
              <TouchableOpacity onPress={() => setResetModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>New Password</Text>
                <TextInput
                  style={styles.input}
                  value={formNewPassword}
                  onChangeText={setFormNewPassword}
                  placeholder="At least 8 characters"
                  placeholderTextColor={COLORS.textTertiary}
                  secureTextEntry
                />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Confirm New Password</Text>
                <TextInput
                  style={styles.input}
                  value={formConfirmNewPassword}
                  onChangeText={setFormConfirmNewPassword}
                  placeholder="Confirm new password"
                  placeholderTextColor={COLORS.textTertiary}
                  secureTextEntry
                />
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setResetModalVisible(false)}
                  disabled={submitting}
                  activeOpacity={0.7}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleResetPassword}
                  disabled={submitting}
                  activeOpacity={0.85}
                >
                  {submitting ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.confirmButtonText}>Reset Password</Text>
                  )}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.bg },
  loadingText: { marginTop: 14, fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, fontFamily: mono },
  scrollContent: { paddingHorizontal: 20, paddingTop: 18, paddingBottom: 20 },

  heroCard: {
    backgroundColor: COLORS.header, borderRadius: 22, padding: 20, marginBottom: 16,
    ...softShadow, shadowOpacity: 0.18,
  },
  brandRow: { flexDirection: 'row', alignItems: 'center' },
  brandBadge: {
    width: 32, height: 32, borderRadius: 10, backgroundColor: COLORS.headerAccent,
    justifyContent: 'center', alignItems: 'center', marginRight: 10,
  },
  brandName: { fontSize: 16, fontWeight: '800', color: '#FFFFFF', letterSpacing: 1 },
  brandSubtitle: { fontSize: 11, color: '#94A3B8', marginTop: 2, maxWidth: 260 },

  statsRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', marginBottom: 4 },
  statCard: {
    backgroundColor: COLORS.surface, borderRadius: 16, padding: 14, width: '48%',
    marginBottom: 12, borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  statAccentDot: { width: 8, height: 8, borderRadius: 4, marginBottom: 8 },
  statLabel: { fontSize: 10, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6, marginBottom: 4 },
  statValue: { fontSize: 20, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },

  sectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12, marginTop: 4 },
  panel: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 20,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  panelSub: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 16, lineHeight: 17 },
  panelDivider: { height: 1, backgroundColor: COLORS.border, marginVertical: 14 },

  filterContainer: { marginBottom: 4 },
  searchWrap: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.bg, borderRadius: 12,
    borderWidth: 1, borderColor: COLORS.border, paddingHorizontal: 12, height: 46, marginBottom: 8,
  },
  searchInput: { flex: 1, fontSize: 14, color: COLORS.textPrimary, height: 46 },
  pickerWrapper: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    height: 46, justifyContent: 'center', marginBottom: 8, overflow: 'hidden',
  },
  picker: { height: 46, width: '100%', color: COLORS.textPrimary },
  clearButton: {
    backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingVertical: 10, borderRadius: 12, alignItems: 'center', marginBottom: 4,
  },
  clearText: { fontSize: 13, color: COLORS.primary, fontWeight: '700' },

  addButton: {
    flexDirection: 'row', backgroundColor: COLORS.primary, paddingVertical: 13, borderRadius: 12,
    alignItems: 'center', justifyContent: 'center', marginBottom: 6, gap: 6, ...softShadow, shadowOpacity: 0.15,
  },
  addButtonText: { color: '#fff', fontSize: 14, fontWeight: '700' },

  emptyState: { flexDirection: 'row', alignItems: 'center', paddingVertical: 20, gap: 8, justifyContent: 'center' },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', flexShrink: 1 },

  userItem: {
    backgroundColor: COLORS.bg, borderRadius: 14, padding: 14,
    borderWidth: 1, borderColor: COLORS.border,
  },
  userHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 },
  userInfo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  avatar: {
    width: 42, height: 42, borderRadius: 21, justifyContent: 'center', alignItems: 'center', marginRight: 12,
  },
  avatarText: { fontSize: 14, fontWeight: '700', fontFamily: mono },
  userName: { fontSize: 14, fontWeight: '700', color: COLORS.textPrimary },
  userEmail: { fontSize: 12, color: COLORS.textTertiary, marginTop: 1 },
  statusBadge: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8,
  },
  statusBadgeDot: { width: 5, height: 5, borderRadius: 2.5, marginRight: 5 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.4, fontFamily: mono },

  userDetails: { flexDirection: 'row', flexWrap: 'wrap', marginBottom: 10 },
  userDetail: { marginRight: 18, marginBottom: 4 },
  detailLabel: { fontSize: 9, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.5, marginBottom: 2 },
  detailValue: { fontSize: 12, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },

  actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  actionButton: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 10, paddingVertical: 6,
    borderRadius: 8, backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border,
  },
  actionText: { fontSize: 11, fontWeight: '700', color: COLORS.textPrimary, marginLeft: 4 },

  modalOverlay: {
    flex: 1, backgroundColor: 'rgba(15,23,42,0.6)', justifyContent: 'center', alignItems: 'center',
  },
  modalContent: {
    backgroundColor: COLORS.surface, borderRadius: 22, width: '92%', maxHeight: '85%', paddingBottom: 20,
  },
  modalHeader: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start',
    padding: 20, borderBottomWidth: 1, borderBottomColor: COLORS.border,
  },
  modalTitle: { fontSize: 17, fontWeight: '700', color: COLORS.textPrimary },
  modalSub: { fontSize: 12, color: COLORS.textTertiary, marginTop: 2, maxWidth: 240 },
  modalCloseButton: {
    width: 28, height: 28, borderRadius: 14, backgroundColor: COLORS.bg,
    justifyContent: 'center', alignItems: 'center',
  },
  modalBody: { padding: 20 },
  formGroup: { marginBottom: 14 },
  label: { fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 0.3, marginBottom: 6 },
  input: {
    backgroundColor: COLORS.bg, borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, fontSize: 14, height: 46, color: COLORS.textPrimary,
  },
  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 16 },
  modalButton: { paddingVertical: 12, paddingHorizontal: 20, borderRadius: 12, minWidth: 100, alignItems: 'center' },
  cancelButton: { backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border },
  confirmButton: { backgroundColor: COLORS.primary, ...softShadow, shadowOpacity: 0.15 },
  cancelButtonText: { fontSize: 13, fontWeight: '700', color: COLORS.textSecondary },
  confirmButtonText: { fontSize: 13, fontWeight: '700', color: '#fff' },
});
