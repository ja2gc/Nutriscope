import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as ImagePicker from 'expo-image-picker';
import { useLocalSearchParams } from 'expo-router';
import {
  AlertCircle,
  Camera,
  ChevronLeft,
  ChevronRight,
  Image as ImageIcon,
  Paperclip,
  Save,
  ShoppingBag,
  Trash2,
  X,
} from 'lucide-react-native';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';
import { PaginatedListFooter } from '../../components/PaginatedListFooter';
import { MOBILE_PAGE_SIZE, PaginatedResponse, flattenUniquePages, getNextPageParam } from '../../lib/pagination';

type AttachmentType = 'receipt' | 'proof';

interface Supplier {
  id: string;
  name: string;
  category: string | null;
}

interface PurchaseOrderItem {
  id: number;
  fs_item_id: number | null;
  description: string;
  qty: number | string | null;
  unit: string | null;
  unit_price: number | string | null;
  total_value: number | string | null;
  purchase_qty: number | string | null;
  purchase_unit: string | null;
  purchase_price: number | string | null;
  actual_qty: number | string;
  actual_unit: string;
  actual_unit_price: number | string;
  actual_total: number | string;
  actual_values_confirmed: boolean;
}

interface PurchaseOrderAttachment {
  id: string;
  vendor_group_id?: number | null;
  type: AttachmentType;
  path: string;
  caption: string | null;
}

interface VendorGroup {
  id: string;
  supplier_id: number | null;
  supplier: Supplier | null;
  or_number: string | null;
  status: string;
  can_change_vendor: boolean;
  vendor_change_blocker: string | null;
  total_amount: number | string | null;
  received_at: string | null;
  stocked_at: string | null;
  items: PurchaseOrderItem[] | null;
  attachments: PurchaseOrderAttachment[] | null;
  evidence_requirements?: { receipt_uploaded: boolean; proof_uploaded: boolean; actual_values_reviewed: boolean; can_mark_received: boolean };
}

interface PurchaseOrder {
  id: string;
  po_number: string;
  order_date: string | null;
  total_amount: number | string | null;
  status: string;
  lifecycle_status: string;
  notes: string | null;
  vendor_groups: VendorGroup[] | null;
  ppa?: {
    activity?: string | null;
    target_date_range?: string | null;
    estimated_output_patients?: number | string | null;
  } | null;
  created_at: string;
}

async function fetchPOs(page: number): Promise<PaginatedResponse<PurchaseOrder>> {
  const res = await api.get<PaginatedResponse<PurchaseOrder>>('/api/fss/purchase-orders', {
    params: { page, per_page: MOBILE_PAGE_SIZE },
  });
  return res.data;
}

async function fetchSuppliers(): Promise<Supplier[]> {
  const res = await api.get<PaginatedResponse<Supplier>>('/api/fss/suppliers', {
    params: { page: 1, per_page: 10 },
  });
  return res.data.data ?? [];
}

