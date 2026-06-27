<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Jobs\GenerateReport;
use App\Models\Report;
use App\Models\ReportBranding;
use App\Models\ReportTemplate;
use App\Services\Reports\ReportBrowser;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** The Food-Service report set produced by Generate-All. */
    private const FOOD_SERVICE_SET = [
        'program_project_activity',
        'dietary_cash_book',
        'procurement_pack',
        'budget_report',
        'inventory_report',
    ];

    /** Reports that expose patient/clinical data — RND-only, never food-service. */
    private const CLINICAL_TYPES = [
        'patient_menu_plan',
        'demographic_census',
        'ncp_summary',
    ];

    /**
     * Report types the Admin role may access.
     * SINGLE source of truth — also enforced in index() filtering.
     * Admin must NEVER reach ncp_summary or patient_menu_plan (PHI).
     */
    public const ADMIN_ALLOWED_TYPES = [
        'demographic_census',
        'budget_report',
        'procurement_pack',
    ];

    public function index(): JsonResponse
    {
        $query = Report::latest();

        // RND supervises FSS: in addition to their own rows, RND sees every
        // accomplishment_report filed by FSS staff (RND is the report's "Noted by").
        if (Auth::user()?->role === 'RND') {
            $query->where(fn ($q) => $q->where('user_id', Auth::id())
                ->orWhere('type', 'accomplishment_report'));
        } else {
            $query->where('user_id', Auth::id());
        }

        // Admin may only see their own allowed-type rows (PHI guard).
        if (Auth::user()?->role === 'Admin') {
            $query->whereIn('type', self::ADMIN_ALLOWED_TYPES);
        }

        // FSS may only see accomplishment_report rows (fss.md §8).
        if (Auth::user()?->role === 'FSS') {
            $query->whereIn('type', self::FSS_ALLOWED_TYPES);
        }

        return response()->json(['data' => ReportResource::collection($query->get())]);
    }

    /**
     * @deprecated Spec 4 — reports are now browsed/rendered on demand and only the
     * deliberate {@see archive()} action persists. Kept working for one release.
     */
    public function store(StoreReportRequest $request, ReportService $reports): JsonResponse
    {
        $template = ReportTemplate::where('type', $request->template_code)->firstOrFail();
        $this->guardClinical($template->type);
        $this->guardAdmin($template->type);
        $this->guardFss($template->type);
        $report   = $this->createReport($template->type, $template->name, $request->parameters ?? []);

        $this->run($report, $reports);

        return response()->json(['data' => new ReportResource($report->fresh())], 201);
    }

    /**
     * Generate-All: produce the full Food-Service set for a chosen period in one go.
     *
     * @deprecated Spec 4 — superseded by browse + on-demand render. Kept for one release.
     */
    public function generateAll(Request $request, ReportService $reports): JsonResponse
    {
        $params = $request->validate([
            'parameters'              => ['nullable', 'array'],
            'parameters.start'        => ['nullable', 'date'],
            'parameters.end'          => ['nullable', 'date'],
            'parameters.menu_cycle_id'=> ['nullable', 'integer'],
            'parameters.budget_id'    => ['nullable', 'integer'],
            'parameters.shopping_list_id' => ['nullable', 'integer'],
        ])['parameters'] ?? [];

        $created = [];
        foreach (self::FOOD_SERVICE_SET as $type) {
            if (! $reports->supports($type)) {
                continue;
            }
            $template = ReportTemplate::where('type', $type)->first();
            $report   = $this->createReport($type, $template?->name ?? $type, $params);
            $this->run($report, $reports);
            $created[] = $report->fresh();
        }

        return response()->json(['data' => ReportResource::collection(collect($created))], 201);
    }

    /**
     * Browse: enumerate the renderable instances of a type for the requested
     * period/entity, from real records (Spec 4 §4.1). Only instances with data.
     */
    public function instances(Request $request, string $type, ReportBrowser $browser): JsonResponse
    {
        abort_unless($browser->supports($type), 404, 'Unknown report type.');
        $this->guardClinical($type);
        $this->guardAdmin($type);
        $this->guardFss($type);

        $source  = $browser->sourceFor($type);
        $filters = $request->only(['year', 'month']);

        return response()->json([
            'data' => [
                'axis'      => $source->axis(),
                'instances' => $source->instances($filters),
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
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');

        $bytes = $reports->streamBytes($type, $params);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $type . '.pdf"',
        ]);
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
        abort_unless($browser->sourceFor($type)->hasData($params), 404, 'No data for this report period.');

        $template = ReportTemplate::where('type', $type)->first();
        $report   = $this->createReport($type, $template?->name ?? $type, $params, 'archived');

        $path = $reports->generate($report);

        $report->update([
            'file_path'    => $path,
            'generated_at' => now(),
            'snapshot'     => [
                'branding'     => ReportBranding::singleton()->only([
                    'hospital_name', 'address', 'accreditation', 'service_name',
                    'province', 'lgu', 'logo_left_path', 'logo_right_path',
                ]),
                'signatories'  => $reports->signatoriesFor($report),
                'params'       => $report->parameters,
                'archived_at'  => now()->toIso8601String(),
            ],
        ]);

        return response()->json(['data' => new ReportResource($report->fresh())], 201);
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
        if ($name = Auth::user()?->name) {
            $params['prepared_by_name'] = $name;
        }

        return $params;
    }

    public function show(Report $report): JsonResponse
    {
        $this->authorizeOwner($report);
        $this->guardAdmin($report->type);
        return response()->json(['data' => new ReportResource($report)]);
    }

    public function download(Report $report): StreamedResponse|JsonResponse
    {
        $this->authorizeOwner($report);
        $this->guardAdmin($report->type);

        if (! $report->file_path || ! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not available.'], 404);
        }

        $name = str($report->title)->slug() . '.pdf';
        return Storage::disk('public')->download($report->file_path, $name);
    }

    /**
     * Stream an archived copy INLINE (for the in-app preview) — frozen stored
     * bytes, never re-rendered, same owner check as {@see download()}.
     */
    public function view(Report $report): StreamedResponse|JsonResponse
    {
        $this->authorizeOwner($report);
        $this->guardAdmin($report->type);

        if (! $report->file_path || ! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not available.'], 404);
        }

        return Storage::disk('public')->response($report->file_path, str($report->title)->slug() . '.pdf', [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroy(Report $report): JsonResponse
    {
        $this->authorizeOwner($report);

        if ($report->file_path) {
            Storage::disk('public')->delete($report->file_path);
        }
        $report->delete();

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
        if ($name = Auth::user()?->name) {
            $params['prepared_by_name'] = $name;
        }

        return Report::create([
            'user_id'    => Auth::id(),
            'title'      => $title,
            'type'       => $type,
            'parameters' => $params,
            'status'     => $status,
        ]);
    }

    /**
     * Generate synchronously so the PDF exists the moment the request returns
     * (the queue is redis; a report is cheap enough to render inline).
     */
    private function run(Report $report, ReportService $reports): void
    {
        if ($reports->supports($report->type)) {
            GenerateReport::dispatchSync($report);
        } else {
            GenerateReport::dispatch($report);
        }
    }

    private function authorizeOwner(Report $report): void
    {
        if (Auth::user()?->role === 'RND' && $report->type === 'accomplishment_report') {
            return;
        }

        abort_unless($report->user_id === Auth::id(), 403, 'This action is unauthorized.');
    }
}
