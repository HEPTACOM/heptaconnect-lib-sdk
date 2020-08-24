<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\FetchMode;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Install extends Command
{
    protected static $defaultName = 'sdk:install';

    private string $dsn;

    private string $vendorDir;

    private TagAwareAdapterInterface $cache;

    private LoggerInterface $logger;

    private EntityIndexerRegistry $entityIndexerRegistry;

    /** @var array|iterable|\Traversable|MigrationSource[] */
    private array $migrationSources;

    public function __construct(
        string $dsn,
        string $vendorDir,
        TagAwareAdapterInterface $cache,
        LoggerInterface $logger,
        EntityIndexerRegistry $entityIndexerRegistry,
        iterable $migrationSources
    ) {
        parent::__construct(null);
        $this->dsn = $dsn;
        $this->vendorDir = $vendorDir;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->entityIndexerRegistry = $entityIndexerRegistry;
        $this->migrationSources = iterable_to_array($migrationSources);
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
        $this->runMigrations($io, new MigrationCollectionLoader(
            $connection,
            new MigrationRuntime($connection, $this->logger),
            $this->migrationSources
        ));
        $this->runIndexers($io);
        $this->cache->clear();

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
        return substr(parse_url($this->dsn)['path'], 1);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    private function getDatabaseLessConnection(): Connection
    {
        $params = parse_url($this->dsn);

        return DriverManager::getConnection([
            'url' => sprintf(
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

        if ($io->confirm(sprintf('Is it ok to create %s?', $dbName), $force)) {
            $connection->executeUpdate('CREATE DATABASE IF NOT EXISTS `' . $dbName . '` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`');
            $io->success('Created database `' . $dbName . '`');
        }

        $connection->exec('USE `' . $dbName . '`');

        $tables = $connection->query('SHOW TABLES')->fetchAll(FetchMode::COLUMN);

        if (!in_array('migration', $tables, true)) {
            $io->writeln('Importing base schema.sql');
            $connection->exec(file_get_contents($this->vendorDir . '/shopware/core/schema.sql'));
            $io->success('Importing base schema.sql');
        }
    }

    /**
     * @throws \Throwable
     */
    private function runMigrations(SymfonyStyle $io, MigrationCollectionLoader $loader): void
    {
        $io->section('Run migrations');

        foreach (['null', 'core', 'Framework', 'HeptaConnectBridgeShopwarePlatform'] as $migrationSourceName) {
            $collection = $loader->collect($migrationSourceName);
            $collection->sync();
            $total = \count($collection->getExecutableMigrations());
            $io->progressStart($total);

            try {
                foreach ($collection->migrateInSteps() as $_return) {
                    $io->progressAdvance();
                }
            } catch (\Throwable $e) {
                $io->progressFinish();
                throw $e;
            }

            $collection = $loader->collect($migrationSourceName);
            $collection->sync();
            $total = \count($collection->getExecutableDestructiveMigrations());
            $io->progressStart($total);

            try {
                foreach ($collection->migrateInSteps() as $_return) {
                    $io->progressAdvance();
                }
            } catch (\Throwable $e) {
                $io->progressFinish();
                throw $e;
            }
        }

        $io->success('Successfully run migrations');
    }

    private function runIndexers(SymfonyStyle $io): void
    {
        $io->section('Run indexers');

        $this->entityIndexerRegistry->index(false);

        $io->success('Successfully run indexers');
    }
}
