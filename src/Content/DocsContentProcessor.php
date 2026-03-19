<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

final readonly class DocsContentProcessor implements ContentProcessor
{

	public function process(string $content, ProcessContext $context): string
	{
		return $content;
	}

	public function postprocess(string $content, PostprocessContext $context): string
	{
		if (!$context->isMainFile) {
			return $content;
		}

		if ($context->dataCollector->docs === []) {
			return $content;
		}

		$str = "\n\n";
		$str .= "## Project Docs (THE MOST IMPORTANT SECTION)\n\n";
		$str .= "**THE MOST IMPORTANT SECTION (READ CAREFULLY)** Read the following docs to get instructions and guidelines and the best practices before the task (**even if you're in planning mode**) if the description describes something related to those topics you're going to work on:";
		$str .= "\n\n";

		$pos = 1;
		foreach ($context->dataCollector->docs as $name => [$relativePath, $description]) {
			$str .= sprintf('%d. [%s](%s) - %s' . "\n", $pos, $name, $relativePath, $description);
			$pos++;
		}

		return $content . rtrim($str);
	}

}
