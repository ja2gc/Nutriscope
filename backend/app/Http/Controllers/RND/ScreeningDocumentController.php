<?php

namespace App\Http\Controllers\RND;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScreeningDocumentResource;
use App\Models\ScreeningDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ScreeningDocumentController extends Controller
{
    public function show(ScreeningDocument $screeningDocument): ScreeningDocumentResource
    {
        return new ScreeningDocumentResource($screeningDocument);
    }

    public function file(ScreeningDocument $screeningDocument)
    {
        $path = $screeningDocument->file_path;

        // Primary: disk-relative path on the configured disk (current upload format).
        if (Storage::exists($path)) {
            return Storage::response($path);
        }

        // Fallback: legacy absolute paths stored before A8.
        abort_unless(is_file($path), 404, 'File not found.');

        return response()->file($path);
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
