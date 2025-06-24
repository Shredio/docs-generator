<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Markdown;

final readonly class ParsedMarkdownHeaders
{
    /**
     * @param list<MarkdownHeader> $headers
     */
    public function __construct(
        public array $headers,
        public string $content,
    ) {
    }
}
