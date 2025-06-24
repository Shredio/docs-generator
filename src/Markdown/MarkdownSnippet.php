<?php declare(strict_types=1);

namespace Shredio\DocsGenerator\Markdown;

final readonly class MarkdownSnippet
{
    /**
     * @param non-empty-list<string> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public int $startPosition,
        public int $endPosition,
    ) {
    }
}
