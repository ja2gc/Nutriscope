<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\AiSuggestDiagnosisRequest;
use App\Http\Requests\RND\AiApproveDiagnosisRequest;
use App\Http\Resources\DiagnosisResource;
use App\Models\Diagnosis;
use App\Models\NcpRecord;
use App\Services\AIService;

class AiDiagnosisController extends Controller
{
    public function __construct(private AIService $aiService) {}

    /**
     * GET AI-suggested diagnoses for an NCP record.
     */
    public function aiSuggest(AiSuggestDiagnosisRequest $request, NcpRecord $ncpRecord): \Illuminate\Http\JsonResponse
    {
        $suggestions = $this->aiService->suggestDiagnoses($request->validated());

        return response()->json(['data' => $suggestions]);
    }

    /**
     * Store an AI-approved diagnosis to the database.
     */
    public function aiApprove(AiApproveDiagnosisRequest $request, NcpRecord $ncpRecord): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();

        $diagnosis = Diagnosis::create([
            'ncp_record_id' => $ncpRecord->id,
            'domain'        => $data['domain'],
            'problem'       => $data['label'],
            'label'         => $data['label'],
            'etiology'      => $data['etiology'],
            'signs_symptoms'=> $data['signs'],
            'pes_statement' => Diagnosis::buildPes($data['label'], $data['etiology'], $data['signs']),
            'ai_generated'  => true,
        ]);

        return response()->json(['data' => new DiagnosisResource($diagnosis)], 201);
    }
}
