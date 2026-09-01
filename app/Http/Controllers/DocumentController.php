<?php

namespace App\Http\Controllers;

use App\Actions\ImportDocumentAction;
use App\DataTransferObjects\ImportDocumentData;
use App\Http\Requests\ImportDocumentRequest;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * Library entry point: renders one card per document (type badge,
     * title, category placeholder, date), sorted most recent first, and
     * hosts the Import modal. Search, filters, pagination and category
     * assignment remain out of scope — see Stories 1.5/1.6/1.7.
     */
    public function index(): Response
    {
        return Inertia::render('Documents/Index', [
            'documents' => Document::query()
                ->latest()
                ->get(['id', 'title', 'source', 'mime_type', 'created_at']),
        ]);
    }

    public function store(ImportDocumentRequest $request, ImportDocumentAction $action): RedirectResponse
    {
        $document = $action(new ImportDocumentData(
            file: $request->file('file'),
        ));

        return to_route('documents.show', $document);
    }

    public function show(Document $document): Response
    {
        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'source' => $document->source,
                'mime_type' => $document->mime_type,
                'created_at' => $document->created_at,
            ],
        ]);
    }
}
