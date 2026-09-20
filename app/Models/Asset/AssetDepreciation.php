<?php

namespace App\Models\Asset;

use App\Models\Accounting\Journal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    protected $fillable = ['fixed_asset_id', 'depreciation_date', 'amount', 'accumulated_depreciation', 'net_book_value', 'journal_id', 'created_by'];

    protected $casts = ['depreciation_date' => 'date', 'amount' => 'decimal:2', 'accumulated_depreciation' => 'decimal:2', 'net_book_value' => 'decimal:2'];

    public function fixedAsset(): BelongsTo { return $this->belongsTo(FixedAsset::class); }
    public function journal(): BelongsTo { return $this->belongsTo(Journal::class); }
}
