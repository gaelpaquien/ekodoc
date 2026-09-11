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
        return Inertia::render('Documents/Configuration', [
            'tags' => Tag::withCount('documents')->orderBy('name')->get(),
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
