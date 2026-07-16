<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\AuditCategory;
use App\Enums\AuditDomain;
use App\Enums\AuditOutcome;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\ReportResource;
use App\Models\MealPlan;
use App\Models\NcpRecord;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Policies\AuditPolicy;
use App\Services\Audit\AuditContextResolver;
use App\Services\Audit\AuditLogger;
use App\Services\Reports\ReportArchiveStorage;
use App\Services\Reports\ReportAuditReference;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
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
        $query = Report::query()->with('user:id,uuid,name,first_name,last_name')->latest();
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
    public function render(Request $request, string $type, ReportService $reports, ReportBrowser $browser): Response
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        $params = $this->renderParams($request, $type);
        $this->authorizeClinicalReportContext($type, $params);
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');

        $bytes = $reports->streamBytes($type, $params);
        $this->recordReportEvent(AuditAction::Viewed, $type, $params, status: 200);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$type.'.pdf"',
        ]);
    }

    public function export(Request $request, string $type, ReportService $reports, ReportBrowser $browser): Response
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);
        $params = $this->renderParams($request, $type);
        $this->authorizeClinicalReportContext($type, $params);
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');
        $bytes = $reports->streamBytes($type, $params);
        $this->recordReportEvent(AuditAction::Downloaded, $type, $params, status: 200);

        return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$type.'.pdf"']);
    }

    /**
     * Archive: render once, store the PDF, and freeze a snapshot of the branding /
     * signatories / period used — the only path that persists a Report (§4.1).
     */
    public function archive(Request $request, string $type, ReportService $reports, ReportBrowser $browser): JsonResponse
    {
        abort_unless($reports->supports($type) && $browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        $params = $this->renderParams($request, $type);
        $this->authorizeClinicalReportContext($type, $params);
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');

        $template = ReportTemplate::where('type', $type)->first();
        $path = null;
        try {
            $report = $this->audited(function () use ($type, $template, $params, $reports, &$path): Report {
                $report = $this->createReport($type, $template?->name ?? $type, $params);
                $path = $reports->generate($report);
                $report->update([
                    'status' => 'archived',
                    'file_path' => $path,
                    'generated_at' => now(),
                    'snapshot' => [
                        'branding' => ReportBranding::singleton()->only([
                            'hospital_name', 'address', 'accreditation', 'service_name',
                            'province', 'lgu', 'logo_left_path', 'logo_right_path',
                        ]),
                        'signatories' => $reports->signatoriesFor($report),
                        'params' => $report->parameters,
                        'archived_at' => now()->toIso8601String(),
                    ],
                ]);
                $this->recordReportEvent(AuditAction::Archived, $type, $params, $report, 201);

                return $report;
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                $this->archiveStorage->cleanupGenerated($path);
            }

            throw $exception;
        }

        return response()->json(['data' => new ReportResource($report->fresh()->load('user:id,uuid,name,first_name,last_name'))], 201);
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

        if (! $report->file_path || ! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not available.'], 404);
        }

        $name = str($report->title)->slug().'.pdf';
        $this->recordReportEvent(AuditAction::Downloaded, $report->type, $report->parameters ?? [], $report, 200);

        return Storage::disk('public')->download($report->file_path, $name);
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

        if (! $report->file_path || ! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not available.'], 404);
        }

        $this->recordReportEvent(AuditAction::Viewed, $report->type, $report->parameters ?? [], $report, 200);

        return Storage::disk('public')->response($report->file_path, str($report->title)->slug().'.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $this->authorizeReportAccess($report);
        $this->guardClinical($report->type);
        $this->guardAdmin($report->type);
        $this->guardFss($report->type);

        $move = $this->archiveStorage->quarantine($report->file_path);
        try {
            $this->audited(function () use ($report, $move): void {
                $this->recordReportEvent(AuditAction::Deleted, $report->type, $report->parameters ?? [], $report, 204);
                $report->delete();
                if ($move !== null) {
                    $this->archiveStorage->deleteAfterCommit($move);
                }
            });
        } catch (\Throwable $exception) {
            if ($move !== null) {
                $this->archiveStorage->restoreDurably($move);
            }
            throw $exception;
        }

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
}
