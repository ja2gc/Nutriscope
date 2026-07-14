<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_id', 'supplier_name', 'air_no', 'invoice_no', 'po_no',
        'invoice_date', 'requisition_office', 'date_received', 'date_inspected',
        'inspection_status', 'inspected_by', 'inspected_by_title',
        'certified_by', 'certified_by_title', 'verified_by', 'verified_by_title',
        'approved_by', 'approved_by_title', 'conforme_name', 'conforme_title',
        'oic_pgso_name', 'oic_pgso_title', 'file_path', 'extracted_data',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'date_received' => 'date',
        'date_inspected' => 'date',
        'extracted_data' => 'array',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'procurement_id');
    }

    public function items()
    {
        return $this->hasMany(InspectionReportItem::class);
    }
}
