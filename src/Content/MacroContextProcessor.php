<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Collector\DataCollector;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Exception\MacroException;
use Shredio\DocsGenerator\Macro\DocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocTemplateMacroContext;
use Shredio\DocsGenerator\Markdown\MarkdownSnippetParser;
use Shredio\TypeSchema\TypeSchema;

final readonly class MacroContextProcessor implements ContentProcessor
{

	/**
	 * @param array<string, DocTemplateMacro> $macros
	 */
	public function __construct(
		private DataCollector $dataCollector,
		private array $macros,
	)
	{
	}

	public function process(string $content, ProcessContext $context): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'main' => $s->optional($s->bool()),
			'macros' => $s->optional($s->arrayShape([
				'disabled' => $s->optional($s->bool()),
			])),
		], true);

		$options = $context->processSchema($schema, $context->frontmatter)['macros'] ?? [];

		$disabled = $options['disabled'] ?? false;
		if ($disabled) {
			return $content;
		}

		$macros = MarkdownSnippetParser::parse($content);
		// Process macros in reverse order to maintain correct positions
		foreach (array_reverse($macros) as $macro) {
			if (isset($this->macros[$macro->name])) {
				$instance = $this->macros[$macro->name];
				try {
					$replacement = $instance->invoke(new DocTemplateMacroContext(
						$context->sourcePath,
						$this->dataCollector,
					), $macro->arguments);
				} catch (MacroException $exception) {
					throw new GeneratingFailedException(
						sprintf(
							'Generating failed for macro "%s" in file "%s": %s',
							$macro->name,
							$context->sourcePath->getRelativePathToRootDir(),
							$exception->getMessage()
						)
					);
				}

				$content = substr_replace(
					$content,
					$replacement,
					$macro->startPosition,
					$macro->endPosition - $macro->startPosition
				);
			}
		}

		return $content;
	}

	public function postprocess(string $content, PostprocessContext $context): string
	{
		return $content;
	}

}
