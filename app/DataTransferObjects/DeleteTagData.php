<?php

namespace App\DataTransferObjects;

use App\Models\Tag;

final readonly class DeleteTagData
{
    public function __construct(
        public Tag $tag,
    ) {
    }
}
