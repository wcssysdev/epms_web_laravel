<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Additive multi-scope grant for a user (multi-estate / multi-country roles).
 *
 * scope_type:
 *   - 'country' => scope_id references m_country.id
 *   - 'estate'  => scope_id references m_estate.id
 */
class UserScope extends Model
{
    protected $table = 'tc_user_scope';

    public const TYPE_COUNTRY = 'country';
    public const TYPE_ESTATE  = 'estate';

    protected $fillable = [
        'user_id',
        'scope_type',
        'scope_id',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────
    public function scopeCountries($query)
    {
        return $query->where('scope_type', self::TYPE_COUNTRY);
    }

    public function scopeEstates($query)
    {
        return $query->where('scope_type', self::TYPE_ESTATE);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
