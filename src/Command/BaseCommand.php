<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

abstract class BaseCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('working-dir', InputArgument::OPTIONAL, 'If specified, use the given directory as working directory.');
    }

    protected function getWorkingDir(InputInterface $input): ?string
    {
        if (!$input->getArgument('working-dir')) {
            return \getcwd();
        }

        $workingDir = \realpath($input->getArgument('working-dir'));

        if ($workingDir) {
            return $workingDir;
        }

        if (\mkdir($input->getArgument('working-dir'), 0775, true) &&
            ($workingDir = \realpath($input->getArgument('working-dir')))) {
            return $workingDir;
        }

        return null;
    }
}
