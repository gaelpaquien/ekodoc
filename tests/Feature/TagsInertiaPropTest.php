<?php

use App\Models\Tag;

// Retrospective Epic 3, action item 11: no test previously tied a tag
// mutation to the shared `tags` Inertia prop (HandleInertiaRequests::share())
// that TagSelector.vue reads everywhere it's mounted — as opposed to
// TagController::index()'s own page-specific `tags` prop, already covered by
// ManageTagsTest.php. `/` (Documents/Index) never overrides the shared prop
// (DocumentController::index() renders only `documents`,
// spec-nettoyage-sidebar-et-page-documents), so it's read here through a
// real Inertia response, same pattern as ManageTagsTest's own
// flash.tagDeleted assertion.
it('reflects a tag rename in the shared tags Inertia prop', function () {
    $tag = Tag::factory()->create(['name' => 'Fiance']);

    test()->patch("/tags/{$tag->id}", ['name' => 'Finance']);

    test()->get('/')->assertInertia(fn ($page) => $page
        ->where('tags', fn ($tags) => $tags->firstWhere('id', $tag->id)['name'] === 'Finance')
    );
});

it('reflects a tag deletion in the shared tags Inertia prop', function () {
    $tag = Tag::factory()->create();

    test()->delete("/tags/{$tag->id}");

    test()->get('/')->assertInertia(fn ($page) => $page
        ->where('tags', fn ($tags) => $tags->doesntContain(fn ($candidate) => $candidate['id'] === $tag->id))
    );
});

// Retrospective Epic 3, action item 10: the shared prop must sort in
// lockstep with TagController::index() (Boundaries & Constraints) — same
// golden order asserted in ManageTagsTest's equivalent case-insensitive test.
it('orders the shared tags prop case-insensitively, matching TagController::index()', function () {
    Tag::factory()->create(['name' => 'Banane']);
    Tag::factory()->create(['name' => 'abricot']);
    Tag::factory()->create(['name' => 'cerise']);

    test()->get('/')->assertInertia(fn ($page) => $page
        ->where('tags.0.name', 'abricot')
        ->where('tags.1.name', 'Banane')
        ->where('tags.2.name', 'cerise')
    );
});
