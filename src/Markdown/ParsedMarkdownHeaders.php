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

	/**
	 * @return list<MarkdownHeader>
	 */
	public function getHeadersByName(string $name): array
	{
		return array_values(array_filter($this->headers, fn(MarkdownHeader $header): bool => $header->name === $name));
	}

	public function getFirstHeaderByName(string $name): ?MarkdownHeader
	{
		foreach ($this->headers as $header) {
			if ($header->name === $name) {
				return $header;
			}
		}

		return null;
	}

}
