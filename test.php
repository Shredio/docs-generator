<?php

use Nette\Utils\Finder;

require __DIR__ . '/vendor/autoload.php';

$pattern = __DIR__ . '/vendor/symfony/*/Resources/file.md';

function getTargetPathsByPattern(string $pattern): iterable
{
	$pos = strrpos($pattern, '*');

	if ($pos === false) {
		return;
	}

	$path = substr($pattern, 0, $pos);
	$suffix = substr($pattern, $pos + 1);

	foreach (Finder::findDirectories('*')->in($path) as $directory) {
		yield $directory->getPathname() . $suffix;
	}
}

foreach (getTargetPathsByPattern($pattern) as $path) {
	echo $path . PHP_EOL;
}
