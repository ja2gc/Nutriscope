<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(): JsonResponse
    {
        $notifications = Notification::where('user_id', Auth::id())->get();
        return response()->json(['data' => NotificationResource::collection($notifications)]);
    }

    public function read(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== Auth::id()) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $notification->update(['read' => true]);

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function readAll(): JsonResponse
    {
        Notification::where('user_id', Auth::id())->update(['read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
