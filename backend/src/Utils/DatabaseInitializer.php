<?php

declare(strict_types=1);

namespace App\Utils;

use App\Core\Environment;
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseInitializer
{
    public static function initialize(array $config = null): void
    {
        $capsule = new Capsule();

    // If no config provided, use environment variables
        if ($config === null) {
            $config = [
                'driver'    => Environment::required('DB_CONNECTION'),
                'host'      => Environment::required('DB_HOST'),
                'database'  => Environment::required('DB_NAME'),
                'username'  => Environment::required('DB_USER'),
                'password'  => Environment::required('DB_PASSWORD'),
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => '',
            ];
        }

        $capsule->addConnection($config);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
    }
}
