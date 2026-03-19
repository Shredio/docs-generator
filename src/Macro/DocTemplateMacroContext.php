<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Collector\DataCollector;
use Shredio\DocsGenerator\Path\ResolvedPath;

final readonly class DocTemplateMacroContext
{

	public function __construct(
		public ResolvedPath $sourcePath,
		public DataCollector $dataCollector,
	)
	{
	}

}
