<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Markdown;

final class MarkdownHeaderParser
{
    public static function parse(string $content): ParsedMarkdownHeaders
    {
        $lines = explode("\n", $content);
        $headers = [];

        // Check if content starts with frontmatter delimiter
        if (trim($lines[0]) !== '---') {
            return new ParsedMarkdownHeaders($headers, $content);
        }

        // Find the closing delimiter
        $closingDelimiterIndex = null;
        for ($i = 1; $i < count($lines); $i++) {
            if (trim($lines[$i]) === '---') {
                $closingDelimiterIndex = $i;
                break;
            }
        }

        // If no closing delimiter found, treat as regular content
        if ($closingDelimiterIndex === null) {
            return new ParsedMarkdownHeaders($headers, $content);
        }

        // Parse frontmatter lines between delimiters
        for ($i = 1; $i < $closingDelimiterIndex; $i++) {
            $line = trim($lines[$i]);
            
            // Skip empty lines
            if ($line === '') {
                continue;
            }
            
            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }
            
            $name = self::normalizeHeaderName(trim(substr($line, 0, $colonPos)));
            $value = trim(substr($line, $colonPos + 1));
            
            if ($name === '' || $value === '') {
                continue;
            }
            
            $headers[] = new MarkdownHeader($name, $value);
        }

        // Remove frontmatter and return remaining content
        $headerEndIndex = $closingDelimiterIndex + 1;
        $contentLines = array_slice($lines, $headerEndIndex);
        $contentWithoutHeaders = ltrim(implode("\n", $contentLines));
        
        return new ParsedMarkdownHeaders(self::processHeaders($headers), $contentWithoutHeaders);
    }

	/**
	 * @param list<MarkdownHeader> $headers
	 * @return list<MarkdownHeader>
	 */
	private static function processHeaders(array $headers): array
	{
		return self::replaceVariables(self::expand($headers));
	}

	/**
	 * @param list<MarkdownHeader> $headers
	 * @return list<MarkdownHeader>
	 */
	private static function expand(array $headers): array
	{
		$return = [];

		foreach ($headers as $header) {
			if (
				(str_starts_with($header->value, '[') && str_ends_with($header->value, ']')) ||
				(str_starts_with($header->value, '"') && str_ends_with($header->value, '"'))
			) {
				$decoded = json_decode($header->value, true, flags: JSON_THROW_ON_ERROR);

				if (is_array($decoded)) {
					foreach ($decoded as $item) {
						if (!is_scalar($item) && $item !== null) {
							throw new \LogicException('Header array items must be scalar or null.');
						}
						$return[] = new MarkdownHeader($header->name, (string) $item);
					}
				} else {
					if (!is_scalar($decoded) && $decoded !== null) {
						throw new \LogicException('Header value must be scalar or null.');
					}
					$return[] = new MarkdownHeader($header->name, (string) $decoded);
				}
			} else {
				$return[] = $header;
			}
		}

		return $return;
	}

	/**
	 * @param list<MarkdownHeader> $headers
	 * @return list<MarkdownHeader>
	 */
	private static function replaceVariables(array $headers): array
	{
		$variables = [];
		foreach ($headers as $key => $header) {
			if (str_starts_with($header->name, '$')) {
				$variables[$header->name] = $header->value;

				unset($headers[$key]);
			}
		}

		if ($variables === []) {
			return array_values($headers);
		}

		foreach ($headers as &$header) {
			foreach ($variables as $varName => $varValue) {
				if (str_contains($header->value, $varName)) {
					$header = new MarkdownHeader(
						$header->name,
						str_replace($varName, $varValue, $header->value)
					);
				}
			}
		}

		return array_values($headers);
	}

	private static function normalizeHeaderName(string $name): string
	{
		$pos = strpos($name, '[');

		if ($pos !== false) {
			$name = substr($name, 0, $pos);
		}

		return $name;
	}

}
