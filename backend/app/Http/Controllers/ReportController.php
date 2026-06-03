<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Jobs\GenerateReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(): JsonResponse
    {
        $reports = Report::where('user_id', Auth::id())->get();
        return response()->json(['data' => ReportResource::collection($reports)]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $template = ReportTemplate::where('type', $request->template_code)->firstOrFail();

        $report = Report::create([
            'user_id'    => Auth::id(),
            'title'      => $template->name,
            'type'       => $template->type,
            'parameters' => $request->parameters ?? [],
            'status'     => 'pending',
        ]);

        GenerateReport::dispatch($report);

        return response()->json(['data' => new ReportResource($report)], 201);
    }

    public function show(Report $report): JsonResponse
    {
        if ($report->user_id !== Auth::id()) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return response()->json(['data' => new ReportResource($report)]);
    }
}
