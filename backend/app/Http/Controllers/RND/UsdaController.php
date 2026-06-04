<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Resources\FoodItemResource;
use App\Services\UsdaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class UsdaController extends Controller
{
    public function __construct(private readonly UsdaService $usda) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate(['query' => 'required|string|min:2|max:200']);

        try {
            $results = $this->usda->search($request->query('query'));
            return response()->json(['data' => $results]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }
    }

    public function import(Request $request, int $fdcId): JsonResponse
    {
        try {
            $food = $this->usda->import($fdcId);
            return (new FoodItemResource($food))
                ->response()
                ->setStatusCode(201);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'already exists') ? 409 : 502;
            return response()->json(['message' => $e->getMessage()], $status);
        }
    }
}
