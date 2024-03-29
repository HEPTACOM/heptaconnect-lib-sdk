<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Core\Portal\Contract\PortalRegistryInterface;
use Heptacom\HeptaConnect\Core\StatusReporting\Contract\StatusReportingContextFactoryInterface;
use Heptacom\HeptaConnect\Portal\Base\StorageKey\Contract\PortalNodeKeyInterface;
use Heptacom\HeptaConnect\Portal\Base\StorageKey\PortalNodeKeyCollection;
use Heptacom\HeptaConnect\Storage\Base\Action\PortalNode\Get\PortalNodeGetCriteria;
use Heptacom\HeptaConnect\Storage\Base\Action\PortalNode\Listing\PortalNodeListResult;
use Heptacom\HeptaConnect\Storage\Base\Contract\Action\PortalNode\PortalNodeGetActionInterface;
use Heptacom\HeptaConnect\Storage\Base\Contract\Action\PortalNode\PortalNodeListActionInterface;
use Heptacom\HeptaConnect\Storage\Base\Contract\StorageKeyGeneratorContract;
use Psy\Configuration;
use Psy\Shell;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Tinker extends Command
{
    protected static $defaultName = 'sdk:tinker';

    protected SymfonyStyle $io;

    private StorageKeyGeneratorContract $storageKeyGenerator;

    private PortalRegistryInterface $portalRegistry;

    private StatusReportingContextFactoryInterface $statusReportingContextFactory;

    private PortalNodeListActionInterface $portalNodeListAction;

    private PortalNodeGetActionInterface $portalNodeGetAction;

    public function __construct(
        StorageKeyGeneratorContract $storageKeyGenerator,
        PortalRegistryInterface $portalRegistry,
        StatusReportingContextFactoryInterface $statusReportingContextFactory,
        PortalNodeListActionInterface $portalNodeListAction,
        PortalNodeGetActionInterface $portalNodeGetAction
    ) {
        parent::__construct();
        $this->storageKeyGenerator = $storageKeyGenerator;
        $this->portalRegistry = $portalRegistry;
        $this->statusReportingContextFactory = $statusReportingContextFactory;
        $this->portalNodeListAction = $portalNodeListAction;
        $this->portalNodeGetAction = $portalNodeGetAction;
    }

    protected function configure(): void
    {
        $this->addArgument('portal-node', InputArgument::OPTIONAL, 'Specify a portal node to tinker with');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        ['portal' => $portal, 'context' => $context] = $this->getPortal($input->getArgument('portal-node'));

        $tinkerVariables = [
            'portal' => $portal,
            'context' => $context,
            'getPortal' => fn (?string $portalNodeKeyString = null) => $this->getPortal($portalNodeKeyString),
        ];

        $config = new Configuration(['updateCheck' => 'never']);
        $config->getPresenter()->addCasters(self::getCasters());

        $shell = new Shell($config);
        $shell->setScopeVariables($tinkerVariables);

        return $shell->run();
    }

    protected function getPortal(?string $portalNodeKeyString = null): array
    {
        $portalNodeKey = $this->getPortalNodeKey($portalNodeKeyString);

        return [
            'portal' => $this->portalRegistry->getPortal($portalNodeKey),
            'context' => $this->statusReportingContextFactory->factory($portalNodeKey),
        ];
    }

    protected function getPortalNodeKey(?string $portalNodeKeyString = null): PortalNodeKeyInterface
    {
        if ($portalNodeKeyString) {
            $portalNodeKey = $this->storageKeyGenerator->deserialize($portalNodeKeyString);
        } else {
            $portalNodeKeys = [];
            $portalNodeClasses = [];

            $portalNodes = $this->portalNodeGetAction->get(new PortalNodeGetCriteria(new PortalNodeKeyCollection(
                \iterable_map(
                    $this->portalNodeListAction->list(),
                    static fn (PortalNodeListResult $r): PortalNodeKeyInterface => $r->getPortalNodeKey()
                )
            )));

            foreach ($portalNodes as $portalNode) {
                $portalNodeKey = $this->storageKeyGenerator->serialize($portalNode->getPortalNodeKey());
                $portalNodeKeys[$portalNodeKey] = $portalNode->getPortalNodeKey();
                $portalNodeClasses[$portalNodeKey] = $portalNode->getPortalClass();
            }

            $portalNodeKeyString = $this->io->choice('Select a portal node.', $portalNodeClasses);
            $portalNodeKey = $portalNodeKeys[$portalNodeKeyString] ?? null;
        }

        if (!$portalNodeKey instanceof PortalNodeKeyInterface) {
            throw new \Exception('Portal node key not found.');
        }

        return $portalNodeKey;
    }

    private static function getCasters(): array
    {
        return [
            \JsonSerializable::class => function (\JsonSerializable $object) {
                return \json_decode(\json_encode($object), true);
            },
            \Generator::class => function (\Generator $object) {
                return \iterable_to_array($object);
            },
        ];
    }
}
