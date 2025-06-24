<?php

declare(strict_types=1);

namespace Tests\Markdown;

use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Markdown\MarkdownHeader;
use Shredio\DocsGenerator\Markdown\MarkdownHeaderParser;
use Shredio\DocsGenerator\Markdown\ParsedMarkdownHeaders;

final class MarkdownHeaderParserTest extends TestCase
{
    public function testParseEmptyContent(): void
    {
        $parsed = MarkdownHeaderParser::parse('');
        
        $this->assertInstanceOf(ParsedMarkdownHeaders::class, $parsed);
        $this->assertEmpty($parsed->headers);
        $this->assertSame('', $parsed->content);
    }
    
    public function testParseContentWithoutHeaders(): void
    {
        $content = "# Regular markdown\n\nSome content here.";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertEmpty($parsed->headers);
        $this->assertSame("# Regular markdown\n\nSome content here.", $parsed->content);
    }
    
    public function testParseSingleHeader(): void
    {
        $content = "#! target: destination/file.md\n\n# Content starts here";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(1, $parsed->headers);
        $this->assertInstanceOf(MarkdownHeader::class, $parsed->headers[0]);
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        $this->assertSame('# Content starts here', $parsed->content);
    }
    
    public function testParseMultipleHeaders(): void
    {
        $content = "#! target: destination/file.md\n#! command: This is prompt for command\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('This is prompt for command', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }
    
    public function testParseHeadersWithExtraWhitespace(): void
    {
        $content = "#!   target   :   destination/file.md   \n#!command:value\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('value', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }
    
    public function testParseStopsAtFirstNonHeaderLine(): void
    {
        $content = "#! target: destination/file.md\n# Regular markdown\n#! target: destination/file2.md\n#! command: This is prompt for command";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(1, $parsed->headers);
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        $this->assertSame("# Regular markdown\n#! target: destination/file2.md\n#! command: This is prompt for command", $parsed->content);
    }
    
    public function testParseIgnoresInvalidHeaders(): void
    {
        $content = "#! target: destination/file.md\n#! invalid-no-colon\n#! : no-name\n#! empty-value: \n#! command: valid\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('valid', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }
    
    public function testParseIgnoresEmptyLines(): void
    {
        $content = "#! target: destination/file.md\n\n\n#! command: valid\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('valid', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }
    
    public function testParseValueWithColons(): void
    {
        $content = "#! url: https://example.com:8080/path\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(1, $parsed->headers);
        $this->assertSame('url', $parsed->headers[0]->name);
        $this->assertSame('https://example.com:8080/path', $parsed->headers[0]->value);
        $this->assertSame('# Content', $parsed->content);
    }
}