function money(value: number | string | null | undefined): string {
  const num = Number(value ?? 0);
  return `PHP ${num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function plain(value: number | string | null | undefined): string {
  if (value === null || value === undefined) return '';
  return String(value);
}

function countReceipts(group: VendorGroup): number {
  return (group.attachments ?? []).filter((att) => att.type === 'receipt').length;
}

function countProofs(group: VendorGroup): number {
  return (group.attachments ?? []).filter((att) => att.type === 'proof').length;
}

function isLocked(po: PurchaseOrder): boolean {
  return ['completed', 'archived'].includes(po.lifecycle_status);
}


function attachmentName(att: PurchaseOrderAttachment): string {
  return att.caption || att.path.split('/').pop() || `${att.type} image`;
}

function attachmentUrl(att: PurchaseOrderAttachment): string {
  const baseUrl = (process.env.EXPO_PUBLIC_API_URL ?? '').replace(/\/+$/, '').replace(/\/api$/, '');
  return `${baseUrl}/storage/${att.path}`;
}

interface UploadModalProps {
  group: VendorGroup | null;
  visible: boolean;
  type: AttachmentType;
  onChangeType: (type: AttachmentType) => void;
  onClose: () => void;
}

function UploadAttachmentModal({ group, visible, type, onChangeType, onClose }: UploadModalProps) {
  const qc = useQueryClient();
  const [caption, setCaption] = useState('');
  const [error, setError] = useState<string | null>(null);

  const uploadMutation = useMutation({
    mutationFn: async (files: { uri: string; name: string; type: string }[]) => {
      const formData = new FormData();
      files.forEach((file) => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        formData.append(files.length === 1 ? 'file' : 'files[]', { uri: file.uri, name: file.name, type: file.type } as any);
      });
      formData.append('type', type);
      if (caption.trim()) formData.append('caption', caption.trim());
      const res = await api.post(
        `/api/fss/purchase-order-vendor-groups/${group!.id}/attachments`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      setCaption('');
      setError(null);
      onClose();
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      setError((err as any)?.response?.data?.message ?? 'Upload failed.');
    },
  });

  const pickWithPermission = useCallback(
    async (source: 'library' | 'camera') => {
      setError(null);
      let assets: ImagePicker.ImagePickerAsset[] = [];

      if (source === 'library') {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          setError('Photo library access denied.');
          return;
        }
        const result = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: 'images',
          quality: 0.85,
          allowsEditing: false,
          allowsMultipleSelection: true,
        });
        if (result.canceled || !result.assets?.length) return;
        assets = result.assets;
      } else {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          setError('Camera access denied.');
          return;
        }
        const result = await ImagePicker.launchCameraAsync({
          mediaTypes: 'images',
          quality: 0.85,
          allowsEditing: false,
        });
        if (result.canceled || !result.assets?.length) return;
        assets = result.assets;
      }

      uploadMutation.mutate(assets.map((asset, index) => {
        const uri = asset.uri;
        const ext = uri.split('.').pop() ?? 'jpg';
        const mimeType = asset.mimeType ?? `image/${ext === 'png' ? 'png' : 'jpeg'}`;
        return { uri, name: `${type}_${Date.now()}_${index}.${ext}`, type: mimeType };
      }));
    },
    [type, uploadMutation],
  );

  const close = () => {
    if (uploadMutation.isPending) return;
    setCaption('');
    setError(null);
    onClose();
  };

  if (!group) return null;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={close}>
      <KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : 'height'} className="flex-1">
        <Pressable className="flex-1 bg-black/50 justify-end" onPress={close}>
          <Pressable onPress={(event) => event.stopPropagation()}>
            <View className="bg-white rounded-t-2xl px-5 pt-5 pb-8">
              <View className="flex-row items-start justify-between mb-4">
                <View className="flex-1 pr-4">
                  <Text className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">
                    Vendor attachment
                  </Text>
                  <Text className="text-base font-semibold text-gray-900" numberOfLines={1}>
                    {group.supplier?.name ?? 'Unassigned vendor'}
                  </Text>
                </View>
                <TouchableOpacity
                  onPress={close}
                  hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
                  disabled={uploadMutation.isPending}
                  accessibilityLabel="Close upload"
                >
                  <Text className="text-gray-400 text-lg">x</Text>
                </TouchableOpacity>
              </View>

              <View className="flex-row bg-gray-100 rounded-xl p-1 mb-4">
                {(['receipt', 'proof'] as AttachmentType[]).map((item) => (
                  <TouchableOpacity
                    key={item}
                    className={`flex-1 items-center justify-center py-2.5 rounded-lg ${
                      type === item ? 'bg-white shadow-sm' : ''
                    }`}
                    onPress={() => onChangeType(item)}
                  >
                    <Text className={`text-sm font-semibold capitalize ${type === item ? 'text-emerald-700' : 'text-gray-400'}`}>
                      {item}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>

              <Text className="text-xs font-medium text-gray-500 mb-1.5">Caption</Text>
              <TextInput
                value={caption}
                onChangeText={setCaption}
                placeholder="Official receipt, delivery proof, or note"
                placeholderTextColor="#9ca3af"
                className="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 mb-4"
                returnKeyType="done"
              />

              {error && (
                <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 mb-4">
                  <AlertCircle color="#ef4444" size={16} />
                  <Text className="text-sm text-red-700 flex-1">{error}</Text>
                </View>
              )}

              {uploadMutation.isPending ? (
                <View className="items-center py-4">
                  <ActivityIndicator color="#059669" />
                  <Text className="mt-2 text-sm text-gray-500">Uploading...</Text>
                </View>
              ) : (
                <View className="flex-row gap-3">
                  <TouchableOpacity
                    className="flex-1 flex-row items-center justify-center gap-2 border border-gray-200 py-3.5 rounded-xl"
                    onPress={() => pickWithPermission('library')}
                    style={{ minHeight: 52 }}
                  >
                    <ImageIcon color="#6b7280" size={18} />
                    <Text className="text-sm font-medium text-gray-700">Library</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    className="flex-1 flex-row items-center justify-center gap-2 bg-emerald-600 py-3.5 rounded-xl"
                    onPress={() => pickWithPermission('camera')}
                    style={{ minHeight: 52 }}
                  >
                    <Camera color="#fff" size={18} />
                    <Text className="text-sm font-medium text-white">Camera</Text>
                  </TouchableOpacity>
                </View>
              )}
            </View>
          </Pressable>
        </Pressable>
      </KeyboardAvoidingView>
    </Modal>
  );
}

function HeaderBack({ label, onPress }: { label: string; onPress: () => void }) {
  return (
    <TouchableOpacity
      className="flex-row items-center gap-2 mb-4"
      onPress={onPress}
      accessibilityRole="button"
      style={{ minHeight: 40 }}
    >
      <ChevronLeft color="#6b7280" size={18} />
      <Text className="text-sm font-semibold text-gray-600">{label}</Text>
    </TouchableOpacity>
  );
}

function AttachmentList({
  group,
  type,
  locked,
  onUpload,
}: {
  group: VendorGroup;
  type: AttachmentType;
  locked: boolean;
  onUpload: (type: AttachmentType) => void;
}) {
  const qc = useQueryClient();
  const attachments = (group.attachments ?? []).filter((att) => att.type === type);
  const [viewing, setViewing] = useState<PurchaseOrderAttachment | null>(null);

  const deleteMutation = useMutation({
    mutationFn: async (attachmentId: string) => {
      await api.delete(`/api/fss/purchase-order-attachments/${attachmentId}`);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
    },
    onError: () => Alert.alert('Error', 'Could not delete attachment.'),
  });

  const confirmDelete = (attachmentId: string) => {
    Alert.alert('Delete attachment', 'Remove this image from the vendor group?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => deleteMutation.mutate(attachmentId),
      },
    ]);
  };

  return (
    <View className="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <View className="flex-row items-center justify-between px-4 py-3 border-b border-gray-100">
        <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
          {type === 'receipt' ? 'Receipt images' : 'Proof of purchase'}
        </Text>
        {!locked && (
          <TouchableOpacity
            className="flex-row items-center gap-1.5 px-3 py-1.5 bg-emerald-600 rounded-lg"
            onPress={() => onUpload(type)}
            style={{ minHeight: 32 }}
          >
            <Paperclip color="#fff" size={13} />
            <Text className="text-xs font-semibold text-white">Upload</Text>
          </TouchableOpacity>
        )}
      </View>

      {attachments.length === 0 ? (
        <View className="px-4 py-5 items-center">
          <Paperclip color="#d1d5db" size={22} />
          <Text className="mt-1.5 text-xs text-gray-400">
            {type === 'receipt' ? 'No receipt uploaded yet.' : 'No proof photo uploaded yet.'}
          </Text>
        </View>
      ) : (
        attachments.map((att, idx) => (
          <View
            key={att.id}
            className={`flex-row items-center px-4 py-3 ${idx < attachments.length - 1 ? 'border-b border-gray-100' : ''}`}
          >
            <TouchableOpacity
              onPress={() => setViewing(att)}
              className="w-14 h-14 rounded-lg bg-gray-50 border border-gray-100 overflow-hidden mr-3"
              accessibilityRole="imagebutton"
              accessibilityLabel={`View ${attachmentName(att)}`}
            >
              <Image
                source={{ uri: attachmentUrl(att) }}
                className="w-full h-full"
                resizeMode="cover"
              />
            </TouchableOpacity>
            <View className="flex-1 pr-3">
              <Text className="text-sm font-medium text-gray-800" numberOfLines={1}>
                {attachmentName(att)}
              </Text>
              <Text className="text-xs text-emerald-700 font-semibold" numberOfLines={1}>
                Tap image to view
              </Text>
            </View>
            {!locked && (
              <TouchableOpacity
                onPress={() => confirmDelete(att.id)}
                disabled={deleteMutation.isPending}
                style={{ minWidth: 36, minHeight: 36, alignItems: 'center', justifyContent: 'center' }}
              >
                {deleteMutation.isPending ? (
                  <ActivityIndicator size="small" color="#ef4444" />
                ) : (
                  <Trash2 color="#ef4444" size={16} />
                )}
              </TouchableOpacity>
            )}
          </View>
        ))
      )}
      <Modal visible={viewing !== null} transparent animationType="fade" onRequestClose={() => setViewing(null)}>
        <Pressable className="flex-1 bg-black/90 justify-center px-4" onPress={() => setViewing(null)}>
          {viewing && (
            <Pressable onPress={(event) => event.stopPropagation()}>
              <View className="mb-3 flex-row items-center justify-between">
                <Text className="text-white text-sm font-semibold flex-1 pr-4" numberOfLines={1}>
                  {attachmentName(viewing)}
                </Text>
                <TouchableOpacity
                  onPress={() => setViewing(null)}
                  className="w-10 h-10 rounded-full bg-white/15 items-center justify-center"
                  accessibilityLabel="Close image preview"
                >
                  <X color="#fff" size={18} />
                </TouchableOpacity>
              </View>
              <Image
                source={{ uri: attachmentUrl(viewing) }}
                className="w-full h-[520px] rounded-xl"
                resizeMode="contain"
              />
            </Pressable>
          )}
        </Pressable>
      </Modal>
    </View>
  );
}

interface VendorDetailProps {
  po: PurchaseOrder;
  group: VendorGroup;
  suppliers: Supplier[];
  onBack: () => void;
  onUpload: (group: VendorGroup, type: AttachmentType) => void;
  onVendorChanged: () => void;
}

function VendorDetail({ po, group, suppliers, onBack, onUpload, onVendorChanged }: VendorDetailProps) {
  const qc = useQueryClient();
  const locked = isLocked(po);
  const [orNumber, setOrNumber] = useState(group.or_number ?? '');
  const [error, setError] = useState<string | null>(null);
  const [actuals, setActuals] = useState<Record<number, { qty: string; price: string }>>(
    Object.fromEntries((group.items ?? []).map((item) => [item.id, { qty: plain(item.actual_qty), price: plain(item.actual_unit_price) }])),
  );
  const [vendorScope, setVendorScope] = useState<'all' | number | null>(null);
  const [expandedItems, setExpandedItems] = useState<Set<number>>(new Set());

  useEffect(() => {
    setOrNumber(group.or_number ?? '');
    setActuals(Object.fromEntries((group.items ?? []).map((item) => [item.id, { qty: plain(item.actual_qty), price: plain(item.actual_unit_price) }])));
    setError(null);
  }, [group]);

  const updateMutation = useMutation({
    mutationFn: async (markReceived: boolean) => {
      const payload = {
        or_number: orNumber.trim() || null,
        items: (group.items ?? []).map((item) => ({ id: item.id, actual_qty: Number(actuals[item.id]?.qty), actual_unit_price: Number(actuals[item.id]?.price) })),
        ...(markReceived ? { status: 'received' } : {}),
      };
      const res = await api.patch(`/api/fss/purchase-order-vendor-groups/${group.id}`, payload);
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      setError(null);
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      setError((err as any)?.response?.data?.message ?? 'Could not save vendor details.');
    },
  });

  const vendorMutation = useMutation({
    mutationFn: async ({ supplierId, itemId }: { supplierId: string; itemId?: number }) => {
      await api.patch(`/api/fss/purchase-order-vendor-groups/${group.id}`, {
        supplier_id: supplierId,
        ...(itemId === undefined ? {} : { item_id: itemId }),
      });
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      setVendorScope(null);
      onVendorChanged();
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      setError((err as any)?.response?.data?.message ?? 'Could not change vendor.');
    },
  });

  const selectVendor = (supplier: Supplier, itemId?: number) => {
    const label = itemId === undefined ? 'Change vendor for all' : 'Change vendor';
    Alert.alert(label, `Use ${supplier.name}?`, [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Confirm', onPress: () => vendorMutation.mutate({ supplierId: supplier.id, itemId }) },
    ]);
  };

  const vendorChoices = (itemId?: number) => (
    <View className="mt-2 gap-2">
      {suppliers.filter((supplier) => supplier.id !== group.supplier?.id).map((supplier) => (
        <TouchableOpacity
          key={supplier.id}
          className="min-h-11 justify-center rounded-lg border border-gray-200 bg-white px-3 py-2"
          onPress={() => selectVendor(supplier, itemId)}
          disabled={vendorMutation.isPending}
        >
          <Text className="text-sm font-semibold text-gray-700">{supplier.name}</Text>
        </TouchableOpacity>
      ))}
      <TouchableOpacity className="min-h-11 justify-center px-3" onPress={() => setVendorScope(null)} disabled={vendorMutation.isPending}>
        <Text className="text-center text-sm font-semibold text-gray-500">Cancel</Text>
      </TouchableOpacity>
    </View>
  );

  return (
    <ScrollView className="flex-1 bg-gray-50" contentContainerStyle={{ paddingBottom: 24 }}>
      <View className="px-4 pt-4">
        <HeaderBack label={po.po_number} onPress={onBack} />

        <View className="bg-white rounded-xl border border-gray-100 px-4 py-4 mb-4">
          <Text className="text-lg font-bold text-gray-900" numberOfLines={2}>
            {group.supplier?.name ?? 'Unassigned vendor'}
          </Text>
          <View className="flex-row flex-wrap gap-2 mt-2">
            <Text className="text-xs text-gray-400">Status: {group.status}</Text>
            <Text className="text-xs text-gray-300">|</Text>
            <Text className="text-xs text-gray-400">{money(group.total_amount)}</Text>
          </View>
        </View>

        <View className="bg-white rounded-xl border border-gray-100 px-4 py-4 mb-4">
          <Text className="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Vendor for this group</Text>
          <TouchableOpacity
            className="min-h-11 justify-center rounded-xl border border-gray-200 px-4"
            onPress={() => setVendorScope(vendorScope === 'all' ? null : 'all')}
            disabled={locked || !group.can_change_vendor}
            accessibilityRole="button"
            accessibilityState={{ expanded: vendorScope === 'all', disabled: locked || !group.can_change_vendor }}
          >
            <Text className={`text-center text-sm font-semibold ${locked || !group.can_change_vendor ? 'text-gray-300' : 'text-emerald-700'}`}>
              Change vendor for all
            </Text>
          </TouchableOpacity>
          {vendorScope === 'all' && vendorChoices()}
          {!group.can_change_vendor && group.vendor_change_blocker && <Text className="mt-2 text-xs text-gray-500">{group.vendor_change_blocker}</Text>}
        </View>

        <View className="bg-white rounded-xl border border-gray-100 px-4 py-4 mb-4">
          <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
            OR number (optional)
          </Text>
          <TextInput
            value={orNumber}
            onChangeText={setOrNumber}
            editable={!locked}
            placeholder="Enter official receipt number"
            placeholderTextColor="#9ca3af"
            className="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900"
          />

          {error && (
            <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 mt-3">
              <AlertCircle color="#ef4444" size={16} />
              <Text className="text-sm text-red-700 flex-1">{error}</Text>
            </View>
          )}

          {!locked && (
            <View className="mt-3">
              <TouchableOpacity
                className="flex-row items-center justify-center gap-2 border border-gray-200 py-3 rounded-xl"
                onPress={() => updateMutation.mutate(false)}
                disabled={updateMutation.isPending}
              >
                {updateMutation.isPending ? <ActivityIndicator size="small" color="#059669" /> : <Save color="#059669" size={16} />}
                <Text className="text-sm font-semibold text-emerald-700">Save actuals and optional OR</Text>
              </TouchableOpacity>
            </View>
          )}
        </View>

        <View className="bg-white rounded-xl border border-gray-100 overflow-hidden mb-4">
          <View className="px-4 py-3 border-b border-gray-100">
            <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
              Vendor line details
            </Text>
          </View>

          {(group.items ?? []).map((item, idx) => (
            <View key={item.id} className={`px-4 py-4 ${idx < (group.items?.length ?? 0) - 1 ? 'border-b border-gray-100' : ''}`}>
              <Text className="text-sm font-semibold text-gray-900 mb-1" numberOfLines={2}>
                {item.description}
              </Text>
              <Text className="text-xs text-gray-500">Planned purchase: {plain(item.purchase_qty ?? item.qty)} {item.actual_unit} at {money(item.purchase_price ?? item.unit_price)}</Text>
              <Text className="mt-0.5 text-xs text-gray-500">Actual purchased: {actuals[item.id]?.qty ?? plain(item.actual_qty)} {item.actual_unit} at {money(actuals[item.id]?.price ?? item.actual_unit_price)}</Text>
              <Text className={`mb-2 mt-1 self-start rounded-full px-2 py-1 text-xs font-semibold ${item.actual_values_confirmed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>
                {item.actual_values_confirmed ? 'Reviewed' : 'Not reviewed'}
              </Text>
              <View className="flex-row gap-2">
                <View className="flex-1"><Text className="text-xs text-gray-500 mb-1">Actual qty</Text><TextInput keyboardType="decimal-pad" editable={!locked} value={actuals[item.id]?.qty ?? ''} onChangeText={(value) => setActuals((current) => ({ ...current, [item.id]: { qty: value, price: current[item.id]?.price ?? plain(item.actual_unit_price) } }))} className="border border-gray-200 rounded-lg px-3 py-2 text-sm" /></View>
                <View className="flex-1"><Text className="text-xs text-gray-500 mb-1">Actual unit price</Text><TextInput keyboardType="decimal-pad" editable={!locked} value={actuals[item.id]?.price ?? ''} onChangeText={(value) => setActuals((current) => ({ ...current, [item.id]: { qty: current[item.id]?.qty ?? plain(item.actual_qty), price: value } }))} className="border border-gray-200 rounded-lg px-3 py-2 text-sm" /></View>
              </View>
              <TouchableOpacity
                className="min-h-11 justify-center"
                onPress={() => setExpandedItems((current) => {
                  const next = new Set(current);
                  if (next.has(item.id)) next.delete(item.id); else next.add(item.id);
                  return next;
                })}
                accessibilityRole="button"
                accessibilityState={{ expanded: expandedItems.has(item.id) }}
              >
                <Text className="text-sm font-semibold text-emerald-700">Calculation details</Text>
              </TouchableOpacity>
              {expandedItems.has(item.id) && <View className="rounded-lg bg-gray-50 p-3">
                <Text className="text-xs text-gray-500">Calculated need: {plain(item.qty)} {item.unit} at {money(item.unit_price)}</Text>
                <Text className="mt-1 text-xs text-gray-500">Planned purchase: {plain(item.purchase_qty ?? item.qty)} {item.actual_unit} at {money(item.purchase_price ?? item.unit_price)}</Text>
                <Text className="mt-1 text-xs text-gray-500">Actual purchased: {actuals[item.id]?.qty ?? plain(item.actual_qty)} {item.actual_unit} at {money(actuals[item.id]?.price ?? item.actual_unit_price)}</Text>
              </View>}
              <TouchableOpacity
                className="min-h-11 justify-center self-start"
                onPress={() => setVendorScope(vendorScope === item.id ? null : item.id)}
                disabled={locked || !group.can_change_vendor}
                accessibilityRole="button"
                accessibilityState={{ expanded: vendorScope === item.id, disabled: locked || !group.can_change_vendor }}
              >
                <Text className={`text-sm font-semibold ${locked || !group.can_change_vendor ? 'text-gray-300' : 'text-emerald-700'}`}>Change vendor</Text>
              </TouchableOpacity>
              {vendorScope === item.id && vendorChoices(item.id)}
            </View>
          ))}
        </View>

        <View className="gap-4">
          <AttachmentList group={group} type="receipt" locked={locked} onUpload={(type) => onUpload(group, type)} />
          <AttachmentList group={group} type="proof" locked={locked} onUpload={(type) => onUpload(group, type)} />
        </View>
        {!locked && <TouchableOpacity className={`mt-4 rounded-xl py-3 ${group.evidence_requirements?.receipt_uploaded && group.evidence_requirements?.proof_uploaded ? 'bg-emerald-600' : 'bg-gray-300'}`} disabled={updateMutation.isPending || !group.evidence_requirements?.receipt_uploaded || !group.evidence_requirements?.proof_uploaded} onPress={() => updateMutation.mutate(true)}>
          <Text className="text-center font-semibold text-white">Mark vendor received</Text>
        </TouchableOpacity>}
        {!locked && <Text className="mt-2 text-center text-xs text-gray-500">Receipt, proof, and reviewed actual values are required. OR number is optional.</Text>}
      </View>
    </ScrollView>
  );
}

