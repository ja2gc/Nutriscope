<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPlanTemplate extends Model
{
    use HasFactory;
    use HasPublicId;

    protected $fillable = ['rnd_user_id', 'name', 'description', 'goal_type'];

    public function rnd()
    {
        return $this->belongsTo(User::class, 'rnd_user_id');
    }

    public function days()
    {
        return $this->hasMany(MealPlanTemplateDay::class, 'template_id');
    }
}
