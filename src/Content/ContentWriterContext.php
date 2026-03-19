<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Collector\DataCollector;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Path\PathFactory;
use Shredio\TypeSchema\Exception\AssertException;
use Shredio\TypeSchema\Types\Type;
use Shredio\TypeSchema\TypeSchemaProcessor;

final readonly class ContentWriterContext
{

	/**
	 * @param mixed[] $frontmatter
	 */
	public function __construct(
		public array $frontmatter,
		public bool $isMainFile,
		public DataCollector $dataCollector,
		public PathFactory $pathFactory,
		private TypeSchemaProcessor $schemaProcessor,
	)
	{
	}

	/**
	 * @template T
	 * @param Type<T> $schema
	 * @param mixed $value
	 * @return T
	 * @throws GeneratingFailedException
	 */
	public function processSchema(Type $schema, mixed $value): mixed
	{
		try {
			return $this->schemaProcessor->process($value, $schema);
		} catch (AssertException $exception) {
			throw new GeneratingFailedException("Invalid frontmatter:\n" . $exception->toPrettyString());
		}
	}

}
