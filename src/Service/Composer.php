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

            self::increaseMemoryLimit(function () use ($install) {
                $install->setUpdate(true)->run();
            });
        } finally {
            chdir($cwd);
        }
    }

    private static function increaseMemoryLimit(callable $callable)
    {
        if (function_exists('ini_set')) {
            @ini_set('display_errors', '1');

            $memoryInBytes = function ($value) {
                $unit = strtolower(substr($value, -1, 1));
                $value = (int) $value;
                switch($unit) {
                    /** @noinspection PhpMissingBreakStatementInspection (cumulative multiplier) */
                    case 'g':
                        $value *= 1024;
                    /** @noinspection PhpMissingBreakStatementInspection (cumulative multiplier) */
                    case 'm':
                        $value *= 1024;
                    case 'k':
                        $value *= 1024;
                }

                return $value;
            };

            $memoryLimit = trim(ini_get('memory_limit'));
            // Increase memory_limit if it is lower than 1.5GB
            if ($memoryLimit != -1 && $memoryInBytes($memoryLimit) < 1024 * 1024 * 1536) {
                @ini_set('memory_limit', '1536M');
            }
            // Set user defined memory limit
            if ($memoryLimit = getenv('COMPOSER_MEMORY_LIMIT')) {
                @ini_set('memory_limit', (string) $memoryLimit);
            }
            unset($memoryInBytes, $memoryLimit);
        }

        return call_user_func($callable);
    }
}
