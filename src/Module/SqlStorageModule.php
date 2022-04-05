<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Module;

use Psr\Log\LoggerInterface;
use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class SqlStorageModule implements ServiceBusModule
{
    private const ADAPTER_TYPE_POSTGRES  = 'postgres';

    private const ADAPTER_TYPE_IN_MEMORY = 'memory';

    private const ADAPTERS_MAPPING = [
        self::ADAPTER_TYPE_IN_MEMORY => DoctrineDBALAdapter::class,
        self::ADAPTER_TYPE_POSTGRES  => AmpPostgreSQLAdapter::class,
    ];

    /**
     * @psalm-var non-empty-string
     *
     * @var string
     */
    private $adapterType;

    /**
     * @psalm-var non-empty-string
     *
     * @var string
     */
    private $connectionDSN;

    /**
     * Log SQL queries.
     *
     * @var bool
     */
    private $loggerEnabled = false;

    /**
     * Configure PostgreSQL storage adapter.
     *
     * DSN example: pgsql://user:password@host:port/database
     *
     * @psalm-param non-empty-string $connectionDSN
     */
    public static function postgreSQL(string $connectionDSN): self
    {
        return new self(self::ADAPTER_TYPE_POSTGRES, $connectionDSN);
    }

    /**
     * Configure in memory adapter (tests only).
     */
    public static function inMemory(): self
    {
        return new self(self::ADAPTER_TYPE_IN_MEMORY, 'sqlite:///:memory:');
    }

    /**
     * Enable SQL queries logging.
     */
    public function enableLogger(): self
    {
        $this->loggerEnabled = true;

        return $this;
    }

    public function boot(ContainerBuilder $containerBuilder): void
    {
        $this->injectParameters($containerBuilder);

        /** @psalm-var class-string<\ServiceBus\Storage\Common\DatabaseAdapter> $adapterClass */
        $adapterClass = self::ADAPTERS_MAPPING[$this->adapterType];

        $configDefinition   = new Definition(StorageConfiguration::class, [
            '%service_bus.infrastructure.sql.connection_dsn%',
        ]);

        $containerBuilder->addDefinitions([StorageConfiguration::class => $configDefinition]);

        $adapterDefinitionParameters = [new Reference(StorageConfiguration::class)];

        if (true === $this->loggerEnabled)
        {
            $adapterDefinitionParameters[] = new Reference(LoggerInterface::class);
        }

        $adapterDefinition = new Definition($adapterClass, $adapterDefinitionParameters);

        $containerBuilder->addDefinitions([DatabaseAdapter::class => $adapterDefinition]);
    }

    private function injectParameters(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->setParameter('service_bus.infrastructure.sql.connection_dsn', $this->connectionDSN);
    }

    /**
     * @psalm-param non-empty-string $adapterType
     * @psalm-param non-empty-string $connectionDSN
     */
    private function __construct(string $adapterType, string $connectionDSN)
    {
        $this->adapterType   = $adapterType;
        $this->connectionDSN = $connectionDSN;
    }
}
