<?php

declare(strict_types=1);

namespace Tests\Event;

use PHPUnit\Framework\TestCase;

final class EventTimelineFilterWiringTest extends TestCase
{
    public function testBackendAcceptsEraFilterAndConvertsItToYearRange(): void
    {
        $backendRoot = dirname(__DIR__, 2);
        $controllerSource = (string)file_get_contents($backendRoot . '/src/Controllers/EventController.php');
        $repositorySource = (string)file_get_contents($backendRoot . '/src/Repositories/EventRepository.php');

        self::assertStringContainsString("'era' => \$queryParams['era'] ?? null", $controllerSource);
        self::assertStringContainsString('private const ERA_LENGTH_YEARS = 100', $repositorySource);
        self::assertStringContainsString("\$era = \$filters['era'] ?? null", $repositorySource);
        self::assertStringContainsString('is_numeric($era)', $repositorySource);
        self::assertStringContainsString("\$query->whereBetween('year', [\$startYear, \$endYear])", $repositorySource);
    }

    public function testFrontendTimelineAndEraChronicleExposeEraFiltering(): void
    {
        $repoRoot = dirname(__DIR__, 3);
        $apiSource = (string)file_get_contents($repoRoot . '/frontend/src/api/apiService.ts');
        $hookSource = (string)file_get_contents($repoRoot . '/frontend/src/hooks/useEvents.ts');
        $eventsPageSource = (string)file_get_contents($repoRoot . '/frontend/src/pages/EventsPage.tsx');
        $erasPageSource = (string)file_get_contents($repoRoot . '/frontend/src/pages/ErasPage.tsx');

        self::assertStringContainsString('era?: string', $apiSource);
        self::assertStringContainsString('resourceId, era, type, status', $hookSource);
        self::assertStringContainsString("searchParams.get('era')", $eventsPageSource);
        self::assertStringContainsString("updateFilter('era'", $eventsPageSource);
        self::assertStringContainsString('Current Era Timeline', $erasPageSource);
        self::assertStringContainsString('/events?era=${transition?.currentEra ?? 1}', $erasPageSource);
        self::assertStringContainsString('/events?era=${entry.completedEra}', $erasPageSource);
    }
}