function PurchaseOrderDetail({
  po,
  onBack,
  onOpenGroup,
}: {
  po: PurchaseOrder;
  onBack: () => void;
  onOpenGroup: (groupId: string) => void;
}) {
  const groups = po.vendor_groups ?? [];

  return (
    <ScrollView className="flex-1 bg-gray-50" contentContainerStyle={{ paddingBottom: 24 }}>
      <View className="px-4 pt-4">
        <HeaderBack label="Procurement" onPress={onBack} />

        <View className="bg-white rounded-xl border border-gray-100 px-4 py-4 mb-4">
          <Text className="text-lg font-bold text-gray-900">{po.po_number}</Text>
          <Text className="text-xs text-gray-400 mt-1">
            {po.ppa?.target_date_range ?? po.order_date ?? 'No schedule'} | {money(po.total_amount)}
          </Text>
          {po.ppa?.activity && (
            <Text className="text-sm text-gray-600 mt-2" numberOfLines={2}>
              {po.ppa.activity}
            </Text>
          )}
        </View>

        <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
          Vendor groups
        </Text>

        <View className="bg-white rounded-xl border border-gray-100 overflow-hidden">
          {groups.length === 0 ? (
            <View className="px-4 py-6 items-center">
              <Text className="text-sm text-gray-400">No vendor groups for this purchase order.</Text>
            </View>
          ) : (
            groups.map((group, idx) => (
              <TouchableOpacity
                key={group.id}
                className={`flex-row items-center px-4 py-4 ${idx < groups.length - 1 ? 'border-b border-gray-100' : ''}`}
                onPress={() => onOpenGroup(group.id)}
                style={{ minHeight: 68 }}
              >
                <View className="flex-1 pr-3">
                  <Text className="text-sm font-semibold text-gray-900" numberOfLines={1}>
                    {group.supplier?.name ?? 'Unassigned vendor'}
                  </Text>
                  <Text className="text-xs text-gray-400 mt-0.5">
                    OR: {group.or_number || 'missing'} | receipt {countReceipts(group) ? 'uploaded' : 'missing'} | proof {countProofs(group)}
                  </Text>
                </View>
                <View className="items-end mr-2">
                  <Text className="text-sm font-bold text-gray-800">{money(group.total_amount)}</Text>
                  <Text className="text-xs text-gray-400">{group.items?.length ?? 0} items</Text>
                </View>
                <ChevronRight color="#9ca3af" size={18} />
              </TouchableOpacity>
            ))
          )}
        </View>
      </View>
    </ScrollView>
  );
}

