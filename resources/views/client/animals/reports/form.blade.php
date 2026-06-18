@php
    $reportModel = $report ?? null;
    $formAction = $reportModel
        ? route('client.animal-reports.update', $reportModel)
        : route('client.animals.reports.store', $animal);
    $editorId = $reportModel ? 'report-editor-'.$reportModel->id : 'report-editor-new';
    $initialContent = app(\App\Services\RichTextSanitizer::class)
        ->sanitize(old('content_html', $reportModel?->content_html ?? ''));
@endphp

<form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" data-report-form class="space-y-5">
    @csrf
    @if($reportModel)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="space-y-2">
            <label class="block text-[10px] font-black uppercase tracking-widest theme-text-heading">Titulo *</label>
            <input type="text" name="title" value="{{ old('title', $reportModel?->title) }}" required maxlength="255" class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold theme-text-heading outline-none theme-input focus:bg-white focus:ring-4 theme-ring-primary" placeholder="Ej. Reporte de consulta">
        </div>
        <div class="space-y-2">
            <label class="block text-[10px] font-black uppercase tracking-widest theme-text-heading">Fecha del reporte *</label>
            <input type="date" name="report_date" value="{{ old('report_date', $reportModel?->report_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold theme-text-heading outline-none theme-input focus:bg-white focus:ring-4 theme-ring-primary">
        </div>
    </div>

    <div class="space-y-2">
        <label class="block text-[10px] font-black uppercase tracking-widest theme-text-heading">Contenido clinico *</label>
        <div id="{{ $editorId }}" data-quill-editor class="hidden min-h-[280px] bg-white">{!! $initialContent !!}</div>
        <textarea name="content_html" data-quill-content rows="12" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold theme-text-heading outline-none theme-input focus:ring-4 theme-ring-primary">{{ $initialContent }}</textarea>
        @error('content_html')<p class="text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="space-y-2">
        <label class="block text-[10px] font-black uppercase tracking-widest theme-text-heading">Imagenes clinicas / RX</label>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="block w-full text-xs font-bold text-slate-500 file:mr-3 file:rounded-xl file:border-0 theme-file-input file:px-4 file:py-2.5 file:text-xs file:font-black file:uppercase file:tracking-widest file:text-white">
        <p class="text-[10px] font-semibold text-slate-400">Hasta 10 imagenes JPG, PNG o WEBP; maximo 15 MB cada una.</p>
        @error('images.*')<p class="text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800">
        Al finalizar se generara el PDF definitivo. El reporte y sus imagenes ya no podran modificarse.
    </div>

    <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end">
        <button type="submit" name="intent" value="draft" class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-600 hover:bg-slate-50">
            Guardar borrador
        </button>
        <button type="submit" name="intent" value="finalize" onclick="return confirm('Finalizar el reporte? Despues no podra editarse.')" class="rounded-xl theme-button-primary px-5 py-3 text-[10px] font-black uppercase tracking-[0.2em]">
            Finalizar y generar PDF
        </button>
    </div>
</form>

@once
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
        <style>
            .ql-toolbar.ql-snow { border-color: #e2e8f0; border-radius: 0.75rem 0.75rem 0 0; }
            .ql-container.ql-snow { border-color: #e2e8f0; border-radius: 0 0 0.75rem 0.75rem; font-family: inherit; font-size: 0.875rem; }
            .ql-editor { min-height: 280px; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof Quill === 'undefined') return;

                document.querySelectorAll('[data-report-form]').forEach((form) => {
                    const editorElement = form.querySelector('[data-quill-editor]');
                    const contentInput = form.querySelector('[data-quill-content]');
                    if (!editorElement || !contentInput || editorElement.dataset.ready) return;

                    editorElement.dataset.ready = 'true';
                    editorElement.classList.remove('hidden');
                    contentInput.classList.add('hidden');
                    const quill = new Quill(editorElement, {
                        theme: 'snow',
                        placeholder: 'Describe los hallazgos, diagnostico, tratamiento y recomendaciones...',
                        modules: {
                            toolbar: [
                                [{ header: [1, 2, 3, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ list: 'ordered' }, { list: 'bullet' }],
                                [{ indent: '-1' }, { indent: '+1' }],
                                [{ align: [] }],
                                ['blockquote', 'link'],
                                ['clean']
                            ]
                        }
                    });

                    const syncContent = () => {
                        contentInput.value = quill.root.innerHTML;
                    };
                    quill.on('text-change', syncContent);
                    syncContent();

                    form.addEventListener('submit', () => {
                        syncContent();
                        const root = form.closest('[x-data]');
                        if (root && window.Alpine) Alpine.$data(root).reportSaving = true;
                    });
                });
            });
        </script>
    @endpush
@endonce
