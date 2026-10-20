<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Support\InMemoryCacheStore;
use App\Support\InMemoryTransactionManager;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\TransactionManager;

final class InMemoryExtension implements Extension
{
    public function register(Application $application): void
    {
        $application->container()->instance(CacheStore::class, new InMemoryCacheStore());
        $application->container()->instance(TransactionManager::class, new InMemoryTransactionManager());
    }
}