function PurchaseOrderRow({ po, onPress }: { po: PurchaseOrder; onPress: () => void }) {
  const groups = po.vendor_groups ?? [];
  const missingReceipts = groups.filter((group) => countReceipts(group) === 0).length;

  return (
    <TouchableOpacity
      className="bg-white rounded-xl border border-gray-100 px-4 py-4 mb-3"
      onPress={onPress}
      accessibilityRole="button"
      style={{ minHeight: 86 }}
    >
      <View className="flex-row items-start">
        <View className="w-10 h-10 rounded-xl bg-emerald-50 items-center justify-center mr-3">
          <ShoppingBag color="#059669" size={20} />
        </View>
        <View className="flex-1 pr-3">
          <Text className="text-sm font-bold text-gray-900" numberOfLines={1}>
            {po.po_number}
          </Text>
          <Text className="text-xs text-gray-400 mt-0.5" numberOfLines={1}>
            {po.ppa?.target_date_range ?? po.order_date ?? 'No schedule'}
          </Text>
          <Text className="text-xs text-gray-500 mt-1">
            {groups.length} vendor{groups.length === 1 ? '' : 's'} | {missingReceipts} awaiting receipt
          </Text>
        </View>
        <View className="items-end">
          <Text className="text-sm font-bold text-gray-800">{money(po.total_amount)}</Text>
          <Text className="text-xs text-gray-400 mt-1">{po.lifecycle_status}</Text>
        </View>
      </View>
    </TouchableOpacity>
  );
}

