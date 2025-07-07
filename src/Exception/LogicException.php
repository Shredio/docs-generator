<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Exception;

final class LogicException extends \LogicException
{

	public ?string $sourceFile = null;

}
