<?php declare(strict_types=1);

namespace Shredio\DocsGenerator\Markdown;

final readonly class MarkdownVariable
{
    public function __construct(
        public string $name,
        public int $startPosition,
        public int $endPosition,
    ) {
    }
}