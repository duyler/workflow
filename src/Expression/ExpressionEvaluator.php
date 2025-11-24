<?php

declare(strict_types=1);

namespace Duyler\Workflow\Expression;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

final class ExpressionEvaluator
{
    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function evaluate(string $expression, array $variables = []): mixed
    {
        return $this->expressionLanguage->evaluate($expression, $variables);
    }

    public function isValid(string $expression): bool
    {
        try {
            $this->expressionLanguage->parse($expression, []);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
