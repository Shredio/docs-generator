<?php declare(strict_types=1);

namespace Tests\Markdown;

use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Markdown\MarkdownSnippetParser;

final class MarkdownSnippetParserTest extends TestCase
{

	public function testParseWithNoSnippets(): void
	{
		$text = '# Regular markdown content without any snippets';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithEmptyString(): void
	{
		$result = MarkdownSnippetParser::parse('');

		$this->assertSame([], $result);
	}

	public function testParseWithSingleUnquotedArgument(): void
	{
		$text = '{{ snippet: argument }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
		$this->assertSame(['argument'], $result[0]->arguments);
		$this->assertSame(0, $result[0]->startPosition);
		$this->assertSame(23, $result[0]->endPosition);
	}

	public function testParseWithSingleQuotedArgument(): void
	{
		$text = '{{ snippet: "argument with spaces" }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
		$this->assertSame(['argument with spaces'], $result[0]->arguments);
	}

	public function testParseWithMultipleUnquotedArguments(): void
	{
		$text = '{{ macro: arg1, arg2, arg3 }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame(['arg1', 'arg2', 'arg3'], $result[0]->arguments);
	}

	public function testParseWithMultipleQuotedArguments(): void
	{
		$text = '{{ macro: "first arg", "second arg" }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame(['first arg', 'second arg'], $result[0]->arguments);
	}

	public function testParseWithMixedQuotedAndUnquotedArguments(): void
	{
		$text = '{{ macro: unquoted, "quoted argument", another }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame(['unquoted', 'quoted argument', 'another'], $result[0]->arguments);
	}

	public function testParseWithNoWhitespace(): void
	{
		$text = '{{macro:arg}}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame(['arg'], $result[0]->arguments);
	}

	public function testParseWithExtraWhitespace(): void
	{
		$text = '{{   macro   :   arg1   ,   arg2   }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame(['arg1', 'arg2'], $result[0]->arguments);
	}

	public function testParseMultipleSnippetsInText(): void
	{
		$text = 'Some text {{ first: arg1 }} middle {{ second: arg2, arg3 }} end';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(2, $result);

		$this->assertSame('first', $result[0]->name);
		$this->assertSame(['arg1'], $result[0]->arguments);

		$this->assertSame('second', $result[1]->name);
		$this->assertSame(['arg2', 'arg3'], $result[1]->arguments);
	}

	public function testParseSnippetAtBeginningOfText(): void
	{
		$text = '{{ start: value }} and some text';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('start', $result[0]->name);
		$this->assertSame(0, $result[0]->startPosition);
	}

	public function testParseSnippetAtEndOfText(): void
	{
		$text = 'Some text and {{ end: value }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('end', $result[0]->name);
		$this->assertSame(strlen($text), $result[0]->endPosition);
	}

	public function testParseWithUnclosedBraces(): void
	{
		$text = '{{ snippet: arg';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithoutColonAndArguments(): void
	{
		$text = '{{ snippet }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
		$this->assertSame([], $result[0]->arguments);
	}

	public function testParseWithColonButNoArguments(): void
	{
		$text = '{{ snippet: }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
		$this->assertSame([], $result[0]->arguments);
	}

	public function testParseWithoutColonHasCorrectPositions(): void
	{
		$text = 'prefix {{ macro }} suffix';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro', $result[0]->name);
		$this->assertSame([], $result[0]->arguments);
		$this->assertSame('{{ macro }}', substr($text, $result[0]->startPosition, $result[0]->endPosition - $result[0]->startPosition));
	}

	public function testParseWithUnclosedQuotedArgument(): void
	{
		$text = '{{ snippet: "unclosed }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithSingleOpenBrace(): void
	{
		$text = '{ not a snippet: value }';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithSingleCloseBrace(): void
	{
		$text = '{{ snippet: value }';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParsePositionsAreCorrect(): void
	{
		$text = 'prefix {{ snippet: arg }} suffix';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame(7, $result[0]->startPosition);
		$this->assertSame(25, $result[0]->endPosition);
		$this->assertSame('{{ snippet: arg }}', substr($text, $result[0]->startPosition, $result[0]->endPosition - $result[0]->startPosition));
	}

	public function testParseWithEmptyName(): void
	{
		$text = '{{ : arg }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithSpecialCharactersInUnquotedArgument(): void
	{
		$text = '{{ macro: path/to/file.txt }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame(['path/to/file.txt'], $result[0]->arguments);
	}

	public function testParseWithSpecialCharactersInQuotedArgument(): void
	{
		$text = '{{ macro: "value with, comma and } braces" }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame(['value with, comma and } braces'], $result[0]->arguments);
	}

	public function testParseConsecutiveSnippets(): void
	{
		$text = '{{ first: a }}{{ second: b }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(2, $result);
		$this->assertSame('first', $result[0]->name);
		$this->assertSame('second', $result[1]->name);
	}

	public function testParseWithNewlinesInText(): void
	{
		$text = "Line 1\n{{ snippet: arg }}\nLine 3";
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
	}

	public function testParseWithTabsInText(): void
	{
		$text = "\t{{ snippet: arg }}\t";
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
	}

	public function testParseComplexMarkdownDocument(): void
	{
		$text = <<<'MD'
# Documentation

Here is some content with {{ include: "path/to/file.md" }} embedded.

## Section

Another {{ macro: arg1, arg2 }} here.

```php
// This should still be parsed: {{ code: example }}
```
MD;
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(3, $result);
		$this->assertSame('include', $result[0]->name);
		$this->assertSame(['path/to/file.md'], $result[0]->arguments);
		$this->assertSame('macro', $result[1]->name);
		$this->assertSame(['arg1', 'arg2'], $result[1]->arguments);
		$this->assertSame('code', $result[2]->name);
		$this->assertSame(['example'], $result[2]->arguments);
	}

	public function testParseWithOnlyBraces(): void
	{
		$text = '{{}}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertSame([], $result);
	}

	public function testParseWithNameOnlyNoWhitespace(): void
	{
		$text = '{{snippet}}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('snippet', $result[0]->name);
		$this->assertSame([], $result[0]->arguments);
	}

	public function testParseTrailingCommaInArguments(): void
	{
		$text = '{{ macro: arg1, arg2, }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame(['arg1', 'arg2'], $result[0]->arguments);
	}

	public function testParseWithHyphenInName(): void
	{
		$text = '{{ my-macro: arg }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('my-macro', $result[0]->name);
	}

	public function testParseWithUnderscoreInName(): void
	{
		$text = '{{ my_macro: arg }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('my_macro', $result[0]->name);
	}

	public function testParseWithNumbersInName(): void
	{
		$text = '{{ macro123: arg }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame('macro123', $result[0]->name);
	}

	public function testParseWithEmptyQuotedArgument(): void
	{
		$text = '{{ macro: "" }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame([''], $result[0]->arguments);
	}

	public function testParseMultipleEmptyQuotedArguments(): void
	{
		$text = '{{ macro: "", "", "value" }}';
		$result = MarkdownSnippetParser::parse($text);

		$this->assertCount(1, $result);
		$this->assertSame(['', '', 'value'], $result[0]->arguments);
	}

}