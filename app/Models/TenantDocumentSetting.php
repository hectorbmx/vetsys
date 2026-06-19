<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDocumentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'letterhead_disk',
        'letterhead_path',
        'letterhead_original_name',
        'letterhead_size',
    ];

    protected $casts = [
        'letterhead_size' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
