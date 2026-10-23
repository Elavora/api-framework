<?php

declare(strict_types=1);

namespace App\Http\Controller;

final class Mailer
{
    public function from(): string
    {
        return 'mailer';
    }
}
