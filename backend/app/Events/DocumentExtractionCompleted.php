<?php

namespace App\Events;

use App\Models\ScreeningDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentExtractionCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(public ScreeningDocument $screeningDocument) {}
}
