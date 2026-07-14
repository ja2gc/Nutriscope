<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Audit\SetAuditRetentionState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAuditRetentionRequest;
use App\Services\Audit\AuditRetentionState;
use Illuminate\Http\JsonResponse;

class AuditRetentionController extends Controller
{
    public function __construct(
        private readonly AuditRetentionState $state,
        private readonly SetAuditRetentionState $setState,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->state->current()]);
    }

    public function update(UpdateAuditRetentionRequest $request): JsonResponse
    {
        $this->setState->execute($request->boolean('enabled'), $request->user());

        return response()->json(['data' => $this->state->current()]);
    }
}
