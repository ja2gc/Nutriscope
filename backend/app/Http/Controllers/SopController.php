<?php

namespace App\Http\Controllers;

use App\Http\Resources\SopResource;
use App\Models\Sop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Standard Operating Procedure board. Append-only versioning: the current SOP is
 * the latest row; {@see history()} returns every past version as the audit trail.
 * Reads are open to any authenticated role; writes are gated to RND/Admin by route.
 */
class SopController extends Controller
{
    /** The current (latest) SOP, or null when none has been set yet. */
    public function current(): JsonResponse
    {
        $sop = Sop::with('author')->latest('id')->first();

        return response()->json([
            'data' => $sop ? new SopResource($sop) : null,
        ]);
    }

    /** Full revision history (newest first) — every past SOP + who saved it. */
    public function history(): AnonymousResourceCollection
    {
        return SopResource::collection(Sop::with('author')->latest('id')->get());
    }

    /** Save a revision: always inserts a NEW row (never updates in place). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body'  => ['required', 'string'],
        ]);

        $sop = Sop::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => new SopResource($sop->load('author')),
        ], 201);
    }
}
