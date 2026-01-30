<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Reference;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\FilePath\SourcePath;

final class ReferenceChecker
{

	/** @var array<non-empty-string, true> */
	private array $skills = [];

	/** @var array<non-empty-string, non-empty-list<non-empty-string>> file => skillName */
	private array $usedSkills = [];

	/** @var array<non-empty-string, true> */
	private array $docs = [];

	/** @var array<non-empty-string, non-empty-list<non-empty-string>> file => docName */
	private array $usedDocs = [];

	/**
	 * @param non-empty-string $skillName
	 */
	public function addSkill(string $skillName): void
	{
		$this->skills[$skillName] = true;
	}

	/**
	 * @param non-empty-string $skillName
	 */
	public function checkSkill(SourcePath $sourcePath, string $skillName): void
	{
		$this->usedSkills[$sourcePath->relativePathFromRoot][] = $skillName;
	}

	/**
	 * @param non-empty-string $docName
	 */
	public function addDoc(string $docName): void
	{
		$this->docs[$docName] = true;
	}

	/**
	 * @param non-empty-string $docName
	 */
	public function checkDoc(SourcePath $sourcePath, string $docName): void
	{
		$this->usedDocs[$sourcePath->relativePathFromRoot][] = $docName;
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
