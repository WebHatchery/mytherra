<?php

declare(strict_types=1);

namespace Tests\Era;

use PHPUnit\Framework\TestCase;

final class EraGenerationWiringTest extends TestCase
{
    private string $serviceSource;

    protected function setUp(): void
    {
        $servicePath = dirname(__DIR__, 2) . '/src/Services/EraGenerationService.php';
        $serviceSource = file_get_contents($servicePath);
        self::assertIsString($serviceSource);
        $this->serviceSource = $serviceSource;
    }

    public function testEraGenerationServiceCreatesEraBornContent(): void
    {
        self::assertStringContainsString('class EraGenerationService', $this->serviceSource);

        foreach (
            [
            'createSettlement',
            'createHero',
            'createLandmark',
            'createResource',
            "'era_generation'",
            'Settlement::create',
            'Hero::create',
            'Landmark::create',
            'ResourceNode::create',
            'GameEvent::create',
            ] as $expected
        ) {
            self::assertStringContainsString($expected, $this->serviceSource);
        }
    }

    public function testEraTransitionStoresGeneratedContentInHistory(): void
    {
        $transition = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/EraTransitionService.php');
        $api = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/api/apiService.ts');
        $chronicle = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/pages/ErasPage.tsx');

        self::assertStringContainsString('EraGenerationService', $transition);
        self::assertStringContainsString("\$changes['generation'] = \$this->eraGenerationService->generate", $transition);
        self::assertStringContainsString("\$changes['descendants'] = \$this->createDescendants", $transition);
        self::assertStringContainsString("'generated' => [", $transition);
        self::assertStringContainsString("'generatedSettlements' => count", $transition);
        self::assertStringContainsString("'generatedDescendants' => count", $transition);
        self::assertStringContainsString("'descendants' => \$changes['descendants']['descendants']", $transition);
        self::assertStringContainsString('EraGeneratedContent', $api);
        self::assertStringContainsString('sourceHeroId', $api);
        self::assertStringContainsString('descendants?: EraGeneratedEntity[]', $api);
        self::assertStringContainsString('New Era Foundations', $chronicle);
        self::assertStringContainsString('Descendants', $chronicle);
        self::assertStringContainsString('Generation Event', $chronicle);
    }
}
