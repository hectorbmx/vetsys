<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Animal;
use App\Models\AnimalReport;
use App\Models\AnimalReportImage;
use App\Models\AnimalShare;
use App\Models\AnimalVideo;
use App\Models\RadiologyImage;
use App\Models\RadiologyStudy;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\VaccinationLetter;
use App\Services\PortalNotificationService;
use App\Services\AnimalReportImageOptimizer;
use App\Services\AnimalReportPdfService;
use App\Services\MicrochipImageOptimizer;
use App\Services\MicrochipLetterPdfService;
use App\Services\RichTextSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AnimalClinicalMediaController extends Controller
{
    public function index(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);

        return response()->json(['data' => $this->serialize($animal)]);
    }

    public function storeVaccination(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $existingLetters = $animal->vaccinationLetters()
            ->where('tenant_id', $animal->tenant_id)
            ->oldest('id')
            ->get();

        foreach ($existingLetters->slice(1) as $letter) {
            Storage::disk('public')->delete($letter->image_path);
            $letter->delete();
        }

        $path = $request->file('image')->store(
            "tenants/{$animal->tenant_id}/animals/{$animal->id}/vaccination-letters",
            'public'
        );

        $letter = VaccinationLetter::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'image_path' => $path,
            'date' => $data['date'],
        ]);

        app(PortalNotificationService::class)->vaccinationLetterPublished($letter);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function vaccinationShareLink(Request $request, VaccinationLetter $vaccinationLetter)
    {
        abort_unless($vaccinationLetter->tenant_id === $request->user()->tenant_id, 404);

        return response()->json([
            'data' => [
                'url' => $this->vaccinationPdfUrl($vaccinationLetter),
            ],
        ]);
    }

    public function storeMicrochip(
        Request $request,
        Animal $animal,
        MicrochipImageOptimizer $imageOptimizer,
        MicrochipLetterPdfService $pdfService
    ) {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'microchip' => ['required', 'string', 'max:255'],
            'image' => [$animal->microchip_image_path ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $oldImagePath = null;
        $newImagePath = null;

        try {
            if ($request->hasFile('image')) {
                $newImagePath = "tenants/{$animal->tenant_id}/animals/{$animal->id}/microchip/".Str::uuid().'.webp';
                $contents = $imageOptimizer->optimize($request->file('image'));
                Storage::disk('r2')->put($newImagePath, $contents, ['mimetype' => 'image/webp']);
                $oldImagePath = $animal->microchip_image_path;
            }

            $animal->update([
                'microchip' => trim($data['microchip']),
                'microchip_image_path' => $newImagePath ?: $animal->microchip_image_path,
                'microchip_print_token' => $animal->microchip_print_token ?: (string) Str::uuid(),
                'microchip_issued_by' => $request->user()->id,
            ]);

            $pdfService->finalize($animal->fresh());

            if ($oldImagePath && $oldImagePath !== $newImagePath) {
                Storage::disk('r2')->delete($oldImagePath);
            }
        } catch (\Throwable $exception) {
            if ($newImagePath && $animal->fresh()->microchip_image_path !== $newImagePath) {
                Storage::disk('r2')->delete($newImagePath);
            }
            throw $exception;
        }

        return response()->json(['data' => $this->serialize($animal->fresh())], 201);
    }

    public function storeReport(
        Request $request,
        Animal $animal,
        RichTextSanitizer $sanitizer,
        AnimalReportImageOptimizer $imageOptimizer,
        AnimalReportPdfService $pdfService
    ) {
        $this->authorizeAnimal($request, $animal);
        $data = $this->validatedReportData($request, $sanitizer);
        $report = AnimalReport::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'author_id' => $request->user()->id,
            'title' => $data['title'],
            'report_date' => $data['report_date'],
            'content_html' => $data['content_html'],
            'status' => 'draft',
        ]);

        $this->storeReportImages($request, $report, $imageOptimizer);
        if ($data['intent'] === 'finalize') {
            $pdfService->finalize($report);
        }

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function updateReport(
        Request $request,
        AnimalReport $animalReport,
        RichTextSanitizer $sanitizer,
        AnimalReportImageOptimizer $imageOptimizer,
        AnimalReportPdfService $pdfService
    ) {
        $this->authorizeReport($request, $animalReport);
        abort_unless($animalReport->isDraft(), 409, 'Los reportes finalizados no se pueden editar.');
        $data = $this->validatedReportData($request, $sanitizer);

        $animalReport->update([
            'title' => $data['title'],
            'report_date' => $data['report_date'],
            'content_html' => $data['content_html'],
        ]);
        $this->storeReportImages($request, $animalReport, $imageOptimizer);
        if ($data['intent'] === 'finalize') {
            $pdfService->finalize($animalReport);
        }

        return response()->json(['data' => $this->serialize($animalReport->animal)], 200);
    }

    public function destroyReport(Request $request, AnimalReport $animalReport)
    {
        $this->authorizeReport($request, $animalReport);
        abort_unless($animalReport->isDraft(), 409, 'Un reporte finalizado no se puede eliminar.');
        $animal = $animalReport->animal;
        $animalReport->load('images');
        foreach ($animalReport->images as $image) {
            Storage::disk($image->disk)->delete($image->path);
        }
        $animalReport->delete();

        return response()->json(['data' => $this->serialize($animal)]);
    }

    public function storeVideo(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'video_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'video' => ['required', 'file', 'mimes:mp4,mov,avi,webm,mkv', 'max:102400'],
        ]);

        $file = $request->file('video');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $path = "tenants/{$animal->tenant_id}/animals/{$animal->id}/videos/" . Str::uuid() . ".{$extension}";
        Storage::disk('r2')->put($path, fopen($file->getRealPath(), 'rb'), ['mimetype' => $file->getMimeType()]);

        $video = AnimalVideo::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'disk' => 'r2',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'video_date' => $data['video_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        app(PortalNotificationService::class)->videoPublished($video);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function storeRadiologyStudy(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'study_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        $study = RadiologyStudy::create([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            ...$data,
        ]);

        app(PortalNotificationService::class)->radiologyStudyPublished($study);

        return response()->json(['data' => $this->serialize($animal)], 201);
    }

    public function storeRadiologyImages(Request $request, RadiologyStudy $radiologyStudy)
    {
        abort_unless($radiologyStudy->tenant_id === $request->user()->tenant_id, 404);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'images' => ['required', 'array', 'min:1', 'max:20'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ]);

        $imageCount = 0;
        $lastImageId = null;

        foreach ($request->file('images', []) as $image) {
            $extension = strtolower($image->getClientOriginalExtension() ?: 'jpg');
            $path = "tenants/{$radiologyStudy->tenant_id}/animals/{$radiologyStudy->animal_id}/radiology/{$radiologyStudy->id}/" . Str::uuid() . ".{$extension}";
            Storage::disk('r2')->put($path, fopen($image->getRealPath(), 'rb'), ['mimetype' => $image->getMimeType()]);

            $radiologyImage = RadiologyImage::create([
                'tenant_id' => $radiologyStudy->tenant_id,
                'animal_id' => $radiologyStudy->animal_id,
                'radiology_study_id' => $radiologyStudy->id,
                'disk' => 'r2',
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getMimeType(),
                'size' => $image->getSize(),
                'label' => $data['label'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $imageCount++;
            $lastImageId = $radiologyImage->id;
        }

        app(PortalNotificationService::class)->radiologyImagesPublished($radiologyStudy, $imageCount, $lastImageId);

        return response()->json(['data' => $this->serialize($radiologyStudy->animal)], 201);
    }

    public function searchTenants(Request $request)
    {
        $search = trim((string) $request->get('q', ''));
        if (strlen($search) < 2) {
            return response()->json(['data' => []]);
        }

        $tenants = Tenant::query()
            ->where('id', '!=', $request->user()->tenant_id)
            ->where('status', 'active')
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('business_name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'business_name']);

        return response()->json(['data' => $tenants]);
    }

    public function share(Request $request, Animal $animal)
    {
        $this->authorizeAnimal($request, $animal);
        $data = $request->validate([
            'shared_with_tenant_id' => [
                'required',
                'integer',
                Rule::exists('tenants', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);

        $share = AnimalShare::withTrashed()->firstOrNew([
            'tenant_id' => $animal->tenant_id,
            'animal_id' => $animal->id,
            'shared_with_tenant_id' => $data['shared_with_tenant_id'],
        ]);
        $share->fill([
            'shared_by_user_id' => $request->user()->id,
            'token' => $share->token ?: Str::random(64),
            'is_active' => true,
            'expires_at' => null,
        ]);
        $share->restore();
        $share->save();

        $shareUrl = route('client.telemedicine.animals.show', $share->token);
        TenantNotification::create([
            'tenant_id' => $share->shared_with_tenant_id,
            'actor_tenant_id' => $animal->tenant_id,
            'actor_user_id' => $request->user()->id,
            'type' => 'telemedicine_share_created',
            'title' => 'Expediente compartido',
            'body' => ($request->user()->tenant->name ?? 'Otra clinica') . " compartio el expediente de {$animal->name}.",
            'url' => $shareUrl,
            'data' => ['animal_share_id' => $share->id, 'animal_id' => $animal->id],
        ]);

        return response()->json(['data' => $this->serialize($animal), 'share_url' => $shareUrl], 201);
    }

    private function authorizeAnimal(Request $request, Animal $animal): void
    {
        abort_unless($animal->tenant_id === $request->user()->tenant_id, 404);
    }

    private function serialize(Animal $animal): array
    {
        $animal->load([
            'vaccinationLetters' => fn ($query) => $query->latest('date')->latest('id'),
            'videos' => fn ($query) => $query->latest('video_date')->latest('id'),
            'radiologyStudies' => fn ($query) => $query->with('images')->latest('study_date')->latest('id'),
            'shares' => fn ($query) => $query->with('sharedWithTenant')->where('is_active', true)->latest('id'),
            'reports' => fn ($query) => $query->with(['author', 'images'])->latest('report_date')->latest('id'),
        ]);

        return [
            'microchip' => [
                'number' => $animal->microchip,
                'has_image' => filled($animal->microchip_image_path),
                'image_url' => $animal->microchip_image_path
                    ? Storage::disk('r2')->temporaryUrl($animal->microchip_image_path, now()->addMinutes(30))
                    : null,
                'pdf_url' => $animal->microchip_print_token && $animal->microchip_pdf_path
                    ? route('public.microchip-letters.print', $animal->microchip_print_token)
                    : null,
                'finalized_at' => $animal->microchip_finalized_at?->toISOString(),
            ],
            'vaccination_letters' => $animal->vaccinationLetters->map(fn ($letter) => [
                'id' => $letter->id,
                'date' => $letter->date?->toDateString(),
                'name' => 'Carta de vacunacion - ' . $letter->date?->format('d/m/Y'),
                'pdf_url' => $this->vaccinationPdfUrl($letter),
            ])->values(),
            'videos' => $animal->videos->map(fn ($video) => [
                'id' => $video->id,
                'video_date' => $video->video_date?->toDateString(),
                'notes' => $video->notes,
                'original_name' => $video->original_name,
                'url' => Storage::disk($video->disk)->temporaryUrl($video->path, now()->addMinutes(30)),
            ])->values(),
            'radiology_studies' => $animal->radiologyStudies->map(fn ($study) => [
                'id' => $study->id,
                'name' => $study->name,
                'study_date' => $study->study_date?->toDateString(),
                'notes' => $study->notes,
                'images' => $study->images->map(fn ($image) => [
                    'id' => $image->id,
                    'label' => $image->label,
                    'notes' => $image->notes,
                    'url' => Storage::disk($image->disk)->temporaryUrl($image->path, now()->addMinutes(30)),
                ])->values(),
            ])->values(),
            'shares' => $animal->shares->map(fn ($share) => [
                'id' => $share->id,
                'tenant_name' => $share->sharedWithTenant?->name,
                'url' => route('client.telemedicine.animals.show', $share->token),
            ])->values(),
            'reports' => $animal->reports->map(fn ($report) => [
                'id' => $report->id,
                'title' => $report->title,
                'report_date' => $report->report_date?->toDateString(),
                'content_html' => $report->content_html,
                'content_text' => trim(strip_tags($report->content_html)),
                'status' => $report->status,
                'author_name' => $report->author?->name,
                'images_count' => $report->images->count(),
                'pdf_url' => $report->status === 'finalized' && $report->public_token
                    ? route('public.animal-reports.pdf', $report->public_token)
                    : null,
            ])->values(),
        ];
    }

    private function validatedReportData(Request $request, RichTextSanitizer $sanitizer): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'content_html' => ['required', 'string', 'max:200000'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'intent' => ['required', 'in:draft,finalize'],
        ]);
        $data['content_html'] = $sanitizer->sanitize($data['content_html']);
        $plainText = trim(str_replace("\xc2\xa0", ' ', html_entity_decode(strip_tags($data['content_html']))));
        if ($plainText === '') {
            throw ValidationException::withMessages(['content_html' => 'Escribe el contenido clinico del reporte.']);
        }

        return $data;
    }

    private function storeReportImages(
        Request $request,
        AnimalReport $report,
        AnimalReportImageOptimizer $imageOptimizer
    ): void {
        $files = $request->file('images', []);
        if ($report->images()->count() + count($files) > 10) {
            throw ValidationException::withMessages(['images' => 'El reporte puede contener un maximo de 10 imagenes.']);
        }

        $position = (int) $report->images()->max('position');
        foreach ($files as $file) {
            $position++;
            $path = "tenants/{$report->tenant_id}/animals/{$report->animal_id}/reports/{$report->id}/images/".Str::uuid().'.webp';
            $contents = $imageOptimizer->optimize($file);
            Storage::disk('r2')->put($path, $contents, ['mimetype' => 'image/webp']);
            try {
                AnimalReportImage::create([
                    'tenant_id' => $report->tenant_id,
                    'animal_report_id' => $report->id,
                    'disk' => 'r2',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => 'image/webp',
                    'size' => strlen($contents),
                    'position' => $position,
                ]);
            } catch (\Throwable $exception) {
                Storage::disk('r2')->delete($path);
                throw $exception;
            }
        }
    }

    private function authorizeReport(Request $request, AnimalReport $report): void
    {
        abort_unless($report->tenant_id === $request->user()->tenant_id, 404);
        abort_unless($report->animal?->tenant_id === $request->user()->tenant_id, 404);
    }

    private function vaccinationPdfUrl(VaccinationLetter $letter): string
    {
        $relativeUrl = URL::temporarySignedRoute(
            'public.vaccination-letters.print',
            now()->addDays(7),
            ['vaccinationLetter' => $letter->id],
            false
        );

        return request()->getSchemeAndHttpHost() . $relativeUrl;
    }
}
