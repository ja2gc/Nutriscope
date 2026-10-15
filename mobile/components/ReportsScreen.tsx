import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, Download, Eye, FileText } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AccomplishmentSnapshot, AccomplishmentPeriod, Report, changeAccomplishmentPeriod, downloadReportPdf, getAccomplishmentPeriod, getReport, listAccomplishmentPeriods, listReports, viewReportPdf } from '../lib/reports';
import { MobilePagination } from './MobilePagination';
import { SearchInput } from './SearchInput';
import { getNextPageParam, paginateItems } from '../lib/pagination';
import { useMobileServerPage } from '../lib/useMobileServerPage';

function fmtDate(d: string): string {
  return new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

// ── Native render of the archived accomplishment grid (from frozen snapshot) ──────
function ReportGrid({ snap }: { snap: AccomplishmentSnapshot }) {
  const taskKeys = Object.keys(snap.tasks);

  return (
    <View>
      {snap.staff_sheets.map((sheet, si) => (
        <View key={si} className="mb-6 bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <View className="px-4 py-3 bg-emerald-50 border-b border-emerald-100">
            <Text className="text-sm font-bold text-emerald-800">{sheet.user.name ?? 'Staff'}</Text>
            <Text className="text-[11px] text-emerald-600">{sheet.user.role ?? 'Food Service Staff'}</Text>
          </View>

          {/* Horizontal scroll: fixed task column + day columns */}
          <ScrollView horizontal showsHorizontalScrollIndicator contentContainerStyle={{ minWidth: '100%' }}>
            <View>
              {/* Header row */}
              <View className="flex-row border-b border-gray-200 bg-gray-50">
                <View className="w-44 px-3 py-2 border-r border-gray-200">
                  <Text className="text-[10px] font-bold uppercase tracking-wider text-gray-500">Task</Text>
                </View>
                {snap.days.map((d) => (
                  <View key={d} className="w-12 px-1 py-2 items-center border-r border-gray-100">
                    <Text className="text-[11px] font-bold text-gray-700 tabular-nums">{new Date(d + 'T00:00:00').getDate()}</Text>
                    <Text className="text-[8px] text-gray-400">{new Date(d + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'short' }).slice(0, 2)}</Text>
                  </View>
                ))}
              </View>

              {/* Task rows */}
              {taskKeys.map((tk, ti) => (
                <View key={tk} className={`flex-row ${ti < taskKeys.length - 1 ? 'border-b border-gray-100' : ''}`}>
                  <View className="w-44 px-3 py-2 border-r border-gray-200 justify-center">
                    <Text className="text-[11px] text-gray-700 leading-4" numberOfLines={2}>{ti + 1}. {snap.tasks[tk]}</Text>
                  </View>
                  {snap.days.map((d) => {
                    const cell = sheet.task_rows[tk]?.[d] ?? '';
                    const isX = cell === 'X';
                    const isTick = cell === '✓';
                    return (
                      <View key={d} className="w-12 px-1 py-2 items-center justify-center border-r border-gray-100">
                        <Text className={`text-xs tabular-nums ${isX ? 'text-red-500 font-bold' : isTick ? 'text-emerald-600 font-bold' : 'text-gray-700'}`}>
                          {String(cell)}
                        </Text>
                      </View>
                    );
                  })}
                </View>
              ))}
            </View>
          </ScrollView>
        </View>
      ))}
    </View>
  );
}

