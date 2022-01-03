<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Install extends Command
{
    private const BLOCKED_MIGRATION_SOURCES = [
        'core.',
        'null',
        'Framework',
        'Storefront',
    ];

    protected static $defaultName = 'sdk:install';

    private string $dsn;

    private string $vendorDir;

    /**
     * @var array|iterable|\Traversable|MigrationSource[]
     */
    private array $migrationSources;

    public function __construct(
        string $dsn,
        string $vendorDir,
        iterable $migrationSources
    ) {
        parent::__construct();
        $this->dsn = $dsn;
        $this->vendorDir = $vendorDir;
        $this->migrationSources = \iterable_to_array($migrationSources);
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');

        if (
            ($binaryPath = \realpath($this->vendorDir . '/../bin/heptaconnect-sdk')) === false
            && ($binaryPath = \realpath($this->vendorDir . '/bin/heptaconnect-sdk')) === false
        ) {
            $io->error('Unable to find SDK binary.');

            return 1;
        }

        if (($symlink = self::createSymlink($binaryPath)) === null) {
            $io->warning('Unable to create symlink to the SDK binary.');
        }

        if (!$input->isInteractive() && !$force) {
            $output->writeln('The installer should run interactively. Please run this command:');
            $output->writeln(\sprintf('<info>%s sdk:install</info>', $symlink === null ? $binaryPath : 'heptaconnect-sdk'));
            $io->comment('Aborting');

            return 0;
        }

        $connection = $this->getDatabaseLessConnection();

        $this->setupDatabase($io, $force, $connection);
        $output->writeln('');

        $commands = [];

        foreach ($this->migrationSources as $migrationSource) {
            foreach (self::BLOCKED_MIGRATION_SOURCES as $blockedMigrationSource) {
                if (\strpos($migrationSource->getName(), $blockedMigrationSource) === 0) {
                    continue 2;
                }
            }

            \array_push($commands, ...[
                [
                    'command' => 'database:migrate',
                    'identifier' => $migrationSource->getName(),
                    '--all' => true,
                ],
                [
                    'command' => 'database:migrate-destructive',
                    'identifier' => $migrationSource->getName(),
                    '--all' => true,
                ],
            ]);
        }

        \array_push($commands, ...[
            [
                'command' => 'dal:refresh:index',
            ],
            [
                'command' => 'user:create',
                'allowedToFail' => true,
                'username' => 'admin',
                '--admin' => true,
                '--password' => 'shopware',
            ],
            [
                'command' => 'sales-channel:create:storefront',
                'allowedToFail' => true,
                '--name' => 'Storefront',
                '--url' => $_SERVER['APP_URL'] ?? 'http://localhost',
            ],
            [
                'command' => 'assets:install',
            ],
            [
                'command' => 'cache:clear',
            ],
        ]);

        $this->runCommands($commands, $output);

        return 0;
    }

    protected static function createSymlink(string $binaryPath): ?string
    {
        $path = \explode(':', (string) \getenv('PATH'));

        foreach (\array_reverse($path) as $directory) {
            $symlink = $directory . '/heptaconnect-sdk';

            try {
                if (\is_writable($directory) && \symlink($binaryPath, $symlink)) {
                    return $symlink;
                }
            } catch (\Throwable $exception) {
                switch ($exception->getMessage()) {
                    case 'Warning: symlink(): File exists':
                    case 'Warning: symlink(): No such file or directory':
                        try {
                            \unlink($symlink);
                            \symlink($binaryPath, $symlink);

                            return $symlink;
                        } catch (\Throwable $exception) {
                            continue 2;
                        }
                }
            }
        }

        return null;
    }

    private function getDatabaseName(): string
    {
        return \substr(\parse_url($this->dsn)['path'], 1);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getDatabaseLessConnection(): Connection
    {
        $params = \parse_url($this->dsn);

        return DriverManager::getConnection([
            'url' => \sprintf(
                '%s://%s%s:%s',
                $params['scheme'],
                isset($params['pass'], $params['user']) ? ($params['user'] . ':' . $params['pass'] . '@') : '',
                $params['host'],
                $params['port'] ?? 3306
            ),
            'charset' => 'utf8mb4',
        ], new Configuration());
    }

    private function setupDatabase(SymfonyStyle $io, bool $force, Connection $connection): void
    {
        $dbName = $this->getDatabaseName();
        $io->section('Setup database');

        if ($io->confirm(\sprintf('Is it ok to create %s?', $dbName), $force)) {
            $connection->executeUpdate('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`');
            $io->success('Created database `' . $dbName . '`');
        }

        $connection->exec('USE `' . $dbName . '`');

        $tables = $connection->query('SHOW TABLES')->fetchAll(FetchMode::COLUMN);

        if (!\in_array('migration', $tables, true)) {
            $io->writeln('Importing base schema.sql');
            $connection->exec(\file_get_contents($this->vendorDir . '/shopware/core/schema.sql'));
            $io->success('Importing base schema.sql');
        }
    }

    private function runCommands(array $commands, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \RuntimeException('No application initialised');
        }

        foreach ($commands as $parameters) {
            $output->writeln('');

            $command = $application->find((string) $parameters['command']);
            $allowedToFail = $parameters['allowedToFail'] ?? false;
            unset($parameters['command'], $parameters['allowedToFail']);

            try {
                $returnCode = $command->run(new ArrayInput($parameters), $output);
                if ($returnCode !== 0 && !$allowedToFail) {
                    return $returnCode;
                }
            } catch (\Throwable $e) {
                if (!$allowedToFail) {
                    throw $e;
                }
            }
        }

        return 0;
    }
}
