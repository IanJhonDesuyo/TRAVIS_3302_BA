import React, { useState, useEffect } from 'react';
import {
  SafeAreaView,
  ScrollView,
  View,
  Text,
  StyleSheet,
  FlatList,
  TextInput,
  TouchableOpacity,
  ActivityIndicator,
  StatusBar,
  RefreshControl,
  Modal,
  Alert,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker'; // optional, or use custom dropdown

// ========== TYPES ==========
interface Violation {
  id: number;
  ticketNumber: string;
  driverName: string;
  licenseNumber: string;
  plateNumber: string;
  vehicleType: string;
  violationType: string;
  location: string;
  date: string;
  time: string;
  penalty: number;
  status: 'pending' | 'overdue' | 'paid' | 'cancelled';
  createdAt: string;
}

// ========== MOCK DATA ==========
const mockViolations: Violation[] = [
  {
    id: 1,
    ticketNumber: 'TRV-20260716-000001',
    driverName: 'Juan Dela Cruz',
    licenseNumber: 'N12-34-567890',
    plateNumber: 'ABC-1234',
    vehicleType: 'Car',
    violationType: 'Speeding',
    location: 'EDSA Ayala',
    date: '2026-07-16',
    time: '10:30',
    penalty: 1200,
    status: 'pending',
    createdAt: '2026-07-16 10:35:00',
  },
  {
    id: 2,
    ticketNumber: 'TRV-20260716-000002',
    driverName: 'Maria Santos',
    licenseNumber: 'M98-76-543210',
    plateNumber: 'XYZ-5678',
    vehicleType: 'SUV',
    violationType: 'Illegal Parking',
    location: 'BGC 32nd St',
    date: '2026-07-16',
    time: '09:15',
    penalty: 800,
    status: 'paid',
    createdAt: '2026-07-16 09:20:00',
  },
  {
    id: 3,
    ticketNumber: 'TRV-20260715-000003',
    driverName: 'Pedro Reyes',
    licenseNumber: 'P11-22-334455',
    plateNumber: 'DEF-9012',
    vehicleType: 'Motorcycle',
    violationType: 'Disregarded Signal',
    location: 'Commonwealth Ave',
    date: '2026-07-15',
    time: '17:45',
    penalty: 600,
    status: 'overdue',
    createdAt: '2026-07-15 17:50:00',
  },
  {
    id: 4,
    ticketNumber: 'TRV-20260715-000004',
    driverName: 'Ana Reyes',
    licenseNumber: 'A55-66-778899',
    plateNumber: 'GHI-3456',
    vehicleType: 'Van',
    violationType: 'Overloading',
    location: 'C5',
    date: '2026-07-15',
    time: '14:20',
    penalty: 1500,
    status: 'pending',
    createdAt: '2026-07-15 14:25:00',
  },
  {
    id: 5,
    ticketNumber: 'TRV-20260714-000005',
    driverName: 'Carlos Gomez',
    licenseNumber: 'C77-88-990011',
    plateNumber: 'JKL-7890',
    vehicleType: 'Tricycle',
    violationType: 'No Helmet',
    location: 'Taft Ave',
    date: '2026-07-14',
    time: '08:10',
    penalty: 300,
    status: 'cancelled',
    createdAt: '2026-07-14 08:15:00',
  },
];

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `₱${amount.toLocaleString()}`;

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'resolved') return '#16a34a';
  if (s === 'pending') return '#f59e0b';
  if (s === 'overdue' || s === 'critical') return '#dc2626';
  if (s === 'cancelled') return '#6b7280';
  return '#6b7280';
};

