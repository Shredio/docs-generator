<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\FilePath\SourcePath;
use Shredio\DocsGenerator\Reference\ReferenceChecker;

final readonly class DocTemplateMacroContext
{

	public function __construct(
		public SourcePath $sourcePath,
		public ReferenceChecker $referenceChecker,
	)
	{
	}

}
