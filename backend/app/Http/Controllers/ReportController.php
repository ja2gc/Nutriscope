<?php

namespace App\Http\Controllers;

use App\Actions\Reports\PrepareSavedReport;
use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Http\Requests\PaginatedRequest;
use App\Http\Requests\PrepareReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Report;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportAuditReference;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportService;
use App\Support\Search\FuzzyText;
use App\Support\Search\RankedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly AuditPolicy $auditPolicy,
        private readonly AuditLogger $auditLogger,
        private readonly AuditContextResolver $auditContextResolver,
        private readonly ReportArchiveStorage $archiveStorage,
        private readonly ReportAuditReference $auditReference,
    ) {}

    /** Reports that expose patient/clinical data — RND-only, never food-service. */
    private const CLINICAL_TYPES = [
        'adime_individual',
        'adime_aggregate',
        'ncp_census',
        'patient_menu_plan',
        'demographic_census',
        'ncp_summary',
    ];

    private const NCP_CONTEXT_TYPES = [
        'adime_individual',
        'ncp_summary',
    ];

    /**
     * Report types the Admin role may access.
     * SINGLE source of truth — also enforced in index() filtering.
     * Admin must NEVER reach ncp_summary or patient_menu_plan (PHI).
     */
    public const ADMIN_ALLOWED_TYPES = Report::ADMIN_ALLOWED_TYPES;

    public function index(PaginatedRequest $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', 'string', 'max:30'],
            'type' => ['nullable', 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $query = Report::query()->with('user:id,uuid,name,first_name,last_name');
        $role = Auth::user()?->role;

        // Report creator fields are attribution only. Active RNDs share the
        // complete report workspace; Admin and FSS retain their role limits.
        if ($role === 'Admin') {
            $query->whereIn('type', self::ADMIN_ALLOWED_TYPES);
        } elseif ($role !== 'RND') {
            $query->where('user_id', Auth::id());
        }

        // FSS may only see accomplishment_report rows (fss.md §8).
        if ($role === 'FSS') {
            $query->whereIn('type', self::FSS_ALLOWED_TYPES);
        }

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('created_at', $request->integer('year')));

        RankedSearch::apply($query, $request->string('search')->toString(), ['title', 'type']);

        return ReportResource::collection($query
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString());
    }

    /**
     * Browse: enumerate the renderable instances of a type for the requested
     * period/entity, from real records (Spec 4 §4.1). Only instances with data.
     */
    public function instances(PaginatedRequest $request, string $type, ReportBrowser $browser): JsonResponse
    {
        abort_unless($browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        $source = $browser->sourceFor($type);
        $filters = $request->only(['year', 'month']);
        $instances = $source->instances($filters);
        $instances = $this->filterReportInstances($instances, $request->string('search')->toString());
        $page = max(1, $request->integer('page', 1));
        $perPage = $request->perPage();
        $total = count($instances);

        return response()->json([
            'data' => [
                'axis' => $source->axis(),
                'instances' => array_slice($instances, ($page - 1) * $perPage, $perPage),
            ],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * On-demand render: stream a freshly rendered PDF from current frozen data,
     * WITHOUT persisting a Report row. 404 when the params hold no data (§6, #3).
     */
    public function render(Request $request, string $type, ReportService $reports, ReportBrowser $browser): JsonResponse
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        return response()->json(['message' => 'Prepare the saved report before previewing it.', 'code' => 'preparation_required'], 409);
    }

    public function export(Request $request, string $type, ReportService $reports, ReportBrowser $browser): JsonResponse
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        return response()->json(['message' => 'Prepare the saved report before downloading it.', 'code' => 'preparation_required'], 409);
    }

    public function prepare(PrepareReportRequest $request, string $type, ReportService $reports, ReportBrowser $browser, PrepareSavedReport $prepare): JsonResponse
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);
        $params = $this->renderParams($request, $type);
        $this->authorizeClinicalReportContext($type, $params);
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');
        $this->auditLogger->assertAvailable();
        $report = $prepare->execute($request->user(), $type, $params);
        $this->applyReportContext($report, $type, $params);
        $this->recordReportEvent(AuditAction::Created, $type, $params, $report, 200);

        return response()->json(['data' => new ReportResource($report)]);
    }

    /**
     * Archive: render once, store the PDF, and freeze a snapshot of the branding /
     * signatories / period used — the only path that persists a Report (§4.1).
     */
    public function archive(Request $request, string $type, ReportService $reports, ReportBrowser $browser, PrepareSavedReport $prepare): JsonResponse
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        $params = $this->renderParams($request, $type);
        $this->authorizeClinicalReportContext($type, $params);
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');

        $this->auditLogger->assertAvailable();
        $report = $prepare->execute($request->user(), $type, $params);
        $this->applyReportContext($report, $type, $params);
        $this->audited(function () use ($report, $type, $params): void {
            $report->update(['status' => 'archived']);
            $this->recordReportEvent(AuditAction::Archived, $type, $params, $report, 200);
        });

        return response()->json(['data' => new ReportResource($report->fresh()->load('user:id,uuid,name,first_name,last_name'))]);
    }

    /**
     * On-demand render/archive params: everything except framework noise, with the
     * "prepared by" forced to the authenticated user so the signatory is always the
     * real filer (never the template default, never a client-supplied value).
     */
    private function renderParams(Request $request, string $type): array
    {
        $params = $request->except(['year', 'month']);
        if (Auth::user()?->role === 'FSS' && $type === 'accomplishment_report') {
            $params['fss_user_id'] = Auth::id();
        }
        if ($name = Auth::user()?->display_name) {
            $params['prepared_by_name'] = $name;
        }

        return $params;
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorizeReportAccess($report);
        $this->guardClinical($report->type);
        $this->guardAdmin($report->type);
        $this->guardFss($report->type);

        $this->recordReportEvent(AuditAction::Viewed, $report->type, $report->parameters ?? [], $report, 200);

        return response()->json(['data' => new ReportResource($report->load('user:id,uuid,name,first_name,last_name'))]);
    }

    public function download(Report $report): StreamedResponse|JsonResponse
    {
        $this->authorizeReportAccess($report);
        $this->guardClinical($report->type);
        $this->guardAdmin($report->type);
        $this->guardFss($report->type);

        $diskName = 'report_cache';
        $path = $report->cache_path;
        if ((! $path || $report->cache_expires_at?->isPast() !== false || ! Storage::disk($diskName)->exists($path))
            && $report->file_path && Storage::disk('public')->exists($report->file_path)) {
            [$diskName, $path] = ['public', $report->file_path];
        }
        if (! $path || ! Storage::disk($diskName)->exists($path)) {
            return response()->json(['message' => 'Prepare the saved report again.', 'code' => 'preparation_required'], 409);
        }

        $name = str($report->title)->slug().'.pdf';
        $this->recordReportEvent(AuditAction::Downloaded, $report->type, $report->parameters ?? [], $report, 200);

        return Storage::disk($diskName)->download($path, $name, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * Stream an archived copy INLINE (for the in-app preview) — frozen stored
     * bytes, never re-rendered, same role access check as {@see download()}.
     */
    public function view(Report $report): StreamedResponse|JsonResponse
    {
        $this->authorizeReportAccess($report);
        $this->guardClinical($report->type);
        $this->guardAdmin($report->type);
        $this->guardFss($report->type);

        $diskName = 'report_cache';
        $path = $report->cache_path;
        if ((! $path || $report->cache_expires_at?->isPast() !== false || ! Storage::disk($diskName)->exists($path))
            && $report->file_path && Storage::disk('public')->exists($report->file_path)) {
            [$diskName, $path] = ['public', $report->file_path];
        }
        if (! $path || ! Storage::disk($diskName)->exists($path)) {
            return response()->json(['message' => 'Prepare the saved report again.', 'code' => 'preparation_required'], 409);
        }

        $this->recordReportEvent(AuditAction::Viewed, $report->type, $report->parameters ?? [], $report, 200);

        return Storage::disk($diskName)->response($path, str($report->title)->slug().'.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $this->authorizeReportAccess($report);
        $this->guardClinical($report->type);
        $this->guardAdmin($report->type);
        $this->guardFss($report->type);

        $this->audited(function () use ($report): void {
            $this->recordReportEvent(AuditAction::Archived, $report->type, $report->parameters ?? [], $report, 204);
            $report->update(['status' => 'archived']);
        });

        return response()->json(null, 204);
    }

    /** Clinical reports carry PHI — only RND may browse/render/file them.
     *  Exception: Admin may access clinical types that appear in ADMIN_ALLOWED_TYPES
     *  (e.g. demographic_census — aggregate-only, no patient identifiers).
     */
    private function guardClinical(string $type): void
    {
        if (! in_array($type, self::CLINICAL_TYPES, true)) {
            return; // not a clinical type — no restriction
        }

        $role = Auth::user()?->role;

        // RND always allowed.
        if ($role === 'RND') {
            return;
        }

        // Admin allowed only for explicitly whitelisted clinical types.
        if ($role === 'Admin' && in_array($type, self::ADMIN_ALLOWED_TYPES, true)) {
            return;
        }

        abort(403, 'This report contains patient data and is restricted to the RND role.');
    }

    private function recordReportEvent(
        AuditAction $action,
        string $type,
        array $parameters,
        ?Report $report = null,
        int $status = 200,
    ): void {
        $ncpRecord = $this->ncpContextForReport($type, $parameters, $report);
        if ($ncpRecord !== null) {
            $this->authorizeClinicalReportContext($type, $parameters, $report);
        }

        $this->auditLogger->record(
            $action,
            $ncpRecord === null ? AuditCategory::Operations : AuditCategory::Clinical,
            AuditDomain::Reports,
            subject: $report ?? $ncpRecord,
            context: $ncpRecord,
            outcome: $status >= 400 ? AuditOutcome::Failure : AuditOutcome::Success,
            details: $this->auditReference->details($type, $parameters, $report, $status),
        );
    }

    private function ncpContextForReport(string $type, array $parameters, ?Report $report = null): ?NcpRecord
    {
        if ($report?->audit_ncp_record_id) {
            $existing = NcpRecord::query()->find($report->audit_ncp_record_id);
            if ($existing !== null) {
                return $existing;
            }

            $snapshot = new NcpRecord([
                'patient_id' => $report->audit_patient_id,
                'rnd_user_id' => $report->audit_owner_id,
            ]);
            $snapshot->setAttribute($snapshot->getKeyName(), $report->audit_ncp_record_id);
            $snapshot->exists = true;

            return $snapshot;
        }

        if (in_array($type, self::NCP_CONTEXT_TYPES, true)) {
            $ncpRecord = NcpRecord::query()->find($parameters['ncp_record_id'] ?? null);
            abort_unless($ncpRecord !== null, 404);

            return $ncpRecord;
        }

        if ($type !== 'patient_menu_plan') {
            return null;
        }

        $mealPlan = MealPlan::query()->find($parameters['meal_plan_id'] ?? null);
        $context = $mealPlan === null ? null : $this->auditContextResolver->resolve($mealPlan);

        abort_unless($mealPlan !== null && $context instanceof NcpRecord, 403);
        abort_if(isset($parameters['ncp_record_id']) && (int) $parameters['ncp_record_id'] !== (int) $context->getKey(), 403);
        abort_if(isset($parameters['patient_id']) && (int) $parameters['patient_id'] !== (int) $mealPlan->patient_id, 403);

        return NcpRecord::query()->find($context->getKey());
    }

    private function authorizeClinicalReportContext(string $type, array $parameters, ?Report $report = null): void
    {
        $ncpRecord = $this->ncpContextForReport($type, $parameters, $report);
        if ($ncpRecord !== null) {
            abort_unless($this->auditPolicy->viewNcpTrail(request()->user(), $ncpRecord), 403);
        }
    }

    /**
     * FSS role may only access the accomplishment_report type (fss.md §8).
     * All other report types are out of scope for FSS; returns 403.
     * RND and other roles are not affected.
     */
    public const FSS_ALLOWED_TYPES = [
        'accomplishment_report',
    ];

    /**
     * Admin role may only access explicitly allowed report types (PHI protection).
     * Returns 403 for any type not in ADMIN_ALLOWED_TYPES when the caller is Admin.
     * RND/FSS are not affected.
     */
    private function guardAdmin(string $type): void
    {
        if (Auth::user()?->role === 'Admin' && ! in_array($type, self::ADMIN_ALLOWED_TYPES, true)) {
            abort(403, 'This report type is not available to the Admin role.');
        }
    }

    /**
     * FSS role may only access accomplishment_report (fss.md §8).
     * Returns 403 for any other type when the caller is FSS.
     * RND and Admin are not affected here (Admin has its own guard).
     */
    private function guardFss(string $type): void
    {
        if (Auth::user()?->role === 'FSS' && ! in_array($type, self::FSS_ALLOWED_TYPES, true)) {
            abort(403, 'This report type is not available to the FSS role.');
        }
    }

    /**
     * Snapshot the "prepared by" name so generators can fill it. Always the
     * authenticated filer — never a client-supplied value (compliance integrity).
     */
    private function createReport(string $type, string $title, array $params, string $status = 'pending'): Report
    {
        $ncpRecord = $this->ncpContextForReport($type, $params);
        if ($name = Auth::user()?->display_name) {
            $params['prepared_by_name'] = $name;
        }

        return Report::create([
            'user_id' => Auth::id(),
            'audit_patient_id' => $ncpRecord?->patient_id,
            'audit_ncp_record_id' => $ncpRecord?->id,
            'audit_owner_id' => $ncpRecord ? Auth::id() : null,
            'title' => $title,
            'type' => $type,
            'parameters' => $params,
            'status' => $status,
        ]);
    }

    private function authorizeReportAccess(Report $report): void
    {
        if (Auth::user()?->role === 'RND') {
            return;
        }

        if (Auth::user()?->role === 'Admin' && in_array($report->type, self::ADMIN_ALLOWED_TYPES, true)) {
            return;
        }

        abort_unless($report->user_id === Auth::id(), 403, 'This action is unauthorized.');
    }

    private function applyReportContext(Report $report, string $type, array $parameters): void
    {
        $ncpRecord = $this->ncpContextForReport($type, $parameters, $report);
        if ($ncpRecord !== null && $report->audit_ncp_record_id === null) {
            $report->update([
                'audit_patient_id' => $ncpRecord->patient_id,
                'audit_ncp_record_id' => $ncpRecord->id,
                'audit_owner_id' => Auth::id(),
            ]);
        }
    }

    /** @param list<array<string, mixed>> $instances @return list<array<string, mixed>> */
    private function filterReportInstances(array $instances, string $search): array
    {
        $search = FuzzyText::normalize($search);
        if ($search === '') {
            return $instances;
        }

        $normal = array_values(array_filter(
            $instances,
            fn (array $instance): bool => str_contains(
                FuzzyText::normalize((string) ($instance['label'] ?? '')),
                $search,
            ),
        ));
        if ($normal !== []) {
            return $normal;
        }

        return array_values(array_filter(
            $instances,
            fn (array $instance): bool => FuzzyText::score($search, (string) ($instance['label'] ?? '')) !== null,
        ));
    }
}
