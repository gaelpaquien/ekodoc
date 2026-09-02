<?php

namespace App\Http\Controllers;

use App\Actions\CreateCategoryAction;
use App\DataTransferObjects\CreateCategoryData;
use App\Http\Requests\CreateCategoryRequest;
use Illuminate\Http\RedirectResponse;

class CategoryController extends Controller
{
    /**
     * Creates a category inline from the CategoryPicker (Import modal or
     * Document Detail) and redirects back to whichever page it was called
     * from. No JSON, no id in the response body: the shared `categories`
     * Inertia prop (HandleInertiaRequests) refreshes on this same
     * redirect, and the picker preselects the new category by name
     * (Design Notes, spec-1-5) rather than depending on an id returned
     * out of band.
     */
    public function store(CreateCategoryRequest $request, CreateCategoryAction $action): RedirectResponse
    {
        $action(new CreateCategoryData(
            name: $request->validated('name'),
        ));

        return back();
    }
}
