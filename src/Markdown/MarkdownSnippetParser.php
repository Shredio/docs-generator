<?php declare(strict_types=1);

namespace Shredio\DocsGenerator\Markdown;

final class MarkdownSnippetParser
{
    /**
     * @return MarkdownSnippet[]
     */
    public static function parse(string $text): array
    {
        $snippets = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            if (self::isSnippetStart($text, $i)) {
                $snippetStart = $i;
                $i += 2; // Skip '{{'

                $snippet = self::parseSnippet($text, $i, $length, $snippetStart);
                if ($snippet !== null) {
                    $snippets[] = $snippet;
                    $i = $snippet->endPosition;
                    continue;
                }
            }
            $i++;
        }

        return $snippets;
    }

    private static function isSnippetStart(string $text, int $position): bool
    {
        return $position < strlen($text) - 1 && $text[$position] === '{' && $text[$position + 1] === '{';
    }

    private static function parseSnippet(string $text, int &$position, int $length, int $startPosition): ?MarkdownSnippet
    {
        self::skipWhitespace($text, $position, $length);

        $name = self::parseName($text, $position, $length);
        if ($name === null) {
            return null;
        }

        self::skipWhitespace($text, $position, $length);

        $arguments = [];

        if ($position < $length && $text[$position] === ':') {
            $position++; // Skip ':'

            self::skipWhitespace($text, $position, $length);

            $arguments = self::parseArguments($text, $position, $length);
        }

        self::skipWhitespace($text, $position, $length);

        if ($position >= $length - 1 || $text[$position] !== '}' || $text[$position + 1] !== '}') {
            return null;
        }

        $endPosition = $position + 2; // Position after '}}'

        return new MarkdownSnippet($name, $arguments, $startPosition, $endPosition);
    }

    private static function parseName(string $text, int &$position, int $length): ?string
    {
        $start = $position;

        while ($position < $length && $text[$position] !== ':' && $text[$position] !== '}' && !ctype_space($text[$position])) {
            $position++;
        }

        if ($position === $start) {
            return null;
        }

        return substr($text, $start, $position - $start);
    }

    /**
     * @return list<string>
     */
    private static function parseArguments(string $text, int &$position, int $length): array
    {
        $arguments = [];

        while ($position < $length) {
            self::skipWhitespace($text, $position, $length);

            if ($position >= $length - 1 || ($text[$position] === '}' && $text[$position + 1] === '}')) {
                break;
            }

            $argument = self::parseArgument($text, $position, $length);
            if ($argument !== null) {
                $arguments[] = $argument;
            }

            self::skipWhitespace($text, $position, $length);

            if ($position < $length && $text[$position] === ',') {
                $position++; // Skip ','
            } else {
                break;
            }
        }

        return $arguments;
    }

    private static function parseArgument(string $text, int &$position, int $length): ?string
    {
        self::skipWhitespace($text, $position, $length);

        if ($position >= $length) {
            return null;
        }

        if ($text[$position] === '"') {
            return self::parseQuotedArgument($text, $position, $length);
        }

        return self::parseUnquotedArgument($text, $position, $length);
    }

    private static function parseQuotedArgument(string $text, int &$position, int $length): ?string
    {
        if ($position >= $length || $text[$position] !== '"') {
            return null;
        }

        $position++; // Skip opening quote
        $start = $position;

        while ($position < $length && $text[$position] !== '"') {
            $position++;
        }

        if ($position >= $length) {
            return null; // No closing quote found
        }

        $argument = substr($text, $start, $position - $start);
        $position++; // Skip closing quote

        return $argument;
    }

    private static function parseUnquotedArgument(string $text, int &$position, int $length): ?string
    {
        $start = $position;

        while ($position < $length &&
               $text[$position] !== ',' &&
               $text[$position] !== '}' &&
               !ctype_space($text[$position])) {
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
