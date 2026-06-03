<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class NotePayment extends Pivot
{
    // Usamos 'Pivot' en lugar de 'Model' para tablas intermedias
    protected $table = 'note_payments';
    
    public $incrementing = true; // Si tu tabla tiene ID propio

    protected $fillable = [
        'note_id', 
        'payment_id', 
        'amount_applied'
    ];

    public function note()
    {
        return $this->belongsTo(Note::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}