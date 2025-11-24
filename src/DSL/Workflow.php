<?php

declare(strict_types=1);

namespace Duyler\Workflow\DSL;

final class Workflow
{
    /** @var array<Step> */
    private array $steps = [];

    private ?string $description = null;

    private function __construct(
        private readonly string $id,
    ) {}

    public static function define(string $id): self
    {
        return new self($id);
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function sequence(Step ...$steps): self
    {
        $this->steps = $steps;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }
}
