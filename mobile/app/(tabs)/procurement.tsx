import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as ImagePicker from 'expo-image-picker';
import {
  AlertCircle,
  Camera,
  ChevronDown,
  ChevronUp,
  Image as ImageIcon,
  Paperclip,
  ShoppingBag,
  Trash2,
} from 'lucide-react-native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface PurchaseOrderItem {
  id: number;
  fs_item_id: number | null;
  description: string;
  qty: number;
  unit: string;
  unit_price: number;
  total_value: number;
}

interface PurchaseOrderAttachment {
  id: number;
  type: 'receipt' | 'proof';
  path: string;
  caption: string | null;
}

interface PurchaseOrder {
  id: number;
  po_number: string;
  or_number: string | null;
  order_date: string | null;
  total_amount: number;
  status: string;
  notes: string | null;
  supplier: { id: number; name: string; category: string } | null;
  items: PurchaseOrderItem[];
  attachments: PurchaseOrderAttachment[];
  created_at: string;
}

type AttachmentType = 'receipt' | 'proof';

// Status ordering for sections
const STATUS_ORDER: string[] = ['ordered', 'received', 'draft', 'cancelled'];

const STATUS_LABEL: Record<string, string> = {
  ordered: 'Ordered — Awaiting Receipt',
  received: 'Received',
  draft: 'Draft',
  cancelled: 'Cancelled',
};

const STATUS_COLORS: Record<string, { badge: string; text: string }> = {
  ordered: { badge: 'bg-amber-100', text: 'text-amber-700' },
  received: { badge: 'bg-green-100', text: 'text-green-700' },
  draft: { badge: 'bg-gray-100', text: 'text-gray-500' },
  cancelled: { badge: 'bg-red-100', text: 'text-red-600' },
};

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

async function fetchPOs(): Promise<PurchaseOrder[]> {
  const res = await api.get<{ data: PurchaseOrder[] }>('/api/fss/purchase-orders');
  return res.data.data;
}

// ---------------------------------------------------------------------------
// Upload attachment modal
// ---------------------------------------------------------------------------

interface UploadModalProps {
  po: PurchaseOrder | null;
  visible: boolean;
  onClose: () => void;
}

