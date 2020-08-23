<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Init extends Command
{
    const KEYWORD_DATASET = 'heptaconnect-dataset';

    const KEYWORD_PORTAL = 'heptaconnect-portal';

    const KEYWORD_STORAGE = 'heptaconnect-storage';

    const VALID_KEYWORDS = [
        self::KEYWORD_DATASET,
        self::KEYWORD_PORTAL,
        self::KEYWORD_STORAGE,
    ];

    protected static $defaultName = 'sdk:init';

    protected function configure()
    {
        $this->addArgument('working-dir', InputArgument::OPTIONAL, 'If specified, use the given directory as working directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (($workingDir = $this->getWorkingDir($input)) === null) {
            $io->error(\sprintf('Invalid working directory specified, %s does not exist and is not writable.', $this->getWorkingDir($input)));

            return 1;
        }

        $composerJsonPath = $workingDir . '/composer.json';
        $composerJson = \file_exists($composerJsonPath) ? \json_decode(\file_get_contents($composerJsonPath), true) : [];

        $packageType = $this->getPackageType($io, $composerJson);
        $this->getPackageName($io, $workingDir, $composerJson);
        $namespace = $this->getNamespace($io, $workingDir, $composerJson);

        switch ($packageType) {
            case self::KEYWORD_DATASET:
                $this->addDependency('heptacom/heptaconnect-dataset-base', '@dev', $composerJson);

                break;
            case self::KEYWORD_PORTAL:
                $this->addDependency('heptacom/heptaconnect-portal-base', '@dev', $composerJson);
                $this->makePortal($io, $workingDir, $namespace, $composerJson);

                break;
            case self::KEYWORD_STORAGE:
                $this->addDependency('heptacom/heptaconnect-storage-base', '@dev', $composerJson);

                break;
            default:
                $io->error(\sprintf('Your package type is not supported by the SDK. (%s)', $packageType));

                return 1;
        }

        \file_put_contents($composerJsonPath, \json_encode($composerJson, JSON_PRETTY_PRINT) . PHP_EOL);

        return 0;
    }

    private function getWorkingDir(InputInterface $input): ?string
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

    protected function getPackageType(SymfonyStyle $io, array &$composerJson): string
    {
        if (!isset($composerJson['keywords']) || empty($composerJson['keywords'])) {
            return $composerJson['keywords'][] = $this->askForPackageType($io);
        }

        foreach ($composerJson['keywords'] as $keyword) {
            if (\in_array($keyword, self::VALID_KEYWORDS, true)) {
                return $keyword;
            }
        }

        return $composerJson['keywords'][] = $this->askForPackageType($io);
    }

    private function askForPackageType(SymfonyStyle $io): string
    {
        return (string) $io->choice('Please choose the type of package you want to build:', self::VALID_KEYWORDS);
    }

    protected function addDependency(string $packageName, string $version, array &$composerJson): void
    {
        $composerJson['require'][$packageName] = $version;
    }

    protected function getPackageName(SymfonyStyle $io, string $workingDir, array &$composerJson): string
    {
        if (isset($composerJson['name']) && $composerJson['name'] !== 'heptacom/heptaconnect-skeleton') {
            return $composerJson['name'];
        }

        $nameSuggestion = \get_current_user() . '/' . \basename($workingDir);

        return $composerJson['name'] = (string) $io->ask('Please give your package a name.', $nameSuggestion);
    }

    protected function getNamespace(SymfonyStyle $io, string $workingDir, array &$composerJson): string
    {
        if (isset($composerJson['autoload']['psr-4']) && !empty($composerJson['autoload']['psr-4'])) {
            return (string) \key($composerJson['autoload']['psr-4']);
        }

        $suggestion = \join('\\', \array_map(function (string $name): string {
            $name = \str_replace(['-', '_'], ' ', $name);
            $name = \ucwords($name);

            return \str_replace(' ', '', $name);
        }, \explode('/', $composerJson['name'])));

        $namespace = (string) $io->ask('Please specify a PSR-4 compliant namespace.', $suggestion);
        $namespace = \trim($namespace, '\\') . '\\';

        $composerJson['autoload']['psr-4'][$namespace] = 'src/';

        $sourceDir = $workingDir . '/' . $composerJson['autoload']['psr-4'][$namespace];

        if (!\is_dir($sourceDir)) {
            \mkdir($sourceDir, 0775, true);
        }

        return $namespace;
    }

    protected function makePortal(SymfonyStyle $io, string $workingDir, string $namespace, array &$composerJson): void
    {
        if (!isset($composerJson['autoload']['psr-4'][$namespace])) {
            $io->warning('A portal could not be created, because your composer.json is missing a PSR-4 compliant autoload definition.');

            return;
        }

        $sourceDir = $workingDir . '/' . $composerJson['autoload']['psr-4'][$namespace];

        if (!\is_dir($sourceDir) && !\mkdir($sourceDir, 0775, true)) {
            $io->warning(\sprintf('A portal could not be created, because the source directory is not writable: %s', $sourceDir));

            return;
        }

        $fileLocation = $sourceDir . '/Portal.php';
        $namespace = \rtrim($namespace, '\\');
        $fqn = $namespace . '\\Portal';

        $template = <<<"PHP"
<?php declare(strict_types=1);

namespace $namespace;

use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;

class Portal extends PortalContract
{
}

PHP;

        \file_put_contents($fileLocation, $template);
        $composerJson['extra']['heptaconnect']['portals'][] = $fqn;
    }
}
