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
     * Minimal library entry point hosting the Import modal. Full browsing
     * (cards, filters, search) is out of scope for this story — see
     * Story 1.2.
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
