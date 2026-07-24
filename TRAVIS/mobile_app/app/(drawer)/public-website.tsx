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
  Image,
  ImageBackground,
  Dimensions,
  Platform,
} from 'react-native';
import { useRouter } from 'expo-router';
import { Picker } from '@react-native-picker/picker';
import * as ImagePicker from 'expo-image-picker';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import api, { APP_ROOT_URL } from '../../api/axiosConfig';

const { width } = Dimensions.get('window');

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

// ========== HELPERS ==========
const announcementTypes = [
  'traffic advisory',
  'tmo activity',
  'public notice',
  'event',
  'road closure',
  'emergency notice',
];

const statusColor = (status: string): string => {
  if (status === 'published') return COLORS.success;
  if (status === 'draft') return COLORS.warning;
  if (status === 'archived') return COLORS.neutral;
  return COLORS.neutral;
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

  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [announcements, setAnnouncements] = useState<Announcement[]>([]);
  const [stats, setStats] = useState({ published: 0, drafts: 0, scheduled: 0, archived: 0 });
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [createModalVisible, setCreateModalVisible] = useState(false);
  const [editModalVisible, setEditModalVisible] = useState(false);
  const [viewModalVisible, setViewModalVisible] = useState(false);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState<Announcement | null>(null);

  const [formTitle, setFormTitle] = useState('');
  const [formType, setFormType] = useState('');
  const [formContent, setFormContent] = useState('');
  const [formPublishDate, setFormPublishDate] = useState(new Date().toISOString().slice(0, 16));
  const [formStatus, setFormStatus] = useState<'draft' | 'published' | 'archived'>('draft');
  const [formImage, setFormImage] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // ===== FETCH ANNOUNCEMENTS =====
  const fetchData = async () => {
    try {
      setLoading(true);
      const res = await api.get('get_announcements.php');
      if (res.data.success) {
        const data = res.data.data;
        setAnnouncements(data);
        const published = data.filter((a: any) => a.status === 'published').length;
        const drafts = data.filter((a: any) => a.status === 'draft').length;
        const archived = data.filter((a: any) => a.status === 'archived').length;
        const scheduled = data.filter((a: any) => a.status === 'published' && new Date(a.publish_date) > new Date()).length;
        setStats({ published, drafts, scheduled, archived });
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to load announcements.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  useFocusEffect(
    useCallback(() => {
      fetchData();
    }, [])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchData();
  };

  // ===== IMAGE PICKER =====
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

  // ===== CREATE =====
  const handleCreate = async () => {
    if (!formTitle || !formType || !formContent || !formPublishDate) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        title: formTitle,
        announcement_type: formType,
        content: formContent,
        publish_date: formPublishDate,
        status: formStatus,
      };
      const res = await api.post('add_announcement.php', payload);
      if (res.data.success) {
        Alert.alert('Success', 'Announcement created.');
        setCreateModalVisible(false);
        resetForm();
        fetchData();
      } else {
        Alert.alert('Error', res.data.error || 'Failed to create.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  // ===== UPDATE =====
  const handleUpdate = async () => {
    if (!selectedAnnouncement) return;
    if (!formTitle || !formType || !formContent || !formPublishDate) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    setSubmitting(true);
    try {
      const payload = {
        announcement_id: selectedAnnouncement.announcement_id,
        title: formTitle,
        announcement_type: formType,
        content: formContent,
        publish_date: formPublishDate,
        status: formStatus,
      };
      const res = await api.post('update_announcement.php', payload);
      if (res.data.success) {
        Alert.alert('Success', 'Announcement updated.');
        setEditModalVisible(false);
        resetForm();
        fetchData();
      } else {
        Alert.alert('Error', res.data.error || 'Failed to update.');
      }
    } catch (error) {
      Alert.alert('Error', 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  // ===== STATUS CHANGE =====
  const handleStatusChange = async (id: number, newStatus: string) => {
    Alert.alert(
      'Confirm',
      `Change status to ${newStatus.toUpperCase()}?`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            try {
              const res = await api.post('update_announcement.php', {
                announcement_id: id,
                status: newStatus,
              });
              if (res.data.success) {
                Alert.alert('Success', 'Status updated.');
                fetchData();
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
    setFormTitle('');
    setFormType('');
    setFormContent('');
    setFormPublishDate(new Date().toISOString().slice(0, 16));
    setFormStatus('draft');
    setFormImage(null);
  };

  const openEditModal = (item: Announcement) => {
    setSelectedAnnouncement(item);
    setFormTitle(item.title);
    setFormType(item.announcement_type);
    setFormContent(item.content);
    setFormPublishDate(item.publish_date.slice(0, 16));
    setFormStatus(item.status);
    setFormImage(null);
    setEditModalVisible(true);
  };

  const openViewModal = (item: Announcement) => {
    setSelectedAnnouncement(item);
    setViewModalVisible(true);
  };

  // ===== FILTER =====
  const filteredAnnouncements = announcements.filter(item => {
    const matchSearch = search === '' ||
      item.title.toLowerCase().includes(search.toLowerCase()) ||
      item.content.toLowerCase().includes(search.toLowerCase());
    const matchType = typeFilter === '' || item.announcement_type === typeFilter;
    const matchStatus = statusFilter === '' || item.status === statusFilter;
    return matchSearch && matchType && matchStatus;
  });

  // ========== RENDER HELPERS ==========
  const renderStatCard = (label: string, value: number, color: string) => (
    <View style={styles.statCard}>
      <View style={[styles.statAccentDot, { backgroundColor: color }]} />
      <Text style={styles.statLabel}>{label.toUpperCase()}</Text>
      <Text style={styles.statValue}>{value}</Text>
    </View>
  );

  const renderAnnouncementItem = ({ item }: { item: Announcement }) => (
    <View style={styles.announcementItem}>
      <View style={styles.announcementHeader}>
        <View style={styles.announcementTitleContainer}>
          <Text style={styles.announcementTitle}>{item.title}</Text>
          <View style={[styles.statusBadge, { backgroundColor: statusColor(item.status) + '1A' }]}>
            <View style={[styles.statusBadgeDot, { backgroundColor: statusColor(item.status) }]} />
            <Text style={[styles.statusText, { color: statusColor(item.status) }]}>
              {getStatusLabel(item.status).toUpperCase()}
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
        <Text style={styles.announcementAuthor}>BY {(item.created_by_name || 'Unknown').toUpperCase()}</Text>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={styles.actionButton} onPress={() => openViewModal(item)} activeOpacity={0.7}>
          <Ionicons name="eye-outline" size={13} color={COLORS.textPrimary} />
          <Text style={styles.actionText}>View</Text>
        </TouchableOpacity>
        <TouchableOpacity style={styles.actionButton} onPress={() => openEditModal(item)} activeOpacity={0.7}>
          <Ionicons name="create-outline" size={13} color={COLORS.textPrimary} />
          <Text style={styles.actionText}>Edit</Text>
        </TouchableOpacity>
        {item.status !== 'published' ? (
          <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.success + '1A' }]} onPress={() => handleStatusChange(item.announcement_id, 'published')} activeOpacity={0.7}>
            <Ionicons name="send-outline" size={13} color={COLORS.success} />
            <Text style={[styles.actionText, { color: COLORS.success }]}>Publish</Text>
          </TouchableOpacity>
        ) : (
          <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.warning + '1A' }]} onPress={() => handleStatusChange(item.announcement_id, 'draft')} activeOpacity={0.7}>
            <Ionicons name="arrow-undo-outline" size={13} color={COLORS.warning} />
            <Text style={[styles.actionText, { color: COLORS.warning }]}>Draft</Text>
          </TouchableOpacity>
        )}
        {item.status !== 'archived' && (
          <TouchableOpacity style={[styles.actionButton, { backgroundColor: COLORS.bg }]} onPress={() => handleStatusChange(item.announcement_id, 'archived')} activeOpacity={0.7}>
            <Ionicons name="archive-outline" size={13} color={COLORS.textSecondary} />
            <Text style={[styles.actionText, { color: COLORS.textSecondary }]}>Archive</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>LOADING ANNOUNCEMENTS…</Text>
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
        {/* Public-site visual identity; management behavior below is unchanged. */}
        <View style={styles.publicHero}>
          <ImageBackground
            source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-municipal-hall.jpg` }}
            style={styles.heroBackground}
            imageStyle={styles.heroBackgroundImage}
          >
            <View style={styles.heroOverlay}>
              <View style={styles.publicNav}>
                <Image
                  source={{ uri: `${APP_ROOT_URL}assets/images/nasugbu-seal.jpg` }}
                  style={styles.seal}
                />
                <View style={styles.publicBrandCopy}>
                  <Text style={styles.publicBrandName}>NASUGBU · TMO</Text>
                  <Text style={styles.publicBrandSub}>Traffic Management Office</Text>
                </View>
                <View style={styles.publicInfoPill}>
                  <Text style={styles.publicInfoPillText}>Public Information</Text>
                </View>
              </View>

              <View style={styles.heroCopy}>
                <View style={styles.eyebrowRow}>
                  <View style={styles.eyebrowLine} />
                  <Text style={styles.heroEyebrow}>A SAFER, INFORMED COMMUNITY</Text>
                </View>
                <Text style={styles.heroTitle}>Traffic updates.</Text>
                <Text style={styles.heroTitleAccent}>Made public.</Text>
                <Text style={styles.heroDescription}>
                  Your direct source for official traffic advisories, road notices, community activities, and emergency information from the Traffic Management Office.
                </Text>
              </View>

              <View style={styles.publicDeskCard}>
                <Text style={styles.deskLabel}>PUBLIC INFORMATION DESK</Text>
                <Text style={styles.deskCount}>{stats.published}</Text>
                <Text style={styles.deskDescription}>
                  Active {stats.published === 1 ? 'announcement' : 'announcements'} available to the public right now.
                </Text>
                <View style={styles.deskDivider} />
                <View style={styles.deskStatusRow}>
                  <View style={styles.deskStatusHalo}><View style={styles.deskStatusDot} /></View>
                  <Text style={styles.deskStatusText}>Official TMO information service</Text>
                </View>
              </View>
            </View>
          </ImageBackground>
        </View>

        <View style={styles.managementHeading}>
          <View style={styles.eyebrowRow}>
            <View style={styles.eyebrowLine} />
            <Text style={styles.managementEyebrow}>PUBLIC INFORMATION MANAGEMENT</Text>
          </View>
          <Text style={styles.managementTitle}>Announcements dashboard</Text>
          <Text style={styles.managementSub}>Create, publish, edit, and archive official public announcements.</Text>
        </View>

        {/* Stats */}
        <View style={styles.statsRow}>
          {renderStatCard('Published', stats.published, COLORS.success)}
          {renderStatCard('Drafts', stats.drafts, COLORS.warning)}
          {renderStatCard('Scheduled', stats.scheduled, COLORS.primary)}
          {renderStatCard('Archived', stats.archived, COLORS.neutral)}
        </View>

        {/* Announcements List */}
        <Text style={styles.sectionLabel}>ANNOUNCEMENTS</Text>
        <View style={styles.panel}>
          <Text style={styles.panelSub}>Published posts will be displayed by the separate public website.</Text>

          {/* Search and Filters */}
          <View style={styles.filterContainer}>
            <View style={styles.searchWrap}>
              <Ionicons name="search-outline" size={15} color={COLORS.textTertiary} style={{ marginRight: 8 }} />
              <TextInput
                style={styles.searchInput}
                placeholder="Search title or content..."
                placeholderTextColor={COLORS.textTertiary}
                value={search}
                onChangeText={setSearch}
              />
            </View>
            <View style={styles.pickerWrapper}>
              <Picker
                selectedValue={typeFilter}
                onValueChange={setTypeFilter}
                style={styles.picker}
                dropdownIconColor={COLORS.primary}
              >
                <Picker.Item label="All Types" value="" />
                {announcementTypes.map(type => (
                  <Picker.Item key={type} label={getTypeLabel(type)} value={type} />
                ))}
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
                <Picker.Item label="Published" value="published" />
                <Picker.Item label="Draft" value="draft" />
                <Picker.Item label="Archived" value="archived" />
              </Picker>
            </View>
            <TouchableOpacity
              style={styles.clearButton}
              onPress={() => { setSearch(''); setTypeFilter(''); setStatusFilter(''); }}
              activeOpacity={0.7}
            >
              <Text style={styles.clearText}>Clear Filters</Text>
            </TouchableOpacity>
          </View>

          {/* Add Button */}
          <TouchableOpacity style={styles.addButton} onPress={() => { resetForm(); setCreateModalVisible(true); }} activeOpacity={0.85}>
            <Ionicons name="add" size={17} color="#fff" />
            <Text style={styles.addButtonText}>New Announcement</Text>
          </TouchableOpacity>

          {/* List */}
          {filteredAnnouncements.length === 0 ? (
            <View style={styles.emptyState}>
              <Ionicons name="megaphone-outline" size={16} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>No announcements matched the current filters.</Text>
            </View>
          ) : (
            <>
              <View style={styles.panelDivider} />
              <FlatList
                data={filteredAnnouncements}
                renderItem={renderAnnouncementItem}
                keyExtractor={item => item.announcement_id.toString()}
                scrollEnabled={false}
                ItemSeparatorComponent={() => <View style={{ height: 10 }} />}
              />
            </>
          )}
        </View>
      </ScrollView>

      {/* Create Modal */}
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
              <TouchableOpacity onPress={() => setCreateModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Title</Text>
                <TextInput
                  style={styles.input}
                  value={formTitle}
                  onChangeText={setFormTitle}
                  placeholder="Announcement title"
                  placeholderTextColor={COLORS.textTertiary}
                  maxLength={255}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Type</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formType}
                    onValueChange={setFormType}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
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
                  style={[styles.input, { height: 100, textAlignVertical: 'top', paddingTop: 10 }]}
                  value={formContent}
                  onChangeText={setFormContent}
                  placeholder="Announcement content"
                  placeholderTextColor={COLORS.textTertiary}
                  multiline
                  numberOfLines={4}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Cover Image</Text>
                <TouchableOpacity style={styles.imagePickerButton} onPress={pickImage} activeOpacity={0.7}>
                  <Ionicons name="image-outline" size={15} color={COLORS.primary} style={{ marginRight: 8 }} />
                  <Text style={styles.imagePickerText}>
                    {formImage ? 'Image selected' : 'Choose image (JPG, PNG, WebP, max 5MB)'}
                  </Text>
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
                  placeholderTextColor={COLORS.textTertiary}
                />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formStatus}
                    onValueChange={setFormStatus}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Draft" value="draft" />
                    <Picker.Item label="Published" value="published" />
                    <Picker.Item label="Archived" value="archived" />
                  </Picker>
                </View>
              </View>

              <View style={styles.modalActions}>
                <TouchableOpacity
                  style={[styles.modalButton, styles.cancelButton]}
                  onPress={() => setCreateModalVisible(false)}
                  disabled={submitting}
                  activeOpacity={0.7}
                >
                  <Text style={styles.cancelButtonText}>Cancel</Text>
                </TouchableOpacity>
                <TouchableOpacity
                  style={[styles.modalButton, styles.confirmButton]}
                  onPress={handleCreate}
                  disabled={submitting}
                  activeOpacity={0.85}
                >
                  {submitting ? (
                    <ActivityIndicator size="small" color="#fff" />
                  ) : (
                    <Text style={styles.confirmButtonText}>Save Announcement</Text>
                  )}
                </TouchableOpacity>
              </View>
            </ScrollView>
          </View>
        </View>
      </Modal>

      {/* Edit Modal */}
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
              <TouchableOpacity onPress={() => setEditModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            <ScrollView style={styles.modalBody}>
              <View style={styles.formGroup}>
                <Text style={styles.label}>Title</Text>
                <TextInput
                  style={styles.input}
                  value={formTitle}
                  onChangeText={setFormTitle}
                  placeholder="Title"
                  placeholderTextColor={COLORS.textTertiary}
                  maxLength={255}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Type</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formType}
                    onValueChange={setFormType}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    {announcementTypes.map(type => (
                      <Picker.Item key={type} label={getTypeLabel(type)} value={type} />
                    ))}
                  </Picker>
                </View>
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Content</Text>
                <TextInput
                  style={[styles.input, { height: 100, textAlignVertical: 'top', paddingTop: 10 }]}
                  value={formContent}
                  onChangeText={setFormContent}
                  placeholder="Content"
                  placeholderTextColor={COLORS.textTertiary}
                  multiline
                  numberOfLines={4}
                />
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Replace Image</Text>
                <TouchableOpacity style={styles.imagePickerButton} onPress={pickImage} activeOpacity={0.7}>
                  <Ionicons name="image-outline" size={15} color={COLORS.primary} style={{ marginRight: 8 }} />
                  <Text style={styles.imagePickerText}>
                    {formImage ? 'Image selected' : 'Choose new image'}
                  </Text>
                </TouchableOpacity>
                {formImage && <Image source={{ uri: formImage }} style={styles.previewImage} />}
              </View>

              <View style={styles.formGroup}>
                <Text style={styles.label}>Publish Date</Text>
                <TextInput
                  style={styles.input}
                  value={formPublishDate}
                  onChangeText={setFormPublishDate}
                  placeholder="YYYY-MM-DDTHH:mm"
                  placeholderTextColor={COLORS.textTertiary}
                />
              </View>

              <View style={[styles.formGroup, { marginBottom: 0 }]}>
                <Text style={styles.label}>Status</Text>
                <View style={styles.pickerWrapper}>
                  <Picker
                    selectedValue={formStatus}
                    onValueChange={setFormStatus}
                    style={styles.picker}
                    dropdownIconColor={COLORS.primary}
                  >
                    <Picker.Item label="Draft" value="draft" />
                    <Picker.Item label="Published" value="published" />
                    <Picker.Item label="Archived" value="archived" />
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
                  onPress={handleUpdate}
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

      {/* View Modal */}
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
                    <View style={[styles.statusBadge, { backgroundColor: statusColor(selectedAnnouncement.status) + '1A' }]}>
                      <View style={[styles.statusBadgeDot, { backgroundColor: statusColor(selectedAnnouncement.status) }]} />
                      <Text style={[styles.statusText, { color: statusColor(selectedAnnouncement.status) }]}>
                        {getStatusLabel(selectedAnnouncement.status).toUpperCase()}
                      </Text>
                    </View>
                  </View>
                )}
              </View>
              <TouchableOpacity onPress={() => setViewModalVisible(false)} style={styles.modalCloseButton}>
                <Ionicons name="close" size={18} color={COLORS.textSecondary} />
              </TouchableOpacity>
            </View>

            {selectedAnnouncement && (
              <ScrollView style={styles.modalBody}>
                <Text style={styles.viewTitle}>{selectedAnnouncement.title}</Text>
                {selectedAnnouncement.image_path && (
                  <Image source={{ uri: selectedAnnouncement.image_path }} style={styles.viewImage} />
                )}
                <View style={styles.viewMeta}>
                  <View style={styles.viewMetaRow}>
                    <Text style={styles.viewMetaLabel}>TYPE</Text>
                    <Text style={styles.viewMetaValue}>{getTypeLabel(selectedAnnouncement.announcement_type)}</Text>
                  </View>
                  <View style={styles.viewMetaRow}>
                    <Text style={styles.viewMetaLabel}>PUBLISH DATE</Text>
                    <Text style={styles.viewMetaValue}>{formatDate(selectedAnnouncement.publish_date)}</Text>
                  </View>
                  <View style={styles.viewMetaRow}>
                    <Text style={styles.viewMetaLabel}>CREATED BY</Text>
                    <Text style={styles.viewMetaValue}>{selectedAnnouncement.created_by_name || 'Not recorded'}</Text>
                  </View>
                </View>
                <View style={styles.panelDivider} />
                <Text style={styles.viewContent}>{selectedAnnouncement.content}</Text>
                <View style={styles.modalActions}>
                  <TouchableOpacity
                    style={[styles.modalButton, styles.cancelButton]}
                    onPress={() => setViewModalVisible(false)}
                    activeOpacity={0.7}
                  >
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
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.bg },
  loadingText: { marginTop: 14, fontSize: 12, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, fontFamily: mono },
  scrollContent: { paddingBottom: 20 },

  publicHero: { marginBottom: 28, overflow: 'hidden', backgroundColor: '#F7F5EE' },
  heroBackground: { width: '100%' },
  heroBackgroundImage: { resizeMode: 'cover' },
  heroOverlay: { backgroundColor: 'rgba(247,245,238,0.76)', paddingBottom: 24 },
  publicNav: {
    minHeight: 72, paddingHorizontal: 20, paddingVertical: 12, flexDirection: 'row', alignItems: 'center',
    backgroundColor: 'rgba(250,249,244,0.94)', borderBottomWidth: 1, borderBottomColor: 'rgba(16,42,67,0.12)',
  },
  seal: { width: 43, height: 43, borderRadius: 22, marginRight: 10, borderWidth: 2, borderColor: '#FFFFFF' },
  publicBrandCopy: { flex: 1 },
  publicBrandName: { color: '#102A43', fontSize: 15, fontWeight: '800' },
  publicBrandSub: { color: '#61706B', fontSize: 10, marginTop: 2 },
  publicInfoPill: { backgroundColor: '#EB941F', paddingHorizontal: 11, paddingVertical: 8, borderRadius: 18 },
  publicInfoPillText: { color: '#10202C', fontSize: 10, fontWeight: '800' },
  heroCopy: { paddingHorizontal: 22, paddingTop: 34 },
  eyebrowRow: { flexDirection: 'row', alignItems: 'center' },
  eyebrowLine: { width: 28, height: 2, backgroundColor: '#EB941F', marginRight: 10 },
  heroEyebrow: { color: '#087D78', fontSize: 10, fontWeight: '800', letterSpacing: 1.4 },
  heroTitle: { color: '#10202C', fontSize: 40, lineHeight: 45, fontWeight: '900', marginTop: 18, letterSpacing: -1.2 },
  heroTitleAccent: { color: '#087D78', fontSize: 40, lineHeight: 45, fontWeight: '900', letterSpacing: -1.2 },
  heroDescription: { color: '#5E716D', fontSize: 14, lineHeight: 22, marginTop: 18 },
  publicDeskCard: {
    marginHorizontal: 22, marginTop: 28, padding: 22, borderRadius: 10, backgroundColor: '#102F49',
    shadowColor: '#102A43', shadowOffset: { width: 8, height: 10 }, shadowOpacity: 0.13, shadowRadius: 0, elevation: 5,
  },
  deskLabel: { color: '#8FD2CC', fontSize: 10, fontWeight: '800', letterSpacing: 1.1 },
  deskCount: { color: '#FFFFFF', fontSize: 46, lineHeight: 54, fontWeight: '800', marginTop: 8 },
  deskDescription: { color: '#C7D5DD', fontSize: 13, lineHeight: 19 },
  deskDivider: { height: 1, backgroundColor: 'rgba(255,255,255,0.13)', marginVertical: 20 },
  deskStatusRow: { flexDirection: 'row', alignItems: 'center' },
  deskStatusHalo: { width: 22, height: 22, borderRadius: 11, backgroundColor: 'rgba(52,211,153,0.12)', alignItems: 'center', justifyContent: 'center', marginRight: 8 },
  deskStatusDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#39C993' },
  deskStatusText: { color: '#FFFFFF', fontSize: 12, fontWeight: '700' },
  managementHeading: { paddingHorizontal: 20, marginBottom: 18 },
  managementEyebrow: { color: '#087D78', fontSize: 10, fontWeight: '800', letterSpacing: 1.2 },
  managementTitle: { color: '#10202C', fontSize: 28, lineHeight: 34, fontWeight: '900', marginTop: 12 },
  managementSub: { color: '#647570', fontSize: 13, lineHeight: 19, marginTop: 5 },

  statsRow: { flexDirection: 'row', flexWrap: 'wrap', justifyContent: 'space-between', marginBottom: 4, paddingHorizontal: 20 },
  statCard: {
    backgroundColor: COLORS.surface, borderRadius: 16, padding: 14, width: '48%',
    marginBottom: 12, borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  statAccentDot: { width: 8, height: 8, borderRadius: 4, marginBottom: 8 },
  statLabel: { fontSize: 10, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.6, marginBottom: 4 },
  statValue: { fontSize: 20, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono },

  sectionLabel: { fontSize: 11, fontWeight: '700', color: COLORS.textSecondary, letterSpacing: 1, marginBottom: 12, marginTop: 4, marginHorizontal: 20 },
  panel: {
    backgroundColor: COLORS.surface, borderRadius: 18, padding: 18, marginBottom: 20, marginHorizontal: 20,
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
    alignItems: 'center', justifyContent: 'center', marginBottom: 6, ...softShadow, shadowOpacity: 0.15,
  },
  addButtonText: { color: '#fff', fontSize: 14, fontWeight: '700', marginLeft: 6 },

  emptyState: { flexDirection: 'row', alignItems: 'center', paddingVertical: 20, gap: 8, justifyContent: 'center' },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center' },

  announcementItem: {
    backgroundColor: COLORS.bg, borderRadius: 14, padding: 14,
    borderWidth: 1, borderColor: COLORS.border,
  },
  announcementHeader: { marginBottom: 6 },
  announcementTitleContainer: {
    flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4,
  },
  announcementTitle: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary, flex: 1, marginRight: 8 },
  statusBadge: {
    flexDirection: 'row', alignItems: 'center', paddingHorizontal: 8, paddingVertical: 3, borderRadius: 8,
  },
  statusBadgeDot: { width: 5, height: 5, borderRadius: 2.5, marginRight: 5 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.4, fontFamily: mono },
  announcementType: { fontSize: 12, color: COLORS.textTertiary },
  announcementContent: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18, marginBottom: 8 },
  announcementFooter: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 10 },
  announcementDate: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
  announcementAuthor: { fontSize: 10, color: COLORS.textTertiary, letterSpacing: 0.3 },

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
    backgroundColor: COLORS.surface, borderRadius: 22, width: '92%', maxHeight: '85%',
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
  imagePickerButton: {
    flexDirection: 'row', backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border,
    paddingVertical: 12, borderRadius: 12, alignItems: 'center', justifyContent: 'center',
  },
  imagePickerText: { fontSize: 13, color: COLORS.primary, fontWeight: '600' },
  previewImage: { width: '100%', height: 150, borderRadius: 12, marginTop: 8 },

  modalActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: 10, marginTop: 16 },
  modalButton: { paddingVertical: 12, paddingHorizontal: 20, borderRadius: 12, minWidth: 100, alignItems: 'center' },
  cancelButton: { backgroundColor: COLORS.bg, borderWidth: 1, borderColor: COLORS.border },
  confirmButton: { backgroundColor: COLORS.primary, ...softShadow, shadowOpacity: 0.15 },
  cancelButtonText: { fontSize: 13, fontWeight: '700', color: COLORS.textSecondary },
  confirmButtonText: { fontSize: 13, fontWeight: '700', color: '#fff' },

  viewStatusRow: { marginTop: 6 },
  viewTitle: { fontSize: 19, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 12 },
  viewImage: { width: '100%', height: 200, borderRadius: 14, marginBottom: 14 },
  viewMeta: { marginBottom: 4 },
  viewMetaRow: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 6 },
  viewMetaLabel: { fontSize: 10, fontWeight: '700', color: COLORS.textTertiary, letterSpacing: 0.5 },
  viewMetaValue: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },
  viewContent: { fontSize: 14, color: COLORS.textSecondary, lineHeight: 21 },
});
