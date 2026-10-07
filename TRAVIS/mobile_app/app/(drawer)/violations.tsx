import React, { useState, useCallback, useEffect, useMemo } from 'react';
import {
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
  Platform,
  KeyboardAvoidingView,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Href, useLocalSearchParams, useRouter, useSegments } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from 'expo-router/react-navigation';
import api from '../../api/axiosConfig';
import * as ImagePicker from 'expo-image-picker';

// ========== COLOR TOKENS ==========
const COLORS = {
  bg: 'rgba(247, 245, 238, 0.74)',
  header: '#102F49',
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

// ========== TYPES ==========
interface Violation {
  id: number;
  ticketNumber: string;
  driverName: string;
  driverAddress: string;
  licenseNumber: string;
  licenseExpiryDate: string;
  hasNoLicense: boolean;
  licenseConfiscated: boolean;
  licenseRemarks: string;
  dateOfBirth: string;
  plateNumber: string;
  hasNoPlate: boolean;
  vehicleOwner: string;
  vehicleRegistrationNumber: string;
  vehicleColor: string;
  insurancePolicyNumber: string;
  codingStickerNumber: string;
  vehicleToda: string;
  vehicleType: string;
  violationType: string;
  location: string;
  date: string;
  time: string;
  penalty: number;
  status: 'pending' | 'overdue' | 'paid' | 'cancelled';
  createdAt: string;
}

interface Violator {
  key: string;
  driverName: string;
  licenseNumber: string;
  records: Violation[];
  latest: Violation;
  totalPenalties: number;
  unpaidCount: number;
}

type StatusFilter = '' | 'pending' | 'overdue' | 'paid' | 'cancelled';
type OffenseAnalysis = { previous_offenses: number; suggested_offense: number; maximum_offense: number; at_maximum: boolean; matched_by: string | null; last_violation_date: string | null; last_ticket_number: string | null; suggested_penalty: number; penalty_schedule: number[] };
type SelectedViolation = { violation_type: string; penalty_amount: string; ocr_confidence?: number; penalty_overridden?: boolean; auto_added?: boolean };

const FormSection = ({ number, title, subtitle, icon }: { number: string; title: string; subtitle: string; icon: keyof typeof Ionicons.glyphMap }) => (
  <View style={styles.formSectionHeader}>
    <View style={styles.formSectionNumber}><Text style={styles.formSectionNumberText}>{number}</Text></View>
    <View style={styles.formSectionHeading}>
      <Text style={styles.formSectionTitle}>{title}</Text>
      <Text style={styles.formSectionSubtitle}>{subtitle}</Text>
    </View>
    <Ionicons name={icon} size={20} color={COLORS.primary} />
  </View>
);

// ========== HELPERS ==========
const formatCurrency = (amount: number): string => `\u20b1${amount.toLocaleString()}`;
const safeText = (value: unknown): string => value == null ? '' : String(value);

const statusColor = (status: string): string => {
  const s = status.toLowerCase();
  if (s === 'paid' || s === 'resolved') return COLORS.success;
  if (s === 'pending') return COLORS.warning;
  if (s === 'overdue' || s === 'critical') return COLORS.danger;
  if (s === 'cancelled') return COLORS.neutral;
  return COLORS.neutral;
};

const STATUS_FILTERS: { label: string; value: StatusFilter }[] = [
  { label: 'All', value: '' },
  { label: 'Pending', value: 'pending' },
  { label: 'Overdue', value: 'overdue' },
  { label: 'Paid', value: 'paid' },
  { label: 'Cancelled', value: 'cancelled' },
];

// ========== SCREEN ==========
export default function ViolationsScreen() {
  const router = useRouter();
  const segments = useSegments();
  const { open, nonce } = useLocalSearchParams<{ open?: string; nonce?: string }>();
  const [loading, setLoading] = useState(true);
  const [violations, setViolations] = useState<Violation[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('');
  const [refreshing, setRefreshing] = useState(false);
  const [modalVisible, setModalVisible] = useState(false);
  const [page, setPage] = useState(1);
  const PAGE_SIZE = 10;

  const [formData, setFormData] = useState({
    ticket_number: '',
    driver_name: '',
    driver_address: '',
    date_of_birth: '',
    license_number: '',
    license_expiry_date: '',
    license_remarks: '',
    plate_number: '',
    vehicle_owner: '',
    vehicle_registration_number: '',
    vehicle_color: '',
    insurance_policy_number: '',
    coding_sticker_number: '',
    vehicle_toda: '',
    vehicle_type: 'Car',
    violation_type: '',
    location: '',
    violation_date: new Date().toISOString().slice(0, 10),
    violation_time: new Date().toTimeString().slice(0, 5),
    penalty_amount: '',
    offense_number: '1',
    ticket_remarks: '',
    apprehending_officer_name: '',
    apprehending_officer_position: '',
    apprehension_datetime: '',
  });
  const [submitting, setSubmitting] = useState(false);
  const [hasNoLicense, setHasNoLicense] = useState(false);
  const [licenseConfiscated, setLicenseConfiscated] = useState(false);
  const [hasNoPlate, setHasNoPlate] = useState(false);
  const [scanning, setScanning] = useState(false);
  const [inputMethod, setInputMethod] = useState<'manual' | 'ocr'>('manual');
  const [ocrNotice, setOcrNotice] = useState('');
  const [selectedViolations, setSelectedViolations] = useState<SelectedViolation[]>([]);
  const [violationDropdownOpen, setViolationDropdownOpen] = useState(false);
  const [selectedViolator, setSelectedViolator] = useState<Violator | null>(null);
  const [violationTypes, setViolationTypes] = useState<string[]>([]);
  const [penaltyFees, setPenaltyFees] = useState<number[]>([]);
  const [vehicleTypes, setVehicleTypes] = useState<string[]>([]);
  const [offenseAnalyses, setOffenseAnalyses] = useState<Record<string, OffenseAnalysis>>({});
  const [analyzingOffense, setAnalyzingOffense] = useState(false);
  const [offenseFloor, setOffenseFloor] = useState(1);

  useEffect(() => {
    if (open !== 'ocr') return;
    const frame = requestAnimationFrame(() => {
      setInputMethod('ocr');
      setOcrNotice('Choose Take Photo or Gallery to scan a traffic ticket.');
      setModalVisible(true);
    });
    return () => cancelAnimationFrame(frame);
  }, [open, nonce]);

  useEffect(() => {
    api.get('get_violation_options.php').then(response => {
      const options = response.data?.data || {};
      setViolationTypes(options.violation_types || []);
      setPenaltyFees(options.penalty_fees || []);
      setVehicleTypes(options.vehicle_types || []);
    }).catch(() => Alert.alert('Configuration error', 'The official violation options could not be loaded.'));
  }, []);

  const selectedTypeKey = selectedViolations.map(item => item.violation_type).sort().join('|');
  useEffect(() => {
    const selectedTypes = selectedTypeKey ? selectedTypeKey.split('|').filter(type => violationTypes.includes(type)) : [];
    if (!selectedTypes.length) {
      setFormData(current => ({ ...current, offense_number: String(offenseFloor) }));
      return;
    }
    let cancelled = false;
    const timer = setTimeout(async () => {
      setAnalyzingOffense(true);
      try {
        const results = await Promise.all(selectedTypes.map(async type => {
          const response = await api.get('analyze_offense.php', { params: {
            driver_name: formData.driver_name.trim(),
            license_number: hasNoLicense ? '' : formData.license_number.trim(),
            date_of_birth: formData.date_of_birth.trim(),
            violation_type: type,
          } });
          return [type, response.data?.data] as const;
        }));
        if (cancelled) return;
        const nextAnalyses = Object.fromEntries(results.filter((entry): entry is readonly [string, OffenseAnalysis] => !!entry[1]));
        setOffenseAnalyses(nextAnalyses);
        const automaticOffense = Math.max(offenseFloor, ...Object.values(nextAnalyses).map(analysis => Number(analysis.suggested_offense || 1)));
        setFormData(current => ({ ...current, offense_number: String(Math.min(4, automaticOffense)) }));
        setSelectedViolations(current => current.map(item => {
          const analysis = nextAnalyses[item.violation_type];
          if (!analysis || item.penalty_overridden) return item;
          return { ...item, penalty_amount: String(analysis.suggested_penalty), penalty_overridden: false };
        }));
      } catch {
        if (!cancelled) setOffenseAnalyses({});
      } finally {
        if (!cancelled) setAnalyzingOffense(false);
      }
    }, 350);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [formData.driver_name, formData.license_number, formData.date_of_birth, hasNoLicense, offenseFloor, selectedTypeKey, violationTypes]);

  const toggleViolation = (type: string, confidence?: number) => {
    const selected = selectedViolations.some(item => item.violation_type === type);
    if (type === "No Driver's License") setHasNoLicense(!selected);
    setSelectedViolations(current => selected
      ? current.filter(item => item.violation_type !== type)
      : [...current, { violation_type: type, penalty_amount: '', ocr_confidence: confidence, penalty_overridden: false }]);
    if (selected) {
      setOffenseAnalyses(current => {
        const next = { ...current };
        delete next[type];
        return next;
      });
    }
  };

  const setViolationPenalty = (type: string, penalty: number | string) => {
    setSelectedViolations(current => current.map(item => item.violation_type === type
      ? { ...item, penalty_amount: String(penalty), penalty_overridden: true }
      : item));
  };

  const toggleNoLicenseStatus = () => {
    const next = !hasNoLicense;
    setHasNoLicense(next);
    if (next) {
      setFormData(current => ({ ...current, license_number: '', license_expiry_date: '' }));
    }
    if (!next) {
      setOffenseAnalyses(analyses => {
        const remaining = { ...analyses };
        delete remaining["No Driver's License"];
        return remaining;
      });
    }
    setSelectedViolations(current => {
      if (next) {
        return current.some(item => item.violation_type === "No Driver's License")
          ? current
          : [...current, { violation_type: "No Driver's License", penalty_amount: '', penalty_overridden: false, auto_added: true }];
      }
      return current.filter(item => item.violation_type !== "No Driver's License");
    });
  };

  const toggleNoPlateStatus = () => {
    setHasNoPlate(value => !value);
  };

  const scanTicket = async (source: 'camera' | 'gallery') => {
    const permission = source === 'camera'
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();
    if (!permission.granted) {
      Alert.alert('Permission required', `Allow ${source === 'camera' ? 'camera' : 'photo library'} access to scan a ticket.`);
      return;
    }
    const result = source === 'camera'
      ? await ImagePicker.launchCameraAsync({ mediaTypes: ['images'], quality: 1, allowsEditing: false })
      : await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 1, allowsEditing: false });
    if (result.canceled || !result.assets[0]) return;

    setScanning(true);
    setOcrNotice('Reading ticket…');
    try {
      const asset = result.assets[0];
      const body = new FormData();
      body.append('ticket', { uri: asset.uri, name: asset.fileName || 'ticket.jpg', type: asset.mimeType || 'image/jpeg' } as any);
      const response = await api.post('scan_ticket.php', body, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 60000 });
      const fields = response.data.fields || {};
      const fieldConfidences = response.data.field_confidences || {};
      const minimumFieldConfidence = 0.55;
      const trustedField = (name: string) => String(fields[name] || '').trim() && Number(fieldConfidences[name] || 0) >= minimumFieldConfidence;
      const checked = Array.isArray(response.data.checked_violations) ? response.data.checked_violations : [];
      const detectedTypes: SelectedViolation[] = checked
        .filter((item: any) => Number(item.confidence || 0) >= minimumFieldConfidence && violationTypes.includes(String(item.violation_type || '')))
        .map((item: any) => ({ violation_type: String(item.violation_type), penalty_amount: '', ocr_confidence: Number(item.confidence || 0) }));
      // Printed violation text alone is never enough: only a positively
      // detected checkbox may populate the recorded violation list.
      const noLicenseDetected = detectedTypes.some((item: any) => item.violation_type === "No Driver's License");
      const recognizedPlate = String(fields.plate_number || '').trim().toUpperCase();
      const noPlateDetected = Boolean(trustedField('plate_number') && ['NO PLATE', 'NOPLATE', 'NONE', 'N/A', 'NA'].includes(recognizedPlate));
      setOffenseAnalyses({});
      setSelectedViolations(detectedTypes);
      setHasNoLicense(noLicenseDetected);
      setHasNoPlate(noPlateDetected);
      const detectedVehicleType = trustedField('vehicle_type') ? String(fields.vehicle_type) : formData.vehicle_type;
      const isDetectedPampasadaTricycle = detectedVehicleType === 'Tricycle';
      setFormData(current => ({
        ...current,
        ticket_number: trustedField('ticket_number') ? fields.ticket_number : current.ticket_number,
        driver_name: trustedField('driver_name') ? fields.driver_name : current.driver_name,
        driver_address: trustedField('driver_address') ? fields.driver_address : current.driver_address,
        date_of_birth: trustedField('date_of_birth') ? fields.date_of_birth : current.date_of_birth,
        license_number: noLicenseDetected ? '' : (trustedField('license_number') ? fields.license_number : current.license_number),
        license_expiry_date: noLicenseDetected ? '' : (trustedField('license_expiry_date') ? fields.license_expiry_date : current.license_expiry_date),
        license_remarks: trustedField('license_remarks') ? fields.license_remarks : current.license_remarks,
        plate_number: noPlateDetected ? '' : (trustedField('plate_number') ? fields.plate_number : current.plate_number),
        vehicle_owner: trustedField('vehicle_owner') ? fields.vehicle_owner : current.vehicle_owner,
        vehicle_registration_number: trustedField('vehicle_registration_number') ? fields.vehicle_registration_number : current.vehicle_registration_number,
        vehicle_color: trustedField('vehicle_color') ? fields.vehicle_color : current.vehicle_color,
        insurance_policy_number: trustedField('insurance_policy_number') ? fields.insurance_policy_number : current.insurance_policy_number,
        coding_sticker_number: isDetectedPampasadaTricycle && trustedField('coding_sticker_number') ? fields.coding_sticker_number : '',
        vehicle_toda: isDetectedPampasadaTricycle && trustedField('vehicle_toda') ? fields.vehicle_toda : '',
        vehicle_type: detectedVehicleType,
        violation_type: '',
        location: trustedField('location') ? fields.location : current.location,
        violation_date: trustedField('violation_date') ? fields.violation_date : current.violation_date,
        violation_time: trustedField('violation_time') ? fields.violation_time : current.violation_time,
        penalty_amount: trustedField('penalty_amount') ? fields.penalty_amount : current.penalty_amount,
        ticket_remarks: trustedField('ticket_remarks') ? fields.ticket_remarks : current.ticket_remarks,
        apprehending_officer_name: trustedField('apprehending_officer_name') ? fields.apprehending_officer_name : current.apprehending_officer_name,
        apprehending_officer_position: trustedField('apprehending_officer_position') ? fields.apprehending_officer_position : current.apprehending_officer_position,
      }));
      setInputMethod('ocr');
      const warning = String(response.data.warning || '').trim();
      const lowConfidenceFields = Object.keys(fields).filter(name => fields[name] && !trustedField(name));
      const confidenceWarning = lowConfidenceFields.length ? ` Low-confidence fields were left blank: ${lowConfidenceFields.join(', ').replaceAll('_', ' ')}.` : '';
      const documentNotice = response.data.document_detected
        ? 'Document edges detected and perspective corrected. '
        : 'Full ticket edges were not detected; keep the entire paper visible on a contrasting background. ';
      setOcrNotice(`${documentNotice}${warning || `${response.data.recognized_fields || 0} fields detected.`}${confidenceWarning} Review all values before saving.`);
    } catch (error: any) {
      setOcrNotice('');
      Alert.alert('Ticket scan failed', error.response?.data?.error || 'The ticket could not be read. Try a clearer, well-lit photo.');
    } finally { setScanning(false); }
  };

  // ===== FETCH VIOLATIONS =====
  const fetchViolations = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get('get_violations.php', {
        params: { limit: 10000 },
      });
      if (response.data.success) {
        const records = Array.isArray(response.data.data) ? response.data.data : [];
        const data = records.map((item: any) => ({
          id: Number(item.violation_id || 0),
          ticketNumber: safeText(item.ticket_number),
          driverName: safeText(item.driver_name) || 'Unknown driver',
          driverAddress: safeText(item.driver_address),
          licenseNumber: safeText(item.license_number),
          licenseExpiryDate: safeText(item.license_expiry_date),
          hasNoLicense: Boolean(Number(item.has_no_license)),
          licenseConfiscated: Boolean(Number(item.license_confiscated)),
          licenseRemarks: safeText(item.license_remarks),
          dateOfBirth: safeText(item.date_of_birth),
          plateNumber: safeText(item.plate_number),
          hasNoPlate: Boolean(Number(item.has_no_plate)),
          vehicleOwner: safeText(item.vehicle_owner),
          vehicleRegistrationNumber: safeText(item.vehicle_registration_number),
          vehicleColor: safeText(item.vehicle_color),
          insurancePolicyNumber: safeText(item.insurance_policy_number),
          codingStickerNumber: safeText(item.coding_sticker_number),
          vehicleToda: safeText(item.vehicle_toda),
          vehicleType: safeText(item.vehicle_type),
          violationType: safeText(item.violation_type) || 'Unspecified violation',
          location: safeText(item.violation_location),
          date: safeText(item.violation_date),
          time: safeText(item.violation_time),
          penalty: Number.isFinite(Number(item.penalty_amount)) ? Number(item.penalty_amount) : 0,
          status: safeText(item.status).toLowerCase() || 'pending',
          createdAt: safeText(item.created_at),
        }));
        setViolations(data);
        setPage(1);
      }
    } catch (error: any) {
      console.warn('Fetch violations request failed:', error.response?.status, error.message);
      Alert.alert(
        'Unable to load violations',
        error.response?.data?.error || error.response?.data?.message || error.message || 'Check the server connection and try again.'
      );
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void fetchViolations();
    }, [fetchViolations])
  );

  const onRefresh = () => {
    setRefreshing(true);
    fetchViolations();
  };

  const clearFilters = () => {
    setSearch('');
    setStatusFilter('');
    setPage(1);
  };

  const violators = useMemo<Violator[]>(() => {
    const grouped = new Map<string, Violator>();
    violations.forEach(record => {
      const license = record.licenseNumber.trim().toUpperCase();
      const hasLicense = license !== '' && license !== 'NO LICENSE';
      const normalizedName = record.driverName.trim().replace(/\s+/g, ' ').toLowerCase();
      const key = hasLicense
        ? `license:${license}`
        : (record.dateOfBirth ? `name-dob:${normalizedName}|${record.dateOfBirth}` : `name:${normalizedName}`);
      const current = grouped.get(key);
      if (current) {
        current.records.push(record);
        current.totalPenalties += record.penalty;
        if (record.status === 'pending' || record.status === 'overdue') current.unpaidCount += 1;
      } else {
        grouped.set(key, {
          key,
          driverName: record.driverName,
          licenseNumber: record.licenseNumber,
          records: [record],
          latest: record,
          totalPenalties: record.penalty,
          unpaidCount: record.status === 'pending' || record.status === 'overdue' ? 1 : 0,
        });
      }
    });
    return Array.from(grouped.values());
  }, [violations]);

  const filteredViolators = useMemo(() => {
    const needle = search.trim().toLowerCase();
    return violators.filter(violator => violator.records.some(record => {
      const matchesStatus = !statusFilter || record.status === statusFilter;
      const searchable = [record.ticketNumber, record.driverName, record.licenseNumber, record.plateNumber, record.violationType, record.location].join(' ').toLowerCase();
      return matchesStatus && (!needle || searchable.includes(needle));
    }));
  }, [search, statusFilter, violators]);

  const totalPages = Math.max(1, Math.ceil(filteredViolators.length / PAGE_SIZE));
  const visibleViolators = filteredViolators.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE);

  // ===== ADD VIOLATION =====
  const handleAddViolation = async () => {
    const { driver_name, license_number, plate_number, vehicle_type, location } = formData;
    if (!driver_name.trim() || (!hasNoLicense && !license_number.trim()) || (!hasNoPlate && !plate_number.trim()) || !location.trim()) {
      Alert.alert('Error', 'Please fill in all fields.');
      return;
    }
    if (!selectedViolations.length || selectedViolations.some(item => !violationTypes.includes(item.violation_type))) {
      Alert.alert('Invalid violation', 'Select at least one violation from the official list.');
      return;
    }
    if (!vehicleTypes.includes(vehicle_type)) {
      Alert.alert('Invalid vehicle', 'Select a valid vehicle type.');
      return;
    }
    if (selectedViolations.some(item => !Number.isFinite(Number(item.penalty_amount)) || Number(item.penalty_amount) <= 0 || Number(item.penalty_amount) > 99999999.99)) {
      Alert.alert('Invalid penalty', 'Enter a valid positive penalty for every violation.');
      return;
    }
    setSubmitting(true);
    try {
      const response = await api.post('add_violations.php', {
        ...formData,
        driver_name,
        license_number: hasNoLicense ? 'NO LICENSE' : license_number,
        license_expiry_date: hasNoLicense ? '' : formData.license_expiry_date,
        has_no_license: hasNoLicense,
        license_confiscated: licenseConfiscated,
        plate_number: hasNoPlate ? 'NO PLATE' : plate_number,
        has_no_plate: hasNoPlate,
        vehicle_type,
        coding_sticker_number: vehicle_type === 'Tricycle' ? formData.coding_sticker_number : '',
        vehicle_toda: vehicle_type === 'Tricycle' ? formData.vehicle_toda : '',
        violations: selectedViolations.map(item => ({ ...item, penalty_amount: Number(item.penalty_amount) })),
        location,
        offense_number: Number(formData.offense_number),
        input_method: inputMethod,
      });
      if (response.data.success) {
        const offense = response.data.offense_analysis;
        Alert.alert('Violation saved', offense?.previous_offenses > 0
          ? `${response.data.ticket_number} was recorded as suggested offense #${offense.suggested_offense}. ${offense.previous_offenses} previous matching offense(s) found.`
          : `${response.data.ticket_number} was recorded as a first offense.`);
        setModalVisible(false);
        setFormData({ ticket_number: '', driver_name: '', driver_address: '', date_of_birth: '', license_number: '', license_expiry_date: '', license_remarks: '', plate_number: '', vehicle_owner: '', vehicle_registration_number: '', vehicle_color: '', insurance_policy_number: '', coding_sticker_number: '', vehicle_toda: '', vehicle_type: 'Car', violation_type: '', location: '', violation_date: new Date().toISOString().slice(0, 10), violation_time: new Date().toTimeString().slice(0, 5), penalty_amount: '', offense_number: '1', ticket_remarks: '', apprehending_officer_name: '', apprehending_officer_position: '', apprehension_datetime: '' });
        setSelectedViolations([]);
        setHasNoLicense(false);
        setLicenseConfiscated(false);
        setHasNoPlate(false);
        setInputMethod('manual');
        setOcrNotice('');
        setOffenseAnalyses({});
        setOffenseFloor(1);
        fetchViolations();
      } else {
        Alert.alert('Error', response.data.error || 'Failed to add violation.');
      }
    } catch (error: any) {
      Alert.alert('Error', error.response?.data?.error || 'Network error.');
    } finally {
      setSubmitting(false);
    }
  };

  const openPayment = (item: Violation) => {
    const route = segments.includes('(treasurer)' as never)
      ? `/(treasurer)/payments?violation_id=${item.id}`
      : `/(drawer)/payments?violation_id=${item.id}`;
    router.push(route as Href);
  };

  const addViolationFor = (violator: Violator) => {
    const latest = violator.latest;
    const noLicense = violator.licenseNumber.trim().toUpperCase() === 'NO LICENSE';
    const normalizedPlate = latest.plateNumber.trim().toUpperCase();
    const noPlate = ['NO PLATE', 'NOPLATE', 'NONE', 'N/A', 'NA'].includes(normalizedPlate);

    const nextOffense = Math.min(4, violator.records.length + 1);
    setOffenseFloor(nextOffense);
    setFormData({
      ticket_number: '',
      driver_name: violator.driverName,
      driver_address: latest.driverAddress || '',
      date_of_birth: latest.dateOfBirth || '',
      license_number: noLicense ? '' : violator.licenseNumber,
      license_expiry_date: noLicense ? '' : (latest.licenseExpiryDate || ''),
      license_remarks: latest.licenseRemarks || '',
      plate_number: noPlate ? '' : latest.plateNumber,
      vehicle_owner: latest.vehicleOwner || '',
      vehicle_registration_number: latest.vehicleRegistrationNumber || '',
      vehicle_color: latest.vehicleColor || '',
      insurance_policy_number: latest.insurancePolicyNumber || '',
      coding_sticker_number: latest.vehicleType === 'Tricycle' ? (latest.codingStickerNumber || '') : '',
      vehicle_toda: latest.vehicleType === 'Tricycle' ? (latest.vehicleToda || '') : '',
      vehicle_type: latest.vehicleType,
      violation_type: '',
      location: '',
      violation_date: new Date().toISOString().slice(0, 10),
      violation_time: new Date().toTimeString().slice(0, 5),
      penalty_amount: '',
      offense_number: String(nextOffense),
      ticket_remarks: '',
      apprehending_officer_name: '',
      apprehending_officer_position: '',
      apprehension_datetime: '',
    });
    setHasNoLicense(noLicense);
    setLicenseConfiscated(latest.licenseConfiscated);
    setLicenseConfiscated(false);
    setHasNoPlate(noPlate);
    setSelectedViolations([]);
    setOffenseAnalyses({});
    setViolationDropdownOpen(false);
    setInputMethod('manual');
    setOcrNotice('Personal and latest vehicle information were copied from this violator. Add the new violation details below.');
    setSelectedViolator(null);
    requestAnimationFrame(() => setModalVisible(true));
  };

  const cancelViolation = (item: Violation) => Alert.alert(
    'Cancel Violation',
    `Cancel ticket ${item.ticketNumber}? This removes it from pending collections.`,
    [
      { text: 'Keep Record', style: 'cancel' },
      { text: 'Cancel Violation', style: 'destructive', onPress: async () => {
        try {
          await api.post('update_violation_status.php', { violation_id: item.id, status: 'cancelled' });
          Alert.alert('Updated', 'The violation has been cancelled.');
          fetchViolations();
        } catch (error: any) {
          Alert.alert('Unable to cancel', error.response?.data?.error || 'Please try again.');
        }
      } },
    ],
  );

  // ========== RENDER HELPERS ==========
  const renderSummaryCell = (icon: React.ReactNode, label: string, value: number, isLast: boolean) => (
    <View style={[styles.summaryCell, !isLast && styles.summaryCellDivider]}>
      {icon}
      <Text style={styles.summaryValue}>{value}</Text>
      <Text style={styles.summaryLabel}>{label}</Text>
    </View>
  );

  const renderViolatorItem = ({ item }: { item: Violator }) => (
    <View style={styles.violationCard}>
      <View style={styles.violationRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.driverInfo}>{item.driverName}</Text>
          <Text style={styles.vehicleInfo}>{item.licenseNumber}</Text>
        </View>
        <View style={[styles.statusBadge, { backgroundColor: (item.unpaidCount ? COLORS.warning : COLORS.success) + '1A' }]}>
          <Text style={[styles.statusText, { color: item.unpaidCount ? COLORS.warning : COLORS.success }]}>
            {item.records.length} RECORD{item.records.length === 1 ? '' : 'S'}
          </Text>
        </View>
      </View>

      <View style={styles.violationMetaRow}>
        <View style={{ flex: 1 }}><Text style={styles.violationType}>{item.latest.violationType}</Text><Text style={styles.location}>Latest: {item.latest.ticketNumber}</Text></View>
        <Text style={styles.location}>{item.latest.plateNumber} · {item.latest.vehicleType}</Text>
      </View>

      <View style={styles.violationDivider} />

      <View style={styles.violationFooter}>
        <View><Text style={styles.dateTime}>LAST RECORDED · {item.latest.date}</Text><Text style={styles.dateTime}>{item.unpaidCount} unpaid ticket{item.unpaidCount === 1 ? '' : 's'}</Text></View>
        <View style={{ alignItems: 'flex-end' }}><Text style={styles.penalty}>{formatCurrency(item.totalPenalties)}</Text><Text style={styles.location}>Total fines</Text></View>
      </View>

      <View style={styles.actionRow}>
        <TouchableOpacity style={[styles.actionButtonOutline, { flex: 1, alignItems: 'center' }]} onPress={() => setSelectedViolator(item)}>
          <Text style={styles.actionTextOutline}>View Record</Text>
        </TouchableOpacity>
      </View>
    </View>
  );

  // ===== COMPUTE COUNTS =====
  const counts = {
    today: violations.filter(v => v.date === new Date().toISOString().slice(0, 10)).length,
    awaiting: violations.filter(v => v.status === 'pending' || v.status === 'overdue').length,
    paid: violations.filter(v => v.status === 'paid').length,
    cancelled: violations.filter(v => v.status === 'cancelled').length,
  };

  if (loading) {
    return (
      <SafeAreaView style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading violations…</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="dark-content" backgroundColor={COLORS.bg} />

      <ScrollView
        style={styles.container}
        contentContainerStyle={{ paddingBottom: 100 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={COLORS.primary} />}
        showsVerticalScrollIndicator={false}
      >
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.eyebrow}>VIOLATION MANAGEMENT</Text>
          <Text style={styles.pageTitle}>Violation Records</Text>
          <Text style={styles.pageSub}>Record, review, and route unpaid traffic violations to the payment module.</Text>
        </View>

        {/* Summary panel */}
        <View style={styles.summaryPanel}>
          {renderSummaryCell(<Ionicons name="calendar-outline" size={16} color={COLORS.warning} />, 'Recorded Today', counts.today, false)}
          {renderSummaryCell(<Ionicons name="alert-circle-outline" size={16} color={COLORS.danger} />, 'Awaiting Payment', counts.awaiting, false)}
          {renderSummaryCell(<Ionicons name="checkmark-circle-outline" size={16} color={COLORS.success} />, 'Paid', counts.paid, false)}
          {renderSummaryCell(<Ionicons name="close-circle-outline" size={16} color={COLORS.neutral} />, 'Cancelled', counts.cancelled, true)}
        </View>

        {/* Search */}
        <View style={styles.searchWrap}>
          <Ionicons name="search" size={16} color={COLORS.textTertiary} style={styles.searchIcon} />
          <TextInput
            style={styles.searchInput}
            placeholder="Ticket, driver, plate, violation, location..."
            placeholderTextColor={COLORS.textTertiary}
            value={search}
            onChangeText={value => { setSearch(value); setPage(1); }}
          />
          {search.length > 0 && (
            <TouchableOpacity onPress={() => setSearch('')} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
              <Ionicons name="close-circle" size={16} color={COLORS.textTertiary} />
            </TouchableOpacity>
          )}
        </View>

        {/* Status filter chips */}
        <View style={styles.filterRow}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingRight: 20 }}>
            {STATUS_FILTERS.map(f => {
              const active = statusFilter === f.value;
              return (
                <TouchableOpacity
                  key={f.label}
                  style={[styles.filterChip, active && styles.filterChipActive]}
                  onPress={() => { setStatusFilter(f.value); setPage(1); }}
                  activeOpacity={0.7}
                >
                  <Text style={[styles.filterChipText, active && styles.filterChipTextActive]}>{f.label}</Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
          {(search.trim() || statusFilter) && (
            <TouchableOpacity onPress={clearFilters} style={styles.clearLink}>
              <Text style={styles.clearLinkText}>Clear</Text>
            </TouchableOpacity>
          )}
        </View>

        {/* Result count */}
        <Text style={styles.resultCount}>{filteredViolators.length} violator{filteredViolators.length === 1 ? '' : 's'} found · {violations.length} total ticket records</Text>

        {/* Violations list */}
        <FlatList
          data={visibleViolators}
          renderItem={renderViolatorItem}
          keyExtractor={item => item.key}
          scrollEnabled={false}
          ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
          ListEmptyComponent={
            <View style={styles.emptyState}>
              <Ionicons name="document-text-outline" size={28} color={COLORS.textTertiary} />
              <Text style={styles.emptyText}>No violation records matched your search or filter.</Text>
              {(search.trim() || statusFilter) && (
                <TouchableOpacity onPress={clearFilters} style={styles.emptyClearButton}>
                  <Text style={styles.emptyClearButtonText}>Clear filters</Text>
                </TouchableOpacity>
              )}
            </View>
          }
        />
        {filteredViolators.length > PAGE_SIZE && (
          <View style={styles.pagination}>
            <TouchableOpacity disabled={page === 1} onPress={() => setPage(value => Math.max(1, value - 1))} style={[styles.pageButton, page === 1 && styles.pageButtonDisabled]}>
              <Ionicons name="chevron-back" size={16} color={page === 1 ? COLORS.textTertiary : '#FFF'} />
              <Text style={[styles.pageButtonText, page === 1 && styles.pageButtonTextDisabled]}>Previous</Text>
            </TouchableOpacity>
            <Text style={styles.pageLabel}>Page {page} of {totalPages}</Text>
            <TouchableOpacity disabled={page === totalPages} onPress={() => setPage(value => Math.min(totalPages, value + 1))} style={[styles.pageButton, page === totalPages && styles.pageButtonDisabled]}>
              <Text style={[styles.pageButtonText, page === totalPages && styles.pageButtonTextDisabled]}>Next</Text>
              <Ionicons name="chevron-forward" size={16} color={page === totalPages ? COLORS.textTertiary : '#FFF'} />
            </TouchableOpacity>
          </View>
        )}
      </ScrollView>

      {/* Floating Add button */}
      <TouchableOpacity style={styles.fab} onPress={() => setModalVisible(true)} activeOpacity={0.85}>
        <Ionicons name="add" size={22} color="#FFFFFF" />
        <Text style={styles.fabText}>Add Violation</Text>
      </TouchableOpacity>

      {/* Add Violation Modal */}
      <Modal animationType="slide" transparent visible={modalVisible} onRequestClose={() => setModalVisible(false)}>
        <KeyboardAvoidingView style={styles.modalOverlay} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeaderRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.modalTitle}>Add Violation</Text>
                <Text style={styles.modalSub}>Scan a paper ticket or enter the details manually.</Text>
              </View>
              <TouchableOpacity onPress={() => setModalVisible(false)} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={22} color={COLORS.textTertiary} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.formScrollContent}>
              <FormSection number="1" title="Ticket capture" subtitle="Scan the ticket or continue with manual entry" icon="scan-outline" />
              <View style={styles.scanPanel}>
                <View style={{ flex: 1 }}><Text style={styles.scanTitle}>OCR Ticket Scanner</Text><Text style={styles.scanSub}>Use a flat, well-lit image with the full ticket visible.</Text></View>
                {scanning && <ActivityIndicator color={COLORS.primary} />}
              </View>
              <View style={styles.scanActions}>
                <TouchableOpacity disabled={scanning} style={styles.scanPrimary} onPress={() => scanTicket('camera')}><Ionicons name="camera-outline" size={17} color="#FFF" /><Text style={styles.scanPrimaryText}>Take Photo</Text></TouchableOpacity>
                <TouchableOpacity disabled={scanning} style={styles.scanSecondary} onPress={() => scanTicket('gallery')}><Ionicons name="images-outline" size={17} color={COLORS.primary} /><Text style={styles.scanSecondaryText}>Gallery</Text></TouchableOpacity>
              </View>
              {!!ocrNotice && <View style={styles.ocrNotice}><Ionicons name="warning-outline" size={16} color={COLORS.warning} /><Text style={styles.ocrNoticeText}>{ocrNotice}</Text></View>}
              <FormSection number="2" title="Driver information" subtitle="Identity and contact details" icon="person-outline" />
              <Text style={styles.modalLabel}>Driver Name</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Driver name"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.driver_name}
                onChangeText={text => setFormData({ ...formData, driver_name: text })}
              />
              <Text style={styles.modalLabel}>Driver Address</Text>
              <TextInput style={styles.modalInput} placeholder="Complete address" placeholderTextColor={COLORS.textTertiary} value={formData.driver_address} onChangeText={text => setFormData({ ...formData, driver_address: text })} />
              <Text style={styles.modalLabel}>Date of Birth</Text>
              <TextInput style={styles.modalInput} placeholder="YYYY-MM-DD" placeholderTextColor={COLORS.textTertiary} value={formData.date_of_birth} onChangeText={text => setFormData({ ...formData, date_of_birth: text })} />
              <FormSection number="3" title="License information" subtitle="License status, expiry, and remarks" icon="card-outline" />
              <Text style={styles.modalLabel}>License Number</Text>
              <TextInput
                style={[styles.modalInput, hasNoLicense && { opacity: 0.55 }]}
                placeholder={hasNoLicense ? 'NO LICENSE' : 'License number'}
                placeholderTextColor={COLORS.textTertiary}
                value={hasNoLicense ? 'NO LICENSE' : formData.license_number}
                onChangeText={text => setFormData({ ...formData, license_number: text })}
                editable={!hasNoLicense}
              />
              <TouchableOpacity
                style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 }}
                onPress={toggleNoLicenseStatus}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: hasNoLicense }}
              >
                <Ionicons name={hasNoLicense ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={{ color: COLORS.textSecondary }}>Driver has no license</Text>
              </TouchableOpacity>
              <Text style={styles.modalLabel}>License Expiry Date</Text>
              {hasNoLicense ? (
                <View style={styles.analysisCard}>
                  <Ionicons name="information-circle-outline" size={20} color={COLORS.primary} />
                  <Text style={styles.analysisText}>Not applicable — driver has no license</Text>
                </View>
              ) : (
                <TextInput style={styles.modalInput} placeholder="YYYY-MM-DD" placeholderTextColor={COLORS.textTertiary} value={formData.license_expiry_date} onChangeText={text => setFormData({ ...formData, license_expiry_date: text })} />
              )}
              <TouchableOpacity style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 }} onPress={() => setLicenseConfiscated(value => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: licenseConfiscated }}>
                <Ionicons name={licenseConfiscated ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={{ color: COLORS.textSecondary }}>License confiscated</Text>
              </TouchableOpacity>
              <TextInput style={styles.modalInput} placeholder="License remarks" placeholderTextColor={COLORS.textTertiary} value={formData.license_remarks} onChangeText={text => setFormData({ ...formData, license_remarks: text })} />
              <FormSection number="4" title="Vehicle information" subtitle="Plate, classification, and registration" icon="car-outline" />
              <Text style={styles.modalLabel}>Plate Number</Text>
              <TextInput
                style={[styles.modalInput, hasNoPlate && { opacity: 0.55 }]}
                placeholder={hasNoPlate ? 'NO PLATE' : 'Plate number'}
                placeholderTextColor={COLORS.textTertiary}
                value={hasNoPlate ? 'NO PLATE' : formData.plate_number}
                onChangeText={text => setFormData({ ...formData, plate_number: text })}
                editable={!hasNoPlate}
              />
              <TouchableOpacity
                style={{ flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 }}
                onPress={toggleNoPlateStatus}
                accessibilityRole="checkbox"
                accessibilityState={{ checked: hasNoPlate }}
              >
                <Ionicons name={hasNoPlate ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={{ color: COLORS.textSecondary }}>Vehicle has no plate number</Text>
              </TouchableOpacity>
              <Text style={styles.modalLabel}>Vehicle Details</Text>
              <TextInput style={styles.modalInput} placeholder="Owner of vehicle" placeholderTextColor={COLORS.textTertiary} value={formData.vehicle_owner} onChangeText={text => setFormData({ ...formData, vehicle_owner: text })} />
              <TextInput style={styles.modalInput} placeholder="Vehicle registration number" placeholderTextColor={COLORS.textTertiary} value={formData.vehicle_registration_number} onChangeText={text => setFormData({ ...formData, vehicle_registration_number: text })} autoCapitalize="characters" />
              <TextInput style={styles.modalInput} placeholder="Vehicle color" placeholderTextColor={COLORS.textTertiary} value={formData.vehicle_color} onChangeText={text => setFormData({ ...formData, vehicle_color: text })} />
              <TextInput style={styles.modalInput} placeholder="Insurance policy number" placeholderTextColor={COLORS.textTertiary} value={formData.insurance_policy_number} onChangeText={text => setFormData({ ...formData, insurance_policy_number: text })} />
              <Text style={styles.modalLabel}>Vehicle Type</Text>
              <View style={styles.optionWrap}>
                {vehicleTypes.map(type => <TouchableOpacity key={type} style={[styles.optionChip, formData.vehicle_type === type && styles.optionChipActive]} onPress={() => setFormData(current => ({ ...current, vehicle_type: type, coding_sticker_number: type === 'Tricycle' ? current.coding_sticker_number : '', vehicle_toda: type === 'Tricycle' ? current.vehicle_toda : '' }))}><Text style={[styles.optionChipText, formData.vehicle_type === type && styles.optionChipTextActive]}>{type === 'Tricycle' ? 'Tricycle (Pampasada)' : type}</Text></TouchableOpacity>)}
              </View>
              {formData.vehicle_type === 'Tricycle' && (
                <>
                  <Text style={styles.modalLabel}>Pampasada Information</Text>
                  <TextInput style={styles.modalInput} placeholder="Coding sticker number" placeholderTextColor={COLORS.textTertiary} value={formData.coding_sticker_number} onChangeText={text => setFormData({ ...formData, coding_sticker_number: text })} />
                  <TextInput style={styles.modalInput} placeholder="TODA" placeholderTextColor={COLORS.textTertiary} value={formData.vehicle_toda} onChangeText={text => setFormData({ ...formData, vehicle_toda: text })} />
                  <Text style={styles.modalSub}>These fields apply only to tricycles operating as public transport.</Text>
                </>
              )}
              <FormSection number="5" title="Violation details" subtitle="Select offenses and confirm penalty amounts" icon="alert-circle-outline" />
              <Text style={styles.modalLabel}>Violations ({selectedViolations.length} selected)</Text>
              <TouchableOpacity
                style={[styles.violationDropdownButton, violationDropdownOpen && styles.violationDropdownButtonOpen]}
                onPress={() => setViolationDropdownOpen(open => !open)}
                activeOpacity={0.75}
              >
                <Ionicons name="list-outline" size={18} color={COLORS.primary} />
                <Text style={[styles.violationDropdownText, !selectedViolations.length && styles.violationDropdownPlaceholder]} numberOfLines={1}>
                  {selectedViolations.length ? `${selectedViolations.length} violation${selectedViolations.length === 1 ? '' : 's'} selected` : 'Select violation types'}
                </Text>
                <Ionicons name={violationDropdownOpen ? 'chevron-up' : 'chevron-down'} size={17} color={COLORS.textTertiary} />
              </TouchableOpacity>
              {violationDropdownOpen && <View style={styles.violationDropdownMenu}>
                <View style={styles.violationSearchRow}>
                  <Ionicons name="search-outline" size={17} color={COLORS.textTertiary} />
                  <TextInput
                    style={styles.violationSearchInput}
                    placeholder="Search violation types"
                    placeholderTextColor={COLORS.textTertiary}
                    value={formData.violation_type}
                    onChangeText={text => setFormData({ ...formData, violation_type: text })}
                  />
                </View>
                <ScrollView style={styles.violationDropdownList} nestedScrollEnabled keyboardShouldPersistTaps="handled">
                  {violationTypes.filter(type => type.toLowerCase().includes(formData.violation_type.toLowerCase())).map(type => {
                    const selected = selectedViolations.some(item => item.violation_type === type);
                    return <TouchableOpacity key={type} style={[styles.violationDropdownOption, selected && styles.violationDropdownOptionSelected]} onPress={() => toggleViolation(type)}>
                      <Text style={[styles.violationDropdownOptionText, selected && styles.violationDropdownOptionTextSelected]}>{type}</Text>
                      <Ionicons name={selected ? 'checkbox' : 'square-outline'} size={20} color={selected ? COLORS.primary : COLORS.textTertiary} />
                    </TouchableOpacity>;
                  })}
                </ScrollView>
                <TouchableOpacity style={styles.violationDropdownDone} onPress={() => setViolationDropdownOpen(false)}>
                  <Text style={styles.violationDropdownDoneText}>Done</Text>
                </TouchableOpacity>
              </View>}
              {selectedViolations.map(item => (
                <View key={item.violation_type} style={styles.analysisCard}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.analysisTitle}>{item.violation_type}</Text>
                    {!!offenseAnalyses[item.violation_type] && <Text style={styles.analysisText}>
                      Offense #{offenseAnalyses[item.violation_type].suggested_offense} by {offenseAnalyses[item.violation_type].matched_by || 'new record'} · suggested {formatCurrency(offenseAnalyses[item.violation_type].suggested_penalty)}
                    </Text>}
                    <TextInput
                      style={[styles.modalInput, { marginTop: 8, marginBottom: 0 }]}
                      placeholder="Editable penalty amount"
                      placeholderTextColor={COLORS.textTertiary}
                      value={item.penalty_amount}
                      onChangeText={value => setViolationPenalty(item.violation_type, value)}
                      keyboardType="decimal-pad"
                    />
                    {!!item.ocr_confidence && <Text style={styles.analysisText}>Checkbox confidence: {Math.round(item.ocr_confidence * 100)}% · confirm the fee</Text>}
                    <View style={[styles.optionWrap, { marginTop: 8 }]}>
                      {penaltyFees.map(fee => <TouchableOpacity key={fee} style={[styles.optionChip, Number(item.penalty_amount) === fee && styles.optionChipActive]} onPress={() => setViolationPenalty(item.violation_type, fee)}><Text style={[styles.optionChipText, Number(item.penalty_amount) === fee && styles.optionChipTextActive]}>₱{fee.toLocaleString()}</Text></TouchableOpacity>)}
                    </View>
                  </View>
                  <TouchableOpacity onPress={() => toggleViolation(item.violation_type)}><Ionicons name="close-circle" size={22} color={COLORS.danger} /></TouchableOpacity>
                </View>
              ))}
              {analyzingOffense && <View style={styles.analysisCard}><ActivityIndicator size="small" color={COLORS.primary} /><Text style={styles.analysisText}>Checking previous offenses…</Text></View>}
              <FormSection number="6" title="Incident information" subtitle="Where and when the violation occurred" icon="location-outline" />
              <Text style={styles.modalLabel}>Location</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Location"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.location}
                onChangeText={text => setFormData({ ...formData, location: text })}
              />
              <Text style={styles.modalLabel}>Date and Time of Violation</Text>
              <TextInput style={styles.modalInput} placeholder="YYYY-MM-DD" placeholderTextColor={COLORS.textTertiary} value={formData.violation_date} onChangeText={text => setFormData({ ...formData, violation_date: text })} />
              <TextInput style={styles.modalInput} placeholder="HH:MM" placeholderTextColor={COLORS.textTertiary} value={formData.violation_time} onChangeText={text => setFormData({ ...formData, violation_time: text })} />
              <Text style={styles.modalLabel}>Offense Level</Text>
              <View style={styles.analysisCard}><Ionicons name="analytics-outline" size={20} color={COLORS.primary} /><Text style={styles.analysisText}>Automatically calculated: {['First', 'Second', 'Third', 'Fourth'][Math.max(0, Number(formData.offense_number) - 1)] || 'First'} offense</Text></View>
              <Text style={styles.modalLabel}>Ticket Remarks</Text>
              <TextInput style={[styles.modalInput, { minHeight: 72, textAlignVertical: 'top' }]} multiline placeholder="Remarks / Treasurer disposition" placeholderTextColor={COLORS.textTertiary} value={formData.ticket_remarks} onChangeText={text => setFormData({ ...formData, ticket_remarks: text })} />
              <FormSection number="7" title="Officer information" subtitle="Apprehending officer and time recorded" icon="shield-checkmark-outline" />
              <Text style={styles.modalLabel}>Apprehending / Arresting Officer</Text>
              <TextInput style={styles.modalInput} placeholder="Officer name" placeholderTextColor={COLORS.textTertiary} value={formData.apprehending_officer_name} onChangeText={text => setFormData({ ...formData, apprehending_officer_name: text })} />
              <TextInput style={styles.modalInput} placeholder="Position" placeholderTextColor={COLORS.textTertiary} value={formData.apprehending_officer_position} onChangeText={text => setFormData({ ...formData, apprehending_officer_position: text })} />
              <TextInput style={styles.modalInput} placeholder="Apprehension date/time: YYYY-MM-DD HH:MM" placeholderTextColor={COLORS.textTertiary} value={formData.apprehension_datetime} onChangeText={text => setFormData({ ...formData, apprehension_datetime: text })} />
              {!!selectedViolations.length && <Text style={styles.modalSub}>Total penalty: ₱{selectedViolations.reduce((sum, item) => sum + Number(item.penalty_amount || 0), 0).toLocaleString()}</Text>}
            </ScrollView>

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelModalButton}
                onPress={() => setModalVisible(false)}
                disabled={submitting}
              >
                <Text style={styles.cancelModalButtonText}>Cancel</Text>
              </TouchableOpacity>
              <TouchableOpacity
                style={styles.saveModalButton}
                onPress={handleAddViolation}
                disabled={submitting}
              >
                {submitting ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.saveModalButtonText}>Save Violation</Text>}
              </TouchableOpacity>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
      <Modal animationType="slide" transparent visible={!!selectedViolator} onRequestClose={() => setSelectedViolator(null)}>
        <View style={styles.modalOverlay}><View style={styles.detailSheet}>
          <View style={styles.modalHeaderRow}><View style={{ flex: 1 }}><Text style={styles.modalTitle}>Violator Record</Text><Text style={styles.modalSub}>Complete driver, vehicle, and violation information</Text></View><TouchableOpacity onPress={() => setSelectedViolator(null)}><Ionicons name="close" size={22} color={COLORS.textTertiary} /></TouchableOpacity></View>
          <ScrollView showsVerticalScrollIndicator={false}>
            {!!selectedViolator && <View style={styles.historyCard}>
              <Text style={styles.violationType}>Driver & License Information</Text>
              <Text style={styles.vehicleInfo}>Name: {selectedViolator.driverName}</Text>
              <Text style={styles.location}>Birth date: {selectedViolator.latest.dateOfBirth || 'Not provided'}</Text>
              <Text style={styles.location}>Address: {selectedViolator.latest.driverAddress || 'Not provided'}</Text>
              <Text style={styles.location}>License: {selectedViolator.licenseNumber || 'Not provided'}</Text>
              <Text style={styles.location}>Expiry: {selectedViolator.latest.hasNoLicense ? 'Not applicable' : (selectedViolator.latest.licenseExpiryDate || 'Not provided')}</Text>
              <Text style={styles.location}>Status: {selectedViolator.latest.hasNoLicense ? 'No driver’s license' : (selectedViolator.latest.licenseConfiscated ? 'Confiscated' : 'Not confiscated')}</Text>
              <Text style={styles.location}>Remarks: {selectedViolator.latest.licenseRemarks || 'None'}</Text>
            </View>}
            {!!selectedViolator && <View style={styles.historyCard}>
              <Text style={styles.violationType}>Latest Vehicle Information</Text>
              <Text style={styles.vehicleInfo}>{selectedViolator.latest.plateNumber} · {selectedViolator.latest.vehicleType}</Text>
              <Text style={styles.location}>Owner: {selectedViolator.latest.vehicleOwner || 'Not provided'}</Text>
              <Text style={styles.location}>Registration: {selectedViolator.latest.vehicleRegistrationNumber || 'Not provided'}</Text>
              <Text style={styles.location}>Color: {selectedViolator.latest.vehicleColor || 'Not provided'}</Text>
              <Text style={styles.location}>Insurance: {selectedViolator.latest.insurancePolicyNumber || 'Not provided'}</Text>
              {selectedViolator.latest.vehicleType === 'Tricycle' && <Text style={styles.location}>Coding sticker: {selectedViolator.latest.codingStickerNumber || 'Not provided'}</Text>}
              {selectedViolator.latest.vehicleType === 'Tricycle' && <Text style={styles.location}>TODA: {selectedViolator.latest.vehicleToda || 'Not provided'}</Text>}
            </View>}
            <Text style={[styles.modalLabel, { marginTop: 4 }]}>Violation History</Text>
            {selectedViolator?.records.map(record => <View key={record.id} style={styles.historyCard}>
              <View style={styles.violationRow}><Text style={styles.ticketNumber}>{record.ticketNumber}</Text><View style={[styles.statusBadge, { backgroundColor: statusColor(record.status) + '1A' }]}><Text style={[styles.statusText, { color: statusColor(record.status) }]}>{record.status.toUpperCase()}</Text></View></View>
              <Text style={styles.violationType}>{record.violationType}</Text>
              <Text style={styles.vehicleInfo}>{record.plateNumber} · {record.vehicleType}</Text>
              <Text style={styles.location}>{record.location}</Text>
              <View style={styles.violationFooter}><Text style={styles.dateTime}>{record.date} · {record.time}</Text><Text style={styles.penalty}>{formatCurrency(record.penalty)}</Text></View>
              {(record.status === 'pending' || record.status === 'overdue') && <View style={styles.actionRow}>
                <TouchableOpacity style={styles.payButton} onPress={() => { setSelectedViolator(null); openPayment(record); }}><Text style={styles.payButtonText}>Proceed to Payment</Text></TouchableOpacity>
                <TouchableOpacity style={styles.cancelButton} onPress={() => cancelViolation(record)}><Text style={styles.cancelButtonText}>Cancel Ticket</Text></TouchableOpacity>
              </View>}
            </View>)}
            {!!selectedViolator && <View style={styles.historyTotal}><Text style={styles.detailLabel}>TOTAL FINES</Text><Text style={styles.penalty}>{formatCurrency(selectedViolator.totalPenalties)}</Text></View>}
          </ScrollView>
          {!!selectedViolator && <TouchableOpacity style={styles.historyAddButton} onPress={() => addViolationFor(selectedViolator)} activeOpacity={0.8}>
            <Ionicons name="add-circle-outline" size={19} color="#FFF" />
            <Text style={styles.historyAddButtonText}>Add Violation for This Person</Text>
          </TouchableOpacity>}
        </View></View>
      </Modal>
    </SafeAreaView>
  );
}

