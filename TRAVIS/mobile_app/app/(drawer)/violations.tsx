import React, { useState, useCallback, useEffect, useMemo, useRef } from 'react';
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
  Linking,
  Platform,
  KeyboardAvoidingView,
  useWindowDimensions,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Href, useLocalSearchParams, useRouter, useSegments } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from 'expo-router/react-navigation';
import api from '../../api/axiosConfig';
import { CameraView, useCameraPermissions } from 'expo-camera';
import DateTimePicker, { DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { Picker } from '@react-native-picker/picker';
import * as ScreenOrientation from 'expo-screen-orientation';

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
  driverLastName: string;
  driverFirstName: string;
  driverMiddleName: string;
  driverSuffix: string;
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
type SelectedViolation = {
  violation_type: string;
  penalty_amount: string;
  ocr_confidence?: number;
  penalty_overridden?: boolean;
  auto_added?: boolean;
  other_violation_specification?: string;
  other_violation_not_specified?: boolean;
};
type TmoOfficer = { officer_id: number; first_name: string; last_name: string; badge_number?: string; rank?: string; zone_name?: string };
type TicketDateTimeField = 'date_of_birth' | 'license_expiry_date' | 'violation_date' | 'violation_time' | 'apprehension_date' | 'apprehension_time';

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
const normalizeNamePart = (value: string): string => value.trim().replace(/\s+/g, ' ');
const formatDateValue = (value: Date): string => {
  const year = value.getFullYear();
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const day = String(value.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};
const formatTimeValue = (value: Date): string => `${String(value.getHours()).padStart(2, '0')}:${String(value.getMinutes()).padStart(2, '0')}`;
const pickerDateValue = (value: string, mode: 'date' | 'time'): Date => {
  const now = new Date();
  if (mode === 'date') {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (match) return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12, 0, 0);
  } else {
    const match = value.match(/^(\d{2}):(\d{2})/);
    if (match) now.setHours(Number(match[1]), Number(match[2]), 0, 0);
  }
  return now;
};
const buildStructuredDriverName = (lastName: string, firstName: string, middleName = '', suffix = ''): string => {
  const parts = [lastName, firstName, middleName, suffix].map(normalizeNamePart);
  return parts[0] && parts[1] ? parts.filter(Boolean).join(', ') : '';
};

const createEmptyViolationForm = () => {
  const now = new Date();
  const currentDate = formatDateValue(now);
  const currentTime = formatTimeValue(now);
  return {
    driver_name: '', driver_last_name: '', driver_first_name: '', driver_middle_name: '', driver_suffix: '',
    driver_address: '', date_of_birth: '', license_number: '', license_expiry_date: '', license_remarks: '',
    plate_number: '', vehicle_owner: '', vehicle_registration_number: '', vehicle_color: '',
    insurance_policy_number: '', coding_sticker_number: '', vehicle_toda: '', vehicle_type: 'Car',
    violation_type: '', location: '', violation_date: currentDate, violation_time: currentTime,
    penalty_amount: '', offense_number: '1', ticket_remarks: '', apprehending_officer_name: '',
    apprehending_officer_position: '', apprehension_date: currentDate, apprehension_time: currentTime,
  };
};

const normalizedOcrText = (value: unknown): string => safeText(value)
  .toLowerCase()
  .replace(/[^a-z0-9]+/g, ' ')
  .trim();

const rejectedLicenseNamePhrases = [
  'republic of the philippines', 'department of transportation', 'land transportation office',
  'driver s license', 'assistant secretary', 'signature of licensee', 'nationality',
  'date of birth', 'weight kg', 'height m', 'agency code', 'blood type', 'eyes color',
  'restrictions', 'conditions', 'license no', 'expiration date',
];

const rejectedLicenseAddressPhrases = [
  'republic of the philippines', 'department of transportation', 'land transportation office',
  'driver s license', 'assistant secretary', 'signature of licensee',
  'last name first name middle name',
];

const isPlausibleScannedName = (value: unknown): boolean => {
  const normalized = normalizedOcrText(value);
  if (!normalized || rejectedLicenseNamePhrases.some(phrase => normalized.includes(phrase))) return false;
  if (/\d/.test(normalized)) return false;
  const words = normalized.split(/\s+/).filter(Boolean);
  return words.length >= 2 && words.length <= 8 && words.every(word => word.length >= 2);
};

const isPlausibleScannedAddress = (value: unknown): boolean => {
  const raw = normalizeNamePart(safeText(value));
  const normalized = normalizedOcrText(raw);
  if (!normalized || raw.length > 180 || rejectedLicenseAddressPhrases.some(phrase => normalized.includes(phrase))) return false;
  const words = normalized.split(/\s+/).filter(Boolean);
  return words.length >= 2 && words.length <= 20;
};

const splitScannedDriverName = (value: string) => {
  let normalizedName = normalizeNamePart(value);
  if (!normalizedName) return { lastName: '', firstName: '', middleName: '', suffix: '' };

  if (!normalizedName.includes(',')) {
    normalizedName = normalizedName.replace(/^([A-Z][A-Z '\-]{1,38})[.;:]\s+(.+)$/i, '$1, $2');
  }
  const surnameSeed = normalizedName.split(',', 1)[0]?.trim();
  if (surnameSeed) {
    const remainderStart = normalizedName.indexOf(',') + 1;
    const remainder = normalizedName.slice(remainderStart);
    const repeatedSurname = remainder.toUpperCase().indexOf(surnameSeed.toUpperCase());
    if (repeatedSurname > 0) {
      normalizedName = normalizedName.slice(0, remainderStart + repeatedSurname).trim().replace(/[, ]+$/, '');
    }
  }

  const suffixPattern = /\b(JR\.?|IR\.?|SR\.?|II|III|IV|V)\b/i;
  const suffixMatch = normalizedName.match(suffixPattern);
  const suffix = suffixMatch?.[1]?.toUpperCase().replace('.', '') === 'IR' ? 'JR' : (suffixMatch?.[1] || '');
  const withoutSuffix = suffixMatch
    ? normalizeNamePart(
        `${normalizedName.slice(0, suffixMatch.index)} ${normalizedName.slice((suffixMatch.index || 0) + suffixMatch[0].length)}`,
      )
    : normalizedName;

  if (withoutSuffix.includes(',')) {
    const commaParts = withoutSuffix.split(',').map(normalizeNamePart);
    if (commaParts.length >= 3) {
      return {
        lastName: commaParts[0] || '',
        firstName: commaParts[1] || '',
        middleName: commaParts[2] || '',
        suffix: commaParts[3] || suffix,
      };
    }
    const [lastName, remainder = ''] = commaParts;
    const givenParts = remainder.split(' ').filter(Boolean);
    return { lastName, firstName: givenParts.shift() || '', middleName: givenParts.join(' '), suffix };
  }

  const parts = withoutSuffix.split(' ').filter(Boolean);
  if (parts.length === 1) return { lastName: '', firstName: parts[0], middleName: '', suffix };
  const firstName = parts.shift() || '';
  let lastName = parts.pop() || '';
  const compoundSurnamePrefixes = ['DE', 'DEL', 'DELA', 'DE LA', 'SAN', 'SANTA', 'VAN', 'VON'];
  const possiblePrefix = parts.length ? parts[parts.length - 1].toUpperCase() : '';
  if (compoundSurnamePrefixes.includes(possiblePrefix)) lastName = `${parts.pop()} ${lastName}`;
  return { lastName, firstName, middleName: parts.join(' '), suffix };
};

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
  const { width: cameraViewportWidth, height: cameraViewportHeight } = useWindowDimensions();
  const licenseGuideWidth = Math.min(cameraViewportWidth * 0.92, cameraViewportHeight * 0.84 * 1.586, 760);
  const licenseGuideHeight = licenseGuideWidth / 1.586;
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
  const [formData, setFormData] = useState(createEmptyViolationForm);
  const [submitting, setSubmitting] = useState(false);
  const [hasNoLicense, setHasNoLicense] = useState(false);
  const [licenseConfiscated, setLicenseConfiscated] = useState(false);
  const [hasNoPlate, setHasNoPlate] = useState(false);
  const [vehicleOwnerNotApplicable, setVehicleOwnerNotApplicable] = useState(false);
  const [registrationNotApplicable, setRegistrationNotApplicable] = useState(false);
  const [vehicleColorNotApplicable, setVehicleColorNotApplicable] = useState(false);
  const [insuranceNotApplicable, setInsuranceNotApplicable] = useState(false);
  const [officerAgency, setOfficerAgency] = useState<'police' | 'tmo'>('police');
  const [selectedTmoOfficerId, setSelectedTmoOfficerId] = useState<number | null>(null);
  const [scanning, setScanning] = useState(false);
  const [cameraVisible, setCameraVisible] = useState(false);
  const [cameraReady, setCameraReady] = useState(false);
  const [capturingTicket, setCapturingTicket] = useState(false);
  const [cameraPermission, requestCameraPermission] = useCameraPermissions();
  const ticketCameraRef = useRef<CameraView>(null);
  const [inputMethod, setInputMethod] = useState<'manual' | 'ocr'>('manual');
  const [ocrNotice, setOcrNotice] = useState('');
  const [selectedViolations, setSelectedViolations] = useState<SelectedViolation[]>([]);
  const [violationDropdownOpen, setViolationDropdownOpen] = useState(false);
  const [selectedViolator, setSelectedViolator] = useState<Violator | null>(null);
  const [violationTypes, setViolationTypes] = useState<string[]>([]);
  const [penaltyFees, setPenaltyFees] = useState<number[]>([]);
  const [vehicleTypes, setVehicleTypes] = useState<string[]>([]);
  const [tmoOfficers, setTmoOfficers] = useState<TmoOfficer[]>([]);
  const [offenseAnalyses, setOffenseAnalyses] = useState<Record<string, OffenseAnalysis>>({});
  const [analyzingOffense, setAnalyzingOffense] = useState(false);
  const [offenseFloor, setOffenseFloor] = useState(1);
  const [activeDateTimePicker, setActiveDateTimePicker] = useState<TicketDateTimeField | null>(null);
  const resetViolationForm = () => {
    setFormData(createEmptyViolationForm());
    setSelectedViolations([]);
    setHasNoLicense(false);
    setLicenseConfiscated(false);
    setHasNoPlate(false);
    setVehicleOwnerNotApplicable(false);
    setRegistrationNotApplicable(false);
    setVehicleColorNotApplicable(false);
    setInsuranceNotApplicable(false);
    setOfficerAgency('police');
    setSelectedTmoOfficerId(null);
    setInputMethod('manual');
    setOcrNotice('');
    setOffenseAnalyses({});
    setOffenseFloor(1);
    setViolationDropdownOpen(false);
    setActiveDateTimePicker(null);
  };
  const confirmClearViolationForm = () => {
    Alert.alert(
      'Clear violation form?',
      'All scanned and manually entered information will be removed.',
      [
        { text: 'Keep Editing', style: 'cancel' },
        { text: 'Clear Form', style: 'destructive', onPress: resetViolationForm },
      ],
    );
  };
  const currentDriverName = buildStructuredDriverName(
    formData.driver_last_name,
    formData.driver_first_name,
    formData.driver_middle_name,
    formData.driver_suffix,
  );
  const pickerMode = activeDateTimePicker?.endsWith('_time') ? 'time' : 'date';
  const activePickerValue = activeDateTimePicker ? formData[activeDateTimePicker] : '';

  const handleDateTimeChange = (event: DateTimePickerEvent, selected?: Date) => {
    if (Platform.OS === 'android') setActiveDateTimePicker(null);
    if (event.type === 'dismissed' || !selected || !activeDateTimePicker) return;
    const nextValue = pickerMode === 'date' ? formatDateValue(selected) : formatTimeValue(selected);
    setFormData(current => ({ ...current, [activeDateTimePicker]: nextValue }));
  };

  const renderDateTimeField = (field: TicketDateTimeField, placeholder: string, optional = false) => {
    const value = formData[field];
    const isTime = field.endsWith('_time');
    return (
      <View style={styles.dateTimeFieldRow}>
        <TouchableOpacity
          style={styles.dateTimeField}
          onPress={() => setActiveDateTimePicker(field)}
          accessibilityRole="button"
          accessibilityLabel={`${placeholder}${value ? `: ${value}` : ''}`}
        >
          <Text style={[styles.dateTimeFieldText, !value && styles.dateTimeFieldPlaceholder]}>{value || placeholder}</Text>
          <Ionicons name={isTime ? 'time-outline' : 'calendar-outline'} size={19} color={COLORS.primary} />
        </TouchableOpacity>
        {optional && value ? (
          <TouchableOpacity style={styles.dateTimeClearButton} onPress={() => setFormData(current => ({ ...current, [field]: '' }))} accessibilityLabel={`Clear ${placeholder}`}>
            <Ionicons name="close" size={18} color={COLORS.textSecondary} />
          </TouchableOpacity>
        ) : null}
      </View>
    );
  };

  useEffect(() => {
    if (open !== 'ocr') return;
    const frame = requestAnimationFrame(() => {
      setInputMethod('ocr');
      setOcrNotice("Use Take Photo to scan the driver's license.");
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
      setTmoOfficers(options.tmo_officers || []);
    }).catch(() => Alert.alert('Configuration error', 'The official violation options could not be loaded.'));
  }, []);

  const selectedTypeKey = selectedViolations.map(item => item.violation_type).sort().join('|');
  useEffect(() => {
    const selectedTypes = selectedTypeKey ? selectedTypeKey.split('|').filter(type => violationTypes.includes(type)) : [];
    if (!selectedTypes.length) {
      const resetTimer = setTimeout(() => {
        setFormData(current => ({ ...current, offense_number: String(offenseFloor) }));
      }, 0);
      return () => clearTimeout(resetTimer);
    }
    let cancelled = false;
    const timer = setTimeout(async () => {
      setAnalyzingOffense(true);
      try {
        const results = await Promise.all(selectedTypes.map(async type => {
          const response = await api.get('analyze_offense.php', { params: {
            driver_name: currentDriverName,
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
  }, [currentDriverName, formData.license_number, formData.date_of_birth, hasNoLicense, offenseFloor, selectedTypeKey, violationTypes]);

  const toggleViolation = (type: string, confidence?: number) => {
    const selected = selectedViolations.some(item => item.violation_type === type);
    if (type === "No Driver's License") setHasNoLicense(!selected);
    setSelectedViolations(current => selected
      ? current.filter(item => item.violation_type !== type)
      : [...current, {
        violation_type: type,
        penalty_amount: '',
        ocr_confidence: confidence,
        penalty_overridden: false,
        other_violation_specification: type === 'Other Traffic Violation' ? '' : undefined,
        other_violation_not_specified: false,
      }]);
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

  const updateOtherViolation = (specification: string, notSpecified: boolean) => {
    setSelectedViolations(current => current.map(item => item.violation_type === 'Other Traffic Violation'
      ? {
        ...item,
        other_violation_specification: notSpecified ? '' : specification,
        other_violation_not_specified: notSpecified,
      }
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

  const processLicenseAsset = async (asset: {
    uri: string;
    fileName?: string | null;
    mimeType?: string | null;
    cropFrame?: Record<string, number | string>;
  }) => {
    setScanning(true);
    setOcrNotice("Reading driver's license…");
    try {
      const body = new FormData();
      body.append('license', { uri: asset.uri, name: asset.fileName || 'license.jpg', type: asset.mimeType || 'image/jpeg' } as any);
      if (asset.cropFrame) body.append('crop_frame', JSON.stringify(asset.cropFrame));
      const response = await api.post('scan_license.php', body, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 120000 });
      const fields = response.data.fields || {};
      const fieldConfidences = response.data.field_confidences || {};
      // Structured fields still undergo strict server-side format validation,
      // so faint but valid card text should not be discarded unnecessarily.
      const minimumFieldConfidence = 0.28;
      const trustedField = (name: string) => {
        const value = String(fields[name] || '').trim();
        if (!value) return false;
        // Older hosted OCR responses did not always include per-field
        // confidence. Values returned here already passed server validation.
        if (!Object.prototype.hasOwnProperty.call(fieldConfidences, name)) return true;
        return Number(fieldConfidences[name] || 0) >= minimumFieldConfidence;
      };
      const nameIsPlausible = trustedField('driver_name') && isPlausibleScannedName(fields.driver_name);
      const addressIsPlausible = trustedField('driver_address') && isPlausibleScannedAddress(fields.driver_address);
      const scannedDriverName = {
        lastName: trustedField('driver_last_name') ? String(fields.driver_last_name).trim() : '',
        firstName: trustedField('driver_first_name') ? String(fields.driver_first_name).trim() : '',
        middleName: trustedField('driver_middle_name') ? String(fields.driver_middle_name).trim() : '',
        suffix: trustedField('driver_suffix') ? String(fields.driver_suffix).trim() : '',
      };
      const structuredNameIsPlausible = isPlausibleScannedName(
        `${scannedDriverName.lastName} ${scannedDriverName.firstName} ${scannedDriverName.middleName}`,
      );
      // The standard LTO card prints one authoritative full-name line. Parse
      // that first so alternate OCR passes cannot mix duplicate component text.
      const parsedFullName = nameIsPlausible ? splitScannedDriverName(String(fields.driver_name)) : null;
      const parsedDriverName = parsedFullName?.firstName || parsedFullName?.lastName
        ? parsedFullName
        : (structuredNameIsPlausible ? scannedDriverName : null);
      setHasNoLicense(false);
      setFormData(current => ({
        ...current,
        driver_name: parsedDriverName
          ? buildStructuredDriverName(parsedDriverName.lastName, parsedDriverName.firstName, parsedDriverName.middleName, parsedDriverName.suffix)
          : current.driver_name,
        driver_last_name: parsedDriverName?.lastName || current.driver_last_name,
        driver_first_name: parsedDriverName?.firstName || current.driver_first_name,
        driver_middle_name: parsedDriverName?.middleName || current.driver_middle_name,
        driver_suffix: parsedDriverName?.suffix || current.driver_suffix,
        driver_address: addressIsPlausible ? fields.driver_address : current.driver_address,
        date_of_birth: trustedField('date_of_birth') ? fields.date_of_birth : current.date_of_birth,
        license_number: trustedField('license_number') ? fields.license_number : current.license_number,
        license_expiry_date: trustedField('license_expiry_date') ? fields.license_expiry_date : current.license_expiry_date,
      }));
      setInputMethod('ocr');
      const warning = String(response.data.warning || '').trim();
      const ocrVersion = String(response.data.ocr_version || 'unverified');
      const gatewayVersion = String(response.data.ocr_gateway_version || 'unverified');
      const executionMode = String(response.data.ocr_execution_mode || 'unknown-mode');
      const lowConfidenceFields = Object.keys(fields).filter(name => fields[name] && !trustedField(name));
      const rejectedFields = [
        ...(trustedField('driver_name') && !nameIsPlausible ? ['driver name'] : []),
        ...(trustedField('driver_address') && !addressIsPlausible ? ['driver address'] : []),
      ];
      const confidenceWarning = lowConfidenceFields.length ? ` Low-confidence fields were left blank: ${lowConfidenceFields.join(', ').replaceAll('_', ' ')}.` : '';
      const validationWarning = rejectedFields.length ? ` Suspicious OCR values were left blank: ${rejectedFields.join(', ')}.` : '';
      const documentNotice = response.data.guide_crop_applied
        ? (response.data.full_frame_fallback_used
          ? 'The guide area was scanned, then the full photo produced a stronger result. '
          : 'Only the padded license guide area was processed. ')
        : (response.data.document_detected
          ? 'License aligned and perspective corrected. '
          : 'Visible labeled fields were scanned without requiring every card edge. ');
      setOcrNotice(`[OCR ${ocrVersion} | Gateway ${gatewayVersion} | ${executionMode}] ${documentNotice}${warning || `${response.data.recognized_fields || 0} fields detected.`}${confidenceWarning}${validationWarning} Review all values before saving.`);
    } catch (error: any) {
      setOcrNotice('');
      Alert.alert('License scan failed', error.response?.data?.error || "The driver's license could not be read. Try a clearer, well-lit photo.");
    } finally { setScanning(false); }
  };

  const openLicenseCamera = async () => {
    let permission = cameraPermission;
    if (!permission?.granted) {
      permission = await requestCameraPermission();
    }
    if (!permission.granted) {
      if (permission.canAskAgain) {
        Alert.alert('Permission required', "Allow camera access to photograph the driver's license, then try again.");
      } else {
        Alert.alert(
          'Camera permission required',
          'Camera access is disabled for TRAVIS. Open App Settings and allow Camera access.',
          [
            { text: 'Cancel', style: 'cancel' },
            { text: 'Open App Settings', onPress: () => Linking.openSettings() },
          ],
        );
      }
      return;
    }
    try {
      await ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.LANDSCAPE_RIGHT);
      setCameraReady(false);
      setCameraVisible(true);
    } catch {
      Alert.alert('Orientation error', 'The license camera could not switch to landscape mode. Please try again.');
    }
  };

  const closeLicenseCamera = async () => {
    setCameraVisible(false);
    setCameraReady(false);
    await ScreenOrientation.lockAsync(ScreenOrientation.OrientationLock.PORTRAIT_UP).catch(() => undefined);
  };

  const captureGuidedLicense = async () => {
    if (!ticketCameraRef.current || !cameraReady || capturingTicket) return;
    setCapturingTicket(true);
    try {
      const photo = await ticketCameraRef.current.takePictureAsync({ quality: 1, skipProcessing: false });
      if (!photo?.uri) throw new Error('The camera did not return a photo.');
      await closeLicenseCamera();
      await processLicenseAsset({
        uri: photo.uri,
        fileName: 'license.jpg',
        mimeType: 'image/jpeg',
        cropFrame: {
          preview_width: cameraViewportWidth,
          preview_height: cameraViewportHeight,
          left: (cameraViewportWidth - licenseGuideWidth) / 2,
          top: (cameraViewportHeight - licenseGuideHeight) / 2,
          width: licenseGuideWidth,
          height: licenseGuideHeight,
          padding_ratio: 0.04,
          resize_mode: 'cover',
        },
      });
    } catch (error: any) {
      Alert.alert('Camera error', error?.message || "The driver's license photo could not be captured.");
    } finally {
      setCapturingTicket(false);
    }
  };

  const scanLicense = async () => {
    await openLicenseCamera();
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
          driverLastName: safeText(item.driver_last_name),
          driverFirstName: safeText(item.driver_first_name),
          driverMiddleName: safeText(item.driver_middle_name),
          driverSuffix: safeText(item.driver_suffix),
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
    const { driver_last_name, driver_first_name, license_number, plate_number, vehicle_type, location } = formData;
    const driverName = buildStructuredDriverName(driver_last_name, driver_first_name, formData.driver_middle_name, formData.driver_suffix);
    if (!driver_last_name.trim() || !driver_first_name.trim()) {
      Alert.alert('Driver name required', 'Enter both the driver surname and first name.');
      return;
    }
    if ((!hasNoLicense && !license_number.trim()) || (!hasNoPlate && !plate_number.trim()) || !location.trim()) {
      Alert.alert('Error', 'Please fill in all required fields.');
      return;
    }
    if (!formData.violation_date || !formData.violation_time || !formData.apprehension_date || !formData.apprehension_time) {
      Alert.alert('Date and time required', 'Select the violation and apprehension dates and times.');
      return;
    }
    if (!selectedViolations.length || selectedViolations.some(item => !violationTypes.includes(item.violation_type))) {
      Alert.alert('Invalid violation', 'Select at least one violation from the official list.');
      return;
    }
    const incompleteOtherViolation = selectedViolations.find(item =>
      item.violation_type === 'Other Traffic Violation'
      && !item.other_violation_not_specified
      && !item.other_violation_specification?.trim()
    );
    if (incompleteOtherViolation) {
      Alert.alert('Specify violation', 'Enter the other traffic violation or select Not specified.');
      return;
    }
    if (selectedViolations.some(item => (item.other_violation_specification?.trim().length || 0) > 80)) {
      Alert.alert('Details too long', 'Other traffic violation details must not exceed 80 characters.');
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
    if ((officerAgency === 'police' && !formData.apprehending_officer_name.trim()) || (officerAgency === 'tmo' && !selectedTmoOfficerId)) {
      Alert.alert('Officer required', officerAgency === 'tmo' ? 'Select an active TMO officer.' : 'Enter the apprehending police officer name.');
      return;
    }
    setSubmitting(true);
    try {
      const response = await api.post('add_violations.php', {
        ...formData,
        driver_name: driverName,
        driver_last_name: normalizeNamePart(driver_last_name),
        driver_first_name: normalizeNamePart(driver_first_name),
        driver_middle_name: normalizeNamePart(formData.driver_middle_name),
        driver_suffix: normalizeNamePart(formData.driver_suffix),
        license_number: hasNoLicense ? 'NO LICENSE' : license_number,
        license_expiry_date: hasNoLicense ? '' : formData.license_expiry_date,
        has_no_license: hasNoLicense,
        license_confiscated: licenseConfiscated,
        plate_number: hasNoPlate ? 'NO PLATE' : plate_number,
        has_no_plate: hasNoPlate,
        vehicle_owner_not_applicable: vehicleOwnerNotApplicable,
        vehicle_owner: vehicleOwnerNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_owner,
        registration_not_applicable: registrationNotApplicable,
        vehicle_registration_number: registrationNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_registration_number,
        vehicle_color_not_applicable: vehicleColorNotApplicable,
        vehicle_color: vehicleColorNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_color,
        insurance_policy_not_applicable: insuranceNotApplicable,
        insurance_policy_number: insuranceNotApplicable ? 'NOT APPLICABLE' : formData.insurance_policy_number,
        vehicle_type,
        coding_sticker_number: vehicle_type === 'Tricycle' ? formData.coding_sticker_number : '',
        vehicle_toda: vehicle_type === 'Tricycle' ? formData.vehicle_toda : '',
        violations: selectedViolations.map(item => ({ ...item, penalty_amount: Number(item.penalty_amount) })),
        location,
        offense_number: Number(formData.offense_number),
        apprehending_officer_agency: officerAgency,
        tmo_officer_id: officerAgency === 'tmo' ? selectedTmoOfficerId : null,
        apprehension_datetime: `${formData.apprehension_date} ${formData.apprehension_time}:00`,
        input_method: inputMethod,
      });
      if (response.data.success) {
        const offense = response.data.offense_analysis;
        Alert.alert('Violation saved', offense?.previous_offenses > 0
          ? `${response.data.ticket_number} was recorded as suggested offense #${offense.suggested_offense}. ${offense.previous_offenses} previous matching offense(s) found.`
          : `${response.data.ticket_number} was recorded as a first offense.`);
        setModalVisible(false);
        setActiveDateTimePicker(null);
        resetViolationForm();
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
    const registrationIsNotApplicable = latest.vehicleRegistrationNumber.trim().toUpperCase() === 'NOT APPLICABLE';
    const ownerIsNotApplicable = latest.vehicleOwner.trim().toUpperCase() === 'NOT APPLICABLE';
    const insuranceIsNotApplicable = latest.insurancePolicyNumber.trim().toUpperCase() === 'NOT APPLICABLE';
    const colorIsNotApplicable = ['NOT APPLICABLE', 'NOT SPECIFIED'].includes(latest.vehicleColor.trim().toUpperCase());
    const currentDate = formatDateValue(new Date());
    const currentTime = formatTimeValue(new Date());

    const nextOffense = Math.min(4, violator.records.length + 1);
    setOffenseFloor(nextOffense);
    setFormData({
      driver_name: violator.driverName,
      driver_last_name: latest.driverLastName || '',
      driver_first_name: latest.driverFirstName || '',
      driver_middle_name: latest.driverMiddleName || '',
      driver_suffix: latest.driverSuffix || '',
      driver_address: latest.driverAddress || '',
      date_of_birth: latest.dateOfBirth || '',
      license_number: noLicense ? '' : violator.licenseNumber,
      license_expiry_date: noLicense ? '' : (latest.licenseExpiryDate || ''),
      license_remarks: latest.licenseRemarks || '',
      plate_number: noPlate ? '' : latest.plateNumber,
      vehicle_owner: ownerIsNotApplicable ? '' : (latest.vehicleOwner || ''),
      vehicle_registration_number: registrationIsNotApplicable ? '' : (latest.vehicleRegistrationNumber || ''),
      vehicle_color: colorIsNotApplicable ? '' : (latest.vehicleColor || ''),
      insurance_policy_number: insuranceIsNotApplicable ? '' : (latest.insurancePolicyNumber || ''),
      coding_sticker_number: latest.vehicleType === 'Tricycle' ? (latest.codingStickerNumber || '') : '',
      vehicle_toda: latest.vehicleType === 'Tricycle' ? (latest.vehicleToda || '') : '',
      vehicle_type: latest.vehicleType,
      violation_type: '',
      location: '',
      violation_date: currentDate,
      violation_time: currentTime,
      penalty_amount: '',
      offense_number: String(nextOffense),
      ticket_remarks: '',
      apprehending_officer_name: '',
      apprehending_officer_position: '',
      apprehension_date: currentDate,
      apprehension_time: currentTime,
    });
    setHasNoLicense(noLicense);
    setLicenseConfiscated(latest.licenseConfiscated);
    setHasNoPlate(noPlate);
    setVehicleOwnerNotApplicable(ownerIsNotApplicable);
    setRegistrationNotApplicable(registrationIsNotApplicable);
    setVehicleColorNotApplicable(colorIsNotApplicable);
    setInsuranceNotApplicable(insuranceIsNotApplicable);
    setOfficerAgency('police');
    setSelectedTmoOfficerId(null);
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
    <View style={styles.tableRow}>
      <View style={styles.tableDriverCell}>
        <Text style={styles.tablePrimary} numberOfLines={1}>{item.driverName}</Text>
        <Text style={styles.tableSecondary} numberOfLines={1}>{item.licenseNumber || 'NO LICENSE'}</Text>
      </View>
      <View style={styles.tableLatestCell}>
        <Text style={styles.tablePrimary} numberOfLines={1}>{item.latest.violationType}</Text>
        <Text style={styles.tableSecondary} numberOfLines={1}>{item.latest.plateNumber} · {item.latest.date}</Text>
      </View>
      <View style={styles.tableCountCell}>
        <Text style={styles.tableCount}>{item.records.length}</Text>
        <Text style={[styles.tableSecondary, { color: item.unpaidCount ? COLORS.warning : COLORS.success }]}>{item.unpaidCount} unpaid</Text>
      </View>
      <TouchableOpacity style={styles.tableViewButton} onPress={() => setSelectedViolator(item)} accessibilityLabel={`View ${item.driverName} record`}>
        <Ionicons name="eye-outline" size={16} color={COLORS.primary} />
      </TouchableOpacity>
    </View>
  );

  // ===== COMPUTE COUNTS =====
  const counts = {
    today: violations.filter(v => v.date === formatDateValue(new Date())).length,
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
      <StatusBar barStyle="dark-content" />

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
        <View style={styles.tableShell}>
          <View style={styles.tableHeader}>
            <Text style={[styles.tableHeaderText, styles.tableDriverCell]}>VIOLATOR</Text>
            <Text style={[styles.tableHeaderText, styles.tableLatestCell]}>LATEST</Text>
            <Text style={[styles.tableHeaderText, styles.tableCountCell]}>RECORDS</Text>
            <Text style={[styles.tableHeaderText, styles.tableActionCell]}>VIEW</Text>
          </View>
          <FlatList
            data={visibleViolators}
            renderItem={renderViolatorItem}
            keyExtractor={item => item.key}
            scrollEnabled={false}
            ItemSeparatorComponent={() => <View style={styles.tableDivider} />}
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
        </View>
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

      {/* Guided in-app camera keeps the landscape driver's license inside one frame. */}
      <Modal
        animationType="fade"
        presentationStyle="fullScreen"
        statusBarTranslucent
        visible={cameraVisible}
        onRequestClose={() => { void closeLicenseCamera(); }}
      >
        <View style={styles.cameraScreen}>
          <CameraView
            ref={ticketCameraRef}
            style={StyleSheet.absoluteFill}
            facing="back"
            onCameraReady={() => setCameraReady(true)}
          />
          <SafeAreaView style={styles.cameraSafeArea}>
            <View style={styles.cameraHeader}>
              <TouchableOpacity
                style={styles.cameraCloseButton}
                onPress={() => { void closeLicenseCamera(); }}
                accessibilityLabel="Close license camera"
              >
                <Ionicons name="close" size={26} color="#FFF" />
              </TouchableOpacity>
              <View style={styles.cameraInstruction}>
                <Text style={styles.cameraInstructionTitle}>Align the driver&apos;s license</Text>
                <Text style={styles.cameraInstructionText}>The area inside the rectangle will be cropped and scanned with a small safety margin.</Text>
              </View>
              <View style={styles.cameraHeaderSpacer} />
            </View>

            <View pointerEvents="none" style={styles.cameraGuideArea}>
              <View
                style={[
                  styles.licenseGuide,
                  {
                    width: licenseGuideWidth,
                    height: licenseGuideHeight,
                  },
                ]}
              >
                <View style={[styles.guideCorner, styles.guideTopLeft]} />
                <View style={[styles.guideCorner, styles.guideTopRight]} />
                <View style={[styles.guideCorner, styles.guideBottomLeft]} />
                <View style={[styles.guideCorner, styles.guideBottomRight]} />
                <View style={styles.guideCenterBadge}>
                  <Ionicons name="document-text-outline" size={16} color="#FFF" />
                  <Text style={styles.guideCenterText}>LICENSE AREA</Text>
                </View>
              </View>
            </View>

            <View style={styles.cameraControls}>
              <Text style={styles.cameraTip}>Keep important text inside the frame and avoid glare. A small margin outside the guide is retained automatically.</Text>
              <TouchableOpacity
                disabled={!cameraReady || capturingTicket}
                style={[styles.shutterOuter, (!cameraReady || capturingTicket) && styles.shutterDisabled]}
                onPress={captureGuidedLicense}
                accessibilityLabel="Capture driver's license"
              >
                {capturingTicket
                  ? <ActivityIndicator color={COLORS.primary} />
                  : <View style={styles.shutterInner} />}
              </TouchableOpacity>
            </View>
          </SafeAreaView>
        </View>
      </Modal>

      {activeDateTimePicker && Platform.OS === 'android' ? (
        <DateTimePicker
          value={pickerDateValue(activePickerValue, pickerMode)}
          mode={pickerMode}
          display="default"
          onChange={handleDateTimeChange}
          maximumDate={activeDateTimePicker === 'date_of_birth' ? new Date() : undefined}
        />
      ) : null}

      {/* Add Violation Modal */}
      <Modal animationType="slide" transparent visible={modalVisible} onRequestClose={() => { setActiveDateTimePicker(null); setModalVisible(false); }}>
        <KeyboardAvoidingView style={styles.modalOverlay} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeaderRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.modalTitle}>Add Violation</Text>
                <Text style={styles.modalSub}>Scan a driver&apos;s license or enter the details manually.</Text>
              </View>
              <TouchableOpacity style={styles.clearFormButton} onPress={confirmClearViolationForm} disabled={submitting} accessibilityLabel="Clear violation form">
                <Ionicons name="trash-outline" size={15} color={COLORS.danger} />
                <Text style={styles.clearFormButtonText}>Clear Form</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => { setActiveDateTimePicker(null); setModalVisible(false); }} hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}>
                <Ionicons name="close" size={22} color={COLORS.textTertiary} />
              </TouchableOpacity>
            </View>

            <ScrollView showsVerticalScrollIndicator={false} keyboardShouldPersistTaps="handled" contentContainerStyle={styles.formScrollContent}>
              <FormSection number="1" title="License capture" subtitle="Scan the driver&apos;s license or continue with manual entry" icon="scan-outline" />
              <View style={styles.scanPanel}>
                <View style={{ flex: 1 }}><Text style={styles.scanTitle}>Driver&apos;s License Scanner</Text><Text style={styles.scanSub}>Scans the driver&apos;s name, address, license number, birth date, and expiration date. Violation and vehicle details remain manual.</Text></View>
                {scanning && <ActivityIndicator color={COLORS.primary} />}
              </View>
              <View style={styles.scanActions}>
                <TouchableOpacity disabled={scanning} style={styles.scanPrimary} onPress={scanLicense}><Ionicons name="camera-outline" size={17} color="#FFF" /><Text style={styles.scanPrimaryText}>Take Photo</Text></TouchableOpacity>
              </View>
              {!!ocrNotice && <View style={styles.ocrNotice}><Ionicons name="warning-outline" size={16} color={COLORS.warning} /><Text style={styles.ocrNoticeText}>{ocrNotice}</Text></View>}
              <FormSection number="2" title="Driver information" subtitle="Identity and contact details" icon="person-outline" />
              <Text style={styles.modalLabel}>Surname</Text>
              <TextInput
                style={styles.modalInput}
                placeholder="Surname"
                placeholderTextColor={COLORS.textTertiary}
                value={formData.driver_last_name}
                onChangeText={text => setFormData({ ...formData, driver_last_name: text })}
                autoCapitalize="words"
              />
              <Text style={styles.modalLabel}>First Name</Text>
              <TextInput style={styles.modalInput} placeholder="First name" placeholderTextColor={COLORS.textTertiary} value={formData.driver_first_name} onChangeText={text => setFormData({ ...formData, driver_first_name: text })} autoCapitalize="words" />
              <Text style={styles.modalLabel}>Middle Name</Text>
              <TextInput style={styles.modalInput} placeholder="Optional full middle name" placeholderTextColor={COLORS.textTertiary} value={formData.driver_middle_name} onChangeText={text => setFormData({ ...formData, driver_middle_name: text })} autoCapitalize="words" />
              <Text style={styles.modalLabel}>Suffix</Text>
              <TextInput style={styles.modalInput} placeholder="Optional: Jr., Sr., III" placeholderTextColor={COLORS.textTertiary} value={formData.driver_suffix} onChangeText={text => setFormData({ ...formData, driver_suffix: text })} autoCapitalize="characters" />
              {!!currentDriverName && <Text style={styles.structuredNamePreview}>Saved as: {currentDriverName}</Text>}
              <Text style={styles.modalLabel}>Driver Address</Text>
              <TextInput style={styles.modalInput} placeholder="Complete address" placeholderTextColor={COLORS.textTertiary} value={formData.driver_address} onChangeText={text => setFormData({ ...formData, driver_address: text })} />
              <Text style={styles.modalLabel}>Date of Birth</Text>
              {renderDateTimeField('date_of_birth', 'Select date of birth', true)}
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
                renderDateTimeField('license_expiry_date', 'Select license expiry date', true)
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
              <Text style={styles.modalLabel}>Owner of Vehicle</Text>
              <TextInput style={[styles.modalInput, vehicleOwnerNotApplicable && styles.disabledInput]} placeholder={vehicleOwnerNotApplicable ? 'NOT APPLICABLE' : 'Full name of registered owner'} placeholderTextColor={COLORS.textTertiary} value={vehicleOwnerNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_owner} onChangeText={text => setFormData({ ...formData, vehicle_owner: text })} editable={!vehicleOwnerNotApplicable} />
              <TouchableOpacity style={styles.checkRow} onPress={() => setVehicleOwnerNotApplicable(value => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: vehicleOwnerNotApplicable }}>
                <Ionicons name={vehicleOwnerNotApplicable ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={styles.checkRowText}>Owner information is not applicable</Text>
              </TouchableOpacity>
              <Text style={styles.modalLabel}>Vehicle Registration Number</Text>
              <TextInput style={[styles.modalInput, registrationNotApplicable && styles.disabledInput]} placeholder={registrationNotApplicable ? 'NOT APPLICABLE' : 'Registration / MV file number'} placeholderTextColor={COLORS.textTertiary} value={registrationNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_registration_number} onChangeText={text => setFormData({ ...formData, vehicle_registration_number: text })} autoCapitalize="characters" editable={!registrationNotApplicable} />
              <TouchableOpacity style={styles.checkRow} onPress={() => setRegistrationNotApplicable(value => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: registrationNotApplicable }}>
                <Ionicons name={registrationNotApplicable ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={styles.checkRowText}>Registration number is not applicable</Text>
              </TouchableOpacity>
              <Text style={styles.modalLabel}>Vehicle Color</Text>
              <TextInput style={[styles.modalInput, vehicleColorNotApplicable && styles.disabledInput]} placeholder={vehicleColorNotApplicable ? 'NOT APPLICABLE' : 'Example: Blue'} placeholderTextColor={COLORS.textTertiary} value={vehicleColorNotApplicable ? 'NOT APPLICABLE' : formData.vehicle_color} onChangeText={text => setFormData({ ...formData, vehicle_color: text })} editable={!vehicleColorNotApplicable} />
              <TouchableOpacity style={styles.checkRow} onPress={() => setVehicleColorNotApplicable(value => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: vehicleColorNotApplicable }}>
                <Ionicons name={vehicleColorNotApplicable ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={styles.checkRowText}>Vehicle color is not applicable</Text>
              </TouchableOpacity>
              <Text style={styles.modalLabel}>Insurance Policy Number</Text>
              <TextInput style={[styles.modalInput, insuranceNotApplicable && styles.disabledInput]} placeholder={insuranceNotApplicable ? 'NOT APPLICABLE' : 'Insurance policy number'} placeholderTextColor={COLORS.textTertiary} value={insuranceNotApplicable ? 'NOT APPLICABLE' : formData.insurance_policy_number} onChangeText={text => setFormData({ ...formData, insurance_policy_number: text })} editable={!insuranceNotApplicable} />
              <TouchableOpacity style={styles.checkRow} onPress={() => setInsuranceNotApplicable(value => !value)} accessibilityRole="checkbox" accessibilityState={{ checked: insuranceNotApplicable }}>
                <Ionicons name={insuranceNotApplicable ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                <Text style={styles.checkRowText}>Insurance policy is not applicable</Text>
              </TouchableOpacity>
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
                    {item.violation_type === 'Other Traffic Violation' && <>
                      <TextInput
                        style={[styles.modalInput, { marginTop: 8, marginBottom: 0 }]}
                        placeholder="Specify the other traffic violation"
                        placeholderTextColor={COLORS.textTertiary}
                        value={item.other_violation_specification || ''}
                        onChangeText={value => updateOtherViolation(value, false)}
                        editable={!item.other_violation_not_specified}
                        maxLength={80}
                      />
                      <TouchableOpacity
                        style={[styles.checkRow, { marginTop: 8, marginBottom: 0 }]}
                        onPress={() => updateOtherViolation('', !item.other_violation_not_specified)}
                        accessibilityRole="checkbox"
                        accessibilityState={{ checked: Boolean(item.other_violation_not_specified) }}
                      >
                        <Ionicons name={item.other_violation_not_specified ? 'checkbox' : 'square-outline'} size={22} color={COLORS.primary} />
                        <Text style={styles.checkRowText}>Not specified</Text>
                      </TouchableOpacity>
                    </>}
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
              {renderDateTimeField('violation_date', 'Select violation date')}
              {renderDateTimeField('violation_time', 'Select violation time')}
              <Text style={styles.modalLabel}>Offense Level</Text>
              <View style={styles.analysisCard}><Ionicons name="analytics-outline" size={20} color={COLORS.primary} /><Text style={styles.analysisText}>Automatically calculated: {['First', 'Second', 'Third', 'Fourth'][Math.max(0, Number(formData.offense_number) - 1)] || 'First'} offense</Text></View>
              <Text style={styles.modalLabel}>Ticket Remarks</Text>
              <TextInput style={[styles.modalInput, { minHeight: 72, textAlignVertical: 'top' }]} multiline placeholder="Remarks / Treasurer disposition" placeholderTextColor={COLORS.textTertiary} value={formData.ticket_remarks} onChangeText={text => setFormData({ ...formData, ticket_remarks: text })} />
              <FormSection number="7" title="Officer information" subtitle="Apprehending officer and time recorded" icon="shield-checkmark-outline" />
              <Text style={styles.modalLabel}>Apprehending / Arresting Officer</Text>
              <View style={styles.optionWrap}>
                {(['police', 'tmo'] as const).map(agency => <TouchableOpacity key={agency} style={[styles.optionChip, officerAgency === agency && styles.optionChipActive]} onPress={() => { setOfficerAgency(agency); if (agency === 'police') setSelectedTmoOfficerId(null); }}><Text style={[styles.optionChipText, officerAgency === agency && styles.optionChipTextActive]}>{agency === 'police' ? 'Police' : 'TMO'}</Text></TouchableOpacity>)}
              </View>
              {officerAgency === 'police' ? <>
                <TextInput style={styles.modalInput} placeholder="Officer name" placeholderTextColor={COLORS.textTertiary} value={formData.apprehending_officer_name} onChangeText={text => setFormData({ ...formData, apprehending_officer_name: text })} />
                <TextInput style={styles.modalInput} placeholder="Position" placeholderTextColor={COLORS.textTertiary} value={formData.apprehending_officer_position} onChangeText={text => setFormData({ ...formData, apprehending_officer_position: text })} />
              </> : <>
                <Text style={styles.modalLabel}>Active TMO Officer</Text>
                {tmoOfficers.length ? (
                  <View style={styles.officerPickerWrap}>
                    <Picker
                      selectedValue={selectedTmoOfficerId ?? 0}
                      onValueChange={(value) => setSelectedTmoOfficerId(Number(value) || null)}
                      style={styles.officerPicker}
                      dropdownIconColor={COLORS.primary}
                      mode="dropdown"
                    >
                      <Picker.Item label="Select an active TMO officer" value={0} color={COLORS.textTertiary} />
                      {tmoOfficers.map(officer => {
                        const details = [officer.badge_number, officer.rank, officer.zone_name || 'Unassigned'].filter(Boolean).join(' · ');
                        return <Picker.Item key={officer.officer_id} label={`${officer.last_name}, ${officer.first_name}${details ? ` · ${details}` : ''}`} value={Number(officer.officer_id)} />;
                      })}
                    </Picker>
                  </View>
                ) : <Text style={styles.officerEmpty}>No active TMO officers are available.</Text>}
              </>}
              <Text style={styles.modalLabel}>Apprehension Date and Time</Text>
              {renderDateTimeField('apprehension_date', 'Select apprehension date')}
              {renderDateTimeField('apprehension_time', 'Select apprehension time')}
              {!!selectedViolations.length && <Text style={styles.modalSub}>Total penalty: ₱{selectedViolations.reduce((sum, item) => sum + Number(item.penalty_amount || 0), 0).toLocaleString()}</Text>}
            </ScrollView>

            {activeDateTimePicker && Platform.OS === 'ios' ? (
              <View style={styles.pickerModalCard}>
                <View style={styles.pickerModalHeader}>
                  <Text style={styles.pickerModalTitle}>{pickerMode === 'date' ? 'Select Date' : 'Select Time'}</Text>
                  <TouchableOpacity onPress={() => setActiveDateTimePicker(null)}><Text style={styles.pickerDoneText}>Done</Text></TouchableOpacity>
                </View>
                <DateTimePicker
                  value={pickerDateValue(activePickerValue, pickerMode)}
                  mode={pickerMode}
                  display="spinner"
                  onChange={handleDateTimeChange}
                  maximumDate={activeDateTimePicker === 'date_of_birth' ? new Date() : undefined}
                />
              </View>
            ) : null}

            <View style={styles.modalActions}>
              <TouchableOpacity
                style={styles.cancelModalButton}
                onPress={() => { setActiveDateTimePicker(null); setModalVisible(false); }}
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

  tableShell: { overflow: 'hidden', backgroundColor: COLORS.surface, borderWidth: 1, borderColor: COLORS.border, borderRadius: 14, ...softShadow },
  tableHeader: { minHeight: 38, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 10, backgroundColor: '#EDF4F2', borderBottomWidth: 1, borderBottomColor: COLORS.border },
  tableHeaderText: { color: COLORS.textSecondary, fontSize: 8, fontWeight: '900', letterSpacing: .35 },
  tableRow: { minHeight: 68, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 10, paddingVertical: 10 },
  tableDriverCell: { flex: 1.1, paddingRight: 7 },
  tableLatestCell: { flex: 1.15, paddingRight: 7 },
  tableCountCell: { width: 48, alignItems: 'center' },
  tableActionCell: { width: 38, textAlign: 'center' },
  tablePrimary: { color: COLORS.textPrimary, fontSize: 10, lineHeight: 14, fontWeight: '800' },
  tableSecondary: { color: COLORS.textTertiary, fontSize: 8, lineHeight: 12, marginTop: 2 },
  tableCount: { color: COLORS.textPrimary, fontSize: 14, fontWeight: '900' },
  tableViewButton: { width: 36, height: 34, marginLeft: 2, borderWidth: 1, borderColor: COLORS.primary + '55', backgroundColor: COLORS.primary + '0D', borderRadius: 9, alignItems: 'center', justifyContent: 'center' },
  tableDivider: { height: 1, backgroundColor: COLORS.border, marginHorizontal: 10 },

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

  cameraScreen: { flex: 1, backgroundColor: '#000' },
  cameraSafeArea: { flex: 1, backgroundColor: 'rgba(0,0,0,0.18)' },
  cameraHeader: { position: 'absolute', top: 0, left: 0, right: 0, zIndex: 3, flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingTop: 8 },
  cameraCloseButton: { width: 44, height: 44, borderRadius: 22, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.58)' },
  cameraInstruction: { flex: 1, alignItems: 'center', paddingHorizontal: 8 },
  cameraInstructionTitle: { color: '#FFF', fontSize: 16, fontWeight: '900', textAlign: 'center' },
  cameraInstructionText: { color: 'rgba(255,255,255,0.86)', fontSize: 11, lineHeight: 15, textAlign: 'center', marginTop: 2 },
  cameraHeaderSpacer: { width: 44 },
  cameraGuideArea: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, alignItems: 'center', justifyContent: 'center' },
  licenseGuide: { maxWidth: 760, borderWidth: 1, borderStyle: 'dashed', borderColor: 'rgba(255,255,255,0.72)', backgroundColor: 'rgba(0,0,0,0.05)', alignItems: 'center', justifyContent: 'center' },
  guideCorner: { position: 'absolute', width: 34, height: 34, borderColor: '#55E8C7' },
  guideTopLeft: { top: -2, left: -2, borderTopWidth: 4, borderLeftWidth: 4, borderTopLeftRadius: 8 },
  guideTopRight: { top: -2, right: -2, borderTopWidth: 4, borderRightWidth: 4, borderTopRightRadius: 8 },
  guideBottomLeft: { bottom: -2, left: -2, borderBottomWidth: 4, borderLeftWidth: 4, borderBottomLeftRadius: 8 },
  guideBottomRight: { bottom: -2, right: -2, borderBottomWidth: 4, borderRightWidth: 4, borderBottomRightRadius: 8 },
  guideCenterBadge: { flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 11, paddingVertical: 7, borderRadius: 18, backgroundColor: 'rgba(0,0,0,0.52)' },
  guideCenterText: { color: '#FFF', fontSize: 10, fontWeight: '900', letterSpacing: 1 },
  cameraControls: { position: 'absolute', left: 0, right: 0, bottom: 6, zIndex: 3, minHeight: 76, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 18, paddingHorizontal: 18 },
  cameraTip: { maxWidth: 430, paddingHorizontal: 8, paddingVertical: 5, borderRadius: 8, backgroundColor: 'rgba(0,0,0,0.46)', color: '#FFF', fontSize: 11, fontWeight: '700', textAlign: 'center', textShadowColor: '#000', textShadowRadius: 4 },
  shutterOuter: { width: 68, height: 68, borderRadius: 34, borderWidth: 5, borderColor: '#FFF', backgroundColor: 'rgba(255,255,255,0.22)', alignItems: 'center', justifyContent: 'center' },
  shutterInner: { width: 50, height: 50, borderRadius: 25, backgroundColor: '#FFF', borderWidth: 2, borderColor: 'rgba(0,0,0,0.15)' },
  shutterDisabled: { opacity: 0.48 },

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
  clearFormButton: { flexDirection: 'row', alignItems: 'center', gap: 4, marginRight: 12, paddingHorizontal: 9, paddingVertical: 7, borderRadius: 8, backgroundColor: COLORS.danger + '10', borderWidth: 1, borderColor: COLORS.danger + '35' },
  clearFormButtonText: { color: COLORS.danger, fontSize: 10, fontWeight: '800' },
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
  ocrNotice: { flexDirection: 'row', gap: 7, alignItems: 'flex-start', backgroundColor: '#FFF7E8', padding: 10, borderRadius: 10, marginTop: 8 },
  ocrNoticeText: { flex: 1, color: '#7C5310', fontSize: 10, lineHeight: 15 },
  modalLabel: { fontSize: 13, fontWeight: '600', color: COLORS.textPrimary, marginTop: 12, marginBottom: 6 },
  modalInput: {
    minHeight: 46, backgroundColor: '#FFF', borderRadius: 10, paddingHorizontal: 12, paddingVertical: 10,
    borderWidth: 1, borderColor: COLORS.border, fontSize: 14, color: COLORS.textPrimary, marginBottom: 2,
  },
  dateTimeFieldRow: { flexDirection: 'row', alignItems: 'center', gap: 7, marginBottom: 2 },
  dateTimeField: { flex: 1, minHeight: 46, paddingHorizontal: 12, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, backgroundColor: '#FFF', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  dateTimeFieldText: { color: COLORS.textPrimary, fontSize: 14 },
  dateTimeFieldPlaceholder: { color: COLORS.textTertiary },
  dateTimeClearButton: { width: 42, height: 46, borderRadius: 10, borderWidth: 1, borderColor: COLORS.border, backgroundColor: COLORS.bg, alignItems: 'center', justifyContent: 'center' },
  pickerModalCard: { padding: 16, paddingBottom: 28, borderTopLeftRadius: 20, borderTopRightRadius: 20, backgroundColor: '#FFF' },
  pickerModalHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingBottom: 8 },
  pickerModalTitle: { color: COLORS.textPrimary, fontSize: 16, fontWeight: '800' },
  pickerDoneText: { color: COLORS.primary, fontSize: 14, fontWeight: '800' },
  disabledInput: { opacity: 0.55, backgroundColor: 'rgba(16, 47, 73, 0.06)' },
  structuredNamePreview: { color: COLORS.primary, fontSize: 11, fontWeight: '700', marginTop: 7, marginBottom: 2 },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 8, marginBottom: 4 },
  checkRowText: { flex: 1, color: COLORS.textSecondary, fontSize: 12 },
  officerPickerWrap: { height: 58, marginTop: 2, borderWidth: 1, borderColor: COLORS.border, borderRadius: 10, overflow: 'hidden', backgroundColor: '#FFF', justifyContent: 'center' },
  officerPicker: { height: 58, color: COLORS.textPrimary, fontSize: 13, fontFamily: Platform.select({ android: 'sans-serif', default: undefined }) },
  officerEmpty: { color: COLORS.danger, fontSize: 11, paddingVertical: 12 },
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
