<?php

namespace App\Http\Middleware;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\Tag;
use App\Support\DocumentMimeTypes;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Powers the extraction-tasks panel (App/Components/ExtractionTasksPanel.vue):
            // documents whose text extraction is still queued or running (AD-6).
            'pendingExtractions' => fn () => Document::query()
                ->whereIn('extraction_status', [ExtractionStatus::Pending, ExtractionStatus::Processing])
                ->orderBy('created_at')
                ->get(['id', 'title', 'extraction_status']),
            // Powers TagSelector.vue everywhere it's mounted (Import modal,
            // editor, Document Detail, Library filter) — the full list of
            // already-existing tags it's allowed to offer (Boundaries &
            // Constraints, spec-3-1: no free-text creation there). Tag
            // management itself is out of scope for this story (story 3.5);
            // rows come from TagFactory/tinker in the meantime.
            'tags' => fn () => Tag::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            // Powers Index.vue's type filter (`TYPE_OPTIONS`, pdf/word/excel
            // entries only) — same pattern as `tags` above. Derived
            // from DocumentMimeTypes::TYPE_TO_MIME/TYPE_LABELS, the single
            // source of truth also used server-side by DocumentController
            // (Epic 1/2 retrospectives, action item 3). `created` is
            // deliberately absent here too: it stays a client-only entry in
            // Index.vue (not tied to a mime type).
            'documentTypeOptions' => fn () => collect(DocumentMimeTypes::TYPE_TO_MIME)
                ->keys()
                ->map(fn (string $type) => [
                    'value' => $type,
                    'label' => DocumentMimeTypes::TYPE_LABELS[$type] ?? $type,
                ])
                ->values(),
            // Sole channel back from the image-upload endpoint to the
            // editor (AD-13, spec-2-2: `return back()`, never
            // `response()->json()`) — Laravel's own flash bag ages this
            // out automatically after the one request that follows the
            // redirect, so no manual cleanup is needed here.
            //
            // `uploadedAttachment` mirrors it exactly for a draft attachment
            // upload (AD-13, spec-3-3: DocumentController::storeEditorAttachment()).
            'flash' => fn () => [
                'uploadedImage' => session('uploadedImage'),
                'uploadedAttachment' => session('uploadedAttachment'),
            ],
        ];
    }
}
