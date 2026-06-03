<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'dob', 'sex', 'religion', 'address', 'contact',
        'physician', 'admission_date', 'medical_diagnosis', 'ward', 'status',
        'screening_type', 'hospital_number', 'age_group_category',
    ];

    protected $casts = [
        'dob' => 'date',
        'admission_date' => 'date',
    ];

    public function ncpRecords(): HasMany
    {
        return $this->hasMany(NcpRecord::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    /**
     * Get patient age from DOB.
     */
    public function getAgeAttribute(): int
    {
        return $this->dob->age;
    }
}
