<?php

namespace App\DataTransferObjects;

use App\Models\Tag;

final readonly class RenameTagData
{
    public function __construct(
        public Tag $tag,
        public string $name,
    ) {
    }
}
