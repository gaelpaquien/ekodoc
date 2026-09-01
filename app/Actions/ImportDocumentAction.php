<?php

namespace App\Actions;

use App\DataTransferObjects\ImportDocumentData;
use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Jobs\ExtractDocumentTextJob;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sole write point for imported documents (AD-2, AD-7, AD-9).
 *
 * Stores the original file on the private disk and persists the Document
 * row within the same synchronous request (AD-6), then dispatches text
 * extraction as a queued job instead of running it inline: a large or
 * complex real-world document can take longer to parse than any HTTP
 * request/proxy timeout should reasonably allow. The import itself
 * returns as soon as the file is safely stored — extraction happening
 * later, or failing entirely, can never undo it (AD-9).
 *
 * A storage failure, in contrast, is not tolerated silently: Document
 * creation + file write are wrapped in a transaction so no orphaned or
 * corrupt row is ever left behind.
 */
class ImportDocumentAction
{
    public function __invoke(ImportDocumentData $data): Document
    {
        $document = DB::transaction(function () use ($data) {
            $document = Document::create([
                'title' => $data->file->getClientOriginalName(),
                'source' => DocumentSource::Imported,
                'mime_type' => $data->file->getMimeType(),
                'extraction_status' => ExtractionStatus::Pending,
            ]);

            $document->forceFill([
                'file_path' => $this->storeFile($data, $document),
            ])->save();

            return $document;
        });

        ExtractDocumentTextJob::dispatch($document);

        return $document;
    }

    /**
     * Stores the original file on the private disk. Any failure — a thrown
     * exception, or `storeAs()` returning `false` (the `local` disk is
     * configured with `throw => false`) — cleans up the destination
     * directory, logs a clear error, and rethrows so the enclosing
     * transaction rolls back the just-created Document row.
     */
    private function storeFile(ImportDocumentData $data, Document $document): string
    {
        $filename = basename($data->file->getClientOriginalName());
        $directory = "documents/{$document->id}";

        try {
            $path = $data->file->storeAs($directory, $filename, 'local');

            if ($path === false) {
                throw new RuntimeException('Storage::storeAs() returned false for the uploaded file.');
            }

            return $path;
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($directory);

            Log::error('Document import failed while storing the original file on the private disk.', [
                'document_id' => $document->id,
                'filename' => $filename,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
