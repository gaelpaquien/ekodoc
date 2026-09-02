<?php

namespace App\Http\Middleware;

use App\Enums\ExtractionStatus;
use App\Models\Category;
use App\Models\Document;
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
            // Powers CategoryPicker.vue (Import modal + Document Detail),
            // shared globally so creating a category from either context
            // updates the picker without a full remount (Design Notes,
            // spec-1-5).
            'categories' => fn () => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
