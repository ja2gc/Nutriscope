<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(): JsonResponse
    {
        $logs = Activity::with('causer')->get();

        return response()->json(['data' => $logs]);
    }
}
