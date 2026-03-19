# Shredio Docs Generator — Template Authoring Guide

This document is for AI agents and developers writing documentation templates for the `shredio/docs-generator` build system. Templates are compiled into AI-consumable files (Claude Skills, Commands, project docs, CLAUDE.md).

## How It Works

1. Templates are Markdown files with YAML frontmatter in a configured source directory
2. The build command (`php bin/console docs:generate`) compiles them into output files
3. Generated files are the output — never edit them directly, always edit the template

## Template Anatomy

Every template is a Markdown file starting with a `---` YAML frontmatter block:

```markdown
---
metadata:
  priority: 10
skill:
  target: .claude/skills/my-skill
  description: What this skill does
api:
  - App\Some\ClassName
  - { class: App\Another\Class, visibilities: ["protected"] }
  - { composer: vendor/package-name }
examples:
  - App\Example\Class
  - { class: App\Another\Example, contains: ["expectedMethod"] }
  - { file: path/to/example.php }
---

# Documentation Content

Your markdown content with {{ macro-name: "arguments" }} support.
```

## Frontmatter Fields

### Output Types

A template must have at least one output type to produce a file. Multiple output types can be combined in a single template.

#### `skill` — Claude AI Skill

Generates a `SKILL.md` file in the target directory:

```yaml
skill:
  target: .claude/skills/skill-name    # required, directory path
  name: Display Name                   # optional, defaults to directory basename
  description: What this skill does    # required
```

#### `commands` — Claude CLI Commands

Generates command files. Each command's `prompt` must contain `$ARGUMENTS`:

```yaml
commands:
  - name: my-command
    prompt: Execute task with $ARGUMENTS
  - name: another-command
    prompt: Run another task with $ARGUMENTS
```

#### `docs` — Project Documentation

Generates doc files that appear in the auto-generated docs list on the main file. Requires `docs_dir` to be configured:

```yaml
docs:
  target: getting-started.md    # required, must end with .md
  description: Getting started  # required, shown in docs list
```

#### `output` — General Output

Generates an arbitrary markdown file:

```yaml
output:
  target: path/to/output.md    # required, must end with .md
```

### `main` — Main File

Set `main: true` to designate the central file. The main file:
- Gets priority `-1` (processed last, so it sees all collected data)
- Receives an auto-generated "Project Docs" section listing all `docs` entries

```yaml
main: true
output:
  target: CLAUDE.md
```

### `metadata` — Processing Control

```yaml
metadata:
  priority: 10    # 0-10, higher = processed first, default: 5
```

### `macros` — Macro Control

```yaml
macros:
  disabled: true    # disables macro expansion for this file
```

### `api` — API Reference

Appends an "## API Reference" section with source code snippets of referenced classes. Each entry is labeled with its kind: `(class)`, `(interface)`, `(trait)`, or `(composer package)`.

Three forms:

```yaml
api:
  # String — class/interface/trait FQN
  - App\Http\Controller

  # Object with class — optionally filter by visibility
  - class: App\Database\BaseQuery
    visibilities: ["public", "protected"]    # default: ["public"]

  # Composer package — resolves to LLM.md, llm.md, AGENTS.md, README.md etc. in vendor/
  - composer: vendor/package-name
```

**Important**: Because `api` appends full method signatures automatically, do not duplicate API signatures in the template body. Focus on *how* and *when* to use the API.

### `examples` — Code Examples

Appends an "## Examples" section with full source code of referenced classes.

Three forms:

```yaml
examples:
  # String — class FQN
  - App\Example\ExampleController

  # Object with validation — build fails if strings are missing from source
  - class: App\Example\ExampleController
    contains:
      - "ResponseFactory"
      - "execute"

  # File reference — relative to project root
  - file: src/Example/example.php
```

## Macros

Macros use `{{ name: "arg1", "arg2" }}` syntax and are resolved at build time. They validate references and fail the build on errors.

| Macro | Args | Output |
|-------|------|--------|
| `{{ class-name: "App\\Full\\Name" }}` | 1 | `` `App\Full\Name` `` (validates class exists) |
| `{{ skill-reference: "skill-name" }}` | 1 | `` `skill-name` `` (validates skill exists) |
| `{{ docs-reference: "doc-name.md" }}` | 1 | `` `doc-name.md` `` (validates doc exists) |
| `{{ module-namespace: "Suffix" }}` | 1 | `` `Module\%ModuleName%\Suffix` `` |
| `{{ submodule-namespace: "Sub", "Suffix" }}` | 2 | `` `Module\%ModuleName%\Sub\%SubmoduleName%\Suffix` `` |
| `{{ test-module-namespace: "Type", "Suffix" }}` | 2 | `` `Tests\Type\Module\%ModuleName%\Suffix` `` |
| `{{ test-submodule-namespace: "Type", "Sub", "Suffix" }}` | 3 | `` `Tests\Type\Module\%ModuleName%\Sub\%SubmoduleName%\Suffix` `` |

## Placeholders

Use `%PlaceholderName%` for variable parts in code examples and namespace patterns:

- `%ModuleName%` — module name (e.g., `Stock`, `Account`)
- `%SubmoduleName%` — submodule name (e.g., `Thread`, `Dividend`)
- `%FeatureName%` — feature name (e.g., `GetDividendsBySymbol`)

## Writing Guidelines

- Write in imperative, instructional tone ("Create a class", "Use this pattern")
- Structure with clear headings: Requirements, Structure, Examples, Writing Tests
- Include complete, copy-pasteable PHP code blocks following project conventions
- Show both correct and incorrect approaches where it prevents common mistakes
- Keep templates focused — one concept per file
- Do not repeat information already visible from `api` source code appendix

## Creating a New Template

1. Create a `.md` file in the appropriate subdirectory of the templates folder
2. Add frontmatter with at least one output type (`skill`, `commands`, `docs`, or `output`)
3. Add `api` entries for classes the AI should see as API reference
4. Add `examples` entries for real-world classes the AI should study as patterns
5. Write the documentation body using macros and placeholders
6. Run `php bin/console docs:generate` to build and verify the output