function ReportDetail({ id, onBack }: { id: string; onBack: () => void }) {
  const insets = useSafeAreaInsets();
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['fss-report', id],
    queryFn: () => getReport(id),
  });

  const snap = data?.snapshot?.accomplishment;
  const start = String(data?.parameters?.start ?? data?.parameters?.from ?? '');
  const end = String(data?.parameters?.end ?? data?.parameters?.to ?? '');
  const period = snap?.period_label ?? (start && end ? `${fmtDate(start)} – ${fmtDate(end)}` : 'Saved accomplishment report');
  const pdfAction = useMutation({
    mutationFn: (action: 'view' | 'download') => action === 'view' ? viewReportPdf(data!) : downloadReportPdf(data!),
    onError: (error: Error, action) => Alert.alert('Could not process PDF', error.message, [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Retry', onPress: () => pdfAction.mutate(action) },
    ]),
  });

  return (
    <View className="flex-1 bg-gray-50">
      <View className="flex-row items-center gap-2 px-4 py-3 bg-white border-b border-gray-100">
        <TouchableOpacity onPress={onBack} hitSlop={8}><ChevronLeft color="#374151" size={22} /></TouchableOpacity>
        <Text className="text-sm font-bold text-gray-900 flex-1" numberOfLines={1}>{data?.title ?? 'Report'}</Text>
      </View>

      {isLoading ? (
        <View className="flex-1 items-center justify-center"><ActivityIndicator color="#059669" /></View>
      ) : isError || !data ? (
        <View className="flex-1 items-center justify-center px-6">
          <FileText color="#ef4444" size={40} />
          <Text className="mt-3 text-gray-700 font-medium">Could not load report</Text>
          <TouchableOpacity className="mt-4 bg-emerald-600 px-5 py-2.5 rounded-lg" onPress={() => refetch()}>
            <Text className="text-white font-semibold text-sm">Retry</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 24 }}>
          <View className="mb-4">
            <Text className="text-[10px] font-bold uppercase tracking-widest text-gray-400">Accomplishment Report</Text>
            <Text className="text-base font-bold text-gray-900 mt-0.5">{period}</Text>
            <View className="mt-3 flex-row gap-2">
              <TouchableOpacity onPress={() => pdfAction.mutate('view')} disabled={pdfAction.isPending} className="min-h-12 flex-1 flex-row items-center justify-center gap-2 rounded-xl bg-[#087F5B]" accessibilityLabel="View PDF">
                {pdfAction.isPending && pdfAction.variables === 'view' ? <ActivityIndicator color="#fff" /> : <Eye color="#fff" size={18} />}
                <Text className="font-bold text-white">{pdfAction.isPending && pdfAction.variables === 'view' ? 'Opening…' : 'View PDF'}</Text>
              </TouchableOpacity>
              <TouchableOpacity onPress={() => pdfAction.mutate('download')} disabled={pdfAction.isPending} className="min-h-12 flex-1 flex-row items-center justify-center gap-2 rounded-xl border border-[#087F5B] bg-white" accessibilityLabel="Download PDF">
                {pdfAction.isPending && pdfAction.variables === 'download' ? <ActivityIndicator color="#087F5B" /> : <Download color="#087F5B" size={18} />}<Text className="font-bold text-[#087F5B]">{pdfAction.isPending && pdfAction.variables === 'download' ? 'Saving…' : 'Download PDF'}</Text>
              </TouchableOpacity>
            </View>
          </View>
          {snap ? <ReportGrid snap={snap} /> : <View className="rounded-2xl border border-gray-200 bg-white p-4"><Text className="text-sm leading-5 text-gray-600">Open the prepared PDF to view the complete signed report.</Text></View>}
        </ScrollView>
      )}
    </View>
  );
}

