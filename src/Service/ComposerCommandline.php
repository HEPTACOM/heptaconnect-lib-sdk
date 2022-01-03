<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ComposerCommandline
{
    public static function update(OutputInterface $output, string $workingDir): void
    {
        Process::fromShellCommandline(
            'composer update -n',
            $workingDir,
            ['COMPOSER_MEMORY_LIMIT' => -1],
            null,
            null
        )->mustRun(function (string $type, string $data) use ($output): void {
            $output->write($data);
        });
    }
}
