import React, { useState, useEffect } from 'react';
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
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';

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

// ========== MOCK DATA (replace with API calls) ==========
const mockUsers: User[] = [
  {
    user_id: 1,
    full_name: 'Zeth Ramzy Pagcaliwagan',
    email: 'admin@travis.com',
    role: 'Administrator',
    status: 'active',
    created_at: '2026-07-15 02:45:00',
    updated_at: '2026-07-15 02:45:00',
  },
  {
    user_id: 2,
    full_name: 'Maria Santos',
    email: 'treasury@travis.com',
    role: 'Treasury Personnel',
    status: 'active',
    created_at: '2026-07-16 09:00:00',
    updated_at: '2026-07-16 09:00:00',
  },
  {
    user_id: 3,
    full_name: 'Juan Dela Cruz',
    email: 'juan@travis.com',
    role: 'Treasury Personnel',
    status: 'inactive',
    created_at: '2026-07-14 11:30:00',
    updated_at: '2026-07-15 08:20:00',
  },
];

const mockStats = {
  totalUsers: 3,
  active: 2,
  inactive: 1,
  suspended: 0,
};

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
  if (s === 'active') return '#16a34a';
  if (s === 'pending') return '#f59e0b';
  if (s === 'inactive') return '#6b7280';
  if (s === 'suspended') return '#dc2626';
  return '#6b7280';
};

const roleColor = (role: string): string => {
  return role === 'Administrator' ? '#2563eb' : '#8b5cf6';
};

