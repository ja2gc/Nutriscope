<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScreeningDocumentResource;
use App\Models\ScreeningDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ScreeningDocumentController extends Controller
{
    public function show(ScreeningDocument $screeningDocument): ScreeningDocumentResource
    {
        return new ScreeningDocumentResource($screeningDocument);
    }

    public function file(ScreeningDocument $screeningDocument): BinaryFileResponse
    {
        $absolutePath = $screeningDocument->file_path;

        // Support both absolute paths stored in DB and relative paths
        if (!file_exists($absolutePath)) {
            $absolutePath = storage_path('app/' . ltrim($absolutePath, '/\\'));
        }

        abort_unless(file_exists($absolutePath), 404, 'File not found.');

        return response()->file($absolutePath);
    }

    /**
     * DELETE /api/rnd/screening-documents/{screeningDocument}
     */
    public function destroy(ScreeningDocument $screeningDocument): JsonResponse
    {
        Storage::delete($screeningDocument->file_path);
        $screeningDocument->delete();

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
