<?php

declare(strict_types=1);

namespace Duyler\Workflow\Contract;

use UnitEnum;

interface ActionResolverInterface
{
    public function resolve(string $actionId): string|UnitEnum;

    public function exists(string $actionId): bool;
}
