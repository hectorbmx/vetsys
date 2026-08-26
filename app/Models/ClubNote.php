<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClubNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'club_id',
        'folio',
        'public_token',
        'total',
        'status',
        'date_at',
        'notes_html',
    ];

    protected $casts = [
        'date_at' => 'date',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClubNote $note) {
            if (empty($note->public_token)) {
                $note->public_token = Str::random(32) . uniqid();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function details()
    {
        return $this->hasMany(ClubNoteDetail::class);
    }

    public function getAmountPaidAttribute(): float
    {
        return 0.0;
    }

    public function getBalanceAttribute(): float
    {
        return (float) DB::table('club_note_details')
            ->where('club_note_id', $this->id)
            ->sum('subtotal');
    }
}
