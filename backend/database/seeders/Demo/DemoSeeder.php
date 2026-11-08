<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use RuntimeException;

abstract class DemoSeeder extends Seeder
{
    public function __invoke(array $parameters = [])
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo seeding is disabled in production.');
        }

        return parent::__invoke($parameters);
    }
}
