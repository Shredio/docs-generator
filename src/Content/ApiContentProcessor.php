<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use ReflectionClass;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\TypeSchema\TypeSchema;

final readonly class ApiContentProcessor implements ContentProcessor
{

	/** @var list<non-empty-string> */
	private const array ComposerDocFiles = ['llm.md', 'LLM.md', 'AGENTS.md', 'agents.md', 'README.md', 'readme.md'];

	public function process(string $content, ProcessContext $context): string
	{
		return $content;
	}

	public function postprocess(string $content, PostprocessContext $context): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'api' => $s->optional($s->nonEmptyList($s->union([
				$s->nonEmptyString(),
				$s->arrayShape([
					'class' => $s->nonEmptyString(),
					'visibilities' => $s->optional($s->nonEmptyList($s->nonEmptyString())), // deprecated
				]),
				$s->arrayShape([
					'composer' => $s->nonEmptyString(),
				]),
			]))),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);
		if (!isset($values['api'])) {
			return $content;
		}

		$content .= "\n\n## API Reference\n\n";
		$content .= "Source files for the API classes referenced in this document you have to read:\n\n";

		$apis = [];
		foreach ($values['api'] as $api) {
			if (is_array($api) && isset($api['composer'])) {
				$apis[] = $this->resolveComposerPackage($api['composer'], $context);

				continue;
			}

			if (is_array($api)) {
				$className = $api['class'];
			} else {
				$className = $api;
			}

			if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
				throw new GeneratingFailedException(sprintf('Api class "%s" does not exist.', $className));
			}

			$reflectionClass = new ReflectionClass($className);
			$fileName = $reflectionClass->getFileName();
			if ($fileName === false) {
				throw new GeneratingFailedException(sprintf('Api class "%s" does not have a file name.', $className));
			}

			$kind = match (true) {
				$reflectionClass->isInterface() => 'interface',
				$reflectionClass->isTrait() => 'trait',
				default => 'class',
			};

			$apiFilePath = $context->pathFactory->createFileFromAbsolute($fileName);

			$apis[] = sprintf('- (%s) `%s` - [%s](%s)', $kind, $className, $apiFilePath->getRelativePathToRootDir(), $apiFilePath->relativeTo($context->sourcePath));
		}

		$content .= implode("\n", $apis);

		return $content;
	}

	/**
	 * @param non-empty-string $package
	 */
	private function resolveComposerPackage(string $package, PostprocessContext $context): string
	{
		foreach (self::ComposerDocFiles as $docFile) {
			$relativePath = sprintf('vendor/%s/%s', $package, $docFile);
			$resolvedPath = $context->pathFactory->createFileFromRelative($relativePath);

			if (file_exists($resolvedPath->getAbsolutePath())) {
				return sprintf('- (composer package) `%s` - [%s](%s)', $package, $resolvedPath->getRelativePathToRootDir(), $resolvedPath->relativeTo($context->sourcePath));
			}
		}

		throw new GeneratingFailedException(sprintf(
			'Composer package "%s" does not have any documentation file (%s).',
			$package,
			implode(', ', self::ComposerDocFiles),
		));
	}

}
