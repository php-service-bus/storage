<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Common;

use ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions;

/**
 * Adapter configuration for storage.
 *
 * @psalm-readonly
 */
final class StorageConfiguration
{
    /**
     * Original DSN.
     *
     * @var string
     */
    public $originalDSN;

    /**
     * Scheme.
     *
     * @var string|null
     */
    public $scheme;

    /**
     * Database host.
     *
     * @var string|null
     */
    public $host;

    /**
     * Database port.
     *
     * @var int|null
     */
    public $port;

    /**
     * Database user.
     *
     * @var string|null
     */
    public $username;

    /**
     * Database user password.
     *
     * @var string|null
     */
    public $password;

    /**
     * Database name.
     *
     * @var string|null
     */
    public $databaseName;

    /**
     * Connection encoding.
     *
     * @var string|null
     */
    public $encoding;

    /**
     * All query parameters.
     *
     * @var array
     */
    public $queryParameters = [];

    /**
     * @param string $connectionDSN DSN examples:
     *                              - inMemory: sqlite:///:memory:
     *                              - AsyncPostgreSQL: pgsql://user:password@host:port/database
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function __construct(string $connectionDSN)
    {
        $preparedDSN = \preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $connectionDSN);

        /**
         * @psalm-var array{
         *    scheme:string|null,
         *    host:string|null,
         *    port:int|null,
         *    user:string|null,
         *    pass:string|null,
         *    path:string|null
         * }|null|false $parsedDSN
         *
         * @var array|false|null $parsedDSN
         */
        $parsedDSN = \parse_url((string) $preparedDSN);

        // @codeCoverageIgnoreStart
        if (\is_array($parsedDSN) === false)
        {
            throw new InvalidConfigurationOptions('Error while parsing connection DSN');
        }
        // @codeCoverageIgnoreEnd

        $queryString = 'charset=UTF-8';

        if (!empty($parsedDSN['query']))
        {
            $queryString = (string) $parsedDSN['query'];
        }

        \parse_str($queryString, $this->queryParameters);

        /** @var array{charset:string|null, max_connections:int|null, idle_timeout:int|null} $queryParameters */
        $queryParameters = $this->queryParameters;

        $this->originalDSN = $connectionDSN;
        $this->port        = $parsedDSN['port'] ?? null;
        $this->scheme      = self::extract('scheme', $parsedDSN);
        $this->host        = self::extract('host', $parsedDSN, 'localhost');
        $this->username    = self::extract('user', $parsedDSN);
        $this->password    = self::extract('pass', $parsedDSN);
        $this->encoding    = self::extract('charset', $queryParameters, 'UTF-8');

        $databaseName = self::extract('path', $parsedDSN);

        if ($databaseName !== null)
        {
            $databaseName = \ltrim($databaseName, '/');
        }

        $this->databaseName = $databaseName;
    }

    private static function extract(string $key, array $collection, ?string $default = null): ?string
    {
        if (\array_key_exists($key, $collection) === false)
        {
            return $default;
        }

        /** @var string|null $value */
        $value = $collection[$key];

        if (empty($value))
        {
            return $default;
        }

        return $value;
    }
}
