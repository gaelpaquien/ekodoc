<?php

namespace Database\Factories;

use App\Enums\DocumentSource;
use App\Enums\ExtractionStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

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

        return [
            'title' => ucfirst(fake()->words(3, true)).'.'.$this->extensionFor($mimeType),
            'source' => DocumentSource::Imported,
            'file_path' => 'documents/'.fake()->uuid().'/'.fake()->word(),
            'mime_type' => $mimeType,
            'extracted_text' => fake()->paragraph(),
            'extraction_status' => ExtractionStatus::Completed,
        ];
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