// ========== SCREEN ==========
export default function ViolationsScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [violations, setViolations] = useState<Violation[]>([]);
  const [filtered, setFiltered] = useState<Violation[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [refreshing, setRefreshing] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);

  // Summary counts
  const counts = {
    today: 2,
    awaiting: 3, // pending + overdue
    paid: 1,
    cancelled: 1,
  };

  useEffect(() => {
    fetchViolations();
  }, []);

  const fetchViolations = async () => {
    // Replace with actual API: fetch('/api/violations')
    await new Promise(resolve => setTimeout(resolve, 800));
    setViolations(mockViolations);
    setFiltered(mockViolations);
    setLoading(false);
    setRefreshing(false);
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchViolations();
  };

  // Filter logic
  useEffect(() => {
    let result = violations;
    if (search.trim()) {
      const lower = search.toLowerCase();
      result = result.filter(
        v =>
          v.ticketNumber.toLowerCase().includes(lower) ||
          v.driverName.toLowerCase().includes(lower) ||
          v.plateNumber.toLowerCase().includes(lower) ||
          v.violationType.toLowerCase().includes(lower) ||
          v.location.toLowerCase().includes(lower)
      );
    }
    if (statusFilter) {
      result = result.filter(v => v.status === statusFilter);
    }
    setFiltered(result);
  }, [search, statusFilter, violations]);

  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <Text style={[styles.statIcon, { color }]}>●</Text>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );

  const renderViolationItem = ({ item }: { item: Violation }) => (
    <View style={styles.violationItem}>
      <View style={styles.violationRow}>
        <Text style={styles.ticketNumber}>{item.ticketNumber}</Text>
        <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
          <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
            {item.status.toUpperCase()}
          </Text>
        </View>
      </View>
      <Text style={styles.driverInfo}>
        {item.driverName} • {item.licenseNumber}
      </Text>
      <Text style={styles.vehicleInfo}>
        {item.plateNumber} • {item.vehicleType}
      </Text>
      <Text style={styles.violationType}>{item.violationType}</Text>
      <Text style={styles.location}>{item.location}</Text>
      <View style={styles.violationFooter}>
        <Text style={styles.dateTime}>
          {item.date} {item.time}
        </Text>
        <Text style={styles.penalty}>{formatCurrency(item.penalty)}</Text>
      </View>
      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButton}>
          <Text style={styles.actionText}>View</Text>
        </TouchableOpacity>
        {(item.status === 'pending' || item.status === 'overdue') && (
          <>
            <TouchableOpacity style={[styles.actionButton, styles.payButton]}>
              <Text style={styles.actionText}>Pay</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[styles.actionButton, styles.cancelButton]}>
              <Text style={[styles.actionText, { color: '#dc2626' }]}>Cancel</Text>
            </TouchableOpacity>
          </>
        )}
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading violations...</Text>
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
          <Text style={styles.pageTitle}>Violation Records</Text>
          <Text style={styles.pageSub}>Record, review, and route unpaid traffic violations to the payment module.</Text>
        </View>

        {/* Stats */}
        <View style={styles.statsRow}>
          {renderStatCard('Recorded Today', counts.today, '#f59e0b')}
          {renderStatCard('Awaiting Payment', counts.awaiting, '#dc2626')}
          {renderStatCard('Paid', counts.paid, '#16a34a')}
          {renderStatCard('Cancelled', counts.cancelled, '#6b7280')}
        </View>

        {/* Search and Filter */}
        <View style={styles.filterContainer}>
          <TextInput
            style={styles.searchInput}
            placeholder="Ticket, driver, plate, violation, location..."
            value={search}
            onChangeText={setSearch}
          />
          <View style={styles.pickerWrapper}>
            <Picker
              selectedValue={statusFilter}
              onValueChange={setStatusFilter}
              style={styles.picker}
              dropdownIconColor="#0b3d78"
            >
              <Picker.Item label="All Statuses" value="" />
              <Picker.Item label="Pending" value="pending" />
              <Picker.Item label="Overdue" value="overdue" />
              <Picker.Item label="Paid" value="paid" />
              <Picker.Item label="Cancelled" value="cancelled" />
            </Picker>
          </View>
          <TouchableOpacity style={styles.clearButton} onPress={() => { setSearch(''); setStatusFilter(''); }}>
            <Text style={styles.clearText}>Clear</Text>
          </TouchableOpacity>
        </View>

        {/* Violations List */}
        <View style={styles.sectionCard}>
          <FlatList
            data={filtered}
            renderItem={renderViolationItem}
            keyExtractor={item => item.id.toString()}
            scrollEnabled={false}
            ListEmptyComponent={
              <View style={styles.emptyState}>
                <Text style={styles.emptyText}>No violation records matched your search or filter.</Text>
              </View>
            }
          />
        </View>

        {/* Add Violation Button */}
        <TouchableOpacity style={styles.addButton} onPress={() => setModalVisible(true)}>
          <Text style={styles.addButtonText}>+ Add Violation</Text>
        </TouchableOpacity>
      </ScrollView>

      {/* Add Violation Modal (simplified) */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={modalVisible}
        onRequestClose={() => setModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Manual Violation Input</Text>
            <Text style={styles.modalSub}>For manually encoded paper ticket records</Text>

            {/* Simplified form – you can expand */}
            <Text style={styles.modalLabel}>Driver Name</Text>
            <TextInput style={styles.modalInput} placeholder="Driver name" />
            <Text style={styles.modalLabel}>License Number</Text>
            <TextInput style={styles.modalInput} placeholder="License number" />
            <Text style={styles.modalLabel}>Plate Number</Text>
            <TextInput style={styles.modalInput} placeholder="Plate number" />
            <Text style={styles.modalLabel}>Violation Type</Text>
            <TextInput style={styles.modalInput} placeholder="Violation type" />
            <Text style={styles.modalLabel}>Location</Text>
            <TextInput style={styles.modalInput} placeholder="Location" />
            <Text style={styles.modalLabel}>Penalty Amount</Text>
            <TextInput style={styles.modalInput} placeholder="Penalty amount" keyboardType="numeric" />

            <View style={styles.modalActions}>
              <TouchableOpacity style={[styles.modalButton, styles.cancelModalButton]} onPress={() => setModalVisible(false)}>
                <Text style={styles.modalButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity style={[styles.modalButton, styles.saveModalButton]} onPress={() => {
                Alert.alert('Success', 'Violation record added (mock)');
                setModalVisible(false);
              }}>
                <Text style={[styles.modalButtonText, { color: '#fff' }]}>Save Violation</Text>
              </TouchableOpacity>
            </View>
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
    padding: 12,
    width: '23%',
    borderLeftWidth: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 4,
    elevation: 1,
    alignItems: 'center',
  },
  statIcon: { fontSize: 16, marginBottom: 2 },
  statLabel: { fontSize: 11, color: '#64748b', fontWeight: '500', textAlign: 'center' },
  statValue: { fontSize: 18, fontWeight: '700' },

  filterContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 16,
    gap: 8,
  },
  searchInput: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    fontSize: 14,
  },
  pickerWrapper: {
    backgroundColor: '#fff',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    width: 130,
    height: 40,
    justifyContent: 'center',
  },
  picker: {
    height: 40,
    width: '100%',
  },
  clearButton: {
    backgroundColor: '#f1f5f9',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  clearText: { fontSize: 14, color: '#0b3d78' },

  sectionCard: {
    backgroundColor: '#fff',
    borderRadius: 16,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.05,
    shadowRadius: 8,
    elevation: 2,
    marginBottom: 16,
  },

  violationItem: {
    paddingVertical: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#e2e8f0',
  },
  violationRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  ticketNumber: { fontSize: 14, fontWeight: '600', color: '#0b3d78' },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12 },
  statusText: { fontSize: 11, fontWeight: '600' },
  driverInfo: { fontSize: 14, color: '#1e293b' },
  vehicleInfo: { fontSize: 13, color: '#475569' },
  violationType: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginTop: 2 },
  location: { fontSize: 13, color: '#64748b' },
  violationFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 4,
  },
  dateTime: { fontSize: 12, color: '#94a3b8' },
  penalty: { fontSize: 14, fontWeight: '700', color: '#0b3d78' },
  actionRow: {
    flexDirection: 'row',
    marginTop: 8,
    gap: 8,
  },
  actionButton: {
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 6,
    backgroundColor: '#f1f5f9',
  },
  payButton: { backgroundColor: '#16a34a' },
  cancelButton: { backgroundColor: '#fee2e2' },
  actionText: { fontSize: 12, fontWeight: '600', color: '#0b3d78' },

  emptyState: { padding: 20, alignItems: 'center' },
  emptyText: { fontSize: 14, color: '#94a3b8', textAlign: 'center' },

  addButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: 'center',
    marginBottom: 20,
  },
  addButtonText: { color: '#fff', fontSize: 16, fontWeight: '600' },

  // Modal
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0,0,0,0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modalContent: {
    backgroundColor: '#fff',
    borderRadius: 20,
    padding: 24,
    width: '90%',
    maxHeight: '80%',
  },
  modalTitle: { fontSize: 20, fontWeight: '700', color: '#0b3d78', marginBottom: 2 },
  modalSub: { fontSize: 14, color: '#64748b', marginBottom: 16 },
  modalLabel: { fontSize: 14, fontWeight: '500', color: '#0b3d78', marginTop: 8, marginBottom: 4 },
  modalInput: {
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    fontSize: 14,
  },
  modalActions: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    marginTop: 20,
    gap: 10,
  },
  modalButton: {
    paddingHorizontal: 18,
    paddingVertical: 10,
    borderRadius: 8,
  },
  cancelModalButton: { backgroundColor: '#f1f5f9' },
  saveModalButton: { backgroundColor: '#2563eb' },
  modalButtonText: { fontSize: 14, fontWeight: '600' },
});