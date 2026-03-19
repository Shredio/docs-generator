<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\File;

use Nette\Utils\Finder;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Markdown\MarkdownFrontmatterParser;
use Shredio\DocsGenerator\Path\PathFactory;
use Shredio\TypeSchema\Exception\AssertException;
use Shredio\TypeSchema\Types\Type;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;

final readonly class MarkdownFileProvider implements FileProvider
{

	/**
	 * @param non-empty-string $path
	 * @param list<non-empty-string> $filesToSkip
	 */
	public function __construct(
		private PathFactory $pathFactory,
		private TypeSchemaProcessor $schemaProcessor,
		private string $path,
		private array $filesToSkip = [],
	)
	{
	}

	/**
	 * @throws GeneratingFailedException
	 */
	public function provide(): iterable
	{
		foreach (Finder::findFiles('*.md')->from($this->path) as $file) {
			if (in_array($file->getBasename(), $this->filesToSkip, true)) {
				continue;
			}

			$sourcePath = $this->pathFactory->createDirectoryFromAbsolute($file->getPathname());
			$content = $file->read();
			$frontmatter = MarkdownFrontmatterParser::extract($content);
			if ($frontmatter === []) {
				throw new GeneratingFailedException(
					sprintf('File "%s" does not contain frontmatter.', $sourcePath->getRelativePathToRootDir())
				);
			}

			if ($content === '') {
				throw new GeneratingFailedException(
					sprintf('File "%s" is empty.', $sourcePath->getRelativePathToRootDir())
				);
			}

			yield new ProvidedFile($sourcePath, $frontmatter, $content, $this->getPriorityFromFrontmatter($frontmatter));
		}
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return int<-1, 10>
	 *
	 * @throws GeneratingFailedException
	 */
	private function getPriorityFromFrontmatter(array $frontmatter): int
	{
		if (!isset($frontmatter['metadata']) && !isset($frontmatter['main'])) {
			return 5;
		}

		$t = TypeSchema::get();
		$schema = $t->arrayShape([
			'priority' => $t->optional($t->intRange(0, 10)),
		]);

		$metadata = $this->processSchema($schema, $frontmatter['metadata'] ?? []);
		if (isset($frontmatter['main']) && $frontmatter['main']) {
			$metadata['priority'] = -1; // Lowest priority
		} else {
			$metadata['priority'] ??= 5;
		}

		return $metadata['priority'];
	}

	/**
	 * @template T
	 * @param Type<T> $schema
	 * @param mixed $value
	 * @return T
	 * @throws GeneratingFailedException
	 */
	private function processSchema(Type $schema, mixed $value): mixed
	{
		try {
			return $this->schemaProcessor->process($value, $schema);
		} catch (AssertException $exception) {
			throw new GeneratingFailedException("Invalid frontmatter:\n" . $exception->toPrettyString());
		}
	}

}
