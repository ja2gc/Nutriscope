<?php

namespace App\Http\Controllers;

use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    protected function audited(Closure $mutation): mixed
    {
        app(AuditLogger::class)->assertAvailable();

        return DB::connection(config('database.default'))->transaction($mutation);
    }
}
