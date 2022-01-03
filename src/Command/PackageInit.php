<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Sdk\Maker\Portal;
use Heptacom\HeptaConnect\Sdk\Service\ComposerCommandline;
use Heptacom\HeptaConnect\Sdk\Service\Git;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PackageInit extends BaseCommand
{
    public const KEYWORD_DATASET = 'heptaconnect-dataset';

    public const KEYWORD_PORTAL = 'heptaconnect-portal';

    public const KEYWORD_STORAGE = 'heptaconnect-storage';

    public const VALID_KEYWORDS = [
        self::KEYWORD_DATASET,
        self::KEYWORD_PORTAL,
        self::KEYWORD_STORAGE,
    ];

    protected static $defaultName = 'sdk:package:init';

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

        $composerJsonPath = $workingDir . '/composer.json';
        $composerJson = \file_exists($composerJsonPath) ? \json_decode(\file_get_contents($composerJsonPath), true) : [];

        $composerJson['version'] = '0.0.1';
        $composerJson['description'] = '';
        $packageType = $this->getPackageType($io, $composerJson);
        $this->getPackageName($io, $workingDir, $composerJson);
        $namespace = $this->getNamespace($io, $workingDir, $composerJson);

        $this->addDependency('php', '^7.4', $composerJson);

        switch ($packageType) {
            case self::KEYWORD_DATASET:
                $this->addDependency('heptacom/heptaconnect-dataset-base', '>=0.0.1', $composerJson);

                break;
            case self::KEYWORD_PORTAL:
                $this->addDependency('heptacom/heptaconnect-portal-base', '>=0.0.1', $composerJson);
                $this->makePortal($io, $workingDir, $namespace, $composerJson);

                break;
            case self::KEYWORD_STORAGE:
                $this->addDependency('heptacom/heptaconnect-storage-base', '>=0.0.1', $composerJson);

                break;
            default:
                $io->error(\sprintf('Your package type is not supported by the SDK. (%s)', $packageType));

                return 1;
        }

        if (\file_exists($projectComposerJsonPath = $this->vendorDir . '/../composer.json')) {
            $projectComposerJson = \json_decode(\file_get_contents($projectComposerJsonPath), true);

            if (isset($projectComposerJson['repositories']['heptaconnect-sources'])) {
                $sources = $projectComposerJson['repositories']['heptaconnect-sources'];

                if (isset($sources['type']) && $sources['type'] === 'path' && isset($sources['url'])) {
                    $sourcesUrl = $sources['url'];

                    if (\mb_strpos($sourcesUrl, '/') !== 0) {
                        $sourcesUrl = \realpath(\dirname($projectComposerJsonPath)) . \DIRECTORY_SEPARATOR . $sourcesUrl;
                        $sources['url'] = $sourcesUrl;
                    }
                }

                $composerJson['repositories']['heptaconnect-sources'] = $sources;
            }
        }

        \file_put_contents($composerJsonPath, \json_encode($composerJson, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES) . \PHP_EOL);
        ComposerCommandline::update($output, $workingDir);

        \file_put_contents($workingDir . '/.gitignore', \implode(\PHP_EOL, [
            '/vendor/',
            'composer.lock',
        ]) . \PHP_EOL);
        Git::init($output, $workingDir);

        return 0;
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

        return $composerJson['name'] = (string) $io->ask('Give your package a name.', $nameSuggestion);
    }

    protected function getNamespace(SymfonyStyle $io, string $workingDir, array &$composerJson): string
    {
        if (isset($composerJson['autoload']['psr-4']) && !empty($composerJson['autoload']['psr-4'])) {
            return (string) \key($composerJson['autoload']['psr-4']);
        }

        $suggestion = \implode('\\', \array_map(function (string $name): string {
            $name = \str_replace(['-', '_'], ' ', $name);
            $name = \ucwords($name);

            return \str_replace(' ', '', $name);
        }, \explode('/', $composerJson['name'])));

        $namespace = (string) $io->ask('Specify a PSR-4 compliant namespace.', $suggestion);
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

        $namespace = \rtrim($namespace, '\\');
        Portal::make($sourceDir, $namespace);
        $composerJson['extra']['heptaconnect']['portals'][] = $namespace . '\\Portal';
    }

    private function askForPackageType(SymfonyStyle $io): string
    {
        return (string) $io->choice('Choose the type of package you want to build', self::VALID_KEYWORDS);
    }
}
