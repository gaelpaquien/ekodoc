<?php

namespace App\Enums;

enum DocumentSource: string
{
    case Imported = 'imported';
    case Created = 'created';
}
