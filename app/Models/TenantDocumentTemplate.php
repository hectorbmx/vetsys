<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantDocumentTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'document_type',
        'body_html',
        'header_color',
        'closing_text',
        'image_section_title',
        'updated_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
