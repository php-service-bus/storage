<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Module;

use ServiceBus\Common\Module\ServiceBusModule;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 */
final class SqlStorageModule implements ServiceBusModule
{
    private const ADAPTER_TYPE_POSTGRES  = 'postgres';

    private const ADAPTER_TYPE_IN_MEMORY = 'memory';

    private const ADAPTERS_MAPPING = [
        self::ADAPTER_TYPE_IN_MEMORY => DoctrineDBALAdapter::class,
        self::ADAPTER_TYPE_POSTGRES  => AmpPostgreSQLAdapter::class,
    ];

    /** @var string  */
    private $adapterType;

    /** @var string  */
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
     * @param string $connectionDSN
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

    /**
     * {@inheritdoc}
     */
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
            $adapterDefinitionParameters[] = new Reference('service_bus.logger');
        }

        $adapterDefinition = new Definition($adapterClass, $adapterDefinitionParameters);

        $containerBuilder->addDefinitions([DatabaseAdapter::class => $adapterDefinition]);
    }

    private function injectParameters(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->setParameter('service_bus.infrastructure.sql.connection_dsn', $this->connectionDSN);
    }

    private function __construct(string $adapterType, string $connectionDSN)
    {
        $this->adapterType   = $adapterType;
        $this->connectionDSN = $connectionDSN;
    }
}
