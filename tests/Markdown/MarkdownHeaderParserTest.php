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
        $content = "---\ntarget: destination/file.md\n---\n\n# Content starts here";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(1, $parsed->headers);
        $this->assertInstanceOf(MarkdownHeader::class, $parsed->headers[0]);
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        $this->assertSame('# Content starts here', $parsed->content);
    }
    
    public function testParseMultipleHeaders(): void
    {
        $content = "---\ntarget: destination/file.md\ncommand: This is prompt for command\n---\n\n# Content";
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
        $content = "---\n   target   :   destination/file.md   \ncommand:value\n---\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('value', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }
    
    public function testParseWithoutClosingDelimiter(): void
    {
        $content = "---\ntarget: destination/file.md\n# Regular markdown";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(0, $parsed->headers);
        $this->assertSame("---\ntarget: destination/file.md\n# Regular markdown", $parsed->content);
    }
    
    public function testParseIgnoresInvalidHeaders(): void
    {
        $content = "---\ntarget: destination/file.md\ninvalid-no-colon\n: no-name\nempty-value: \ncommand: valid\n---\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(2, $parsed->headers);
        
        $this->assertSame('target', $parsed->headers[0]->name);
        $this->assertSame('destination/file.md', $parsed->headers[0]->value);
        
        $this->assertSame('command', $parsed->headers[1]->name);
        $this->assertSame('valid', $parsed->headers[1]->value);
        
        $this->assertSame('# Content', $parsed->content);
    }

	public function testParseVariables(): void
	{
		$headers = $this->parse([
			'target' => '$target',
			'$target' => 'destination/file.md',
		]);

		$this->assertCount(1, $headers->headers);
		$this->assertSame('destination/file.md', $headers->headers[0]->value);
	}

	public function testParseArrays(): void
	{
		$headers = $this->parse([
			'target' => '["$target", "another.md"]',
			'$target' => 'destination/file.md',
		]);

		$this->assertCount(2, $headers->headers);
		$this->assertSame('destination/file.md', $headers->headers[0]->value);
		$this->assertSame('another.md', $headers->headers[1]->value);
	}

	public function testParseQuotedString(): void
	{
		$headers = $this->parse([
			'target' => '"another.md"',
		]);

		$this->assertCount(1, $headers->headers);
		$this->assertSame('another.md', $headers->headers[0]->value);
	}

	public function testParseArrayIndexedKeys(): void
	{
		$headers = $this->parse([
			'target[0]' => 'first.md',
			'target[1]' => 'another.md',
		]);

		$this->assertCount(2, $headers->headers);
		$this->assertSame('target', $headers->headers[0]->name);
		$this->assertSame('target', $headers->headers[1]->name);
	}
    
    public function testParseIgnoresEmptyLines(): void
    {
        $content = "---\ntarget: destination/file.md\n\n\ncommand: valid\n---\n\n# Content";
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
        $content = "---\nurl: https://example.com:8080/path\n---\n\n# Content";
        $parsed = MarkdownHeaderParser::parse($content);
        
        $this->assertCount(1, $parsed->headers);
        $this->assertSame('url', $parsed->headers[0]->name);
        $this->assertSame('https://example.com:8080/path', $parsed->headers[0]->value);
        $this->assertSame('# Content', $parsed->content);
    }

	private function parse(array $lines): ParsedMarkdownHeaders
	{
		$code = "---\n";
		foreach ($lines as $name => $value) {
			$code .= "$name: $value\n";
		}
		$code .= "---\n\n# Content";

		return MarkdownHeaderParser::parse($code);
	}

}
