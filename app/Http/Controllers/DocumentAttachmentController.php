<?php

namespace App\Http\Controllers;

use App\Actions\AttachDocumentFileAction;
use App\Actions\DetachDocumentFileAction;
use App\DataTransferObjects\AttachDocumentFileData;
use App\DataTransferObjects\DetachDocumentFileData;
use App\Http\Requests\AttachDocumentFileRequest;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sole entry point for attaching/detaching/previewing/downloading a file on
 * an already-saved document (spec-3-3, FR13) — a not-yet-saved draft goes
 * through DocumentController::storeEditorAttachment() instead (mirroring
 * storeEditorImage() vs. this controller's split from DocumentController
 * proper, kept separate since it's a full resource of its own rather than
 * one more method bolted onto DocumentController).
 *
 * `{attachment}` has no implicit scoping to `{document}` configured in this
 * repo (Code Map: "pas de scoping implicite vérifié dans ce repo") — every
 * action here explicitly guards that the resolved attachment actually
 * belongs to the resolved document before doing anything with it.
 */
class DocumentAttachmentController extends Controller
{
    /**
     * User-uploaded content is streamed inline — mirrors
     * DocumentController::PREVIEW_RESPONSE_HEADERS exactly.
     */
    private const PREVIEW_RESPONSE_HEADERS = ['X-Content-Type-Options' => 'nosniff'];

    /**
     * Sole entry point for FR13's immediate attach — always delegates to
     * AttachDocumentFileAction, never writes `document_attachments` itself.
     * Communicates back to the Editor exclusively through a standard
     * Inertia redirect (`back()`), which triggers the panel's own reload of
     * `document.attachments` — never `response()->json()` (AD-13).
     */
    public function store(AttachDocumentFileRequest $request, Document $document, AttachDocumentFileAction $attach): RedirectResponse
    {
        $attach(new AttachDocumentFileData(
            document: $document,
            file: $request->file('file'),
        ));

        return back();
    }

    /**
     * Sole entry point for FR13's immediate detach — always delegates to
     * DetachDocumentFileAction, never removes the file/row itself directly.
     */
    public function destroy(Document $document, DocumentAttachment $attachment, DetachDocumentFileAction $detach): RedirectResponse
    {
        abort_unless($attachment->document_id === $document->id, 404);

        $detach(new DetachDocumentFileData(attachment: $attachment));

        return back();
    }

    /**
     * Streams an attachment inline for the browser's built-in viewer — no
     * Office→PDF conversion, unlike DocumentController::preview() (Boundaries
     * & Constraints: "pas de conversion Office→PDF pour l'aperçu d'une
     * pièce jointe"), so a `.docx`/`.xlsx` attachment streams its raw bytes
     * directly, same as a PDF one. Guards the same way
     * DocumentController::preview()/download() do via `sourceMissing()`
     * (Code Map: "même garde") — a missing/corrupted file resolves to a
     * clean 404 instead of an unhandled exception.
     */
    public function preview(Document $document, DocumentAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->document_id === $document->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->original_filename,
            self::PREVIEW_RESPONSE_HEADERS,
        );
    }

    /**
     * Downloads the attachment's original file — mirrors
     * DocumentController::download() exactly, just scoped to one attachment
     * of the given document instead of the document's own source file.
     */
    public function download(Document $document, DocumentAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->document_id === $document->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_filename,
        );
    }
}