// ========== STYLES ==========
const softShadow = {
  shadowColor: '#0F172A',
  shadowOffset: { width: 0, height: 4 },
  shadowOpacity: 0.08,
  shadowRadius: 16,
  elevation: 4,
};

const styles = StyleSheet.create({
  safeArea: { flex: 1, backgroundColor: COLORS.bg },
  container: { flex: 1, paddingHorizontal: 20 },
  loadingContainer: { flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.bg },
  loadingText: { marginTop: 12, fontSize: 14, color: COLORS.textSecondary },

  header: { paddingTop: 18, marginBottom: 18 },
  eyebrow: { fontSize: 11, fontWeight: '700', color: COLORS.primary, letterSpacing: 1, marginBottom: 6 },
  pageTitle: { fontSize: 26, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 6, letterSpacing: -0.3 },
  pageSub: { fontSize: 13, color: COLORS.textSecondary, lineHeight: 18 },

  summaryPanel: {
    flexDirection: 'row', backgroundColor: COLORS.surface, borderRadius: 18,
    borderWidth: 1, borderColor: COLORS.border, paddingVertical: 16, marginBottom: 18, ...softShadow,
  },
  summaryCell: { flex: 1, alignItems: 'center', paddingHorizontal: 4 },
  summaryCellDivider: { borderRightWidth: 1, borderRightColor: COLORS.border },
  summaryValue: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, fontFamily: mono, marginTop: 6, marginBottom: 3 },
  summaryLabel: { fontSize: 10, color: COLORS.textTertiary, textAlign: 'center' },

  searchWrap: {
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.surface,
    borderRadius: 12, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 12, height: 44, marginBottom: 12,
  },
  searchIcon: { marginRight: 8 },
  searchInput: { flex: 1, fontSize: 14, color: COLORS.textPrimary },

  filterRow: { flexDirection: 'row', alignItems: 'center', marginBottom: 14 },
  filterChip: {
    backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border,
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 20, marginRight: 8,
  },
  filterChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  filterChipText: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  filterChipTextActive: { color: '#FFFFFF' },
  clearLink: { paddingLeft: 4 },
  clearLinkText: { fontSize: 12, fontWeight: '700', color: COLORS.primary },

  resultCount: { fontSize: 12, color: COLORS.textTertiary, marginBottom: 12 },

  violationCard: {
    backgroundColor: COLORS.surface, borderRadius: 16, padding: 16,
    borderWidth: 1, borderColor: COLORS.border, ...softShadow,
  },
  violationRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 },
  ticketNumber: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, fontFamily: mono },
  statusBadge: { paddingHorizontal: 9, paddingVertical: 3, borderRadius: 10 },
  statusText: { fontSize: 10, fontWeight: '700', letterSpacing: 0.3 },

  driverInfo: { fontSize: 14, color: COLORS.textPrimary, marginBottom: 2 },
  vehicleInfo: { fontSize: 13, color: COLORS.textSecondary, marginBottom: 8 },

  violationMetaRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  violationType: { fontSize: 13, fontWeight: '600', color: COLORS.primary },
  location: { fontSize: 12, color: COLORS.textTertiary },

  violationDivider: { height: 1, backgroundColor: COLORS.border, marginVertical: 12 },

  violationFooter: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 },
  dateTime: { fontSize: 11, color: COLORS.textTertiary, fontFamily: mono },
  penalty: { fontSize: 15, fontWeight: '700', color: COLORS.textPrimary },

  actionRow: { flexDirection: 'row', gap: 8 },
  actionButtonOutline: {
    paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8,
    borderWidth: 1, borderColor: COLORS.border,
  },
  actionTextOutline: { fontSize: 12, fontWeight: '600', color: COLORS.textSecondary },
  payButton: { backgroundColor: COLORS.success, paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
  payButtonText: { fontSize: 12, fontWeight: '700', color: '#FFFFFF' },
  cancelButton: { backgroundColor: COLORS.danger + '14', paddingHorizontal: 14, paddingVertical: 8, borderRadius: 8 },
  cancelButtonText: { fontSize: 12, fontWeight: '700', color: COLORS.danger },

  emptyState: { alignItems: 'center', paddingVertical: 40 },
  emptyText: { fontSize: 13, color: COLORS.textSecondary, textAlign: 'center', marginTop: 10, lineHeight: 18, paddingHorizontal: 20 },
  emptyClearButton: { marginTop: 14, paddingHorizontal: 16, paddingVertical: 8, borderRadius: 8, backgroundColor: COLORS.primary + '14' },
  emptyClearButtonText: { fontSize: 12, fontWeight: '700', color: COLORS.primary },
  pagination: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 18 },
  pageButton: { flexDirection: 'row', alignItems: 'center', gap: 4, backgroundColor: COLORS.primary, paddingHorizontal: 13, paddingVertical: 9, borderRadius: 9 },
  pageButtonDisabled: { backgroundColor: '#E2E8F0' },
  pageButtonText: { color: '#FFF', fontSize: 12, fontWeight: '700' },
  pageButtonTextDisabled: { color: COLORS.textTertiary },
  pageLabel: { color: COLORS.textSecondary, fontSize: 12, fontWeight: '700' },

  fab: {
    position: 'absolute', right: 20, bottom: 24,
    flexDirection: 'row', alignItems: 'center', backgroundColor: COLORS.primary,
    paddingHorizontal: 18, paddingVertical: 14, borderRadius: 28,
    shadowColor: COLORS.primary, shadowOffset: { width: 0, height: 6 }, shadowOpacity: 0.3, shadowRadius: 12, elevation: 6,
  },
  fabText: { fontSize: 14, fontWeight: '700', color: '#FFFFFF', marginLeft: 6 },

  modalOverlay: { flex: 1, backgroundColor: 'rgba(15,23,42,0.66)', justifyContent: 'flex-end', alignItems: 'center' },
  modalContent: {
    backgroundColor: '#FFFEFA', borderTopLeftRadius: 24, borderTopRightRadius: 24, paddingHorizontal: 18, paddingTop: 18, paddingBottom: 12, width: '100%', maxHeight: '94%', borderWidth: 1, borderColor: COLORS.border,
  },
  detailSheet: { backgroundColor: COLORS.surface, borderTopLeftRadius: 24, borderTopRightRadius: 24, padding: 22, width: '100%', maxHeight: '82%', position: 'absolute', bottom: 0, borderWidth: 1, borderColor: COLORS.border },
  detailRow: { paddingVertical: 11, borderBottomWidth: 1, borderBottomColor: COLORS.border },
  detailLabel: { color: COLORS.textTertiary, fontSize: 10, fontWeight: '800', textTransform: 'uppercase', marginBottom: 4 },
  detailValue: { color: COLORS.textPrimary, fontSize: 14, lineHeight: 20 },
  historyCard: { padding: 14, marginBottom: 10, borderRadius: 14, borderWidth: 1, borderColor: COLORS.border, backgroundColor: '#FFF' },
  historyTotal: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 14 },
  historyAddButton: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 7, marginTop: 10, paddingVertical: 12, borderRadius: 11, backgroundColor: COLORS.primary },
  historyAddButtonText: { color: '#FFF', fontSize: 12, fontWeight: '800' },
  modalHeaderRow: { flexDirection: 'row', alignItems: 'flex-start', marginBottom: 16 },
  modalTitle: { fontSize: 18, fontWeight: '700', color: COLORS.textPrimary, marginBottom: 2 },
  modalSub: { fontSize: 12, color: COLORS.textSecondary },
  formScrollContent: { paddingBottom: 18 },
  formSectionHeader: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 20, marginBottom: 2, paddingVertical: 11, paddingHorizontal: 12, borderRadius: 12, backgroundColor: '#EAF6F5', borderWidth: 1, borderColor: '#C8E5E2' },
  formSectionNumber: { width: 26, height: 26, borderRadius: 13, backgroundColor: COLORS.primary, alignItems: 'center', justifyContent: 'center' },
  formSectionNumberText: { color: '#FFF', fontSize: 11, fontWeight: '900' },
  formSectionHeading: { flex: 1 },
  formSectionTitle: { color: COLORS.textPrimary, fontSize: 13, fontWeight: '800' },
  formSectionSubtitle: { color: COLORS.textSecondary, fontSize: 10, lineHeight: 14, marginTop: 1 },
  scanPanel: { flexDirection: 'row', alignItems: 'center', backgroundColor: '#EAF6F5', borderRadius: 12, padding: 12, marginBottom: 10, borderWidth: 1, borderColor: '#B9DDDA' },
  scanTitle: { fontSize: 13, fontWeight: '800', color: COLORS.primary },
  scanSub: { fontSize: 10, color: COLORS.textSecondary, marginTop: 3, lineHeight: 14 },
  scanActions: { flexDirection: 'row', gap: 9, marginBottom: 4 },
  scanPrimary: { flex: 1, flexDirection: 'row', gap: 6, justifyContent: 'center', alignItems: 'center', backgroundColor: COLORS.primary, borderRadius: 10, paddingVertical: 11 },
  scanPrimaryText: { color: '#FFF', fontSize: 12, fontWeight: '800' },
  scanSecondary: { flex: 1, flexDirection: 'row', gap: 6, justifyContent: 'center', alignItems: 'center', backgroundColor: '#FFF', borderRadius: 10, paddingVertical: 11, borderWidth: 1, borderColor: COLORS.primary },
  scanSecondaryText: { color: COLORS.primary, fontSize: 12, fontWeight: '800' },
  ocrNotice: { flexDirection: 'row', gap: 7, alignItems: 'flex-start', backgroundColor: '#FFF7E8', padding: 10, borderRadius: 10, marginTop: 8 },
  ocrNoticeText: { flex: 1, color: '#7C5310', fontSize: 10, lineHeight: 15 },
  modalLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginTop: 12, marginBottom: 6 },
  modalInput: {
    minHeight: 46, backgroundColor: '#FFF', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10,
    borderWidth: 1, borderColor: COLORS.border, fontSize: 14, color: COLORS.textPrimary, marginBottom: 2,
  },
  violationDropdownButton: { minHeight: 46, paddingHorizontal: 12, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.bg, flexDirection: 'row', alignItems: 'center', gap: 9 },
  violationDropdownButtonOpen: { borderColor: COLORS.primary, borderBottomLeftRadius: 0, borderBottomRightRadius: 0 },
  violationDropdownText: { flex: 1, color: COLORS.textPrimary, fontSize: 13, fontWeight: '700' },
  violationDropdownPlaceholder: { color: COLORS.textTertiary, fontWeight: '500' },
  violationDropdownMenu: { borderWidth: 1, borderTopWidth: 0, borderColor: COLORS.primary, borderBottomLeftRadius: 10, borderBottomRightRadius: 10, backgroundColor: '#FFF', overflow: 'hidden' },
  violationSearchRow: { height: 42, margin: 9, paddingHorizontal: 10, borderRadius: 9, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.bg, flexDirection: 'row', alignItems: 'center', gap: 7 },
  violationSearchInput: { flex: 1, height: 40, color: COLORS.textPrimary, fontSize: 12, paddingVertical: 0 },
  violationDropdownList: { maxHeight: 230 },
  violationDropdownOption: { minHeight: 44, paddingHorizontal: 12, paddingVertical: 10, borderTopWidth: 1, borderTopColor: 'rgba(16, 47, 73, 0.09)', flexDirection: 'row', alignItems: 'center', gap: 10 },
  violationDropdownOptionSelected: { backgroundColor: '#EAF6F5' },
  violationDropdownOptionText: { flex: 1, color: COLORS.textPrimary, fontSize: 12, lineHeight: 17 },
  violationDropdownOptionTextSelected: { color: COLORS.primary, fontWeight: '800' },
  violationDropdownDone: { alignItems: 'center', paddingVertical: 11, backgroundColor: COLORS.primary },
  violationDropdownDoneText: { color: '#FFF', fontSize: 12, fontWeight: '800' },
  optionWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginTop: 8 },
  optionChip: { paddingHorizontal: 10, paddingVertical: 7, borderRadius: 16, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.surface },
  optionChipActive: { backgroundColor: COLORS.primary, borderColor: COLORS.primary },
  optionChipText: { color: COLORS.textSecondary, fontSize: 10, fontWeight: '700' },
  optionChipTextActive: { color: '#FFF' },
  analysisCard: { flexDirection: 'row', gap: 8, alignItems: 'center', backgroundColor: '#EAF6F5', borderWidth: 1, borderColor: '#B9DDDA', padding: 10, borderRadius: 10, marginTop: 10 },
  analysisTitle: { color: COLORS.textPrimary, fontSize: 11, fontWeight: '800', marginBottom: 2 },
  analysisText: { color: COLORS.textSecondary, fontSize: 10, lineHeight: 14 },
  modalActions: { flexDirection: 'row', marginTop: 10, paddingTop: 12, borderTopWidth: 1, borderTopColor: COLORS.border, gap: 10 },
  cancelModalButton: { flex: 1, alignItems: 'center', backgroundColor: COLORS.bg, paddingHorizontal: 18, paddingVertical: 13, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border },
  cancelModalButtonText: { fontSize: 13, fontWeight: '600', color: COLORS.textSecondary },
  saveModalButton: { flex: 2, alignItems: 'center', backgroundColor: COLORS.primary, paddingHorizontal: 18, paddingVertical: 13, borderRadius: 10 },
  saveModalButtonText: { fontSize: 13, fontWeight: '700', color: '#FFFFFF' },
});
