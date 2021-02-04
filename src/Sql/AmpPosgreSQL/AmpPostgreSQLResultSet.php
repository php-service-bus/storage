<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Sql\AmpPosgreSQL;

use function Amp\call;
use Amp\Postgres\PgSqlCommandResult;
use Amp\Postgres\PooledResultSet;
use Amp\Postgres\PqCommandResult;
use Amp\Promise;
use Amp\Sql\ResultSet as AmpResultSet;
use Amp\Success;
use ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed;
use ServiceBus\Storage\Common\ResultSet;

/**
 *
 */
class AmpPostgreSQLResultSet implements ResultSet
{
    /**
     * @var AmpResultSet|PgSqlCommandResult|PooledResultSet|PqCommandResult
     */
    private $originalResultSet;

    /**
     * @var bool
     */
    private $advanceCalled = false;

    /**
     * @param AmpResultSet|PgSqlCommandResult|PooledResultSet|PqCommandResult $originalResultSet
     */
    public function __construct(object $originalResultSet)
    {
        $this->originalResultSet = $originalResultSet;
    }

    public function advance(): Promise
    {
        $this->advanceCalled = true;

        try
        {
            if ($this->originalResultSet instanceof AmpResultSet)
            {
                return $this->originalResultSet->advance();
            }

            return new Success(false);
        }
        // @codeCoverageIgnoreStart
        catch (\Throwable $throwable)
        {
            throw new ResultSetIterationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
        // @codeCoverageIgnoreEnd
    }

    public function getCurrent(): ?array
    {
        try
        {
            if (
                $this->originalResultSet instanceof PgSqlCommandResult ||
                $this->originalResultSet instanceof PqCommandResult
            ) {
                return null;
            }

            /** @var array<string, float|int|resource|string|null>|null $data */
            $data = $this->originalResultSet->getCurrent();

            return $data;
        }
        // @codeCoverageIgnoreStart
        catch (\Throwable $throwable)
        {
            throw new ResultSetIterationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
        // @codeCoverageIgnoreEnd
    }

    public function lastInsertId(?string $sequence = null): Promise
    {
        return call(
            function (): \Generator
            {
                try
                {
                    if ($this->originalResultSet instanceof PooledResultSet)
                    {
                        if ($this->advanceCalled === false)
                        {
                            yield $this->originalResultSet->advance();

                            $this->advanceCalled = true;
                        }

                        /** @var array<string, mixed> $result */
                        $result = $this->originalResultSet->getCurrent();

                        if (\count($result) !== 0)
                        {
                            /** @var bool|int|string $value */
                            $value = \reset($result);

                            if (false !== $value)
                            {
                                return (string) $value;
                            }
                        }
                    }

                    return null;
                }
                catch (\Throwable $throwable)
                {
                    throw new ResultSetIterationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function affectedRows(): int
    {
        try
        {
            if (
                $this->originalResultSet instanceof PgSqlCommandResult ||
                $this->originalResultSet instanceof PqCommandResult
            ) {
                return $this->originalResultSet->getAffectedRowCount();
            }

            return 0;
        }
        // @codeCoverageIgnoreStart
        catch (\Throwable $throwable)
        {
            throw new ResultSetIterationFailed($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
        }
        // @codeCoverageIgnoreEnd
    }
}
