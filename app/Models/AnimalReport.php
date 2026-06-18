<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnimalReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'animal_id',
        'author_id',
        'public_token',
        'title',
        'report_date',
        'content_html',
        'status',
        'pdf_disk',
        'pdf_path',
        'finalized_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AnimalReport $report) {
            $report->public_token ??= str()->random(48);
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function images()
    {
        return $this->hasMany(AnimalReportImage::class)->orderBy('position')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
