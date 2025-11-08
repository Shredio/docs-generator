<?php

declare(strict_types=1);

namespace Tests\Markdown;

use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Markdown\MarkdownFrontmatterParser;

final class MarkdownFrontmatterParserTest extends TestCase
{

	public function testExtractWithNoFrontmatter(): void
	{
		$markdown = "# Regular markdown\n\nSome content here.";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertEmpty($result);
		$this->assertSame("# Regular markdown\n\nSome content here.", $markdown);
	}

	public function testExtractWithEmptyFrontmatterThrowsException(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid frontmatter format');

		$markdown = "---\n---\n\n# Content";
		MarkdownFrontmatterParser::extract($markdown);
	}

	public function testExtractWithSingleKeyValue(): void
	{
		$markdown = "---\ntarget: destination/file.md\n---\n\n# Content starts here";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertIsArray($result);
		$this->assertArrayHasKey('target', $result);
		$this->assertSame('destination/file.md', $result['target']);
		$this->assertSame('# Content starts here', $markdown);
	}

	public function testExtractWithMultipleKeyValues(): void
	{
		$markdown = "---\ntarget: destination/file.md\ncommand: This is prompt\nauthor: John Doe\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertCount(3, $result);
		$this->assertSame('destination/file.md', $result['target']);
		$this->assertSame('This is prompt', $result['command']);
		$this->assertSame('John Doe', $result['author']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithNestedYamlStructure(): void
	{
		$markdown = "---\nconfig:\n  name: test\n  value: 123\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertArrayHasKey('config', $result);
		$this->assertIsArray($result['config']);
		$this->assertSame('test', $result['config']['name']);
		$this->assertSame(123, $result['config']['value']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithYamlArray(): void
	{
		$markdown = "---\ntags:\n  - php\n  - markdown\n  - yaml\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertArrayHasKey('tags', $result);
		$this->assertIsArray($result['tags']);
		$this->assertCount(3, $result['tags']);
		$this->assertSame('php', $result['tags'][0]);
		$this->assertSame('markdown', $result['tags'][1]);
		$this->assertSame('yaml', $result['tags'][2]);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithoutClosingDelimiter(): void
	{
		$markdown = "---\ntarget: destination/file.md\n# Regular markdown";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertEmpty($result);
		$this->assertSame("---\ntarget: destination/file.md\n# Regular markdown", $markdown);
	}

	public function testExtractWithExtraWhitespace(): void
	{
		$markdown = "---\ntarget: destination/file.md\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertArrayHasKey('target', $result);
		$this->assertSame('destination/file.md', $result['target']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithEmptyLinesInFrontmatter(): void
	{
		$markdown = "---\ntarget: destination/file.md\n\n\ncommand: valid\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertCount(2, $result);
		$this->assertSame('destination/file.md', $result['target']);
		$this->assertSame('valid', $result['command']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractTrimsMarkdownAfterExtraction(): void
	{
		$markdown = "---\ntarget: file.md\n---\n\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertNotEmpty($result);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithComplexYamlValues(): void
	{
		$markdown = "---\nurl: https://example.com:8080/path\ndate: '2025-10-17'\nenabled: true\ncount: 42\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertSame('https://example.com:8080/path', $result['url']);
		$this->assertSame('2025-10-17', $result['date']);
		$this->assertTrue($result['enabled']);
		$this->assertSame(42, $result['count']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractWithMultilineYamlValue(): void
	{
		$markdown = "---\ndescription: |\n  This is a multiline\n  description text\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertArrayHasKey('description', $result);
		$this->assertStringContainsString('This is a multiline', $result['description']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractThrowsExceptionOnInvalidYaml(): void
	{
		$this->expectException(\InvalidArgumentException::class);

		$markdown = "---\ninvalid yaml: [unclosed\n---\n\n# Content";
		MarkdownFrontmatterParser::extract($markdown);
	}

	public function testExtractWithQuotedStrings(): void
	{
		$markdown = "---\ntitle: \"A title with: colons\"\ndescription: 'Single quoted value'\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertSame('A title with: colons', $result['title']);
		$this->assertSame('Single quoted value', $result['description']);
		$this->assertSame('# Content', $markdown);
	}

	public function testExtractModifiesOriginalMarkdownVariable(): void
	{
		$original = "---\nkey: value\n---\n\n# Original Content";
		$markdown = $original;

		MarkdownFrontmatterParser::extract($markdown);

		$this->assertNotSame($original, $markdown);
		$this->assertSame('# Original Content', $markdown);
	}

	public function testExtractWithOnlyFrontmatter(): void
	{
		$markdown = "---\ntarget: file.md\n---";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertNotEmpty($result);
		$this->assertSame('file.md', $result['target']);
		$this->assertSame('', $markdown);
	}

	public function testExtractWithFrontmatterNotAtStart(): void
	{
		$markdown = "# Title\n\n---\ntarget: file.md\n---\n\n# Content";
		$result = MarkdownFrontmatterParser::extract($markdown);

		$this->assertEmpty($result);
		$this->assertSame("# Title\n\n---\ntarget: file.md\n---\n\n# Content", $markdown);
	}

	public function testDumpWithEmptyArray(): void
	{
		$result = MarkdownFrontmatterParser::dump([]);

		$this->assertSame('', $result);
	}

	public function testDumpWithSingleKeyValue(): void
	{
		$result = MarkdownFrontmatterParser::dump(['target' => 'destination/file.md']);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('target:', $result);
		$this->assertStringContainsString('destination/file.md', $result);
	}

	public function testDumpWithMultipleKeyValues(): void
	{
		$result = MarkdownFrontmatterParser::dump([
			'target' => 'destination/file.md',
			'author' => 'John Doe',
			'enabled' => true,
		]);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('target:', $result);
		$this->assertStringContainsString('destination/file.md', $result);
		$this->assertStringContainsString('author:', $result);
		$this->assertStringContainsString('John Doe', $result);
		$this->assertStringContainsString('enabled:', $result);
		$this->assertStringContainsString('true', $result);
	}

	public function testDumpWithNestedArray(): void
	{
		$result = MarkdownFrontmatterParser::dump([
			'config' => [
				'name' => 'test',
				'value' => 123,
			],
		]);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('config:', $result);
		$this->assertStringContainsString('name:', $result);
		$this->assertStringContainsString('test', $result);
		$this->assertStringContainsString('value:', $result);
		$this->assertStringContainsString('123', $result);
	}

	public function testDumpWithList(): void
	{
		$result = MarkdownFrontmatterParser::dump([
			'tags' => ['php', 'markdown', 'yaml'],
		]);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('tags:', $result);
		$this->assertStringContainsString('php', $result);
		$this->assertStringContainsString('markdown', $result);
		$this->assertStringContainsString('yaml', $result);
	}

	public function testDumpAndExtractRoundtrip(): void
	{
		$original = [
			'target' => 'destination/file.md',
			'author' => 'John Doe',
			'count' => 42,
			'enabled' => true,
		];

		$dumped = MarkdownFrontmatterParser::dump($original);
		$markdown = $dumped . '# Content';
		$extracted = MarkdownFrontmatterParser::extract($markdown);

		$this->assertSame($original['target'], $extracted['target']);
		$this->assertSame($original['author'], $extracted['author']);
		$this->assertSame($original['count'], $extracted['count']);
		$this->assertSame($original['enabled'], $extracted['enabled']);
		$this->assertSame('# Content', $markdown);
	}

	public function testDumpWithSpecialCharacters(): void
	{
		$result = MarkdownFrontmatterParser::dump([
			'title' => 'A title with: colons',
			'url' => 'https://example.com:8080/path',
		]);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('title:', $result);
		$this->assertStringContainsString('url:', $result);
	}

	public function testDumpWithMultilineString(): void
	{
		$result = MarkdownFrontmatterParser::dump([
			'description' => "This is a multiline\ndescription text",
		]);

		$this->assertStringStartsWith('---', $result);
		$this->assertStringEndsWith("---\n\n", $result);
		$this->assertStringContainsString('description:', $result);
		$this->assertStringContainsString('This is a multiline', $result);
		$this->assertStringContainsString('description text', $result);
	}

	public function testDumpProducesValidYaml(): void
	{
		$values = [
			'target' => 'file.md',
			'nested' => ['key' => 'value'],
			'list' => [1, 2, 3],
		];

		$dumped = MarkdownFrontmatterParser::dump($values);
		$markdown = $dumped . '# Content';

		$extracted = MarkdownFrontmatterParser::extract($markdown);

		$this->assertSame('file.md', $extracted['target']);
		$this->assertIsArray($extracted['nested']);
		$this->assertSame('value', $extracted['nested']['key']);
		$this->assertIsArray($extracted['list']);
		$this->assertCount(3, $extracted['list']);
	}

}
