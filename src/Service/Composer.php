<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Composer;

use Composer\Factory;
use Composer\Installer;
use Composer\IO\ConsoleIO;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Composer
{
    public static function update(InputInterface $input, OutputInterface $output, string $workingDir): void
    {
        $cwd = getcwd();
        chdir($workingDir);

        try {
            $io = new ConsoleIO($input, $output, new HelperSet());
            $composer = Factory::create($io, null, true);
            $install = Installer::create($io, $composer);

            $install->setUpdate(true)->run();
        } finally {
            chdir($cwd);
        }
    }
}
