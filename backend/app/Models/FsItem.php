<?php

namespace App\Models;

use App\Support\UnitConverter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Food-service catalog item — the kitchen's own list of things it buys.
 *
 * INTENTIONALLY UNRELATED to `food_items` (the USDA/NCP food library). No FK, no
 * query path. FS items carry no nutrition; they only track how the item is bought
 * (purchase_unit/purchase_price) and the unit recipes/stock use (base_unit).
 *
 * `kind` splits the catalog into edible ingredients and non-food supplies.
 */
class FsItem extends Model
{
    use HasFactory;

    protected $table = 'fs_items';

    protected $fillable = [
        'name', 'kind', 'category',
        'base_unit', 'purchase_unit', 'purchase_price', 'units_per_purchase',
        'default_supplier_id', 'is_active', 'notes',
    ];

    protected $casts = [
        'purchase_price'     => 'decimal:2',
        'units_per_purchase' => 'decimal:2',
        'is_active'          => 'boolean',
    ];

    /**
     * Cost of ONE base_unit (e.g. ₱ per gram), derived from how the item is bought.
     *
     *  - same unit (pc→pc)          → the purchase price itself
     *  - physical units (kg→g, L→mL)→ price ÷ UnitConverter factor (exact, DRY)
     *  - count packs (pack→pc)      → price ÷ units_per_purchase
     *  - misconfigured              → 0.0 (degrade, never throw inside a list view)
     */
    public function getUnitCostAttribute(): float
    {
        $price = (float) $this->purchase_price;
        $from  = (string) $this->purchase_unit;
        $to    = (string) $this->base_unit;

        if ($from === '' || $to === '' || UnitConverter::normalize($from) === UnitConverter::normalize($to)) {
            return round($price, 6);
        }

        if (UnitConverter::isKnown($from) && UnitConverter::isKnown($to)) {
            $basePerPurchase = UnitConverter::convert(1, $from, $to); // e.g. 1 kg = 1000 g
            return $basePerPurchase > 0 ? round($price / $basePerPurchase, 6) : 0.0;
        }

        $n = (float) ($this->units_per_purchase ?? 0);
        return $n > 0 ? round($price / $n, 6) : 0.0;
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function scopeIngredients($query)
    {
        return $query->where('kind', 'ingredient');
    }

    public function scopeSupplies($query)
    {
        return $query->where('kind', 'supply');
    }
}
