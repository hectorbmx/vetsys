<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coggin extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'club_id',
        'file_path',
        'file_name',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }
}
