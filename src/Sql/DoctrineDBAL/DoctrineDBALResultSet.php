<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Sql\DoctrineDBAL;

use Amp\Promise;
use Amp\Success;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed;
use ServiceBus\Storage\Common\ResultSet;

/**
 *
 */
final class DoctrineDBALResultSet implements ResultSet
{
    /**
     * Last row emitted.
     *
     * @var array|null
     */
    private $currentRow;

    /**
     * Pdo fetch result.
     *
     * @var array
     */
    private $fetchResult;

    /**
     * Results count.
     *
     * @var int
     */
    private $resultsCount;

    /**
     * Current iterator position.
     *
     * @var int
     */
    private $currentPosition = 0;

    /**
     * Connection instance.
     *
     * @var Connection
     */
    private $connection;

    /**
     * Number of rows affected by the last DELETE, INSERT, or UPDATE statement.
     *
     * @var int
     */
    private $affectedRows;

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(Connection $connection, Result $result)
    {
        $this->connection   = $connection;
        $this->fetchResult  = $result->fetchAllAssociative();
        $this->affectedRows = $result->rowCount();
        $this->resultsCount = \count($this->fetchResult);

        $result->free();
    }

    public function advance(): Promise
    {
        $this->currentRow = null;

        if (++$this->currentPosition > $this->resultsCount)
        {
            return new Success(false);
        }

        return new Success(true);
    }

    public function getCurrent(): ?array
    {
        if (null !== $this->currentRow)
        {
            /**
             * @psalm-var array<string, float|int|resource|string|null>|null $row
             *
             * @var array                                                    $row
             */
            $row = $this->currentRow;

            return $row;
        }

        /**
         * @psalm-var array<string, float|int|resource|string|null>|null $data
         */
        $data = $this->fetchResult[$this->currentPosition - 1] ?? null;

        if (\is_array($data) && \count($data) === 0)
        {
            $data = null;
        }

        return $this->currentRow = $data;
    }

    public function lastInsertId(?string $sequence = null): Promise
    {
        try
        {
            return new Success($this->connection->lastInsertId($sequence));
        }
        catch (\Throwable $throwable)
        {
            throw new ResultSetIterationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
    }

    public function affectedRows(): int
    {
        return $this->affectedRows;
    }
}
