<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\TypeSchema\TypeSchema;

final readonly class OutputContentWriter implements ContentWriter
{
	/**
	 * @return list<ContentToWrite>
	 */
	public function write(string $content, ContentWriterContext $context): array
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'output' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
			])),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);
		if (!isset($values['output'])) {
			return [];
		}

		if (!str_ends_with($values['output']['target'], '.md')) {
			throw new GeneratingFailedException('Output target must be a markdown file with ".md" extension.');
		}

		return [
			new ContentToWrite($values['output']['target'], $content),
		];
	}

}
