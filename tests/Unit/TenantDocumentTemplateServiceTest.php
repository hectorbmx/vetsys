<?php

namespace Tests\Unit;

use App\Services\RichTextSanitizer;
use App\Services\TenantDocumentTemplateService;
use PHPUnit\Framework\TestCase;

class TenantDocumentTemplateServiceTest extends TestCase
{
    public function test_every_document_template_has_a_valid_default_header_color(): void
    {
        $service = new TenantDocumentTemplateService(new RichTextSanitizer);

        foreach ($service->definitions() as $definition) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $definition['header_color']);
        }
    }
}
