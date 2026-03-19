# Shredio Docs Generator

A Symfony bundle for generating structured documentation from markdown templates. Designed primarily for creating AI-consumable documentation (Claude Skills, Commands, project docs), but also supports generating general documentation files.

## Features

- **Markdown templates with YAML frontmatter** - write documentation in markdown with YAML configuration
- **Multiple output types** - generate Claude Skills, Claude Commands, project docs, or general files
- **Macro system** - dynamic content generation using `{{ macro-name: "args" }}` syntax with cross-reference validation
- **API reference generation** - auto-document PHP classes, interfaces, traits with reflection
- **Composer package references** - link to documentation of composer dependencies
- **Code examples** - embed and validate PHP class source code in documentation
- **Priority-based processing** - control generation order with priority settings
- **Main file support** - designate a central file that collects references to all docs

## Installation

```bash
composer require shredio/docs-generator
```

Register the bundle in your Symfony application:

```php
// config/bundles.php
return [
    // ...
    Shredio\DocsGenerator\DocsGeneratorBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/docs_generator.yaml
docs_generator:
    root_dir: '%kernel.project_dir%'     # Project root directory (default)
    source_dir: '/docs-templates'         # Directory containing template files
    docs_dir: '/docs'                     # Base path for generated docs (optional)
```

## Usage

Generate documentation with the console command:

```bash
php bin/console docs:generate
```

The generator discovers all `.md` files in the configured `source_dir`, parses their YAML frontmatter, and produces output files based on the configuration.

## Template Structure

Templates are markdown files with YAML frontmatter that controls how the output is generated:

```markdown
---
metadata:
  priority: 10
skill:
  target: .claude/skills/my-skill
  description: Description of what this skill does
api:
  - App\Some\ClassName
  - class: App\Some\Interface
  - composer: vendor/package-name
examples:
  - App\Example\ExampleClass
  - class: App\Another\Example
    contains:
      - "expectedMethod"
  - file: path/to/example.php
---

# My Skill Documentation

Content goes here with {{ macro-name: "arguments" }} support.
```

## Output Types

A single template can produce one or more output types simultaneously.

### Skills

Generates Claude AI Skill files (`SKILL.md`) in the target directory:

```yaml
skill:
  target: .claude/skills/skill-name
  name: Skill Display Name          # optional, defaults to directory basename
  description: What this skill does
```

### Commands

Generates Claude CLI command files. The `prompt` field must contain `$ARGUMENTS`:

```yaml
commands:
  - name: my-command
    prompt: Execute this task with $ARGUMENTS
```

### Docs

Generates documentation files that can be cross-referenced by other templates. When a main file exists, all docs are automatically listed in a "Project Docs" section:

```yaml
docs:
  target: getting-started.md    # must end with .md
  description: Getting started guide
```

### Output

Generates arbitrary markdown files:

```yaml
output:
  target: path/to/output.md    # must end with .md
```

## Frontmatter Reference

### Metadata

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `metadata.priority` | `int` | `5` | Processing priority (0-10, higher = processed first) |
| `main` | `bool` | `false` | Designates this as the main file (sets priority to -1, enables docs list) |
| `macros.disabled` | `bool` | `false` | Disable macro processing for this file |

### API Reference

The `api` frontmatter generates an "API Reference" section with links to source files. Each entry is labeled with its kind (class, interface, trait, or composer package):

```yaml
api:
  # String form - class/interface/trait name
  - App\Http\Controller

  # Object form with class
  - class: App\Http\ResponseFactory

  # Composer package - resolves to llm.md, LLM.md, AGENTS.md, README.md, etc.
  - composer: vendor/package-name
```

### Code Examples

The `examples` frontmatter generates an "Examples" section with links to source files:

```yaml
examples:
  # String form - class name
  - App\Example\ExampleController

  # Object form with validation
  - class: App\Example\ExampleController
    contains:
      - "ResponseFactory"
      - "execute"

  # File reference (relative to root dir)
  - file: src/Example/example.php
```

## Built-in Macros

| Macro | Arguments | Description |
|-------|-----------|-------------|
| `{{ class-name: "FQN" }}` | 1 | Validates class exists, outputs backtick-wrapped FQN |
| `{{ skill-reference: "name" }}` | 1 | Validates skill exists, outputs backtick-wrapped reference |
| `{{ docs-reference: "name" }}` | 1 | Validates doc exists, outputs backtick-wrapped reference |
| `{{ module-namespace: "Suffix" }}` | 1 | Outputs `Module\%ModuleName%\Suffix` |
| `{{ submodule-namespace: "Sub", "Suffix" }}` | 2 | Outputs `Module\%ModuleName%\Sub\%SubmoduleName%\Suffix` |
| `{{ test-module-namespace: "Type", "Suffix" }}` | 2 | Outputs `Tests\Type\Module\%ModuleName%\Suffix` |
| `{{ test-submodule-namespace: "Type", "Sub", "Suffix" }}` | 3 | Outputs `Tests\Type\Module\%ModuleName%\Sub\%SubmoduleName%\Suffix` |

## Requirements

- PHP 8.3+
- Symfony 7.0+

## License

MIT
