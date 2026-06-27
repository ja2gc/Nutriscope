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
    use \App\Models\Concerns\AuditsChanges;

    protected $table = 'fs_items';

    protected $fillable = [
        'name', 'kind', 'category',
        'base_unit', 'purchase_unit', 'purchase_price', 'units_per_purchase',
        'default_supplier_id', 'default_supplier_locked_at', 'default_supplier_locked_by',
        'is_active', 'notes',
    ];

    protected $casts = [
        'purchase_price'             => 'decimal:2',
        'units_per_purchase'         => 'decimal:2',
        'is_active'                  => 'boolean',
        'default_supplier_locked_at' => 'datetime',
    ];

    /** Vendor suggestion is locked when an explicit lock timestamp is set. */
    public function vendorLocked(): bool
    {
        return $this->default_supplier_locked_at !== null;
    }

    public function defaultSupplierLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_supplier_locked_by');
    }

    /**
     * Base units contained in ONE purchase unit (e.g. 1000 g per kg, or
     * units_per_purchase for count packs).
     *
     *  - same/empty unit (pc→pc)    → 1.0
     *  - physical units (kg→g, L→mL)→ UnitConverter factor (exact, DRY)
     *  - count packs (pack→pc)      → units_per_purchase
     *  - misconfigured              → 0.0 (degrade, never throw inside a list view)
     */
    public function basePerPurchase(): float
    {
        $from = (string) $this->purchase_unit;
        $to   = (string) $this->base_unit;

        if ($from === '' || $to === '' || UnitConverter::normalize($from) === UnitConverter::normalize($to)) {
            return 1.0;
        }
        if (UnitConverter::isKnown($from) && UnitConverter::isKnown($to)) {
            return UnitConverter::convert(1, $from, $to); // e.g. 1 kg = 1000 g
        }
        return (float) ($this->units_per_purchase ?? 0);
    }

    /**
     * Cost of ONE base_unit (e.g. ₱ per gram), derived from how the item is bought.
     * = purchase_price ÷ basePerPurchase(); 0.0 when misconfigured.
     */
    public function getUnitCostAttribute(): float
    {
        $n = $this->basePerPurchase();
        return $n > 0 ? round((float) $this->purchase_price / $n, 6) : 0.0;
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
