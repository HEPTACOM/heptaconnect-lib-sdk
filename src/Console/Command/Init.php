<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Console\Command;

use Symfony\Component\Console\Command\Command;
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

    private string $vendorDir;

    public function __construct(string $vendorDir)
    {
        parent::__construct();
        $this->vendorDir = $vendorDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $composerJsonPath = realpath($this->vendorDir . '/../composer.json');

        if ($composerJsonPath === false) {
            $io->error('The composer.json file of your package was not found.');

            return 1;
        }

        $composerJson = \json_decode(\file_get_contents($composerJsonPath), true);
        $packageType = $this->getPackageType($io, $composerJson);

        switch ($packageType) {
            case self::KEYWORD_DATASET:
                $nameSuggestion = 'heptaconnect-new-dataset';
                $this->addDependency('heptacom/heptaconnect-dataset-base', '@dev', $composerJson);

                break;
            case self::KEYWORD_PORTAL:
                $nameSuggestion = 'heptaconnect-new-portal';
                $this->addDependency('heptacom/heptaconnect-portal-base', '@dev', $composerJson);

                break;
            case self::KEYWORD_STORAGE:
                $nameSuggestion = 'heptaconnect-new-storage';
                $this->addDependency('heptacom/heptaconnect-storage-base', '@dev', $composerJson);

                break;
            default:
                $io->error(sprintf('Your package type is not supported by the SDK. (%s)', $packageType));

                return 1;
        }

        if ($composerJson['name'] === 'heptacom/heptaconnect-skeleton') {
            $this->getPackageName($io, $nameSuggestion, $composerJson);
        }

        file_put_contents($composerJsonPath, \json_encode($composerJson, JSON_PRETTY_PRINT));

        return 0;
    }

    protected function getPackageType(SymfonyStyle $io, array &$composerJson): string
    {
        if (!isset($composerJson['keywords']) || empty($composerJson['keywords'])) {
            return $composerJson['keywords'][] = $this->askForPackageType($io);
        }

        foreach ($composerJson['keywords'] as $keyword) {
            if (in_array($keyword, self::VALID_KEYWORDS, true)) {
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

    protected function getPackageName(SymfonyStyle $io, string $nameSuggestion, array &$composerJson): string
    {
        $nameSuggestion = get_current_user() . '/' . $nameSuggestion;

        return $composerJson['name'] = (string) $io->ask('Please give your package a name.', $nameSuggestion);
    }
}
