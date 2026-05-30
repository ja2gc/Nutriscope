<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $announcements = Announcement::query()
            ->with('user:id,name,role')
            ->when($user->role === 'RND', function ($query) use ($user) {
                $query->where(function ($nested) use ($user) {
                    $nested->where('visibility', 'All')
                        ->orWhere('user_id', $user->id);
                });
            })
            ->when($user->role === 'Admin', fn($query) => $query)
            ->orderByDesc('pinned')
            ->orderByDesc('created_at')
            ->get();

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $announcement = Announcement::create([
            ...$data,
            'user_id' => $user->id,
            'pinned' => $user->role === 'Admin' ? (bool) ($data['pinned'] ?? false) : false,
        ])->load('user:id,name,role');

        return response()->json([
            'data' => new AnnouncementResource($announcement),
        ], 201);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'Admin' && $announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only edit your own announcements.'], 403);
        }

        $data = $request->validated();

        if ($user->role !== 'Admin') {
            unset($data['pinned']);
        }

        $announcement->update($data);
        $announcement->load('user:id,name,role');

        return response()->json([
            'data' => new AnnouncementResource($announcement),
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'Admin' && $announcement->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden. You can only delete your own announcements.'], 403);
        }

        $announcement->delete();

        return response()->json(null, 204);
    }
}