function UploadAttachmentModal({ po, visible, onClose }: UploadModalProps) {
  const qc = useQueryClient();
  const [attachType, setAttachType] = useState<AttachmentType>('receipt');
  const [caption, setCaption] = useState('');
  const [error, setError] = useState<string | null>(null);

  const uploadMutation = useMutation({
    mutationFn: async (file: { uri: string; name: string; type: string }) => {
      const formData = new FormData();
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      formData.append('file', { uri: file.uri, name: file.name, type: file.type } as any);
      formData.append('type', attachType);
      if (caption.trim()) formData.append('caption', caption.trim());
      const res = await api.post(`/api/fss/purchase-orders/${po!.id}/attachments`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
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
      setError((err as any)?.response?.data?.message ?? 'Upload failed. Try again.');
    },
  });

  const pickWithPermission = useCallback(
    async (source: 'library' | 'camera') => {
      setError(null);
      let asset: ImagePicker.ImagePickerAsset | null = null;

      if (source === 'library') {
        const perm = await ImagePicker.requestMediaLibraryPermissionsAsync();
        if (!perm.granted) {
          setError('Photo library access denied. Enable it in Settings.');
          return;
        }
        const result = await ImagePicker.launchImageLibraryAsync({
          mediaTypes: 'images',
          quality: 0.85,
          allowsEditing: false,
        });
        if (result.canceled || !result.assets?.length) return;
        asset = result.assets[0];
      } else {
        const perm = await ImagePicker.requestCameraPermissionsAsync();
        if (!perm.granted) {
          setError('Camera access denied. Enable it in Settings.');
          return;
        }
        const result = await ImagePicker.launchCameraAsync({
          mediaTypes: 'images',
          quality: 0.85,
          allowsEditing: false,
        });
        if (result.canceled || !result.assets?.length) return;
        asset = result.assets[0];
      }

      const uri = asset.uri;
      const ext = uri.split('.').pop() ?? 'jpg';
      const mimeType = asset.mimeType ?? `image/${ext === 'png' ? 'png' : 'jpeg'}`;
      const fileName = `${attachType}_${Date.now()}.${ext}`;
      uploadMutation.mutate({ uri, name: fileName, type: mimeType });
    },
    [attachType, uploadMutation],
  );

  const handleClose = () => {
    if (uploadMutation.isPending) return;
    setCaption('');
    setError(null);
    onClose();
  };

  if (!po) return null;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={handleClose}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        className="flex-1"
      >
        <Pressable className="flex-1 bg-black/50 justify-end" onPress={handleClose}>
          <Pressable onPress={(e) => e.stopPropagation()}>
            <View className="bg-white rounded-t-2xl px-5 pt-5 pb-8">
              {/* Header */}
              <View className="flex-row items-start justify-between mb-4">
                <View className="flex-1 pr-4">
                  <Text className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">
                    Upload Attachment
                  </Text>
                  <Text className="text-base font-semibold text-gray-900" numberOfLines={1}>
                    {po.po_number}
                  </Text>
                </View>
                <TouchableOpacity
                  onPress={handleClose}
                  hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
                  disabled={uploadMutation.isPending}
                  accessibilityLabel="Close upload modal"
                >
                  <Text className="text-gray-400 text-lg">✕</Text>
                </TouchableOpacity>
              </View>

              {/* Type toggle */}
              <Text className="text-xs font-medium text-gray-500 mb-1.5">Attachment type</Text>
              <View className="flex-row bg-gray-100 rounded-xl p-1 mb-4">
                {(['receipt', 'proof'] as AttachmentType[]).map((t) => (
                  <TouchableOpacity
                    key={t}
                    className={`flex-1 items-center justify-center py-2.5 rounded-lg ${
                      attachType === t ? 'bg-white shadow-sm' : ''
                    }`}
                    onPress={() => setAttachType(t)}
                    accessibilityLabel={`Select ${t} type`}
                    accessibilityRole="button"
                  >
                    <Text
                      className={`text-sm font-semibold capitalize ${
                        attachType === t ? 'text-emerald-700' : 'text-gray-400'
                      }`}
                    >
                      {t}
                    </Text>
                  </TouchableOpacity>
                ))}
              </View>

              {/* Caption */}
              <Text className="text-xs font-medium text-gray-500 mb-1.5">
                Caption (optional)
              </Text>
              <TextInput
                value={caption}
                onChangeText={setCaption}
                placeholder="e.g. Official receipt from Supplier X"
                placeholderTextColor="#9ca3af"
                className="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 mb-4"
                returnKeyType="done"
                accessibilityLabel="Attachment caption"
              />

              {/* Error */}
              {error && (
                <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 mb-4">
                  <AlertCircle color="#ef4444" size={16} />
                  <Text className="text-sm text-red-700 flex-1">{error}</Text>
                </View>
              )}

              {uploadMutation.isPending ? (
                <View className="items-center py-4">
                  <ActivityIndicator color="#059669" />
                  <Text className="mt-2 text-sm text-gray-500">Uploading…</Text>
                </View>
              ) : (
                <View className="flex-row gap-3">
                  <TouchableOpacity
                    className="flex-1 flex-row items-center justify-center gap-2 border border-gray-200 py-3.5 rounded-xl"
                    onPress={() => pickWithPermission('library')}
                    accessibilityLabel="Choose from photo library"
                    accessibilityRole="button"
                    style={{ minHeight: 52 }}
                  >
                    <ImageIcon color="#6b7280" size={18} />
                    <Text className="text-sm font-medium text-gray-700">Library</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    className="flex-1 flex-row items-center justify-center gap-2 bg-emerald-600 py-3.5 rounded-xl"
                    onPress={() => pickWithPermission('camera')}
                    accessibilityLabel="Take a photo with camera"
                    accessibilityRole="button"
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

// ---------------------------------------------------------------------------
// PO detail / expanded view
// ---------------------------------------------------------------------------

interface PODetailProps {
  po: PurchaseOrder;
  onUpload: () => void;
}

function PODetail({ po, onUpload }: PODetailProps) {
  const qc = useQueryClient();

  const deleteAttachmentMutation = useMutation({
    mutationFn: async (attachmentId: number) => {
      await api.delete(`/api/fss/purchase-order-attachments/${attachmentId}`);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-purchase-orders'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
    },
    onError: () => {
      Alert.alert('Error', 'Could not delete attachment. Please try again.');
    },
  });

  const handleDeleteAttachment = (attachmentId: number) => {
    Alert.alert('Delete Attachment', 'Are you sure you want to remove this attachment?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => deleteAttachmentMutation.mutate(attachmentId),
      },
    ]);
  };

  return (
    <View className="bg-gray-50 border-t border-gray-100 px-4 py-3">
      {/* Items */}
      {po.items.length > 0 && (
        <View className="mb-3">
          <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">
            Items
          </Text>
          <View className="bg-white rounded-xl border border-gray-100 overflow-hidden">
            {po.items.map((item, idx) => (
              <View
                key={item.id}
                className={`flex-row items-center px-3 py-2.5 ${
                  idx < po.items.length - 1 ? 'border-b border-gray-100' : ''
                }`}
              >
                <View className="flex-1 pr-2">
                  <Text className="text-sm text-gray-800" numberOfLines={2}>
                    {item.description}
                  </Text>
                </View>
                <View className="items-end">
                  <Text className="text-xs font-medium text-gray-600 tabular-nums">
                    {item.qty} {item.unit}
                  </Text>
                  <Text className="text-xs text-gray-400 tabular-nums">
                    ₱{Number(item.total_value).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
                  </Text>
                </View>
              </View>
            ))}
          </View>
        </View>
      )}

      {/* Attachments */}
      <View className="mb-3">
        <View className="flex-row items-center justify-between mb-2">
          <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wide">
            Attachments ({po.attachments.length})
          </Text>
          <TouchableOpacity
            className="flex-row items-center gap-1 px-3 py-1.5 bg-emerald-600 rounded-lg"
            onPress={onUpload}
            accessibilityLabel="Upload receipt or proof"
            accessibilityRole="button"
            style={{ minHeight: 32 }}
          >
            <Paperclip color="#fff" size={13} />
            <Text className="text-xs font-semibold text-white">Upload</Text>
          </TouchableOpacity>
        </View>

        {po.attachments.length === 0 ? (
          <View className="bg-white rounded-xl border border-dashed border-gray-200 px-4 py-4 items-center">
            <Paperclip color="#d1d5db" size={22} />
            <Text className="mt-1.5 text-xs text-gray-400">No attachments yet. Upload a receipt or proof.</Text>
          </View>
        ) : (
          <View className="bg-white rounded-xl border border-gray-100 overflow-hidden">
            {po.attachments.map((att, idx) => {
              const colorMap = att.type === 'receipt'
                ? { badge: 'bg-emerald-100', text: 'text-emerald-700' }
                : { badge: 'bg-purple-100', text: 'text-purple-700' };
              return (
                <View
                  key={att.id}
                  className={`flex-row items-center px-3 py-3 ${
                    idx < po.attachments.length - 1 ? 'border-b border-gray-100' : ''
                  }`}
                >
                  <View className={`px-2 py-0.5 rounded-full ${colorMap.badge} mr-2`}>
                    <Text className={`text-xs font-medium capitalize ${colorMap.text}`}>
                      {att.type}
                    </Text>
                  </View>
                  <Text className="flex-1 text-sm text-gray-700" numberOfLines={1}>
                    {att.caption || att.path.split('/').pop()}
                  </Text>
                  <TouchableOpacity
                    onPress={() => handleDeleteAttachment(att.id)}
                    disabled={deleteAttachmentMutation.isPending}
                    hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
                    accessibilityLabel={`Delete attachment ${att.caption ?? att.type}`}
                    accessibilityRole="button"
                    style={{ minWidth: 36, minHeight: 36, alignItems: 'center', justifyContent: 'center' }}
                  >
                    {deleteAttachmentMutation.isPending ? (
                      <ActivityIndicator size="small" color="#ef4444" />
                    ) : (
                      <Trash2 color="#ef4444" size={16} />
                    )}
                  </TouchableOpacity>
                </View>
              );
            })}
          </View>
        )}
      </View>

      {/* Notes */}
      {po.notes && (
        <View className="bg-amber-50 border border-amber-100 rounded-xl px-3 py-2.5">
          <Text className="text-xs font-semibold text-amber-700 mb-0.5">Notes</Text>
          <Text className="text-sm text-amber-800">{po.notes}</Text>
        </View>
      )}
    </View>
  );
}

// ---------------------------------------------------------------------------
// PO row
// ---------------------------------------------------------------------------

interface PORowProps {
  po: PurchaseOrder;
  isExpanded: boolean;
  isLast: boolean;
  onToggle: () => void;
  onUpload: () => void;
}

function PORow({ po, isExpanded, isLast, onToggle, onUpload }: PORowProps) {
  const color = STATUS_COLORS[po.status] ?? { badge: 'bg-gray-100', text: 'text-gray-500' };

  return (
    <View className={isLast ? '' : 'border-b border-gray-100'}>
      <TouchableOpacity
        className="flex-row items-center px-4 py-3.5 bg-white active:bg-gray-50"
        onPress={onToggle}
        accessibilityLabel={`${po.po_number} — ${po.status}. Tap to ${isExpanded ? 'collapse' : 'expand'}`}
        accessibilityRole="button"
        style={{ minHeight: 60 }}
      >
        <View className="flex-1 pr-3">
          <Text className="text-sm font-semibold text-gray-900" numberOfLines={1}>
            {po.po_number}
          </Text>
          <View className="flex-row items-center gap-1.5 mt-0.5 flex-wrap">
            {po.supplier && (
              <Text className="text-xs text-gray-400" numberOfLines={1}>
                {po.supplier.name}
              </Text>
            )}
            {po.order_date && (
              <Text className="text-xs text-gray-300">·</Text>
            )}
            {po.order_date && (
              <Text className="text-xs text-gray-400 tabular-nums">{po.order_date}</Text>
            )}
          </View>
        </View>
        <View className="items-end gap-1.5">
          <Text className="text-sm font-bold text-gray-800 tabular-nums">
            ₱{Number(po.total_amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })}
          </Text>
          <View className={`px-2 py-0.5 rounded-full ${color.badge}`}>
            <Text className={`text-xs font-medium ${color.text}`}>{po.status}</Text>
          </View>
        </View>
        <View className="ml-3">
          {isExpanded ? (
            <ChevronUp color="#9ca3af" size={18} />
          ) : (
            <ChevronDown color="#9ca3af" size={18} />
          )}
        </View>
      </TouchableOpacity>

      {isExpanded && <PODetail po={po} onUpload={onUpload} />}
    </View>
  );
}

// ---------------------------------------------------------------------------
// Section
// ---------------------------------------------------------------------------

interface SectionProps {
  status: string;
  pos: PurchaseOrder[];
  expandedId: number | null;
  onToggle: (id: number) => void;
  onUpload: (po: PurchaseOrder) => void;
}

function POSection({ status, pos, expandedId, onToggle, onUpload }: SectionProps) {
  const label = STATUS_LABEL[status] ?? status;
  const color = STATUS_COLORS[status] ?? { badge: 'bg-gray-100', text: 'text-gray-500' };

  return (
    <View className="mx-4 mb-4">
      <View className="flex-row items-center gap-2 mb-2">
        <View className={`px-2.5 py-1 rounded-full ${color.badge}`}>
          <Text className={`text-xs font-semibold ${color.text}`}>{label}</Text>
        </View>
        <Text className="text-xs text-gray-400 tabular-nums">{pos.length}</Text>
      </View>
      <View className="bg-white rounded-xl border border-gray-100 overflow-hidden">
        {pos.map((po, idx) => (
          <PORow
            key={po.id}
            po={po}
            isExpanded={expandedId === po.id}
            isLast={idx === pos.length - 1}
            onToggle={() => onToggle(po.id)}
            onUpload={() => onUpload(po)}
          />
        ))}
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Main screen
// ---------------------------------------------------------------------------

export default function ProcurementScreen() {
  const insets = useSafeAreaInsets();
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [uploadPO, setUploadPO] = useState<PurchaseOrder | null>(null);
  const [uploadModalVisible, setUploadModalVisible] = useState(false);

  const { data, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey: ['fss-purchase-orders'],
    queryFn: fetchPOs,
    staleTime: 60_000,
  });

  const handleToggle = useCallback((id: number) => {
    setExpandedId((prev) => (prev === id ? null : id));
  }, []);

  const handleUpload = useCallback((po: PurchaseOrder) => {
    setUploadPO(po);
    setUploadModalVisible(true);
  }, []);

  const handleCloseUpload = useCallback(() => {
    setUploadModalVisible(false);
  }, []);

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
        <Text className="mt-3 text-gray-500 text-sm">Loading purchase orders…</Text>
      </View>
    );
  }

  if (isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <AlertCircle color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">
          Could not load purchase orders
        </Text>
        <Text className="mt-1 text-gray-500 text-sm text-center">
          Check your connection and try again.
        </Text>
        <TouchableOpacity
          className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg"
          onPress={() => refetch()}
          accessibilityLabel="Retry loading purchase orders"
          style={{ minHeight: 44 }}
        >
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  // Group by status
  const grouped = (data ?? []).reduce<Record<string, PurchaseOrder[]>>((acc, po) => {
    const s = po.status ?? 'draft';
    if (!acc[s]) acc[s] = [];
    acc[s].push(po);
    return acc;
  }, {});

  // Sort statuses by STATUS_ORDER, then any remainder
  const orderedStatuses = [
    ...STATUS_ORDER.filter((s) => grouped[s]?.length),
    ...Object.keys(grouped).filter((s) => !STATUS_ORDER.includes(s)),
  ];

  return (
    <>
      <ScrollView
        className="flex-1 bg-gray-50"
        contentContainerStyle={{ paddingTop: 16, paddingBottom: insets.bottom + 16 }}
        refreshControl={
          <RefreshControl refreshing={isFetching && !isLoading} onRefresh={() => refetch()} />
        }
      >
        {/* Header summary */}
        <View className="px-4 mb-4 flex-row items-center gap-2">
          <ShoppingBag color="#059669" size={20} />
          <Text className="text-base font-semibold text-gray-800">Purchase Orders</Text>
          <Text className="text-sm text-gray-400 tabular-nums">({data?.length ?? 0})</Text>
        </View>

        {data?.length === 0 ? (
          <View className="mx-4 bg-white rounded-xl border border-gray-100 px-4 py-10 items-center">
            <ShoppingBag color="#d1d5db" size={40} />
            <Text className="mt-4 text-gray-400 text-base font-medium">No purchase orders yet</Text>
            <Text className="mt-1 text-gray-400 text-sm text-center">
              Purchase orders created by RND will appear here.
            </Text>
          </View>
        ) : (
          orderedStatuses.map((status) => (
            <POSection
              key={status}
              status={status}
              pos={grouped[status]}
              expandedId={expandedId}
              onToggle={handleToggle}
              onUpload={handleUpload}
            />
          ))
        )}
      </ScrollView>

      <UploadAttachmentModal
        po={uploadPO}
        visible={uploadModalVisible}
        onClose={handleCloseUpload}
      />
    </>
  );
}
