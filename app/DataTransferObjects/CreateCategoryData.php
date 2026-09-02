<?php

namespace App\DataTransferObjects;

final readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
    ) {
    }
}
