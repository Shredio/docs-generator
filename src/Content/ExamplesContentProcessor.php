<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use ReflectionClass;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\TypeSchema\TypeSchema;

final readonly class ExamplesContentProcessor implements ContentProcessor
{

	public function process(string $content, ProcessContext $context): string
	{
		return $content;
	}

	public function postprocess(string $content, PostprocessContext $context): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'examples' => $s->optional($s->nonEmptyList(
				$s->union([
					$s->nonEmptyString(),
					$s->arrayShape([
						'class' => $s->nonEmptyString(),
						'contains' => $s->optional($s->nonEmptyList($s->nonEmptyString())),
					]),
					$s->arrayShape([
						'file' => $s->nonEmptyString(),
					]),
				]),
			)),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);
		if (!isset($values['examples'])) {
			return $content;
		}

		$content .= "\n\n## Examples\n\n";
		$content .= "Source files with usage examples:\n\n";

		$examples = [];
		foreach ($values['examples'] as $value) {
			if (is_string($value) || isset($value['class'])) {
				$className = is_string($value) ? $value : $value['class'];

				if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
					throw new GeneratingFailedException(sprintf('Example class "%s" does not exist.', $className));
				}

				$reflectionClass = new ReflectionClass($className);
				$fileName = $reflectionClass->getFileName();
				if ($fileName === false) {
					throw new GeneratingFailedException(sprintf('Example class "%s" does not have a file name.', $className));
				}

				if (is_array($value) && isset($value['contains'])) {
					$contents = file_get_contents($fileName);
					if ($contents === false) {
						throw new GeneratingFailedException(sprintf('Unable to read file for class "%s".', $className));
					}

					foreach ($value['contains'] as $constraint) {
						if (!str_contains($contents, $constraint)) {
							throw new GeneratingFailedException(sprintf(
								'Example for class "%s" does not contain required string "%s".',
								$className,
								$constraint,
							));
						}
					}
				}

				$exampleFilePath = $context->pathFactory->createFileFromAbsolute($fileName);

				$examples[] = sprintf('- `%s` - [%s](%s)', $className, $exampleFilePath->getRelativePathToRootDir(), $exampleFilePath->relativeTo($context->sourcePath));

				continue;
			}

			$exampleFilePath = $context->pathFactory->createFileFromRelative($value['file']);

			$examples[] = sprintf('- [%s](%s)', $exampleFilePath->getRelativePathToRootDir(), $exampleFilePath->relativeTo($context->sourcePath));
		}

		$content .= implode("\n", $examples);
		return $content;
	}

}
