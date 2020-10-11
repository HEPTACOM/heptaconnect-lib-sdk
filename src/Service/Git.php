<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class Git
{
    public static function init(OutputInterface $output, string $workingDir): void
    {
        Process::fromShellCommandline(
            'git init',
            $workingDir,
            [],
            null,
            null
        )->mustRun(function (string $type, string $data) use ($output) {
            $output->write($data);
        });
    }
}
