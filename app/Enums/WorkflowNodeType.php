<?php

namespace App\Enums;

enum WorkflowNodeType: string
{
    case Trigger = 'trigger';
    case Action = 'action';
    case End = 'end';
}
