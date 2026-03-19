<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Path\ResolvedPath;
use Shredio\TypeSchema\TypeSchema;

final readonly class DocsContentWriter implements ContentWriter
{

	public function __construct(
		private ?ResolvedPath $docsPath = null,
	)
	{
	}

	/**
	 * @return list<ContentToWrite>
	 */
	public function write(string $content, ContentWriterContext $context): array
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'docs' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
				'description' => $s->nonEmptyString(),
			])),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);
		if (!isset($values['docs'])) {
			return [];
		}

		if (!str_ends_with($values['docs']['target'], '.md')) {
			throw new GeneratingFailedException('Docs target must be a markdown file with ".md" extension.');
		}

		if ($this->docsPath === null) {
			throw new GeneratingFailedException('Docs base path is not set, cannot create docs.');
		}

		$relativePath = sprintf('%s/%s', $this->docsPath->getRelativePathToRootDir(), ltrim($values['docs']['target'], '/'));

		$context->dataCollector->addDoc(
			$values['docs']['target'],
			$relativePath,
			$values['docs']['description'],
		);

		return [
			new ContentToWrite($relativePath, $content),
		];
	}

}
