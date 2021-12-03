<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Common;

use ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions;

/**
 * Adapter configuration for storage.
 */
final class StorageConfiguration
{
    /**
     * Original DSN.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $originalDSN;

    /**
     * Scheme.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $scheme;

    /**
     * Database host.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string
     *
     * @var string
     */
    public $host;

    /**
     * Database port.
     *
     * @psalm-readonly
     * @psalm-var positive-int|null
     *
     * @var int|null
     */
    public $port;

    /**
     * Database user.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $username;

    /**
     * Database user password.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $password;

    /**
     * Database name.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $databaseName;

    /**
     * Connection encoding.
     *
     * @psalm-readonly
     * @psalm-var non-empty-string|null
     *
     * @var string|null
     */
    public $encoding;

    /**
     * All query parameters.
     *
     * @psalm-readonly
     * @psalm-var array<array-key, string>
     *
     * @var array
     */
    public $queryParameters = [];

    /**
     * @psalm-param non-empty-string $connectionDSN DSN examples:
     *                                              - inMemory: sqlite:///:memory:
     *                                              - AsyncPostgreSQL: pgsql://user:password@host:port/database
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function __construct(string $connectionDSN)
    {
        $preparedDSN = \preg_replace('#^((?:pdo_)?sqlite3?):///#', '$1://localhost/', $connectionDSN);

        /**
         * @psalm-var array{
         *    scheme:non-empty-string|null,
         *    host:non-empty-string|null,
         *    port:positive-int|null,
         *    user:non-empty-string|null,
         *    pass:non-empty-string|null,
         *    path:non-empty-string|null,
         *    query:non-empty-string|null
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
            $queryString = $parsedDSN['query'];
        }

        \parse_str($queryString, $queryParameters);

        /** @psalm-var array<array-key, string> $queryParameters */
        $this->queryParameters = $queryParameters;

        $this->originalDSN  = $connectionDSN;
        $this->port         = !empty($parsedDSN['port']) ? $parsedDSN['port'] : null;
        $this->scheme       = !empty($parsedDSN['scheme']) ? $parsedDSN['scheme'] : null;
        $this->host         = !empty($parsedDSN['host']) ? $parsedDSN['host'] : 'localhost';
        $this->username     = !empty($parsedDSN['user']) ? $parsedDSN['user'] : null;
        $this->password     = !empty($parsedDSN['pass']) ? $parsedDSN['pass'] : null;
        $this->encoding     = !empty($queryParameters['charset']) ? $queryParameters['charset'] : 'UTF-8';

        /** @psalm-var non-empty-string|null $databaseName */
        $databaseName = !empty($parsedDSN['path']) ? \ltrim($parsedDSN['path'], '/') : null;

        $this->databaseName = $databaseName;
    }
}
