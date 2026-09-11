<?php

namespace App\DataTransferObjects;

final readonly class CreateTagData
{
    public function __construct(
        public string $name,
    ) {
    }
}
