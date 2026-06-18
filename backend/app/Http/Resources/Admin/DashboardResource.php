<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * The resource wraps a plain array, not an Eloquent model.
     */
    public static $wrap = 'data';

    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'users' => $this->resource['users'],
            'patients' => $this->resource['patients'],
            'ai_usage' => $this->resource['ai_usage'],
            'audit_logs' => $this->resource['audit_logs'],
            'reports' => $this->resource['reports'],
        ];
    }
}
