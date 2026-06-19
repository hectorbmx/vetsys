<?php

namespace Tests\Feature;

use App\Models\Animal;
use App\Models\AnimalType;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Http\Middleware\EnsureApiTenantAccess;
use App\Http\Middleware\EnsureValidMobileAccessSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnimalClinicalMediaApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_mobile_report_draft_can_be_created_updated_and_deleted(): void
    {
        [$animal, $user] = $this->animalForTenant('report-flow');
        $this->withoutMiddleware([EnsureValidMobileAccessSession::class, EnsureApiTenantAccess::class]);
        Sanctum::actingAs($user);

        $created = $this->post('/api/v1/animals/'.$animal->id.'/reports', [
            'title' => 'Consulta inicial',
            'report_date' => now()->toDateString(),
            'content_html' => '<p>Hallazgos iniciales.</p>',
            'intent' => 'draft',
        ])->assertCreated()
            ->assertJsonPath('data.reports.0.status', 'draft')
            ->assertJsonPath('data.reports.0.title', 'Consulta inicial');

        $reportId = $created->json('data.reports.0.id');
        $this->post('/api/v1/animal-reports/'.$reportId, [
            'title' => 'Consulta actualizada',
            'report_date' => now()->toDateString(),
            'content_html' => '<p>Hallazgos actualizados.</p>',
            'intent' => 'draft',
        ])->assertOk()
            ->assertJsonPath('data.reports.0.title', 'Consulta actualizada');

        $this->delete('/api/v1/animal-reports/'.$reportId)
            ->assertOk()
            ->assertJsonCount(0, 'data.reports');

        $this->assertDatabaseMissing('animal_reports', ['id' => $reportId]);
    }

    public function test_mobile_clinical_media_is_isolated_by_tenant(): void
    {
        [$animal] = $this->animalForTenant('owner');
        [, $otherUser] = $this->animalForTenant('other');

        $this->withoutMiddleware([EnsureValidMobileAccessSession::class, EnsureApiTenantAccess::class]);
        Sanctum::actingAs($otherUser);
        $this->get('/api/v1/animals/'.$animal->id.'/clinical-media')
            ->assertNotFound();
    }

    public function test_mobile_microchip_upload_generates_printable_pdf(): void
    {
        Storage::fake('r2');
        [$animal, $user] = $this->animalForTenant('microchip');
        $this->withoutMiddleware([EnsureValidMobileAccessSession::class, EnsureApiTenantAccess::class]);
        Sanctum::actingAs($user);

        $this->post('/api/v1/animals/'.$animal->id.'/microchip', [
            'microchip' => '939000001637122',
            'image' => UploadedFile::fake()->image('lector.png', 1200, 800),
        ])->assertCreated()
            ->assertJsonPath('data.microchip.number', '939000001637122')
            ->assertJsonPath('data.microchip.has_image', true)
            ->assertJsonPath('data.microchip.pdf_url', fn ($url) => is_string($url) && str_contains($url, '/cartas-microchip/'));

        $animal->refresh();
        $this->assertNotNull($animal->microchip_image_path);
        $this->assertNotNull($animal->microchip_pdf_path);
        Storage::disk('r2')->assertExists($animal->microchip_image_path);
        Storage::disk('r2')->assertExists($animal->microchip_pdf_path);
    }

    public function test_mobile_vaccination_upload_retains_at_most_two_latest(): void
    {
        Storage::fake('public');
        [$animal, $user] = $this->animalForTenant('vaccination');
        $this->withoutMiddleware([EnsureValidMobileAccessSession::class, EnsureApiTenantAccess::class]);
        Sanctum::actingAs($user);

        $this->post('/api/v1/animals/'.$animal->id.'/vaccination-letters', [
            'date' => '2026-06-01',
            'image' => UploadedFile::fake()->image('letter1.png', 800, 600),
        ])->assertCreated();

        $this->post('/api/v1/animals/'.$animal->id.'/vaccination-letters', [
            'date' => '2026-06-02',
            'image' => UploadedFile::fake()->image('letter2.png', 800, 600),
        ])->assertCreated();

        $this->assertEquals(2, $animal->vaccinationLetters()->count());

        $this->post('/api/v1/animals/'.$animal->id.'/vaccination-letters', [
            'date' => '2026-06-03',
            'image' => UploadedFile::fake()->image('letter3.png', 800, 600),
        ])->assertCreated();

        $this->assertEquals(2, $animal->vaccinationLetters()->count());

        $remainingDates = $animal->vaccinationLetters()->pluck('date')->map->toDateString()->toArray();
        $this->assertNotContains('2026-06-01', $remainingDates);
        $this->assertContains('2026-06-02', $remainingDates);
        $this->assertContains('2026-06-03', $remainingDates);
    }

    private function animalForTenant(string $suffix): array
    {
        $tenant = Tenant::create([
            'name' => 'Media '.$suffix,
            'slug' => 'media-'.$suffix.'-'.str()->random(6),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        $customer = Customer::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente '.$suffix,
            'status' => 'active',
        ]);
        $type = AnimalType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Canino',
            'slug' => 'canino-'.str()->random(6),
            'is_active' => true,
        ]);
        $animal = Animal::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'animal_type_id' => $type->id,
            'name' => 'Paciente '.$suffix,
            'sex' => 'unknown',
            'status' => 'active',
        ]);

        return [$animal, $user];
    }
}
