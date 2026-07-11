<?php

namespace App\Http\Controllers\Admin;

use App\Services\Audit\AuditLogger;

class AnnouncementController extends \App\Http\Controllers\RND\AnnouncementController
{
    public function __construct(AuditLogger $auditLogger)
    {
        parent::__construct($auditLogger);
    }
}
