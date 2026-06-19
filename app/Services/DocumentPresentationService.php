<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VeterinarianProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentPresentationService
{
    public function __construct(private readonly TenantDocumentTemplateService $templates) {}

    public function build(
        Tenant $tenant,
        Animal $animal,
        ?User $veterinarian,
        string $documentType,
        CarbonInterface $documentDate,
        array $extraValues = []
    ): array {
        $tenant->loadMissing('documentSetting');
        $animal->loadMissing(['customer', 'animalType']);
        $veterinarian?->loadMissing('veterinarianProfile');

        $profile = $this->resolveSigningProfile($tenant, $veterinarian);

        $years = $animal->birthdate?->diffInYears($documentDate);
        $sexLabels = ['male' => 'Macho', 'female' => 'Hembra', 'unknown' => 'Desconocido'];
        $values = array_merge([
            'patient_name' => $animal->name,
            'owner_name' => $animal->customer?->full_name ?? '',
            'species' => $animal->animalType?->name ?? '',
            'breed' => $animal->animalType?->name ?? '',
            'color' => $animal->color ?? '',
            'sex' => $sexLabels[$animal->sex] ?? 'Desconocido',
            'age' => is_null($years) ? '' : ($years === 1 ? '1 ano' : $years.' anos'),
            'document_date' => $documentDate->format('d/m/Y'),
            'veterinarian_name' => $profile?->professional_name ?? $veterinarian?->name ?? '',
            'veterinarian_title' => $profile?->professional_title ?? '',
            'license_number' => $profile?->license_number ?? '',
            'clinic_name' => $tenant->business_name ?: $tenant->name,
        ], $extraValues);

        $template = $this->templates->forTenantAndType($tenant->id, $documentType);

        return [
            'values' => $values,
            'body_html' => $this->templates->render($documentType, $template['body_html'], $values),
            'header_color' => $template['header_color'],
            'closing_text' => $this->templates->renderPlainText($documentType, $template['closing_text'], $values),
            'image_section_title' => $this->templates->renderPlainText($documentType, $template['image_section_title'], $values),
            'letterhead_data_uri' => $this->letterheadDataUri($tenant),
            'signature_data_uri' => $profile?->signature_path
                ? $this->dataUri($profile->signature_disk ?: 'r2', $profile->signature_path, 'image/webp')
                : null,
            'veterinarian_profile' => $profile,
        ];
    }

    public function dataUri(string $disk, string $path, ?string $mimeType = null): ?string
    {
        try {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }

            return 'data:'.($mimeType ?: $this->mimeTypeFromPath($path)).';base64,'.base64_encode(Storage::disk($disk)->get($path));
        } catch (Throwable) {
            return null;
        }
    }

    private function letterheadDataUri(Tenant $tenant): ?string
    {
        $settings = $tenant->documentSetting;
        if ($settings?->letterhead_path) {
            $letterhead = $this->dataUri(
                $settings->letterhead_disk ?: 'r2',
                $settings->letterhead_path,
                'image/webp'
            );
            if ($letterhead) {
                return $letterhead;
            }
        }

        $logo = $tenant->logo;
        if (! $logo || filter_var($logo, FILTER_VALIDATE_URL)) {
            return null;
        }

        foreach (['public', 'r2'] as $disk) {
            $data = $this->dataUri($disk, $logo);
            if ($data) {
                return $data;
            }
        }

        return null;
    }

    private function resolveSigningProfile(Tenant $tenant, ?User $veterinarian): ?VeterinarianProfile
    {
        $profile = $veterinarian?->veterinarianProfile;

        if ($profile?->is_active && $profile->signature_path) {
            return $profile;
        }

        $fallbackQuery = VeterinarianProfile::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotNull('signature_path');

        if ($veterinarian) {
            $fallbackQuery->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [$veterinarian->id]);
        }

        return $fallbackQuery->orderBy('id')->first();
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            default => 'image/jpeg',
        };
    }
}
