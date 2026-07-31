<?php

namespace App\Enums;

enum RecoveryIncidentType: string
{
    case WebsiteUnavailable = 'website_unavailable';
    case DamagedDatabase = 'damaged_database';
    case AccidentallyDeletedRecords = 'accidentally_deleted_records';
    case MissingUpload = 'missing_upload';
    case BadDeployment = 'bad_deployment';
}