// ========== SCREEN ==========
export default function UsersScreen() {
  const router = useRouter();

  // State
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [users, setUsers] = useState<User[]>([]);
  const [stats, setStats] = useState(mockStats);

  // Filters
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Modal states
  const [addModalVisible, setAddModalVisible] = useState(false);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [resetModalVisible, setResetModalVisible] = useState(false);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  // Form states for add/edit
  const [formName, setFormName] = useState('');
  const [formEmail, setFormEmail] = useState('');
  const [formRole, setFormRole] = useState<'Administrator' | 'Treasury Personnel'>('Administrator');
  const [formStatus, setFormStatus] = useState<'active' | 'inactive' | 'suspended' | 'pending'>('active');
  const [formPassword, setFormPassword] = useState('');
  const [formConfirmPassword, setFormConfirmPassword] = useState('');
  const [formNewPassword, setFormNewPassword] = useState('');
  const [formConfirmNewPassword, setFormConfirmNewPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Load data on mount
  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    // Replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 800));
    setUsers(mockUsers);
    setStats(mockStats);
    setLoading(false);
    setRefreshing(false);
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // Filter users
  const filteredUsers = users.filter(user => {
    const matchSearch = search === '' ||
      user.full_name.toLowerCase().includes(search.toLowerCase()) ||
      user.email.toLowerCase().includes(search.toLowerCase());
    const matchRole = roleFilter === '' || user.role === roleFilter;
    const matchStatus = statusFilter === '' || user.status === statusFilter;
    return matchSearch && matchRole && matchStatus;
  });

  // Handle Add User
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
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert('Success', 'User account created successfully.');
    setSubmitting(false);
    setAddModalVisible(false);
    resetForm();
    fetchData();
  };

  // Handle Edit User
  const handleEditUser = async () => {
    if (!selectedUser) return;
    if (!formName || !formEmail) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }

    setSubmitting(true);
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert('Success', 'User account updated successfully.');
    setSubmitting(false);
    setEditModalVisible(false);
    setSelectedUser(null);
    resetForm();
    fetchData();
  };

  // Handle Reset Password
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
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert('Success', 'Password reset successfully.');
    setSubmitting(false);
    setResetModalVisible(false);
    setSelectedUser(null);
    resetForm();
  };

  // Handle status change
  const handleStatusChange = async (user: User, newStatus: string) => {
    Alert.alert(
      'Confirm Status Change',
      `Are you sure you want to change ${user.full_name}'s status to ${newStatus.toUpperCase()}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            // Simulate API call
            await new Promise(resolve => setTimeout(resolve, 500));
            // Update local state
            const updatedUsers = users.map(u =>
              u.user_id === user.user_id ? { ...u, status: newStatus as any } : u
            );
            setUsers(updatedUsers);
            // Update stats
            const active = updatedUsers.filter(u => u.status === 'active').length;
            const inactive = updatedUsers.filter(u => u.status === 'inactive').length;
            const suspended = updatedUsers.filter(u => u.status === 'suspended').length;
            setStats({
              totalUsers: updatedUsers.length,
              active,
              inactive,
              suspended,
            });
            Alert.alert('Success', `User status updated to ${newStatus.toUpperCase()}.`);
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

  // Render stats cards
  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );

  // Render user item
  const renderUserItem = ({ item }: { item: User }) => (
    <View style={styles.userItem}>
      <View style={styles.userHeader}>
        <View style={styles.userInfo}>
          <View style={[styles.avatar, { backgroundColor: roleColor(item.role) + '30' }]}>
            <Text style={[styles.avatarText, { color: roleColor(item.role) }]}>
              {getInitials(item.full_name)}
            </Text>
          </View>
          <View>
            <Text style={styles.userName}>{item.full_name}</Text>
            <Text style={styles.userEmail}>{item.email}</Text>
          </View>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>

      <View style={styles.userDetails}>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>Role</Text>
          <Text style={[styles.detailValue, { color: roleColor(item.role) }]}>{item.role}</Text>
        </View>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>Created</Text>
          <Text style={styles.detailValue}>{item.created_at}</Text>
        </View>
        <View style={styles.userDetail}>
          <Text style={styles.detailLabel}>Last Updated</Text>
          <Text style={styles.detailValue}>{item.updated_at}</Text>
        </View>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButton} onPress={() => openEditModal(item)}>
          <Text style={styles.actionText}>✏️ Edit</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionButton} onPress={() => openResetModal(item)}>
          <Text style={styles.actionText}>🔑 Reset</Text>
        </TouchableOpacity>
        {item.status === 'active' ? (
          <TouchableOpacity
            style={[styles.actionButton, styles.deactivateButton]}
            onPress={() => handleStatusChange(item, 'inactive')}
          >
            <Text style={[styles.actionText, { color: '#f59e0b' }]}>⏸️ Deactivate</Text>
          </TouchableOpacity>
        ) : item.status === 'inactive' ? (
          <TouchableOpacity
            style={[styles.actionButton, styles.activateButton]}
            onPress={() => handleStatusChange(item, 'active')}
          >
            <Text style={[styles.actionText, { color: '#16a34a' }]}>▶️ Activate</Text>
          </TouchableOpacity>
        ) : null}
        {item.status !== 'suspended' && (
          <TouchableOpacity
            style={[styles.actionButton, styles.suspendButton]}
            onPress={() => handleStatusChange(item, 'suspended')}
          >
            <Text style={[styles.actionText, { color: '#dc2626' }]}>⛔ Suspend</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading users...</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor="#f8fafc" />
      <ScrollView
        style={styles.container}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.pageTitle}>User Management</Text>
          <Text style={styles.pageSub}>
            Manage Administrator and Treasury Personnel accounts.
          </Text>
        </View>

        {/* Stats Cards */}
        <View style={styles.statsRow}>
          {renderStatCard('Total Users', stats.totalUsers, '#2563eb')}
          {renderStatCard('Active', stats.active, '#16a34a')}
          {renderStatCard('Inactive', stats.inactive, '#f59e0b')}
          {renderStatCard('Suspended', stats.suspended, '#dc2626')}
        </View>

        {/* Users List */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>System Users</Text>
            <Text style={styles.sectionSub}>
              Passwords are stored as secure hashes and are never displayed.
            </Text>
          </View>

          {/* Search and Filters */}
          <View style={styles.filterContainer}>
            <TextInput
              style={styles.searchInput}
              placeholder="Search name or email..."
              value={search}
              onChangeText={setSearch}
            />
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={roleFilter}
                onValueChange={setRoleFilter}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
              >
                <Picker.Item label="All Roles" value="" />
                <Picker.Item label="Administrator" value="Administrator" />
                <Picker.Item label="Treasury Personnel" value="Treasury Personnel" />
              </Picker>
            </View>
            <View style={[styles.pickerWrapper, { flex: 1 }]}>
              <Picker
                selectedValue={statusFilter}
                onValueChange={setStatusFilter}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
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
            >
              <Text style={styles.clearText}>Clear</Text>
            </TouchableOpacity>
          </View>

          {/* Add User Button */}
          <TouchableOpacity
            style={styles.addButton}
            onPress={() => {
              resetForm();
              setAddModalVisible(true);
            }}
          >
            <Text style={styles.addButtonText}>+ Add User</Text>
          </TouchableOpacity>

          {/* User List */}
          {filteredUsers.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyText}>
                No user accounts matched your current search and filters.
              </Text>
            </View>
          ) : (
            <FlatList
              data={filteredUsers}
              renderItem={renderUserItem}
              keyExtractor={item => item.user_id.toString()}
              scrollEnabled={false}
            />
          )}
        </View>
      </ScrollView>

      {/* ===== Add User Modal ===== */}
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
                <Text style={styles.modalSub}>
                  Create an Administrator or Treasury Personnel account.
                </Text>
              </View>
              <TouchableOpacity onPress={() => setAddModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
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
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Email Address</Text>
                <TextInput
                  style={styles.input}
                  value={formEmail}
                  onChangeText={setFormEmail}
                  placeholder="name@example.gov.ph"
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
                    dropdownIconColor="#0b3d78"
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
                    dropdownIconColor="#0b3d78"
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
                  secureTextEntry
                />
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setAddModalVisible(false)}
                  disabled={submitting}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleAddUser}
                  disabled={submitting}
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

      {/* ===== Edit User Modal ===== */}
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
              <TouchableOpacity onPress={() => setEditModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
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
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Email Address</Text>
                <TextInput
                  style={styles.input}
                  value={formEmail}
                  onChangeText={setFormEmail}
                  placeholder="Email"
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
                    dropdownIconColor="#0b3d78"
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
                    dropdownIconColor="#0b3d78"
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
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleEditUser}
                  disabled={submitting}
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

      {/* ===== Reset Password Modal ===== */}
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
              <TouchableOpacity onPress={() => setResetModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
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
                  secureTextEntry
                />
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setResetModalVisible(false)}
                  disabled={submitting}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleResetPassword}
                  disabled={submitting}
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
  safeArea: { flex: 1, backgroundColor: '#f8fafc' },
  container: { flex: 1, padding: 16 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: '#f8fafc' },
  loadingText: { marginTop: 12, fontSize: 16, color: '#1e293b' },
  header: { marginBottom: 16 },
  pageTitle: { fontSize: 24, fontWeight: '700', color: '#0b3d78', marginBottom: 4 },
  pageSub: { fontSize: 14, color: '#64748b' },

  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    marginBottom: 16,
  },
  statCard: {
    backgroundColor: '#fff',
    borderRadius: 12,
    padding: 14,
    width: '48%',
    borderLeftWidth: 4,
    marginBottom: 12,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
  },
  statLabel: { fontSize: 12, color: '#64748b', marginBottom: 2 },
  statValue: { fontSize: 20, fontWeight: '700' },

  sectionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    marginBottom: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
  },
  sectionHeader: { marginBottom: 12 },
  sectionTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78' },
  sectionSub: { fontSize: 12, color: '#64748b' },

  filterContainer: {
    marginBottom: 12,
  },
  searchInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    fontSize: 14,
    height: 44,
    marginBottom: 8,
  },
  pickerWrapper: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    height: 44,
    justifyContent: 'center',
    marginBottom: 8,
  },
  picker: {
    height: 44,
    width: '100%',
  },
  clearButton: {
    backgroundColor: '#f1f5f9',
    paddingVertical: 8,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 8,
  },
  clearText: { fontSize: 14, color: '#0b3d78', fontWeight: '500' },

  addButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 12,
    borderRadius: 8,
    alignItems: 'center',
    marginBottom: 12,
  },
  addButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },

  emptyState: { padding: 20, alignItems: 'center' },
  emptyText: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },

  userItem: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  userHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  userInfo: { flexDirection: 'row', alignItems: 'center', flex: 1 },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  avatarText: { fontSize: 16, fontWeight: '700' },
  userName: { fontSize: 15, fontWeight: '600', color: '#0b3d78' },
  userEmail: { fontSize: 13, color: '#64748b' },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12 },
  statusText: { fontSize: 11, fontWeight: '600' },

  userDetails: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginBottom: 8,
  },
  userDetail: {
    marginRight: 16,
    marginBottom: 4,
  },
  detailLabel: { fontSize: 12, color: '#94a3b8' },
  detailValue: { fontSize: 13, fontWeight: '500', color: '#0b3d78' },

  actionRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 4,
  },
  actionButton: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
    backgroundColor: '#f1f5f9',
    marginRight: 4,
    marginBottom: 4,
  },
  actionText: { fontSize: 12, fontWeight: '500', color: '#0b3d78' },
  deactivateButton: { backgroundColor: '#fef3c7' },
  activateButton: { backgroundColor: '#d1fae5' },
  suspendButton: { backgroundColor: '#fee2e2' },

  // Modal styles
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 20,
    width: '92%',
    maxHeight: '85%',
    paddingBottom: 20,
  },
  modalHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  modalTitle: { fontSize: 18, fontWeight: '700', color: '#0b3d78' },
  modalSub: { fontSize: 13, color: '#64748b' },
  modalClose: { fontSize: 22, color: '#94a3b8', padding: 4 },
  modalBody: { padding: 20 },
  formGroup: { marginBottom: 14 },
  label: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginBottom: 4 },
  input: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    padding: 10,
    fontSize: 14,
    height: 44,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: 10,
    marginTop: 16,
  },
  modalButton: {
    paddingVertical: 10,
    paddingHorizontal: 20,
    borderRadius: 8,
    minWidth: 100,
    alignItems: 'center',
  },
  cancelButton: { backgroundColor: '#f1f5f9' },
  confirmButton: { backgroundColor: '#2563eb' },
  cancelButtonText: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  confirmButtonText: { fontSize: 14, fontWeight: '600', color: '#fff' },
});