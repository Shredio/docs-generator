<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\TypeSchema\TypeSchema;

final readonly class CommandsContentWriter implements ContentWriter
{

	/**
	 * @return list<ContentToWrite>
	 */
	public function write(string $content, ContentWriterContext $context): array
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'commands' => $s->optional($s->nonEmptyList($s->arrayShape([
				'name' => $s->nonEmptyString(),
				'prompt' => $s->nonEmptyString(),
			]))),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);

		$files = [];
		foreach ($values['commands'] ?? [] as $command) {
			if (!str_contains($command['prompt'], '$ARGUMENTS')) {
				throw new GeneratingFailedException('Command prompt must contain "$ARGUMENTS" placeholder.');
			}

			$files[] = new ContentToWrite(
				sprintf('.claude/commands/%s.md', $command['name']),
				sprintf("%s\n\n%s", $content, $command['prompt']),
			);
		}

		return $files;
	}

}
