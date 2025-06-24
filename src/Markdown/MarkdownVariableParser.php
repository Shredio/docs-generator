<?php declare(strict_types=1);

namespace Shredio\DocsGenerator\Markdown;

final class MarkdownVariableParser
{
    /**
     * @return MarkdownVariable[]
     */
    public static function parse(string $text): array
    {
        $variables = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            if (self::isVariableStart($text, $i)) {
                $variableStart = $i;
                $i += 2; // Skip '{{'

                $variable = self::parseVariable($text, $i, $length, $variableStart);
                if ($variable !== null) {
                    $variables[] = $variable;
                    $i = $variable->endPosition;
                    continue;
                }
            }
            $i++;
        }

        return $variables;
    }

    private static function isVariableStart(string $text, int $position): bool
    {
        return $position < strlen($text) - 1 && $text[$position] === '{' && $text[$position + 1] === '{';
    }

    private static function parseVariable(string $text, int &$position, int $length, int $startPosition): ?MarkdownVariable
    {
        self::skipWhitespace($text, $position, $length);

        $name = self::parseName($text, $position, $length);
        if ($name === null) {
            return null;
        }

        self::skipWhitespace($text, $position, $length);

        if ($position >= $length - 1 || $text[$position] !== '}' || $text[$position + 1] !== '}') {
            return null;
        }

        $endPosition = $position + 2; // Position after '}}'

        return new MarkdownVariable($name, $startPosition, $endPosition);
    }

    private static function parseName(string $text, int &$position, int $length): ?string
    {
        $start = $position;

        while ($position < $length && $text[$position] !== '}' && !ctype_space($text[$position])) {
            $position++;
        }

        if ($position === $start) {
            return null;
        }

        return substr($text, $start, $position - $start);
    }

    private static function skipWhitespace(string $text, int &$position, int $length): void
    {
        while ($position < $length && ctype_space($text[$position])) {
            $position++;
        }
    }
}