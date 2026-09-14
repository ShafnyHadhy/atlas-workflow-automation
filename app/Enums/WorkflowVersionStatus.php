<?php

namespace App\Enums;

enum WorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
