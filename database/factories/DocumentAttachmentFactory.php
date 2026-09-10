<?php

namespace Database\Factories;

use App\Enums\ExtractionStatus;
use App\Models\Document;
use App\Models\DocumentAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentAttachment>
 */
class DocumentAttachmentFactory extends Factory
{
    protected $model = DocumentAttachment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement([
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $filename = fake()->uuid().'.'.$this->extensionFor($mimeType);

        return [
            'document_id' => Document::factory(),
            // A placeholder directory here — real attachment paths are
            // always `documents/{document_id}/attachments/{filename}`
            // (code review finding), and `document_id` isn't resolved to a
            // real id yet at this point (`Document::factory()` above is
            // still an unresolved nested factory). Rebuilt with the real
            // id in `configure()`'s `afterMaking()` below, once Laravel has
            // resolved it.
            'file_path' => "documents/_pending/attachments/{$filename}",
            'original_filename' => ucfirst(fake()->words(2, true)).'.'.$this->extensionFor($mimeType),
            'mime_type' => $mimeType,
            'extracted_text' => fake()->paragraph(),
            'extraction_status' => ExtractionStatus::Completed,
        ];
    }

    /**
     * Rebuilds `file_path` using the actual `document_id` once Laravel has
     * resolved it (the nested `Document::factory()` in `definition()` above
     * is only resolved to a real id by the time a model is made, not while
     * `definition()` itself runs) — so any test that doesn't explicitly
     * override `file_path` gets the realistic `documents/{document_id}/
     * attachments/{filename}` shape every real attachment actually has,
     * instead of an unrelated random directory segment (code review
     * finding).
     */
    public function configure(): static
    {
        return $this->afterMaking(function (DocumentAttachment $attachment) {
            $filename = basename($attachment->file_path);
            $attachment->file_path = "documents/{$attachment->document_id}/attachments/{$filename}";
        });
    }

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }
}
