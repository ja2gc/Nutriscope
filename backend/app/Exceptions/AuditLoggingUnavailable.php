<?php

namespace App\Exceptions;

use App\Services\Audit\AuditHealthMonitor;
use RuntimeException;
use Throwable;

class AuditLoggingUnavailable extends RuntimeException
{
    public function report(): void
    {
        try {
            app(AuditHealthMonitor::class)->writerFailure($this);
        } catch (Throwable) {
        }
    }
}
