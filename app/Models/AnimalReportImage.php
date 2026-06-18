<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnimalReportImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_report_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'position',
    ];

    protected $casts = [
        'size' => 'integer',
        'position' => 'integer',
    ];

    public function report()
    {
        return $this->belongsTo(AnimalReport::class, 'animal_report_id');
    }
}
