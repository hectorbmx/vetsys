<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
class Note extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'client_uuid',
        'synced_from_mobile',
        'customer_id',
        'folio',
        'public_token',
        'total',
        'status',
        'date_at',
        'visible_to_customer',
        'published_at',
        'published_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($note) {
            if (empty($note->public_token)) {
                $note->public_token = \Illuminate\Support\Str::random(32) . uniqid();
            }
        });
    }

    protected $casts = [
        'date_at' => 'date',
        'total' => 'decimal:2',
        'synced_from_mobile' => 'boolean',
        'visible_to_customer' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function details() { return $this->hasMany(NoteDetail::class); }
    public function paymentLinks() { return $this->hasMany(NotePaymentLink::class); }
    
    // Relación a los abonos aplicados mediante la tabla pivote
   // En app/Models/Note.php

public function payments()
{
    return $this->belongsToMany(Payment::class, 'note_payments')
                ->using(NotePayment::class) // <--- Aquí vinculamos el nuevo modelo
                ->withPivot('amount_applied')
                ->withTimestamps();
}

    /**
     * Helper para saber cuánto se ha pagado acumulado en esta nota
     */
    public function getAmountPaidAttribute(): float
    {
        return (float) DB::table('note_payments')
            ->where('note_id', $this->id)
            ->sum('amount_applied');
    }

    /**
     * Helper para saber el saldo pendiente de la nota
     */
    public function getBalanceAttribute(): float
    {
        return (float) ($this->total - $this->amount_paid);
    }
    public function invoice()
{
    return $this->hasOne(Invoice::class);
}

public function invoices()
{
    return $this->hasMany(Invoice::class);
}

public function publisher()
{
    return $this->belongsTo(User::class, 'published_by');
}
}
