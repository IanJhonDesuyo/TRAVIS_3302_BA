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
  Image,
  Dimensions,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import * as ImagePicker from 'expo-image-picker';

const { width } = Dimensions.get('window');

// ========== TYPES ==========
interface Announcement {
  announcement_id: number;
  title: string;
  announcement_type: string;
  content: string;
  image_path: string | null;
  publish_date: string;
  status: 'draft' | 'published' | 'archived';
  created_by_name: string | null;
  created_at: string;
  updated_at: string;
}

// ========== MOCK DATA ==========
const mockAnnouncements: Announcement[] = [
  {
    announcement_id: 1,
    title: 'Road Closure on EDSA',
    announcement_type: 'road closure',
    content: 'EDSA will be closed for repairs from July 20 to July 25. Please take alternate routes.',
    image_path: null,
    publish_date: '2026-07-20 00:00:00',
    status: 'published',
    created_by_name: 'Admin',
    created_at: '2026-07-16 10:00:00',
    updated_at: '2026-07-16 10:00:00',
  },
  {
    announcement_id: 2,
    title: 'TMO Activity: Traffic Summit',
    announcement_type: 'tmo activity',
    content: 'Join the Traffic Management Summit on July 30 at the City Hall. Registration is free.',
    image_path: null,
    publish_date: '2026-07-30 09:00:00',
    status: 'draft',
    created_by_name: 'Zeth Ramzy Pagcaliwagan',
    created_at: '2026-07-16 11:30:00',
    updated_at: '2026-07-16 11:30:00',
  },
  {
    announcement_id: 3,
    title: 'New Traffic Scheme in BGC',
    announcement_type: 'traffic advisory',
    content: 'Effective August 1, new traffic scheme will be implemented in BGC. Please check the map.',
    image_path: null,
    publish_date: '2026-08-01 06:00:00',
    status: 'published',
    created_by_name: 'Admin',
    created_at: '2026-07-15 14:20:00',
    updated_at: '2026-07-15 14:20:00',
  },
  {
    announcement_id: 4,
    title: 'Emergency Notice: Power Interruption',
    announcement_type: 'emergency notice',
    content: 'Power interruption on July 18 from 8am to 5pm. Traffic lights may be affected.',
    image_path: null,
    publish_date: '2026-07-18 08:00:00',
    status: 'archived',
    created_by_name: 'Maria Santos',
    created_at: '2026-07-14 09:00:00',
    updated_at: '2026-07-14 09:00:00',
  },
];

// ========== HELPERS ==========
const announcementTypes = [
  'traffic advisory',
  'tmo activity',
  'public notice',
  'event',
  'road closure',
  'emergency notice',
];

const statusOptions = ['draft', 'published', 'archived'];

const statusColor = (status: string): string => {
  if (status === 'published') return '#16a34a';
  if (status === 'draft') return '#f59e0b';
  if (status === 'archived') return '#6b7280';
  return '#6b7280';
};

