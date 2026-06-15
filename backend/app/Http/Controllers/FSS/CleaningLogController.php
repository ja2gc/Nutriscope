<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Http\Requests\FSS\StoreCleaningLogRequest;
use App\Http\Requests\FSS\UpdateCleaningLogRequest;
use App\Http\Resources\CleaningLogResource;
use App\Models\CleaningLog;
use Illuminate\Http\JsonResponse;

class CleaningLogController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = CleaningLog::latest()->get();

        return response()->json(['data' => CleaningLogResource::collection($logs)]);
    }

    public function store(StoreCleaningLogRequest $request): JsonResponse
    {
        $log = CleaningLog::create([
            ...$request->validated(),
            'fss_user_id' => $request->user()->id,
        ]);

        return response()->json(['data' => new CleaningLogResource($log)], 201);
    }

    public function show(CleaningLog $cleaningLog): JsonResponse
    {
        return response()->json(['data' => new CleaningLogResource($cleaningLog)]);
    }

    public function update(UpdateCleaningLogRequest $request, CleaningLog $cleaningLog): JsonResponse
    {
        $cleaningLog->update($request->validated());

        return response()->json(['data' => new CleaningLogResource($cleaningLog)]);
    }

    public function destroy(CleaningLog $cleaningLog): JsonResponse
    {
        $cleaningLog->delete();

        return response()->json(null, 204);
    }
}
