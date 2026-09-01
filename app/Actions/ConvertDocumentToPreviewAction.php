<?php

namespace App\Actions;

use App\DataTransferObjects\ConvertDocumentToPreviewData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * On-demand, cached Word/Excel -> PDF conversion for the preview panel
 * (AD "Office preview via on-demand, cached conversion"). Shells out to
 * LibreOffice headless (`soffice --headless --convert-to pdf`) and writes
 * the result at storage/app/private/previews/{document_id}.pdf.
 *
 * Never throws: a missing/misconfigured binary, a non-zero exit code, a
 * corrupted source file, or a filesystem error are all reported back as a
 * plain boolean so the controller can surface an explicit "preview
 * unavailable" state instead of an uncaught exception (Boundaries &
 * Constraints, spec-1-3) — the whole body runs inside a single try/catch.
 *
 * Converts into a document-id-scoped temp subdirectory
 * (`previews/tmp/{documentId}/`), never the shared `previews/` root:
 * LibreOffice names its output after the *source* file's basename, not the
 * document id, so two concurrent conversions for source files that happen
 * to share a basename could otherwise cross-write the same intermediate
 * path. Combined with the caller serializing conversion per document (a
 * cache lock in DocumentController::previewOfficeDocument()), this makes
 * the intermediate write collision-proof.
 */
class ConvertDocumentToPreviewAction
{
    private const PREVIEW_DIRECTORY = 'previews';

    private const TEMP_DIRECTORY = 'previews/tmp';

    public function __invoke(ConvertDocumentToPreviewData $data): bool
    {
        $disk = Storage::disk('local');
        $tempDirectory = self::TEMP_DIRECTORY."/{$data->documentId}";

        try {
            $disk->makeDirectory($tempDirectory);

            $sourceAbsolutePath = $disk->path($data->sourcePath);
            $outputAbsoluteDirectory = $disk->path($tempDirectory);
            $destinationRelativePath = self::PREVIEW_DIRECTORY."/{$data->documentId}.pdf";

            $result = Process::timeout(120)->run([
                config('services.libreoffice.binary', 'soffice'),
                '--headless',
                '--convert-to', 'pdf',
                '--outdir', $outputAbsoluteDirectory,
                $sourceAbsolutePath,
            ]);

            if (! $result->successful()) {
                Log::warning('Document preview conversion via soffice exited with an error.', [
                    'document_id' => $data->documentId,
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                ]);

                return false;
            }

            // soffice writes {original-basename}.pdf into --outdir, never
            // the {document_id}.pdf name the rest of the app expects —
            // locate it (in this document's own temp directory, so it can
            // never collide with another document's conversion) and move
            // it into place.
            $convertedName = pathinfo($sourceAbsolutePath, PATHINFO_FILENAME).'.pdf';
            $convertedAbsolutePath = rtrim($outputAbsoluteDirectory, '\\/').DIRECTORY_SEPARATOR.$convertedName;

            if (! is_file($convertedAbsolutePath)) {
                Log::warning('Document preview conversion reported success but produced no output file.', [
                    'document_id' => $data->documentId,
                    'expected_path' => $convertedAbsolutePath,
                ]);

                return false;
            }

            $destinationAbsolutePath = $disk->path($destinationRelativePath);

            if (! rename($convertedAbsolutePath, $destinationAbsolutePath)) {
                Log::warning('Document preview conversion succeeded but the converted file could not be moved into place.', [
                    'document_id' => $data->documentId,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Document preview conversion failed.', [
                'document_id' => $data->documentId,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            $disk->deleteDirectory($tempDirectory);
        }
    }
}
