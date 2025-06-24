<?php

declare(strict_types=1);

namespace Tests\Markdown;

use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Markdown\MarkdownVariable;
use Shredio\DocsGenerator\Markdown\MarkdownVariableParser;

final class MarkdownVariableParserTest extends TestCase
{
    public function testParseEmptyContent(): void
    {
        $variables = MarkdownVariableParser::parse('');
        
        $this->assertEmpty($variables);
    }
    
    public function testParseContentWithoutVariables(): void
    {
        $content = "Regular text without any variables.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertEmpty($variables);
    }
    
    public function testParseSingleVariable(): void
    {
        $content = "Text with {{variable}} inside.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(1, $variables);
        $this->assertInstanceOf(MarkdownVariable::class, $variables[0]);
        $this->assertSame('variable', $variables[0]->name);
        $this->assertSame(10, $variables[0]->startPosition);
        $this->assertSame(22, $variables[0]->endPosition);
    }
    
    public function testParseVariableWithSpaces(): void
    {
        $content = "Text with {{ variable }} inside.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(1, $variables);
        $this->assertSame('variable', $variables[0]->name);
        $this->assertSame(10, $variables[0]->startPosition);
        $this->assertSame(24, $variables[0]->endPosition);
    }
    
    public function testParseMultipleVariables(): void
    {
        $content = "Text with {{first}} and {{second}} variables.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(2, $variables);
        
        $this->assertSame('first', $variables[0]->name);
        $this->assertSame(10, $variables[0]->startPosition);
        $this->assertSame(19, $variables[0]->endPosition);
        
        $this->assertSame('second', $variables[1]->name);
        $this->assertSame(24, $variables[1]->startPosition);
        $this->assertSame(34, $variables[1]->endPosition);
    }
    
    public function testParseVariablesWithDifferentSpacing(): void
    {
        $content = "{{noSpaces}} {{ withSpaces }} {{   moreSpaces   }}";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(3, $variables);
        
        $this->assertSame('noSpaces', $variables[0]->name);
        $this->assertSame('withSpaces', $variables[1]->name);
        $this->assertSame('moreSpaces', $variables[2]->name);
    }
    
    public function testParseIgnoresIncompleteBraces(): void
    {
        $content = "Text with {single} and {{incomplete and {{valid}} variable.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(1, $variables);
        $this->assertSame('valid', $variables[0]->name);
    }
    
    public function testParseIgnoresEmptyVariables(): void
    {
        $content = "Text with {{}} and {{   }} and {{valid}} variable.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(1, $variables);
        $this->assertSame('valid', $variables[0]->name);
    }
    
    public function testParseVariablesAtStringBoundaries(): void
    {
        $content = "{{start}} middle {{end}}";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(2, $variables);
        
        $this->assertSame('start', $variables[0]->name);
        $this->assertSame(0, $variables[0]->startPosition);
        $this->assertSame(9, $variables[0]->endPosition);
        
        $this->assertSame('end', $variables[1]->name);
        $this->assertSame(17, $variables[1]->startPosition);
        $this->assertSame(24, $variables[1]->endPosition);
    }
    
    public function testParseVariablesWithSpecialCharacters(): void
    {
        $content = "Text with {{user.name}} and {{user_id}} and {{user-email}}.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(3, $variables);
        
        $this->assertSame('user.name', $variables[0]->name);
        $this->assertSame('user_id', $variables[1]->name);
        $this->assertSame('user-email', $variables[2]->name);
    }
    
    public function testParseNestedBraces(): void
    {
        $content = "Text with {{outer}} and {{inner{{nested}}}} pattern.";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(2, $variables);
        
        $this->assertSame('outer', $variables[0]->name);
        $this->assertSame('inner{{nested', $variables[1]->name);
    }
    
    public function testParseVariablesInMultilineContent(): void
    {
        $content = "Line 1 with {{first}}\nLine 2 with {{second}}\nLine 3 without variables";
        $variables = MarkdownVariableParser::parse($content);
        
        $this->assertCount(2, $variables);
        
        $this->assertSame('first', $variables[0]->name);
        $this->assertSame('second', $variables[1]->name);
    }
}