<?php

namespace App\Services;

use App\Models\TenantDocumentTemplate;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TenantDocumentTemplateService
{
    public const VACCINATION = 'vaccination_letter';

    public const MICROCHIP = 'microchip_letter';

    public const CLINICAL_REPORT = 'clinical_report';

    public function __construct(private readonly RichTextSanitizer $sanitizer) {}

    public function definitions(): array
    {
        return [
            self::VACCINATION => [
                'label' => 'Carta de vacunacion',
                'description' => 'Texto que acompana la evidencia y los datos de la vacuna.',
                'header_color' => '#0F766E',
                'body_html' => '<p>Por medio de la presente hago constar que <strong>{{patient_name}}</strong>, propiedad de {{owner_name}}, cuenta con su registro de vacunacion. La vacuna {{vaccine_name}} fue aplicada el {{vaccination_date}}.</p>',
                'closing_text' => 'Cualquier duda estoy a sus ordenes.',
                'image_section_title' => 'Evidencia de vacunacion',
                'variables' => [
                    'patient_name', 'owner_name', 'species', 'breed', 'color', 'sex', 'age',
                    'document_date', 'vaccination_date', 'vaccine_name', 'veterinarian_name',
                    'veterinarian_title', 'license_number', 'clinic_name',
                ],
            ],
            self::MICROCHIP => [
                'label' => 'Carta de microchip',
                'description' => 'Constancia de lectura e identificacion del microchip.',
                'header_color' => '#0F766E',
                'body_html' => '<p>Por medio de la presente hago constar la lectura del numero de microchip <strong>{{microchip_number}}</strong>, correspondiente a {{patient_name}}, propiedad de {{owner_name}}.</p>',
                'closing_text' => 'Cualquier duda estoy a sus ordenes.',
                'image_section_title' => 'Evidencia del microchip',
                'variables' => [
                    'patient_name', 'owner_name', 'species', 'breed', 'color', 'sex', 'age',
                    'document_date', 'microchip_number', 'veterinarian_name', 'veterinarian_title',
                    'license_number', 'clinic_name',
                ],
            ],
            self::CLINICAL_REPORT => [
                'label' => 'Reporte clinico',
                'description' => 'Texto introductorio opcional; el contenido medico se captura en cada reporte.',
                'header_color' => '#0F766E',
                'body_html' => '',
                'closing_text' => 'Cualquier duda estoy a sus ordenes.',
                'image_section_title' => 'Imagenes de referencia significativas',
                'variables' => [
                    'patient_name', 'owner_name', 'species', 'breed', 'color', 'sex', 'age',
                    'document_date', 'veterinarian_name', 'veterinarian_title', 'license_number',
                    'clinic_name',
                ],
            ],
        ];
    }

    public function types(): array
    {
        return array_keys($this->definitions());
    }

    public function forTenant(int $tenantId): Collection
    {
        $saved = TenantDocumentTemplate::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('document_type');

        return collect($this->definitions())->map(function (array $definition, string $type) use ($saved) {
            $template = $saved->get($type);

            return [
                'type' => $type,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'variables' => $definition['variables'],
                'body_html' => $template?->body_html ?? $definition['body_html'],
                'header_color' => $template?->header_color ?? $definition['header_color'],
                'closing_text' => $template?->closing_text ?? $definition['closing_text'],
                'image_section_title' => $template?->image_section_title ?? $definition['image_section_title'],
                'customized' => (bool) $template,
                'updated_at' => $template?->updated_at,
            ];
        })->values();
    }

    public function forTenantAndType(int $tenantId, string $type): array
    {
        $template = $this->forTenant($tenantId)->firstWhere('type', $type);

        if (! $template) {
            throw ValidationException::withMessages(['document_type' => 'Tipo de documento no valido.']);
        }

        return $template;
    }

    public function sanitizeAndValidate(string $type, ?string $html): string
    {
        $definition = $this->definitions()[$type] ?? null;
        if (! $definition) {
            throw ValidationException::withMessages(['document_type' => 'Tipo de documento no valido.']);
        }

        $clean = $this->sanitizer->sanitize($html);
        preg_match_all('/\{\{([a-z_]+)\}\}/', $clean, $matches);
        $unknown = array_diff(array_unique($matches[1] ?? []), $definition['variables']);

        if ($unknown) {
            throw ValidationException::withMessages([
                'body_html' => 'Variables no permitidas: '.implode(', ', $unknown).'.',
            ]);
        }

        return $clean;
    }

    public function render(string $type, ?string $html, array $values): string
    {
        $clean = $this->sanitizeAndValidate($type, $html);
        $replacements = [];

        foreach ($this->definitions()[$type]['variables'] as $variable) {
            $replacements['{{'.$variable.'}}'] = e((string) ($values[$variable] ?? ''));
        }

        return strtr($clean, $replacements);
    }

    public function validatePlainText(string $type, ?string $text, string $errorField = 'closing_text'): string
    {
        $definition = $this->definitions()[$type] ?? null;
        if (! $definition) {
            throw ValidationException::withMessages(['document_type' => 'Tipo de documento no valido.']);
        }

        $text = trim((string) $text);
        preg_match_all('/\{\{([a-z_]+)\}\}/', $text, $matches);
        $unknown = array_diff(array_unique($matches[1] ?? []), $definition['variables']);

        if ($unknown) {
            throw ValidationException::withMessages([
                $errorField => 'Variables no permitidas: '.implode(', ', $unknown).'.',
            ]);
        }

        return $text;
    }

    public function renderPlainText(string $type, ?string $text, array $values): string
    {
        $text = $this->validatePlainText($type, $text);
        $replacements = [];

        foreach ($this->definitions()[$type]['variables'] as $variable) {
            $replacements['{{'.$variable.'}}'] = (string) ($values[$variable] ?? '');
        }

        return strtr($text, $replacements);
    }
}