function PeriodDetail({ start, onBack, onEditDay, onOpenReport }: { start: string; onBack: () => void; onEditDay: (date: string) => void; onOpenReport: (id: string) => void }) {
  const queryClient = useQueryClient();
  const [dayPage, setDayPage] = useState(1);
  const { data: period, isLoading, isError, refetch } = useQuery({ queryKey: ['fss-accomplishment-period', start], queryFn: () => getAccomplishmentPeriod(start) });
  const days = paginateItems(period?.days ?? [], dayPage);
  const action = useMutation({
    mutationFn: (kind: 'submit' | 'withdraw') => changeAccomplishmentPeriod(start, kind),
    onSuccess: async () => {
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['fss-accomplishment-period', start] }),
        queryClient.invalidateQueries({ queryKey: ['fss-accomplishment-periods'] }),
        queryClient.invalidateQueries({ queryKey: ['fss-reports'] }),
      ]);
    },
    onError: (error: any) => Alert.alert('Could not update period', error?.response?.data?.message ?? 'Refresh and try again.'),
  });

  return <ScrollView className="flex-1 bg-gray-50" contentContainerStyle={{ padding: 16, paddingBottom: 96 }}>
    <TouchableOpacity onPress={onBack} className="min-h-12 flex-row items-center gap-2" accessibilityRole="button" accessibilityLabel="Back to periods"><ChevronLeft color="#087F5B" size={20} /><Text className="font-bold text-[#087F5B]">Periods</Text></TouchableOpacity>
    {isLoading ? <ActivityIndicator color="#087F5B" /> : isError || !period ? <TouchableOpacity onPress={() => refetch()} className="min-h-12 rounded-xl bg-white p-4"><Text>Could not load period. Tap to retry.</Text></TouchableOpacity> : <>
      <View className="rounded-2xl border border-gray-200 bg-white p-4"><Text className="text-lg font-extrabold text-[#16352B]">{fmtDate(period.start)} – {fmtDate(period.end)}</Text><Text className="mt-1 text-sm text-gray-600">{period.saved_days}/{period.expected_days} days logged · {period.status === 'draft' ? 'In progress' : period.status === 'pending' ? 'Submitted for review' : period.status === 'rejected' ? 'Needs correction' : 'Approved'}</Text></View>
      {period.rejection_reason && <View className="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4"><Text className="font-bold text-amber-900">Reason for correction</Text><Text className="mt-1 text-sm text-amber-900">{period.rejection_reason}</Text></View>}
      <View className="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-white">{days.items.map((day) => <TouchableOpacity key={day.date} onPress={() => onEditDay(day.date)} className="min-h-14 flex-row items-center justify-between gap-3 border-b border-gray-100 px-4 py-2" accessibilityRole="button" accessibilityLabel={`${fmtDate(day.date)}, ${day.logged ? day.off_duty ? 'off duty log saved' : 'log saved' : 'no log'}, open daily log`}><Text className="font-semibold text-gray-800">{fmtDate(day.date)}</Text><Text className={`text-sm ${day.logged ? 'text-emerald-700' : 'text-amber-700'}`}>{day.logged ? day.off_duty ? 'Off duty' : 'Saved' : 'Missing'}</Text></TouchableOpacity>)}</View>
      <MobilePagination page={days.page} pages={days.pages} total={days.total} onPrevious={() => setDayPage(days.page - 1)} onNext={() => setDayPage(days.page + 1)} />
      {(period.status === 'draft' || period.status === 'rejected') && <TouchableOpacity onPress={() => action.mutate('submit')} disabled={action.isPending || period.saved_days !== period.expected_days} className={`mt-4 min-h-12 items-center justify-center rounded-xl ${action.isPending || period.saved_days !== period.expected_days ? 'bg-gray-300' : 'bg-[#087F5B]'}`} accessibilityRole="button"><Text className="font-bold text-white">Submit for review</Text></TouchableOpacity>}
      {(period.status === 'draft' || period.status === 'rejected') && period.saved_days !== period.expected_days && <Text className="mt-2 text-sm text-gray-600">Save a log for every day before submitting.</Text>}
      {period.status === 'pending' && <TouchableOpacity onPress={() => action.mutate('withdraw')} disabled={action.isPending} className="mt-4 min-h-12 items-center justify-center rounded-xl border border-amber-700 bg-white" accessibilityRole="button"><Text className="font-bold text-amber-800">Withdraw submission</Text></TouchableOpacity>}
      {(period.status === 'pending' || period.status === 'approved') && period.report_id && <TouchableOpacity onPress={() => onOpenReport(period.report_id!)} className="mt-3 min-h-12 items-center justify-center rounded-xl border border-emerald-700 bg-white" accessibilityRole="button"><Text className="font-bold text-emerald-700">View submitted report</Text></TouchableOpacity>}
      {action.isPending && <ActivityIndicator className="mt-3" color="#087F5B" />}
    </>}
  </ScrollView>;
}

