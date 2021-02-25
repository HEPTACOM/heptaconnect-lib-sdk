<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Sdk\Service\Composer;
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

        if (!file_exists($packageComposerJsonPath = $workingDir . '/composer.json')) {
            $io->error(\sprintf('Failed to locate composer.json file in %s.', $workingDir));

            return 1;
        }

        $packageComposerJson = \json_decode(\file_get_contents($packageComposerJsonPath), true);

        if (!isset($packageComposerJson['name'])) {
            $io->error(sprintf('Your composer.json file (%s) is missing a name attribute.', $packageComposerJsonPath));

            return 1;
        }

        $projectDir = \realpath($this->vendorDir . '/..');

        if (!$projectDir || !file_exists($projectComposerJsonPath = $projectDir . '/composer.json')) {
            $io->error(\sprintf('Failed to locate composer.json file in %s.', $this->vendorDir . '/..'));

            return 1;
        }

        $projectComposerJson = \json_decode(\file_get_contents($projectComposerJsonPath), true);

        $packageName = $packageComposerJson['name'];
        $projectComposerJson['require'][$packageName] = '>=0.0.1';

        $shouldAddRepository = true;
        if (isset($projectComposerJson['repositories'])) {
            foreach ($projectComposerJson['repositories'] as $repository) {
                if (isset($repository['type']) && $repository['type'] === 'path' && isset($repository['url']) && $repository['url'] === $workingDir) {
                    $shouldAddRepository = false;
                }
            }
        }

        if ($shouldAddRepository) {
            $projectComposerJson['repositories'][] = [
                'type' => 'path',
                'url' => $workingDir,
            ];
        }

        \file_put_contents($projectComposerJsonPath, \json_encode($projectComposerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        Composer::update($output, $projectDir);

        return 0;
    }
}
