<?php

namespace App\Models\Master;

use App\Traits\AuditTrailTrait;
use App\Traits\FilterableSearchableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BeneficiaryPartner extends Model
{
    use HasFactory, SoftDeletes, FilterableSearchableTrait, AuditTrailTrait;

    protected $table = 'beneficiary_partners';

    protected $fillable = ['partner_key', 'code', 'name', 'type', 'address', 'pic_name', 'contact_info', 'bank_info', 'is_active', 'created_by', 'updated_by', 'deleted_by'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (self $partner): void {
            if (blank($partner->partner_key)) {
                // The database ID is concurrency-safe, so it is also the safest
                // source for a stable, automatically generated partner key.
                $partner->partner_key = 'BP-' . str_pad((string) $partner->getKey(), 6, '0', STR_PAD_LEFT);
                $partner->saveQuietly();
            }
        });
    }

}