export default function ReportsScreen({ embedded = false, onEditDay }: { embedded?: boolean; onEditDay?: (date: string) => void }) {
  const insets = useSafeAreaInsets();
  const [periodPage, setPeriodPage] = useState(1);
  const [openId, setOpenId] = useState<string | null>(null);
  const [openPeriod, setOpenPeriod] = useState<string | null>(null);
  const periodsQuery = useQuery({ queryKey: ['fss-accomplishment-periods'], queryFn: listAccomplishmentPeriods });
  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedSearch(search.trim()), 250);
    return () => clearTimeout(timer);
  }, [search]);
  const { data: pages, isLoading, isFetching, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['fss-reports', debouncedSearch],
    queryFn: ({ pageParam }) => listReports(pageParam, debouncedSearch),
    initialPageParam: 1,
    getNextPageParam,
  });
  const periodPager = paginateItems(periodsQuery.data ?? [], periodPage);
  const pdfPager = useMobileServerPage(pages, fetchNextPage);
  const data = pdfPager.items;

  if (openId != null) return <ReportDetail id={openId} onBack={() => setOpenId(null)} />;
  if (openPeriod != null && onEditDay) return <PeriodDetail start={openPeriod} onBack={() => setOpenPeriod(null)} onEditDay={onEditDay} onOpenReport={setOpenId} />;

  return (
    <FlatList
      className="bg-gray-50"
      data={data}
      keyExtractor={(r: Report) => String(r.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + (embedded ? 80 : 16), flexGrow: 1 }}
      ListHeaderComponent={<View className="mb-4">
        <Text className="mb-3 text-base font-extrabold text-[#16352B]">Semi-monthly periods</Text>
        {periodsQuery.isLoading ? <ActivityIndicator color="#087F5B" /> : periodsQuery.isError ? <TouchableOpacity onPress={() => periodsQuery.refetch()} className="min-h-12 justify-center rounded-xl bg-white px-4"><Text className="text-red-700">Could not load periods. Tap to retry.</Text></TouchableOpacity> : periodPager.total ? periodPager.items.map((period: AccomplishmentPeriod) => <TouchableOpacity key={period.start} onPress={() => setOpenPeriod(period.start)} className="mb-2 min-h-16 flex-row items-center justify-between gap-2 rounded-xl border border-gray-200 bg-white p-4" accessibilityRole="button" accessibilityLabel={`${fmtDate(period.start)} to ${fmtDate(period.end)}, ${period.saved_days} of ${period.expected_days} days logged, ${period.status}`}><View className="flex-1"><Text className="font-bold text-gray-900">{fmtDate(period.start)} – {fmtDate(period.end)}</Text><Text className="mt-1 text-sm text-gray-600">{period.saved_days}/{period.expected_days} days logged · {period.status}</Text></View><ChevronRight color="#087F5B" size={20} /></TouchableOpacity>) : <Text className="rounded-xl bg-white p-4 text-sm text-gray-600">No periods yet.</Text>}
        <MobilePagination page={periodPager.page} pages={periodPager.pages} total={periodPager.total} onPrevious={() => setPeriodPage(periodPager.page - 1)} onNext={() => setPeriodPage(periodPager.page + 1)} />
        <Text className="mb-3 mt-6 text-base font-extrabold text-[#16352B]">Saved PDFs</Text>
        <SearchInput label="Search saved reports" value={search} onChangeText={(value) => { setSearch(value); pdfPager.setPage(1); }} loading={isFetching && search.trim() !== debouncedSearch} />
        {isLoading && <ActivityIndicator className="mt-3" color="#087F5B" />}
        {isError && <TouchableOpacity onPress={() => refetch()} className="mt-3 min-h-12 justify-center rounded-xl border border-red-200 bg-white px-4"><Text className="text-red-700">Could not load saved PDFs. Tap to retry.</Text></TouchableOpacity>}
      </View>}
      renderItem={({ item }) => {
        const period = item.snapshot?.accomplishment?.period_label
          ?? (item.parameters?.start && item.parameters?.end
            ? `${fmtDate(String(item.parameters.start))} – ${fmtDate(String(item.parameters.end))}`
            : '');
        return (
          <TouchableOpacity
            onPress={() => setOpenId(item.id)}
            className="bg-white rounded-2xl border border-gray-200 p-4 mb-3 flex-row items-center"
          >
            <View className="h-10 w-10 rounded-xl bg-emerald-50 items-center justify-center mr-3">
              <FileText color="#059669" size={20} />
            </View>
            <View className="flex-1">
              <Text className="text-sm font-bold text-gray-900" numberOfLines={1}>{item.title}</Text>
              {period ? <Text className="text-[11px] text-gray-400 mt-0.5">{period}</Text> : null}
            </View>
            <View className="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 mr-2">
              <Text className="text-[9px] font-extrabold uppercase tracking-wider text-emerald-700">{item.review_state === 'approved' ? 'Approved' : 'Earlier report'}</Text>
            </View>
            <ChevronRight color="#9ca3af" size={18} />
          </TouchableOpacity>
        );
      }}
      ListFooterComponent={<MobilePagination page={pdfPager.page} pages={pdfPager.pages} total={pdfPager.total} onPrevious={pdfPager.previous} onNext={() => void pdfPager.next()} loading={isFetchingNextPage} error={isFetchNextPageError} />}
      ListEmptyComponent={!isLoading && !isError ?
        <View className="items-center justify-center py-20">
          <FileText color="#d1d5db" size={40} />
          <Text className="mt-4 text-gray-400 text-sm text-center">{debouncedSearch ? 'No matching reports.' : 'No saved PDFs yet.'}</Text>
          <Text className="mt-1 text-gray-400 text-xs text-center px-8">
            Submitted and earlier reports appear here.
          </Text>
        </View>
      : null}
    />
  );
}
