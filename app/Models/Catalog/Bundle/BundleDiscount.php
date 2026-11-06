<?php

namespace App\Models\Catalog\Bundle;

use App\Models\User\CustomerGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BundleDiscount extends Model
{
    protected $table = 'catalog_bundle_discounts';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['value' => 'decimal:4'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }
}
