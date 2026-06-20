<?php

namespace App\Http\Controllers\FSS;

use App\Http\Controllers\Controller;
use App\Services\FSS\FssDashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(FssDashboardService $service): JsonResponse
    {
        return response()->json(['data' => $service->summary()]);
    }
}
