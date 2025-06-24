<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Markdown;

final class MarkdownHeaderParser
{
    public static function parse(string $content): ParsedMarkdownHeaders
    {
        $lines = explode("\n", $content);
        $headers = [];
        $headerEndIndex = 0;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            
            // Skip empty lines - they don't stop header parsing
            if ($trimmedLine === '') {
                $headerEndIndex = $index + 1;
                continue;
            }
            
            // If we encounter a non-header line, stop parsing
            if (!str_starts_with($trimmedLine, '#!')) {
                break;
            }
            
            $headerContent = trim(substr($trimmedLine, 2)); // Remove '#!' prefix
            
            if ($headerContent === '') {
                $headerEndIndex = $index + 1;
                continue;
            }
            
            $colonPos = strpos($headerContent, ':');
            if ($colonPos === false) {
                $headerEndIndex = $index + 1;
                continue;
            }
            
            $name = trim(substr($headerContent, 0, $colonPos));
            $value = trim(substr($headerContent, $colonPos + 1));
            
            if ($name === '' || $value === '') {
                $headerEndIndex = $index + 1;
                continue;
            }
            
            $headers[] = new MarkdownHeader($name, $value);
            $headerEndIndex = $index + 1;
        }

        // Remove header lines and return the remaining content
        $contentLines = array_slice($lines, $headerEndIndex);
        $contentWithoutHeaders = ltrim(implode("\n", $contentLines));
        
        return new ParsedMarkdownHeaders($headers, $contentWithoutHeaders);
    }
}
