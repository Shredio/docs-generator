<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Markdown\MarkdownFrontmatterParser;
use Shredio\TypeSchema\TypeSchema;

final readonly class SkillContentWriter implements ContentWriter
{
	/**
	 * @return list<ContentToWrite>
	 */
	public function write(string $content, ContentWriterContext $context): array
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'skill' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
				'name' => $s->optional($s->nonEmptyString()),
				'description' => $s->nonEmptyString(),
			])),
		], true);

		$values = $context->processSchema($schema, $context->frontmatter);
		if (!isset($values['skill'])) {
			return [];
		}

		$skillName = basename($values['skill']['target']);
		if ($skillName === '') {
			throw new GeneratingFailedException('Skill target must not be empty.');
		}

		$context->dataCollector->addSkill($skillName, $values['skill']['description']);

		return [
			new ContentToWrite(
				$values['skill']['target'] . '/SKILL.md',
				MarkdownFrontmatterParser::dump([
					'name' => $values['skill']['name'] ?? $skillName,
					'description' => $values['skill']['description'],
				]) . $content,
			),
		];
	}

}
