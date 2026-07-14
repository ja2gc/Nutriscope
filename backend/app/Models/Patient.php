<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use AuditsChanges, HasFactory, HasPublicId;

    /** Clinical model — log which fields changed, never the PHI values (Spec 5 Decision A). */
    protected bool $auditRedactValues = true;

    protected $fillable = [
        'name', 'dob', 'sex', 'religion', 'address', 'contact',
        'physician', 'admission_date', 'medical_diagnosis', 'ward', 'status',
        'screening_type', 'hospital_number', 'age_group_category',
    ];

    protected $casts = [
        'dob' => 'date',
        'admission_date' => 'date',
    ];

    protected function auditAttributes(): array
    {
        return [
            'name', 'dob', 'sex', 'religion', 'address', 'contact',
            'physician', 'admission_date', 'medical_diagnosis', 'ward', 'status',
            'screening_type', 'hospital_number', 'age_group_category',
        ];
    }

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
