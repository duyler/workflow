<?php

declare(strict_types=1);

namespace Duyler\Workflow\State;

enum WorkflowStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
