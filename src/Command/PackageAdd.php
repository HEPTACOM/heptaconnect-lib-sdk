<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Sdk\Service\Composer;
use Heptacom\HeptaConnect\Sdk\Service\ComposerCommandline;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PackageAdd extends BaseCommand
{
    protected static $defaultName = 'sdk:package:add';

    private string $vendorDir;

    public function __construct(string $vendorDir)
    {
        parent::__construct();
        $this->vendorDir = $vendorDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (($workingDir = $this->getWorkingDir($input)) === null) {
            $io->error(\sprintf('Invalid working directory specified, %s does not exist and is not writable.', $this->getWorkingDir($input)));

            return 1;
        }

        if (!\file_exists($packageComposerJsonPath = $workingDir.'/composer.json')) {
            $io->error(\sprintf('Failed to locate composer.json file in %s.', $workingDir));

            return 1;
        }

        $packageComposerJson = \json_decode(\file_get_contents($packageComposerJsonPath), true);

        if (!isset($packageComposerJson['name'])) {
            $io->error(\sprintf('Your composer.json file (%s) is missing a name attribute.', $packageComposerJsonPath));

            return 1;
        }

        $projectDir = \realpath($this->vendorDir.'/..');

        if (!$projectDir || !\file_exists($projectComposerJsonPath = $projectDir.'/composer.json')) {
            $io->error(\sprintf('Failed to locate composer.json file in %s.', $this->vendorDir.'/..'));

            return 1;
        }

        $composer = new Composer($projectComposerJsonPath);
        $composer->addPathRepository($packageComposerJson['name']);
        $composer->requirePackage($packageComposerJson['name'], '>=0.0.1');

        ComposerCommandline::update($output, $projectDir);

        return 0;
    }
}
