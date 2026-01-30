# Shredio Docs Generator

A Symfony bundle for generating structured documentation from markdown templates. Designed primarily for creating AI-consumable documentation like Claude AI Skills, but also supports generating general documentation files.

## Features

- **Markdown Templates with YAML Frontmatter** - Write documentation in markdown with YAML configuration
- **Macro System** - Dynamic content generation using `{{ macro-name: args }}` syntax
- **Multiple Output Types** - Generate Claude Skills, Claude Commands, or general documentation
- **PHP Reflection** - Auto-generate API documentation from PHP classes, interfaces, traits, and enums
- **Code Examples** - Embed and validate PHP class examples in documentation
- **Cross-Reference Validation** - Validate references between skills and docs
- **Priority-Based Processing** - Control generation order with priority settings

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
    root_dir: '%kernel.project_dir%'     # Project root directory
    source_dir: '/docs-templates'         # Directory containing template files
    docs_dir: '/docs'                      # Base path for generated documentation (optional)
```

## Usage

### Generate Documentation

```bash
php bin/console docs:generate
```

### Template Structure

Templates are markdown files with YAML frontmatter:

```markdown
---
skill:
  target: .claude/skills/my-skill
  name: My Skill Name
  description: Description of what this skill does
api:
  - App\Some\Class
  - App\Another\Interface
examples:
  - App\Example\ExampleClass
---

# My Skill Documentation

Content goes here with {{ macro-name: arguments }} support.
```

### Output Types

#### Skills (Claude AI Skills)

```yaml
skill:
  target: .claude/skills/skill-name
  name: Skill Display Name
  description: Skill description
```

Generates `SKILL.md` file in the target directory.

#### Commands (Claude Commands)

```yaml
commands:
  - target: .claude/commands/command-name.md
```

Generates command files for Claude CLI.

#### Output Files

```yaml
output:
  target: path/to/output.md
```

Generates arbitrary markdown files.

#### Documentation

```yaml
docs:
  target: docs/my-doc.md
  description: Documentation description
```

Generates documentation that can be referenced by other templates.

### Frontmatter Options

| Option | Description |
|--------|-------------|
| `metadata.priority` | Processing priority (0-10, higher = processed first) |
| `skill` | Claude Skill configuration |
| `commands` | Array of Claude Command configurations |
| `output` | Output file configuration |
| `docs` | Documentation file configuration |
| `api` | Array of PHP classes to document |
| `examples` | Array of PHP classes to include as examples |

### Built-in Macros

| Macro | Description |
|-------|-------------|
| `{{ class-name: ClassName }}` | Outputs fully-qualified class name |
| `{{ module-namespace: "Pattern" }}` | Module namespace pattern |
| `{{ submodule-namespace: "Name", "Pattern" }}` | Submodule namespace pattern |
| `{{ test-module-namespace: "Type", "Pattern" }}` | Test module namespace |
| `{{ test-submodule-namespace: "Type", "Name", "Pattern" }}` | Test submodule namespace |
| `{{ skill-reference: skillName }}` | Reference to another skill (validated) |
| `{{ docs-reference: docName }}` | Reference to documentation (validated) |
| `{{ docs-list }}` | Auto-generated list of all collected docs |

### API Documentation

The `api` frontmatter option automatically generates documentation for PHP classes:

```yaml
api:
  - App\Framework\Controller
  - App\Http\Response\ResponseFactory
```

This generates class signatures including:
- Class/interface/trait/enum declarations
- Methods with full signatures
- Properties and constants
- PHPDoc comments

### Code Examples

The `examples` frontmatter option embeds PHP class source code:

```yaml
examples:
  - Module\Stock\Feature\Example\ExampleController
```

You can also specify required strings to validate examples contain expected content:

```yaml
examples:
  - class: Module\Stock\Feature\Example\ExampleController
    contains:
      - "ResponseFactory"
      - "execute"
```

## Requirements

- PHP 8.3+
- Symfony 7.0+

## License

MIT