const getStatusLabel = (status: string): string => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const getTypeLabel = (type: string): string => {
  return type.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const formatDate = (dateStr: string): string => {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

// ========== SCREEN ==========
export default function PublicWebsiteScreen() {
  const router = useRouter();

  // State
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [stats, setStats] = useState({ published: 0, drafts: 0, scheduled: 0, archived: 0 });

  // Filters
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Modal states
  const [createModalVisible, setCreateModalVisible] = useState(false);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [viewModalVisible, setViewModalVisible] = useState(false);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState<Announcement | null>(null);

  // Form states
  const [formTitle, setFormTitle] = useState('');
  const [formType, setFormType] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formPublishDate, setFormPublishDate] = useState(new Date().toISOString().slice(0, 16));
  const [formStatus, setFormStatus] = useState<'draft' | 'published' | 'archived'>('draft');
  const [formImage, setFormImage] = useState<string | null>(null);
  const [removeImage, setRemoveImage] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  // Load data
  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 800));
    setAnnouncements(mockAnnouncements);
    // Compute stats
    const published = mockAnnouncements.filter(a => a.status === 'published').length;
    const drafts = mockAnnouncements.filter(a => a.status === 'draft').length;
    const archived = mockAnnouncements.filter(a => a.status === 'archived').length;
    const scheduled = mockAnnouncements.filter(a => a.status === 'published' && new Date(a.publish_date) > new Date()).length;
    setStats({ published, drafts, scheduled, archived });
    setLoading(false);
    setRefreshing(false);
  };

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // Filter announcements
  const filteredAnnouncements = announcements.filter(item => {
    const matchSearch = search === '' ||
      item.title.toLowerCase().includes(search.toLowerCase()) ||
      item.content.toLowerCase().includes(search.toLowerCase());
    const matchType = typeFilter === '' || item.announcement_type === typeFilter;
    const matchStatus = statusFilter === '' || item.status === statusFilter;
    return matchSearch && matchType && matchStatus;
  });

  // Image picker
  const pickImage = async () => {
    const { status } = await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (status !== 'granted') {
      Alert.alert('Permission needed', 'Please allow access to your photo library to upload images.');
      return;
    }
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      allowsEditing: true,
      quality: 0.8,
    });
    if (!result.canceled && result.assets && result.assets[0]) {
      setFormImage(result.assets[0].uri);
    }
  };

  // Create announcement
  const handleCreate = async () => {
    if (!formTitle || !formType || !formContent || !formPublishDate) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    setSubmitting(true);
    // Simulate API call
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert('Success', 'Announcement created successfully.');
    setSubmitting(false);
    setCreateModalVisible(false);
    resetForm();
    fetchData();
  };

  // Update announcement
  const handleUpdate = async () => {
    if (!selectedAnnouncement) return;
    if (!formTitle || !formType || !formContent || !formPublishDate) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    setSubmitting(true);
    await new Promise(resolve => setTimeout(resolve, 1500));
    Alert.alert('Success', 'Announcement updated successfully.');
    setSubmitting(false);
    setEditModalVisible(false);
    setSelectedAnnouncement(null);
    resetForm();
    fetchData();
  };

  // Change status
  const handleStatusChange = async (id: number, newStatus: string) => {
    const announcement = announcements.find(a => a.announcement_id === id);
    if (!announcement) return;
    Alert.alert(
      'Confirm Status Change',
      `Change status of "${announcement.title}" to ${newStatus.toUpperCase()}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            // Simulate API
            await new Promise(resolve => setTimeout(resolve, 500));
            const updated = announcements.map(a =>
              a.announcement_id === id ? { ...a, status: newStatus as any } : a
            );
            setAnnouncements(updated);
            // Recalculate stats
            const published = updated.filter(a => a.status === 'published').length;
            const drafts = updated.filter(a => a.status === 'draft').length;
            const archived = updated.filter(a => a.status === 'archived').length;
            const scheduled = updated.filter(a => a.status === 'published' && new Date(a.publish_date) > new Date()).length;
            setStats({ published, drafts, scheduled, archived });
            Alert.alert('Success', 'Status updated.');
          },
        },
      ]
    );
  };

  const resetForm = () => {
    setFormTitle('');
    setFormType('');
    setFormContent('');
    setFormPublishDate(new Date().toISOString().slice(0, 16));
    setFormStatus('draft');
    setFormImage(null);
    setRemoveImage(false);
  };

  const openEditModal = (item: Announcement) => {
    setSelectedAnnouncement(item);
    setFormTitle(item.title);
    setFormType(item.announcement_type);
    setFormContent(item.content);
    setFormPublishDate(item.publish_date.slice(0, 16));
    setFormStatus(item.status);
    setFormImage(null);
    setRemoveImage(false);
    setEditModalVisible(true);
  };

  const openViewModal = (item: Announcement) => {
    setSelectedAnnouncement(item);
    setViewModalVisible(true);
  };

  // Render stats cards
  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={[styles.statCard, { borderLeftColor: color }]}>
      <Text style={styles.statLabel}>{label}</Text>
      <Text style={[styles.statValue, { color }]}>{value}</Text>
    </View>
  );

  // Render announcement item
  const renderAnnouncementItem = ({ item }: { item: Announcement }) => (
    <View style={styles.announcementItem}>
      <View style={styles.announcementHeader}>
        <View style={styles.announcementTitleContainer}>
          <Text style={styles.announcementTitle}>{item.title}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '20' }]}>
            <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
              {getStatusLabel(item.status)}
            </Text>
          </View>
        </View>
        <Text style={styles.announcementType}>{getTypeLabel(item.announcement_type)}</Text>
      </View>

      <Text style={styles.announcementContent} numberOfLines={2}>
        {item.content}
      </Text>

      <View style={styles.announcementFooter}>
        <Text style={styles.announcementDate}>{formatDate(item.publish_date)}</Text>
        <Text style={styles.announcementAuthor}>by {item.created_by_name || 'Unknown'}</Text>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButton} onPress={() => openViewModal(item)}>
          <Text style={styles.actionText}>👁️ View</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionButton} onPress={() => openEditModal(item)}>
          <Text style={styles.actionText}>✏️ Edit</Text>
        </TouchableOpacity>
        {item.status !== 'published' ? (
          <TouchableOpacity style={[styles.actionButton, styles.publishButton]} onPress={() => handleStatusChange(item.announcement_id, 'published')}>
            <Text style={[styles.actionText, { color: '#16a34a' }]}>📤 Publish</Text>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity style={[styles.actionButton, styles.draftButton]} onPress={() => handleStatusChange(item.announcement_id, 'draft')}>
            <Text style={[styles.actionText, { color: '#f59e0b' }]}>↩️ Draft</Text>
          </TouchableOpacity>
        )}
        {item.status !== 'archived' && (
          <TouchableOpacity style={[styles.actionButton, styles.archiveButton]} onPress={() => handleStatusChange(item.announcement_id, 'archived')}>
            <Text style={[styles.actionText, { color: '#6b7280' }]}>📦 Archive</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#2563eb" />
        <Text style={styles.loadingText}>Loading announcements...</Text>
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
          <Text style={styles.pageTitle}>Public Website CMS</Text>
          <Text style={styles.pageSub}>
            Create, upload, publish, edit, and archive announcements for the public TRAVIS website.
          </Text>
        </View>

        {/* Stats */}
        <View style={styles.statsRow}>
          {renderStatCard('Published', stats.published, '#16a34a')}
          {renderStatCard('Drafts', stats.drafts, '#f59e0b')}
          {renderStatCard('Scheduled', stats.scheduled, '#2563eb')}
          {renderStatCard('Archived', stats.archived, '#6b7280')}
        </View>

        {/* Announcements List */}
        <View style={styles.sectionCard}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>Announcements</Text>
            <Text style={styles.sectionSub}>Published posts will be displayed by the separate public website.</Text>
          </View>

          {/* Search and Filters */}
          <View style={styles.filterContainer}>
            <TextInput
              style={styles.searchInput}
              placeholder="Search title or content..."
              value={search}
              onChangeText={setSearch}
            />
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={typeFilter}
                onValueChange={setTypeFilter}
                style={styles.picker}
                dropdownIconColor="#0b3d78"
              >
                <Picker.Item label="All Types" value="" />
                {announcementTypes.map(type => (
                  <Picker.Item key={type} label={getTypeLabel(type)} value={type} />
                ))}
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
                <Picker.Item label="Published" value="published" />
                <Picker.Item label="Draft" value="draft" />
                <Picker.Item label="Archived" value="archived" />
              </Picker>
            </View>
            <TouchableOpacity
              style={styles.clearButton}
              onPress={() => { setSearch(''); setTypeFilter(''); setStatusFilter(''); }}
            >
              <Text style={styles.clearText}>Clear</Text>
            </TouchableOpacity>
          </View>

          {/* Add Button */}
          <TouchableOpacity style={styles.addButton} onPress={() => { resetForm(); setCreateModalVisible(true); }}>
            <Text style={styles.addButtonText}>+ New Announcement</Text>
          </TouchableOpacity>

          {/* List */}
          {filteredAnnouncements.length === 0 ? (
            <View style={styles.emptyState}>
              <Text style={styles.emptyText}>No announcements matched the current filters.</Text>
            </View>
          ) : (
            <FlatList
              data={filteredAnnouncements}
              renderItem={renderAnnouncementItem}
              keyExtractor={item => item.announcement_id.toString()}
              scrollEnabled={false}
            />
          )}
        </View>
      </ScrollView>

      {/* ===== CREATE MODAL ===== */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={createModalVisible}
        onRequestClose={() => setCreateModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Create Announcement</Text>
                <Text style={styles.modalSub}>Add content and an optional cover image.</Text>
              </View>
              <TouchableOpacity onPress={() => setCreateModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Title</Text>
                <TextInput style={styles.input} value={formTitle} onChangeText={setFormTitle} placeholder="Announcement title" maxLength={255} />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Type</Text>
                <View style={styles.pickerWrapper}>
                  <Picker selectedValue={formType} onValueChange={setFormType} style={styles.picker} dropdownIconColor="#0b3d78">
                    <Picker.Item label="Select type" value="" />
                    {announcementTypes.map(type => (
                      <Picker.Item key={type} label={getTypeLabel(type)} value={type} />
                    ))}
                  </Picker>
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Content</Text>
                <TextInput
                  style={[styles.input, { height: 100, textAlignVertical: 'top' }]}
                  value={formContent}
                  onChangeText={setFormContent}
                  placeholder="Announcement content"
                  multiline
                  numberOfLines={4}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Cover Image</Text>
                <TouchableOpacity style={styles.imagePickerButton} onPress={pickImage}>
                  <Text style={styles.imagePickerText}>{formImage ? 'Image selected' : 'Choose image (JPG, PNG, WebP, max 5MB)'}</Text>
                </TouchableOpacity>
                {formImage && (
                  <Image source={{ uri: formImage }} style={styles.previewImage} />
                )}
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Publish Date</Text>
                <TextInput
                  style={styles.input}
                  value={formPublishDate}
                  onChangeText={setFormPublishDate}
                  placeholder="YYYY-MM-DDTHH:mm"
                />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker selectedValue={formStatus} onValueChange={setFormStatus} style={styles.picker} dropdownIconColor="#0b3d78">
                    <Picker.Item label="Draft" value="draft" />
                    <Picker.Item label="Published" value="published" />
                    <Picker.Item label="Archived" value="archived" />
                  </Picker>
                </View>
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity style={[styles.modalButton, styles.cancelButton]} onPress={() => setCreateModalVisible(false)} disabled={submitting}>
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.modalButton, styles.confirmButton]} onPress={handleCreate} disabled={submitting}>
                  {submitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.confirmButtonText}>Save Announcement</Text>}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ===== EDIT MODAL ===== */}
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
                <Text style={styles.modalTitle}>Edit Announcement</Text>
                <Text style={styles.modalSub}>{selectedAnnouncement?.title}</Text>
              </View>
              <TouchableOpacity onPress={() => setEditModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Title</Text>
                <TextInput style={styles.input} value={formTitle} onChangeText={setFormTitle} placeholder="Title" maxLength={255} />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Type</Text>
                <View style={styles.pickerWrapper}>
                  <Picker selectedValue={formType} onValueChange={setFormType} style={styles.picker} dropdownIconColor="#0b3d78">
                    {announcementTypes.map(type => (
                      <Picker.Item key={type} label={getTypeLabel(type)} value={type} />
                    ))}
                  </Picker>
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Content</Text>
                <TextInput
                  style={[styles.input, { height: 100, textAlignVertical: 'top' }]}
                  value={formContent}
                  onChangeText={setFormContent}
                  placeholder="Content"
                  multiline
                  numberOfLines={4}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Replace Image</Text>
                <TouchableOpacity style={styles.imagePickerButton} onPress={pickImage}>
                  <Text style={styles.imagePickerText}>{formImage ? 'Image selected' : 'Choose new image'}</Text>
                </TouchableOpacity>
                {selectedAnnouncement?.image_path && !formImage && (
                  <View style={styles.imageInfo}>
                    <Text style={styles.imageInfoText}>Current image exists</Text>
                    <TouchableOpacity onPress={() => setRemoveImage(true)}>
                      <Text style={styles.removeImageText}>Remove current image</Text>
                    </TouchableOpacity>
                  </View>
                )}
                {formImage && <Image source={{ uri: formImage }} style={styles.previewImage} />}
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Publish Date</Text>
                <TextInput style={styles.input} value={formPublishDate} onChangeText={setFormPublishDate} placeholder="YYYY-MM-DDTHH:mm" />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker selectedValue={formStatus} onValueChange={setFormStatus} style={styles.picker} dropdownIconColor="#0b3d78">
                    <Picker.Item label="Draft" value="draft" />
                    <Picker.Item label="Published" value="published" />
                    <Picker.Item label="Archived" value="archived" />
                  </Picker>
                </View>
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity style={[styles.modalButton, styles.cancelButton]} onPress={() => setEditModalVisible(false)} disabled={submitting}>
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity style={[styles.modalButton, styles.confirmButton]} onPress={handleUpdate} disabled={submitting}>
                  {submitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.confirmButtonText}>Save Changes</Text>}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* ===== VIEW MODAL ===== */}
      <Modal
        animationType="slide"
        transparent={true}
        visible={viewModalVisible}
        onRequestClose={() => setViewModalVisible(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={[styles.modalContent, { maxHeight: '85%' }]}>
            <View style={styles.modalHeader}>
              <View>
                <Text style={styles.modalTitle}>Announcement Details</Text>
                {selectedAnnouncement && (
                  <View style={styles.viewStatusRow}>
                    <View style={[styles.statusBadge, { backgroundColor: statusColor(selectedAnnouncement.status) + '20' }]}>
                      <Text style={[styles.statusText, { color: statusColor(selectedAnnouncement.status) }]}>
                        {getStatusLabel(selectedAnnouncement.status)}
                      </Text>
                    </View>
                  </View>
                )}
              </View>
              <TouchableOpacity onPress={() => setViewModalVisible(false)}>
                <Text style={styles.modalClose}>✕</Text>
              </TouchableOpacity>
            </View>

            {selectedAnnouncement && (
              <ScrollView style={styles.modalBody}>
                <Text style={styles.viewTitle}>{selectedAnnouncement.title}</Text>
                {selectedAnnouncement.image_path && (
                  <Image source={{ uri: selectedAnnouncement.image_path }} style={styles.viewImage} />
                )}
                <View style={styles.viewMeta}>
                  <Text style={styles.viewMetaItem}><Text style={styles.viewMetaLabel}>Type:</Text> {getTypeLabel(selectedAnnouncement.announcement_type)}</Text>
                  <Text style={styles.viewMetaItem}><Text style={styles.viewMetaLabel}>Publish Date:</Text> {formatDate(selectedAnnouncement.publish_date)}</Text>
                  <Text style={styles.viewMetaItem}><Text style={styles.viewMetaLabel}>Created By:</Text> {selectedAnnouncement.created_by_name || 'Not recorded'}</Text>
                </View>
                <Text style={styles.viewContent}>{selectedAnnouncement.content}</Text>
                <View style={styles.modalActions}>
                  <TouchableOpacity style={[styles.modalButton, styles.cancelButton]} onPress={() => setViewModalVisible(false)}>
                    <Text style={styles.cancelButtonText}>Close</Text>
                  </TouchableOpacity>
                </View>
              </ScrollView>
            )}
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

  filterContainer: { marginBottom: 12 },
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
  picker: { height: 44, width: '100%' },
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

  announcementItem: {
    backgroundColor: '#f8fafc',
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  announcementHeader: { marginBottom: 6 },
  announcementTitleContainer: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 2,
  },
  announcementTitle: { fontSize: 16, fontWeight: '600', color: '#0b3d78', flex: 1 },
  statusBadge: { paddingHorizontal: 8, paddingVertical: 2, borderRadius: 12, marginLeft: 6 },
  statusText: { fontSize: 11, fontWeight: '600' },
  announcementType: { fontSize: 13, color: '#64748b' },
  announcementContent: { fontSize: 14, color: '#1e293b', marginBottom: 6 },
  announcementFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  announcementDate: { fontSize: 12, color: '#94a3b8' },
  announcementAuthor: { fontSize: 12, color: '#94a3b8' },

  actionRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
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
  publishButton: { backgroundColor: '#d1fae5' },
  draftButton: { backgroundColor: '#fef3c7' },
  archiveButton: { backgroundColor: '#f1f5f9' },

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
  imagePickerButton: {
    backgroundColor: '#f1f5f9',
    paddingVertical: 10,
    borderRadius: 8,
    alignItems: 'center',
  },
  imagePickerText: { fontSize: 14, color: '#0b3d78' },
  previewImage: { width: '100%', height: 150, borderRadius: 8, marginTop: 8 },
  imageInfo: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 6 },
  imageInfoText: { fontSize: 13, color: '#64748b' },
  removeImageText: { fontSize: 13, color: '#dc2626', fontWeight: '500' },

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

  // View modal specific
  viewStatusRow: { marginTop: 4 },
  viewTitle: { fontSize: 20, fontWeight: '700', color: '#0b3d78', marginBottom: 10 },
  viewImage: { width: '100%', height: 200, borderRadius: 8, marginBottom: 12 },
  viewMeta: { marginBottom: 12 },
  viewMetaItem: { fontSize: 14, color: '#1e293b', marginBottom: 4 },
  viewMetaLabel: { fontWeight: '600', color: '#0b3d78' },
  viewContent: { fontSize: 15, color: '#1e293b', lineHeight: 22 },
});