<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Markdown;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class MarkdownFrontmatterParser
{

	/**
	 * @return mixed[]
	 * @throws \InvalidArgumentException When the frontmatter is invalid
	 */
	public static function extract(string &$markdown): array
	{
		if (str_starts_with($markdown, '---')) {
			$endPos = strpos($markdown, '---', 3);
			if ($endPos !== false) {
				$frontmatter = substr($markdown, 3, $endPos - 3);
				try {
					$data = Yaml::parse($frontmatter);
				} catch (ParseException $exception) {
					throw new \InvalidArgumentException('Invalid frontmatter format', 0, $exception);
				}

				if (!is_array($data)) {
					throw new \InvalidArgumentException('Invalid frontmatter format');
				}

				$markdown = ltrim(substr($markdown, $endPos + 3));

				return $data;
			}
		}

		return [];
	}

	/**
	 * @param mixed[] $values
	 */
	public static function dump(array $values): string
	{
		if ($values === []) {
			return '';
		}

		$yaml = Yaml::dump($values, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
		return sprintf("---\n%s---\n\n", rtrim($yaml) . "\n");
	}

}
