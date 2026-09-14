<?php

namespace App\Http\Controllers;

use App\Actions\CreateTagAction;
use App\Actions\DeleteTagAction;
use App\Actions\RenameTagAction;
use App\DataTransferObjects\CreateTagData;
use App\DataTransferObjects\DeleteTagData;
use App\DataTransferObjects\RenameTagData;
use App\Http\Requests\CreateTagRequest;
use App\Http\Requests\RenameTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    /**
     * Configuration surface entry point (FR14, spec-3-5) — lists every
     * existing tag with its document count. This `tags` prop is a shape
     * specific to this page render (`id`, `name`, `documents_count`) that
     * takes precedence over the shared minimal `tags` prop
     * (HandleInertiaRequests: `id`, `name`) for this render only — Inertia
     * merges shared props then page props, the page wins (Design Notes).
     */
    public function index(): Response
    {
        // Sorted in PHP via `Str::lower()` rather than `orderBy('name')`
        // (retrospective Epic 3, action item 10) — same rationale as the
        // duplicate check in CreateTagRequest: SQLite's `LOWER()` only folds
        // ASCII, so an accented name (e.g. "École") would sort differently
        // there than on a driver with full Unicode collation. Must stay in
        // lockstep with the shared `tags` prop's own sort
        // (HandleInertiaRequests::share()) — same mechanism in both.
        // `orderBy('id')` gives a deterministic base order before the PHP
        // sort (SQL makes no ordering guarantee without an ORDER BY), so two
        // tags whose names differ only by case get a stable, reproducible
        // tie-break instead of depending on incidental storage-engine order.
        // `SORT_STRING` keeps the comparison lexicographic even for a
        // purely-numeric tag name (SORT_REGULAR would compare "9"/"10"
        // arithmetically instead of as strings).
        $tags = Tag::withCount('documents')->orderBy('id')->get()
            ->sortBy(fn (Tag $tag) => Str::lower($tag->name), SORT_STRING)
            ->values();

        return Inertia::render('Documents/Configuration', [
            'tags' => $tags,
        ]);
    }

    /**
     * Sole entry point for creating a tag — CreateTagRequest already
     * rejected a case-insensitive duplicate before this runs.
     */
    public function store(CreateTagRequest $request, CreateTagAction $action): RedirectResponse
    {
        $action(new CreateTagData(
            name: $request->validated('name'),
        ));

        return back();
    }

    /**
     * Sole entry point for renaming a tag — RenameTagRequest already
     * rejected a case-insensitive duplicate (excluding this tag itself)
     * before this runs.
     */
    public function update(RenameTagRequest $request, Tag $tag, RenameTagAction $action): RedirectResponse
    {
        $action(new RenameTagData(
            tag: $tag,
            name: $request->validated('name'),
        ));

        return back();
    }

    /**
     * Sole entry point for deleting a tag — the document count captured by
     * DeleteTagAction (before the row, and the cascaded pivot rows, are
     * gone) is flashed alongside the tag's name so Configuration.vue can
     * render the factual post-deletion message (Boundaries & Constraints:
     * "Tag supprimé — détaché de N documents."), same
     * flash-then-redirect pattern as `uploadedImage`/`uploadedAttachment`.
     */
    public function destroy(Tag $tag, DeleteTagAction $action): RedirectResponse
    {
        $name = $tag->name;
        $count = $action(new DeleteTagData(
            tag: $tag,
        ));

        return back()->with('tagDeleted', ['name' => $name, 'count' => $count]);
    }
}
