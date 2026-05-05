<?php

namespace App\Utils;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Cache\CacheManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Cache\FileStore;

class LaravelBootstrap
{
    public static function initialize(): void
    {
        $container = Container::getInstance();

    // Set up Cache facade
        $container->singleton('files', function () {
            return new Filesystem();
        });

        $container->singleton('cache', function () use ($container) {
            $cacheManager = new CacheManager($container);

    // Configure file cache driver
            $cachePath = __DIR__ . '/../../storage/cache';
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0777, true);
            }

            $store = new FileStore(new Filesystem(), $cachePath);
            $cacheManager->extend('file', function () use ($store) {
                return $store;
            });

            return $cacheManager;
        });

    // Set up Cache alias
        $container->alias('cache', \Illuminate\Contracts\Cache\Factory::class);
        $container->alias('cache', \Illuminate\Contracts\Cache\Repository::class);

    // Set the facade root
        Facade::setFacadeApplication($container);
    }
}
