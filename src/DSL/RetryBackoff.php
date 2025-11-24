<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

enum RetryBackoff: string
{
    case Fixed = 'fixed';
    case Linear = 'linear';
    case Exponential = 'exponential';
}
