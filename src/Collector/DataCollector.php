<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Collector;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Path\ResolvedPath;

final class DataCollector
{

	/** @var array<non-empty-string, non-empty-string> */
	private array $skills = [];

	/** @var array<non-empty-string, array{non-empty-string, non-empty-string}> $docs relativePath => description */
	public array $docs = [];

	/** @var array<non-empty-string, non-empty-list<non-empty-string>> file => docName */
	private array $usedDocs = [];

	/** @var array<non-empty-string, non-empty-list<non-empty-string>> file => skillName */
	private array $usedSkills = [];

	/**
	 * @param non-empty-string $name
	 * @param non-empty-string $description
	 *
	 * @throws GeneratingFailedException
	 */
	public function addSkill(string $name, string $description): void
	{
		if (isset($this->skills[$name])) {
			throw new GeneratingFailedException(sprintf('Skill "%s" is already defined.', $name));
		}

		$this->skills[$name] = $description;
	}

	/**
	 * @param non-empty-string $name
	 * @param non-empty-string $path
	 * @param non-empty-string $description
	 *
	 * @throws GeneratingFailedException
	 */
	public function addDoc(string $name, string $path, string $description): void
	{
		if (isset($this->docs[$name])) {
			throw new GeneratingFailedException(sprintf('Doc "%s" is already defined.', $name));
		}

		$this->docs[$name] = [$path, $description];
	}

	/**
	 * @param non-empty-string $name
	 */
	public function checkDoc(ResolvedPath $sourcePath, string $name): void
	{
		$this->usedDocs[$sourcePath->getRelativePathToRootDir()][] = $name;
	}

	/**
	 * @param non-empty-string $name
	 */
	public function checkSkill(ResolvedPath $sourcePath, string $name): void
	{
		$this->usedSkills[$sourcePath->getRelativePathToRootDir()][] = $name;
	}

	/**
	 * @throws GeneratingFailedException
	 */
	public function validate(): void
	{
		foreach ($this->usedSkills as $file => $skillNames) {
			foreach ($skillNames as $skillName) {
				if (!isset($this->skills[$skillName])) {
					throw new GeneratingFailedException(sprintf(
						'File "%s" uses unknown skill "%s".',
						$file,
						$skillName,
					));
				}
			}
		}

		foreach ($this->usedDocs as $file => $docNames) {
			foreach ($docNames as $docName) {
				if (!isset($this->docs[$docName])) {
					throw new GeneratingFailedException(sprintf(
						'File "%s" uses unknown doc "%s".',
						$file,
						$docName,
					));
				}
			}
		}
	}

}
