<?php

declare(strict_types=1);

namespace App\Support;

use Elavora\Api\Framework\Contracts\TransactionManager;

final class InMemoryTransactionManager implements TransactionManager
{
    /** @var list<string> */
    private array $log = [];

    public function begin(): bool
    {
        $this->log[] = 'begin';

        return true;
    }

    public function commit(): bool
    {
        $this->log[] = 'commit';

        return true;
    }

    public function rollback(): bool
    {
        $this->log[] = 'rollback';

        return true;
    }

    /**
     * @return list<string>
     */
    public function log(): array
    {
        return $this->log;
    }
}