export default function ProcurementScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ poId?: string }>();
  // PO ids are public uuids (strings) — match as strings, never Number().
  const targetPoId = params.poId || null;
  const [selectedPoId, setSelectedPoId] = useState<string | null>(null);
  const [selectedGroupId, setSelectedGroupId] = useState<string | null>(null);
  const [uploadGroup, setUploadGroup] = useState<VendorGroup | null>(null);
  const [uploadType, setUploadType] = useState<AttachmentType>('receipt');
  const [uploadOpen, setUploadOpen] = useState(false);

  const { data, isLoading, isError, refetch, isFetching, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['fss-purchase-orders'],
    queryFn: ({ pageParam }) => fetchPOs(pageParam),
    initialPageParam: 1,
    getNextPageParam,
    staleTime: 60_000,
  });
  const { data: suppliers = [] } = useQuery({
    queryKey: ['fss-suppliers', 'purchase-change'],
    queryFn: () => fetchSuppliers(),
    staleTime: 5 * 60_000,
  });

  const orders = useMemo(() => flattenUniquePages(data?.pages), [data]);
  const selectedPo = useMemo(
    () => orders.find((po) => String(po.id) === selectedPoId) ?? null,
    [orders, selectedPoId],
  );
  const selectedGroup = useMemo(
    () => selectedPo?.vendor_groups?.find((group) => group.id === selectedGroupId) ?? null,
    [selectedPo, selectedGroupId],
  );

  useEffect(() => {
    if (!targetPoId || selectedPoId === targetPoId) return;
    if (orders.some((po) => String(po.id) === targetPoId)) {
      setSelectedPoId(targetPoId);
      setSelectedGroupId(null);
    } else if (hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [fetchNextPage, hasNextPage, isFetchingNextPage, orders, selectedPoId, targetPoId]);

  const openUpload = useCallback((group: VendorGroup, type: AttachmentType) => {
    setUploadGroup(group);
    setUploadType(type);
    setUploadOpen(true);
  }, []);

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
        <Text className="mt-3 text-gray-500 text-sm">Loading procurement...</Text>
      </View>
    );
  }

  if (isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <AlertCircle color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">Could not load procurement</Text>
        <Text className="mt-1 text-gray-500 text-sm text-center">
          Check your connection and try again.
        </Text>
        <TouchableOpacity className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg" onPress={() => refetch()}>
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (selectedPo && selectedGroup) {
    return (
      <>
        <VendorDetail
          po={selectedPo}
          group={selectedGroup}
          suppliers={suppliers}
          onBack={() => setSelectedGroupId(null)}
          onUpload={openUpload}
          onVendorChanged={() => setSelectedGroupId(null)}
        />
        <UploadAttachmentModal
          group={uploadGroup}
          visible={uploadOpen}
          type={uploadType}
          onChangeType={setUploadType}
          onClose={() => setUploadOpen(false)}
        />
      </>
    );
  }

  if (selectedPo) {
    return (
      <PurchaseOrderDetail
        po={selectedPo}
        onBack={() => {
          setSelectedPoId(null);
          setSelectedGroupId(null);
        }}
        onOpenGroup={setSelectedGroupId}
      />
    );
  }

  return (
    <>
      <FlatList
        className="flex-1 bg-gray-50"
        data={orders}
        keyExtractor={(po) => String(po.id)}
        contentContainerStyle={{ paddingBottom: insets.bottom + 16, paddingTop: 16 }}
        refreshing={isFetching && !isLoading}
        onRefresh={refetch}
        onEndReached={() => { if (hasNextPage && !isFetchingNextPage) void fetchNextPage(); }}
        onEndReachedThreshold={0.4}
        ListHeaderComponent={<View className="px-4 mb-4">
          <Text className="text-lg font-bold text-gray-900">Procurement</Text>
          <Text className="text-sm text-gray-500 mt-1">
            Open purchase events, save OR numbers, and upload receipt/proof images.
          </Text>
        </View>}
        renderItem={({ item: po }) => <View className="px-4"><PurchaseOrderRow
          po={po}
          onPress={() => {
            setSelectedPoId(String(po.id));
            setSelectedGroupId(null);
          }}
        /></View>}
        ListEmptyComponent={<View className="px-4"><View className="bg-white rounded-xl border border-gray-100 px-4 py-8 items-center">
              <ShoppingBag color="#d1d5db" size={32} />
              <Text className="mt-2 text-sm text-gray-400">No purchase orders assigned yet.</Text>
            </View></View>}
        ListFooterComponent={<PaginatedListFooter loading={isFetchingNextPage} error={isFetchNextPageError} onRetry={() => void fetchNextPage()} />}
      />

      <UploadAttachmentModal
        group={uploadGroup}
        visible={uploadOpen}
        type={uploadType}
        onChangeType={setUploadType}
        onClose={() => setUploadOpen(false)}
      />
    </>
  );
}
