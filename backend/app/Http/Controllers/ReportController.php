<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Jobs\GenerateReport;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(): JsonResponse
    {
        $reports = Report::where('user_id', Auth::id())->latest()->get();
        return response()->json(['data' => ReportResource::collection($reports)]);
    }

    public function store(StoreReportRequest $request, ReportService $reports): JsonResponse
    {
        $template = ReportTemplate::where('type', $request->template_code)->firstOrFail();
        $report   = $this->createReport($template->type, $template->name, $request->parameters ?? []);

        $this->run($report, $reports);

        return response()->json(['data' => new ReportResource($report->fresh())], 201);
    }

    /**
     * Generate-All: produce the full Food-Service set for a chosen period in one go.
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

    public function show(Report $report): JsonResponse
    {
        $this->authorizeOwner($report);
        return response()->json(['data' => new ReportResource($report)]);
    }

    public function download(Report $report): StreamedResponse|JsonResponse
    {
        $this->authorizeOwner($report);

        if (! $report->file_path || ! Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'Report file not available.'], 404);
        }

        $name = str($report->title)->slug() . '.pdf';
        return Storage::disk('public')->download($report->file_path, $name);
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

    /** Snapshot the requesting user's name so generators can fill "prepared by". */
    private function createReport(string $type, string $title, array $params): Report
    {
        $params['prepared_by_name'] ??= Auth::user()?->name;

        return Report::create([
            'user_id'    => Auth::id(),
            'title'      => $title,
            'type'       => $type,
            'parameters' => $params,
            'status'     => 'pending',
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
        abort_unless($report->user_id === Auth::id(), 403, 'This action is unauthorized.');
    }
}
