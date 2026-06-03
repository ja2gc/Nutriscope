<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionReportItem extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'inspection_report_id', 'item_no', 'unit', 'description',
        'quantity', 'food_item_id'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function inspectionReport()
    {
        return $this->belongsTo(InspectionReport::class);
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }

}

