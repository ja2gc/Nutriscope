<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreCalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CalendarEventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = CalendarEvent::where('user_id', Auth::id())->get();
        return response()->json(['data' => CalendarEventResource::collection($events)]);
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['type'] = $data['event_type'] ?? 'followup';

        $event = CalendarEvent::create($data);

        return response()->json(['data' => new CalendarEventResource($event)], 201);
    }
}
