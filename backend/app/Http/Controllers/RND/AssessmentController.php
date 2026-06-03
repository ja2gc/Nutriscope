<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Requests\RND\StoreAssessmentRequest;
use App\Http\Requests\RND\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\NcpRecord;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    /**
     * POST /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function store(StoreAssessmentRequest $request, NcpRecord $ncpRecord)
    {
        if ($ncpRecord->assessment()->exists()) {
            return response()->json(['message' => 'Assessment already exists for this NCP record.'], 409);
        }

        $data = $request->validated();
        $assessment = new Assessment($data);
        $assessment->ncp_record_id = $ncpRecord->id;
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        // Calculate and save risk score
        $calculator = resolve(\App\Services\RiskScoreCalculator::class);
        $riskResult = $calculator->calculate($assessment);

        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
        $ncpRecord->update(['risk_score' => $riskResult['score']]);

        return (new AssessmentResource($assessment->fresh()))->response()->setStatusCode(201);
    }

    /**
     * GET /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function show(NcpRecord $ncpRecord): AssessmentResource
    {
        $assessment = $ncpRecord->assessment()->firstOrFail();
        return new AssessmentResource($assessment);
    }

    /**
     * PATCH /api/rnd/ncp-records/{ncpRecord}/assessment
     */
    public function update(UpdateAssessmentRequest $request, NcpRecord $ncpRecord): AssessmentResource
    {
        $assessment = $ncpRecord->assessment()->firstOrFail();
        
        $data = $request->validated();
        $assessment->fill($data);
        $assessment->bmi = $assessment->calculateBmi();
        $assessment->save();

        // Calculate and save risk score
        $calculator = resolve(\App\Services\RiskScoreCalculator::class);
        $riskResult = $calculator->calculate($assessment);

        $assessment->update(['nutritional_status' => $riskResult['nutritional_status']]);
        $ncpRecord->update(['risk_score' => $riskResult['score']]);

        return new AssessmentResource($assessment->fresh());
    }
}

