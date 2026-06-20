<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DietListCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_date',
        'menu_cycle_id',
        'ward',
        'fss_user_id',
        'population',
        'helped_food_prep',
        'stored_supplies',
        'collected_diet_list',
        'apportioned_food',
        'cleaned_utensils',
        'assistant_cook',
        'maintained_cleanliness',
        'off_duty',
    ];

    protected $casts = [
        'service_date'          => 'date',
        'population'            => 'integer',
        'helped_food_prep'      => 'bool',
        'stored_supplies'       => 'bool',
        'collected_diet_list'   => 'bool',
        'apportioned_food'      => 'bool',
        'cleaned_utensils'      => 'bool',
        'assistant_cook'        => 'bool',
        'maintained_cleanliness'=> 'bool',
        'off_duty'              => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fss_user_id');
    }

    public function menuCycle(): BelongsTo
    {
        return $this->belongsTo(MenuCycle::class);
    }
}
