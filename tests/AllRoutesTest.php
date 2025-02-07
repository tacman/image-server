<?php

namespace App\Tests;

use Pierstoval\SmokeTesting\SmokeTestStaticRoutes;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class AllRoutesTest extends SmokeTestStaticRoutes
{

    // Your smoke testing class that extends SmokeTestStaticRoutes

    protected function beforeRequest(KernelBrowser $client, string $routeName, string $routePath): void
    {
        if (in_array($routeName, ['monitor.internal_health', 'monitor.health'])) {
            self::markTestSkipped('Untestable route');
        }
    }
}
