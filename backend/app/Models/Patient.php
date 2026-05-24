<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'name', 'dob', 'sex', 'religion', 'address', 'contact',
        'physician', 'admission_date', 'medical_diagnosis', 'ward', 'status',
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
