<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameState;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Player;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use App\Repositories\EventRepository;
use App\Repositories\HeroRepository;
use App\Repositories\LandmarkRepository;
use App\Repositories\RegionRepository;

class ArtifactService
{
    private const CONFIG_CATEGORY = 'artifacts';
    private const CONFIG_KEY_ROSTER = 'roster';
    private const MAX_ARTIFACTS = 9;
    private const CREATION_COST = 40;
    private const EMPOWER_BASE_COST = 20;
    private const TRANSFER_COST = 8;
    private const STABILIZE_BASE_COST = 15;

    private const FOCUS_OPTIONS = [
        'protection' => [
            'label' => 'Protection',
            'summary' => 'Wards settlements and champions, but resists being moved.',
            'instability' => 10,
            'corruption' => 0,
        ],
        'prosperity' => [
            'label' => 'Prosperity',
            'summary' => 'Amplifies growth and resources, but attracts theft.',
            'instability' => 14,
            'corruption' => 2,
        ],
        'war' => [
            'label' => 'War',
            'summary' => 'Turns conflict in the owner region, but corrupts quickly.',
            'instability' => 18,
            'corruption' => 6,
        ],
        'knowledge' => [
            'label' => 'Knowledge',
            'summary' => 'Draws out magic and research, but behaves unpredictably.',
            'instability' => 16,
            'corruption' => 3,
        ],
    ];

    private const STARTER_ARTIFACTS = [
        'artifact-starter-crystal-aegis' => [
            'name' => 'Crystal Aegis of First Dawn',
            'focus' => 'protection',
            'powerLevel' => 2,
            'instability' => 18,
            'corruption' => 0,
            'status' => 'active',
            'ownerType' => 'landmark',
            'ownerId' => 'landmark-001',
            'originSummary' => 'A warding relic kept inside the Crystal Sanctuary since the first temple bells.',
        ],
        'artifact-starter-goldtide-ledger' => [
            'name' => 'Goldtide Ledger',
            'focus' => 'prosperity',
            'powerLevel' => 2,
            'instability' => 22,
            'corruption' => 4,
            'status' => 'active',
            'ownerType' => 'region',
            'ownerId' => 'region-002',
            'originSummary' => 'A living account book that turns bargains, tithes, and debts into regional fortune.',
        ],
        'artifact-starter-moonwell-lens' => [
            'name' => 'Moonwell Lens',
            'focus' => 'knowledge',
            'powerLevel' => 3,
            'instability' => 31,
            'corruption' => 7,
            'status' => 'active',
            'ownerType' => 'hero',
            'ownerId' => 'hero-003',
            'originSummary' => 'A silver lens that lets prophets read the reflection between omen and memory.',
        ],
        'artifact-starter-ashen-banner' => [
            'name' => 'Ashen Banner of Broken Oaths',
            'focus' => 'war',
            'powerLevel' => 3,
            'instability' => 44,
            'corruption' => 24,
            'status' => 'active',
            'ownerType' => 'landmark',
            'ownerId' => 'landmark-004',
            'originSummary' => 'A battle standard sealed in the Forgotten Tower, still calling old rivalries by name.',
        ],
    ];

    private ?array $artifactCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?HeroRepository $heroRepository = null,
        private ?RegionRepository $regionRepository = null,
        private ?LandmarkRepository $landmarkRepository = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->heroRepository ??= new HeroRepository();
        $this->regionRepository ??= new RegionRepository();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $artifacts = array_values(array_map(
            fn(array $artifact): array => $this->serializeArtifact($artifact),
            $this->loadArtifacts()
        ));

        return [
            'artifactLimit' => self::MAX_ARTIFACTS,
            'activeCount' => count($artifacts),
            'creationCost' => self::CREATION_COST,
            'empowerBaseCost' => self::EMPOWER_BASE_COST,
            'transferCost' => self::TRANSFER_COST,
            'stabilizeBaseCost' => self::STABILIZE_BASE_COST,
            'focusOptions' => $this->focusOptions(),
            'summary' => $this->summary($artifacts),
            'artifacts' => $artifacts,
        ];
    }

    public function advanceWorld(int $currentYear): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'consequences' => [],
            'chains' => [],
            'errors' => [],
        ];
        $artifacts = $this->loadArtifacts();

        foreach ($artifacts as $artifactId => $artifact) {
            $summary['processed']++;
            try {
                if (!is_array($artifact)) {
                    continue;
                }

                $changed = false;
                $chainResults = $this->advanceArtifactChains($artifact, $currentYear);
                foreach ($chainResults as $chainResult) {
                    $artifact['eventIds'][] = $chainResult['eventId'];
                    $artifact['history'] = $this->appendLimited(
                        $artifact['history'] ?? [],
                        $this->historyEntry($artifact, [
                            'id' => $chainResult['eventId'],
                            'title' => $chainResult['title'],
                            'description' => $chainResult['summary'],
                            'type' => 'artifact_chain',
                            'year' => $currentYear,
                        ]),
                        24
                    );
                    $summary['events']++;
                    $summary['chains'][] = $chainResult;
                    $changed = true;
                }

                if ($this->shouldResolveConsequence($artifact, $currentYear)) {
                    $consequence = $this->resolveWorldConsequence($artifact, $currentYear);
                    if ($consequence !== null) {
                        $artifact['lastConsequenceYear'] = $currentYear;
                        $artifact['consequences'] = $this->prependLimited(
                            $artifact['consequences'] ?? [],
                            $consequence,
                            8
                        );
                        $artifact['eventIds'][] = $consequence['eventId'];
                        $artifact['history'] = $this->appendLimited(
                            $artifact['history'] ?? [],
                            $this->historyEntry($artifact, [
                                'id' => $consequence['eventId'],
                                'title' => $consequence['title'],
                                'description' => $consequence['summary'],
                                'type' => 'artifact_consequence',
                                'year' => $currentYear,
                            ]),
                            24
                        );
                        $chain = $this->seedArtifactChain($artifact, $consequence, $currentYear);
                        if ($chain !== null) {
                            $artifact['chains'] = $this->prependLimited($artifact['chains'] ?? [], $chain, 6);
                        }
                        $summary['events']++;
                        $summary['consequences'][] = $consequence;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $artifacts[$artifactId] = $artifact;
                    $summary['changed']++;
                }
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'id' => is_string($artifactId) ? $artifactId : null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        if ($summary['changed'] > 0) {
            $this->saveArtifacts($artifacts);
        }

        return $summary;
    }

    public function create(array $payload): array
    {
        $artifacts = $this->loadArtifacts();

        if (count($artifacts) >= self::MAX_ARTIFACTS) {
            throw new \InvalidArgumentException('The divine artifact limit has been reached.');
        }

        $name = $this->validateArtifactName((string)($payload['name'] ?? ''));
        $focus = $this->normalizeFocus((string)($payload['focus'] ?? 'protection'));
        $owner = $this->resolveOwner(
            is_string($payload['targetType'] ?? null) ? $payload['targetType'] : 'unbound',
            is_string($payload['targetId'] ?? null) ? $payload['targetId'] : null
        );

        foreach ($artifacts as $artifact) {
            if (strcasecmp((string)($artifact['name'] ?? ''), $name) === 0) {
                throw new \InvalidArgumentException('An artifact with that name already exists.');
            }
        }

        $player = Player::getSinglePlayer();
        if (!$player->spendDivineFavor(self::CREATION_COST)) {
            return $this->insufficientFavor(self::CREATION_COST, $player);
        }

        $year = $this->currentYear();
        $focusConfig = self::FOCUS_OPTIONS[$focus];
        $artifact = [
            'id' => 'artifact-' . bin2hex(random_bytes(8)),
            'name' => $name,
            'focus' => $focus,
            'createdYear' => $year,
            'powerLevel' => 1,
            'instability' => $focusConfig['instability'],
            'corruption' => $focusConfig['corruption'],
            'status' => 'active',
            'ownerType' => $owner['type'],
            'ownerId' => $owner['id'],
            'ownerName' => $owner['name'],
            'ownerRegionId' => $owner['regionId'],
            'eventIds' => [],
            'history' => [],
        ];

        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Created',
            "{$name} was shaped as an artifact of {$focusConfig['label']} in year {$year}.",
            'artifact_created'
        );
        $artifact['eventIds'][] = $event['id'];
        $artifact['history'][] = $this->historyEntry($artifact, $event);
        $artifacts[$artifact['id']] = $artifact;
        $this->saveArtifacts($artifacts);

        return $this->successResponse(
            "{$name} has been created.",
            self::CREATION_COST,
            $player,
            $artifact
        );
    }

    public function empower(string $artifactId): array
    {
        $artifacts = $this->loadArtifacts();
        $artifact = $this->requireArtifact($artifacts, $artifactId);
        $cost = $this->empowerCost($artifact);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor($cost)) {
            return $this->insufficientFavor($cost, $player);
        }

        $year = $this->currentYear();
        $focus = $this->normalizeFocus((string)($artifact['focus'] ?? 'protection'));
        $focusConfig = self::FOCUS_OPTIONS[$focus];
        $artifact['powerLevel'] = min(10, (int)($artifact['powerLevel'] ?? 1) + 1);
        $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + 12 + (int)$artifact['powerLevel']);
        $artifact['corruption'] = min(
            100,
            (int)($artifact['corruption'] ?? 0) + (int)$focusConfig['corruption'] + intdiv((int)$artifact['instability'], 25)
        );

        if (($artifact['status'] ?? 'active') === 'lost') {
            $artifact['status'] = 'active';
            $artifact['ownerType'] = 'unbound';
            $artifact['ownerId'] = null;
            $artifact['ownerName'] = 'Unbound';
            $artifact['ownerRegionId'] = null;
        }

        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Empowered',
            "{$artifact['name']} was empowered to level {$artifact['powerLevel']} in year {$year}.",
            'artifact_empowered'
        );
        $artifact['eventIds'][] = $event['id'];
        $artifact['history'][] = $this->historyEntry($artifact, $event);
        $riskOutcome = $this->resolveArtifactRisk($artifact);

        $artifacts[$artifact['id']] = $artifact;
        $this->saveArtifacts($artifacts);

        return array_merge(
            $this->successResponse(
                "{$artifact['name']} has been empowered.",
                $cost,
                $player,
                $artifact
            ),
            ['riskOutcome' => $riskOutcome]
        );
    }

    public function transfer(string $artifactId, string $targetType, ?string $targetId): array
    {
        $artifacts = $this->loadArtifacts();
        $artifact = $this->requireArtifact($artifacts, $artifactId);
        $owner = $this->resolveOwner($targetType, $targetId);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor(self::TRANSFER_COST)) {
            return $this->insufficientFavor(self::TRANSFER_COST, $player);
        }

        $artifact['ownerType'] = $owner['type'];
        $artifact['ownerId'] = $owner['id'];
        $artifact['ownerName'] = $owner['name'];
        $artifact['ownerRegionId'] = $owner['regionId'];
        $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + 4);
        $currentStatus = (string)($artifact['status'] ?? 'active');
        $artifact['status'] = $currentStatus === 'lost' ? 'active' : $currentStatus;

        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Transferred',
            "{$artifact['name']} was transferred to {$owner['name']}.",
            'artifact_transferred'
        );
        $artifact['eventIds'][] = $event['id'];
        $artifact['history'][] = $this->historyEntry($artifact, $event);
        $artifacts[$artifact['id']] = $artifact;
        $this->saveArtifacts($artifacts);

        return $this->successResponse(
            "{$artifact['name']} has been transferred to {$owner['name']}.",
            self::TRANSFER_COST,
            $player,
            $artifact
        );
    }

    public function stabilize(string $artifactId): array
    {
        $artifacts = $this->loadArtifacts();
        $artifact = $this->requireArtifact($artifacts, $artifactId);
        $cost = self::STABILIZE_BASE_COST + intdiv(
            (int)($artifact['instability'] ?? 0) + (int)($artifact['corruption'] ?? 0),
            8
        );
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor($cost)) {
            return $this->insufficientFavor($cost, $player);
        }

        $artifact['instability'] = max(0, (int)($artifact['instability'] ?? 0) - 18);
        $artifact['corruption'] = max(0, (int)($artifact['corruption'] ?? 0) - 12);
        if (($artifact['status'] ?? 'active') === 'corrupted' && (int)$artifact['corruption'] < 70) {
            $artifact['status'] = 'active';
        }

        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Stabilized',
            "{$artifact['name']}'s instability and corruption were contained.",
            'artifact_stabilized'
        );
        $artifact['eventIds'][] = $event['id'];
        $artifact['history'][] = $this->historyEntry($artifact, $event);
        $artifacts[$artifact['id']] = $artifact;
        $this->saveArtifacts($artifacts);

        return $this->successResponse(
            "{$artifact['name']} has been stabilized.",
            $cost,
            $player,
            $artifact
        );
    }

    private function loadArtifacts(): array
    {
        if ($this->artifactCache !== null) {
            return $this->artifactCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_ROSTER, []);
        $this->artifactCache = is_array($value) ? $value : [];
        $this->artifactCache = $this->ensureStarterArtifacts($this->artifactCache);

        return $this->artifactCache;
    }

    private function ensureStarterArtifacts(array $artifacts): array
    {
        $changed = false;
        $currentYear = $this->currentYear();

        foreach (self::STARTER_ARTIFACTS as $artifactId => $definition) {
            if (isset($artifacts[$artifactId]) || count($artifacts) >= self::MAX_ARTIFACTS) {
                continue;
            }

            $artifact = $this->buildStarterArtifact($artifactId, $definition, $currentYear);
            $event = $this->recordArtifactEvent(
                $artifact,
                'Divine Artifact Discovered',
                "{$artifact['name']} was already part of the world in year {$artifact['createdYear']}. {$definition['originSummary']}",
                'artifact_created',
                (int)$artifact['createdYear']
            );
            $artifact['eventIds'][] = $event['id'];
            $artifact['history'][] = $this->historyEntry($artifact, $event);
            $artifacts[$artifactId] = $artifact;
            $changed = true;
        }

        if ($changed) {
            $this->saveArtifacts($artifacts);
        }

        return $artifacts;
    }

    private function buildStarterArtifact(string $artifactId, array $definition, int $currentYear): array
    {
        $focus = $this->normalizeFocus((string)$definition['focus']);
        $owner = $this->resolveStarterOwner(
            (string)($definition['ownerType'] ?? 'unbound'),
            is_string($definition['ownerId'] ?? null) ? (string)$definition['ownerId'] : null
        );

        return [
            'id' => $artifactId,
            'name' => (string)$definition['name'],
            'focus' => $focus,
            'createdYear' => max(1, $currentYear - 1),
            'powerLevel' => max(1, min(10, (int)($definition['powerLevel'] ?? 1))),
            'instability' => max(0, min(100, (int)($definition['instability'] ?? 0))),
            'corruption' => max(0, min(100, (int)($definition['corruption'] ?? 0))),
            'status' => in_array($definition['status'] ?? 'active', ['active', 'corrupted', 'lost'], true)
                ? (string)$definition['status']
                : 'active',
            'ownerType' => $owner['type'],
            'ownerId' => $owner['id'],
            'ownerName' => $owner['name'],
            'ownerRegionId' => $owner['regionId'],
            'eventIds' => [],
            'history' => [],
            'seeded' => true,
            'originSummary' => (string)$definition['originSummary'],
        ];
    }

    private function resolveStarterOwner(string $targetType, ?string $targetId): array
    {
        $targetType = strtolower(trim($targetType));

        if ($targetId === null || $targetId === '') {
            return $this->unboundOwner();
        }

        if ($targetType === 'region') {
            $region = Region::find($targetId);
            if ($region instanceof Region) {
                return [
                    'type' => 'region',
                    'id' => (string)$region->id,
                    'name' => (string)$region->name,
                    'regionId' => (string)$region->id,
                ];
            }
        }

        if ($targetType === 'hero') {
            $hero = Hero::find($targetId);
            if ($hero instanceof Hero) {
                return [
                    'type' => 'hero',
                    'id' => (string)$hero->id,
                    'name' => (string)$hero->name,
                    'regionId' => $hero->region_id ? (string)$hero->region_id : null,
                ];
            }
        }

        if ($targetType === 'landmark') {
            $landmark = Landmark::find($targetId);
            if ($landmark instanceof Landmark) {
                return [
                    'type' => 'landmark',
                    'id' => (string)$landmark->id,
                    'name' => (string)$landmark->name,
                    'regionId' => $landmark->region_id ? (string)$landmark->region_id : null,
                ];
            }
        }

        return $this->unboundOwner();
    }

    private function unboundOwner(): array
    {
        return [
            'type' => 'unbound',
            'id' => null,
            'name' => 'Unbound',
            'regionId' => null,
        ];
    }

    private function saveArtifacts(array $artifacts): void
    {
        $this->artifactCache = $artifacts;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_ROSTER,
            $artifacts,
            'array',
            'Divine artifact roster, risk state, owners, and history.'
        );
    }

    private function requireArtifact(array $artifacts, string $artifactId): array
    {
        if (!isset($artifacts[$artifactId]) || !is_array($artifacts[$artifactId])) {
            throw new \InvalidArgumentException("Artifact not found: {$artifactId}");
        }

        return $artifacts[$artifactId];
    }

    private function validateArtifactName(string $name): string
    {
        $name = trim($name);

        if (strlen($name) < 2 || strlen($name) > 60) {
            throw new \InvalidArgumentException('Artifact name must be 2 to 60 characters.');
        }

        return $name;
    }

    private function normalizeFocus(string $focus): string
    {
        $normalized = strtolower(trim($focus));

        if (!isset(self::FOCUS_OPTIONS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported artifact focus: {$focus}");
        }

        return $normalized;
    }

    private function focusOptions(): array
    {
        return array_map(
            fn(string $key, array $option): array => [
                'key' => $key,
                'label' => $option['label'],
                'summary' => $option['summary'],
            ],
            array_keys(self::FOCUS_OPTIONS),
            self::FOCUS_OPTIONS
        );
    }

    private function resolveOwner(string $targetType, ?string $targetId): array
    {
        $type = strtolower(trim($targetType));

        if ($type === 'unbound' || $targetId === null || $targetId === '') {
            return $this->unboundOwner();
        }

        if ($type === 'hero') {
            $hero = $this->heroRepository->getById($targetId);
            if (!$hero instanceof Hero) {
                throw new \InvalidArgumentException("Hero not found: {$targetId}");
            }

            return [
                'type' => 'hero',
                'id' => $hero->id,
                'name' => $hero->name,
                'regionId' => $hero->region_id,
            ];
        }

        if ($type === 'region') {
            $region = $this->regionRepository->getById($targetId);
            if (!is_array($region)) {
                throw new \InvalidArgumentException("Region not found: {$targetId}");
            }

            return [
                'type' => 'region',
                'id' => (string)$region['id'],
                'name' => (string)$region['name'],
                'regionId' => (string)$region['id'],
            ];
        }

        if ($type === 'landmark') {
            if (!$this->landmarkRepository) {
                throw new \InvalidArgumentException('Landmark transfer is unavailable.');
            }
            $landmark = $this->landmarkRepository->getLandmarkById($targetId);
            if (!is_array($landmark)) {
                throw new \InvalidArgumentException("Landmark not found: {$targetId}");
            }

            return [
                'type' => 'landmark',
                'id' => (string)$landmark['id'],
                'name' => (string)$landmark['name'],
                'regionId' => $landmark['region_id'] ?? null,
            ];
        }

        throw new \InvalidArgumentException("Unsupported artifact owner type: {$targetType}");
    }

    private function empowerCost(array $artifact): int
    {
        return self::EMPOWER_BASE_COST
            + ((int)($artifact['powerLevel'] ?? 1) * 10)
            + intdiv((int)($artifact['instability'] ?? 0), 5);
    }

    private function resolveArtifactRisk(array &$artifact): array
    {
        $corruption = (int)($artifact['corruption'] ?? 0);
        $instability = (int)($artifact['instability'] ?? 0);
        $roll = abs(crc32((string)$artifact['id'] . '-' . $this->currentYear() . '-' . (string)$artifact['powerLevel'])) % 100;
        $corruptionChance = max(0, $corruption - 55);
        $theftChance = max(0, $instability - 45);

        if ($corruption >= 85 || $roll < $corruptionChance) {
            $artifact['status'] = 'corrupted';
            $event = $this->recordArtifactEvent(
                $artifact,
                'Divine Artifact Corrupted',
                "{$artifact['name']} buckled under divine pressure and became corrupted.",
                'artifact_corrupted'
            );
            $artifact['eventIds'][] = $event['id'];
            $artifact['history'][] = $this->historyEntry($artifact, $event);

            return [
                'type' => 'corruption',
                'summary' => "{$artifact['name']} became corrupted.",
                'eventId' => $event['id'],
            ];
        }

        if ($instability >= 70 || $roll < ($corruptionChance + $theftChance)) {
            return $this->stealArtifact($artifact);
        }

        return [
            'type' => 'none',
            'summary' => 'No artifact backlash occurred.',
            'eventId' => null,
        ];
    }

    private function stealArtifact(array &$artifact): array
    {
        $candidate = $this->selectThief($artifact);

        if ($candidate instanceof Hero) {
            $artifact['ownerType'] = 'hero';
            $artifact['ownerId'] = $candidate->id;
            $artifact['ownerName'] = $candidate->name;
            $artifact['ownerRegionId'] = $candidate->region_id;
            $artifact['status'] = 'active';
            $description = "{$artifact['name']} slipped from divine intent and was seized by {$candidate->name}.";
        } else {
            $artifact['ownerType'] = 'unbound';
            $artifact['ownerId'] = null;
            $artifact['ownerName'] = 'Lost to the world';
            $artifact['ownerRegionId'] = null;
            $artifact['status'] = 'lost';
            $description = "{$artifact['name']} vanished beyond direct divine control.";
        }

        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Stolen',
            $description,
            'artifact_stolen'
        );
        $artifact['eventIds'][] = $event['id'];
        $artifact['history'][] = $this->historyEntry($artifact, $event);

        return [
            'type' => 'theft',
            'summary' => $description,
            'eventId' => $event['id'],
        ];
    }

    private function selectThief(array $artifact): ?Hero
    {
        $heroes = $this->heroRepository->getAllHeroes(['isAlive' => true], 20);
        foreach ($heroes as $hero) {
            if ($hero instanceof Hero && $hero->id !== ($artifact['ownerId'] ?? null)) {
                return $hero;
            }
        }

        return null;
    }

    private function shouldResolveConsequence(array $artifact, int $currentYear): bool
    {
        if ((int)($artifact['createdYear'] ?? $currentYear) >= $currentYear) {
            return false;
        }
        if ((int)($artifact['lastConsequenceYear'] ?? 0) >= $currentYear) {
            return false;
        }

        $status = (string)($artifact['status'] ?? 'active');
        if ($status === 'lost' && (int)($artifact['powerLevel'] ?? 1) < 3) {
            return false;
        }

        $power = (int)($artifact['powerLevel'] ?? 1);
        $instability = (int)($artifact['instability'] ?? 0);
        $corruption = (int)($artifact['corruption'] ?? 0);
        $pressure = ($power * 7) + intdiv($instability, 2) + intdiv($corruption, 2);
        $pressure += $status === 'corrupted' ? 22 : 0;
        $pressure += $status === 'lost' ? 10 : 0;
        $chance = max(10, min(75, $pressure));

        return $pressure >= 70 || $this->rollPercent(
            (string)($artifact['id'] ?? 'artifact') . ':artifact-consequence:' . $currentYear
        ) < $chance;
    }

    private function resolveWorldConsequence(array &$artifact, int $currentYear): ?array
    {
        $region = $this->resolveArtifactRegion($artifact);
        $focus = $this->normalizeFocus((string)($artifact['focus'] ?? 'protection'));
        $focusConfig = self::FOCUS_OPTIONS[$focus];
        $power = max(1, (int)($artifact['powerLevel'] ?? 1));
        $status = (string)($artifact['status'] ?? 'active');
        $artifactName = (string)($artifact['name'] ?? 'An artifact');
        $ownerName = (string)($artifact['ownerName'] ?? 'Unbound');
        $related = $this->relatedIds($artifact);
        $effectParts = [];

        if ($region instanceof Region) {
            $before = [
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'dangerLevel' => (int)$region->danger_level,
                'magicAffinity' => (int)$region->magic_affinity,
            ];
            $this->applyRegionalConsequence($region, $focus, $power, $status);
            $region->save();
            $after = [
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'dangerLevel' => (int)$region->danger_level,
                'magicAffinity' => (int)$region->magic_affinity,
            ];
            $effectParts[] = "{$region->name} shifted from prosperity {$before['prosperity']}/chaos {$before['chaos']}/danger {$before['dangerLevel']}/magic {$before['magicAffinity']} to {$after['prosperity']}/{$after['chaos']}/{$after['dangerLevel']}/{$after['magicAffinity']}.";
            $related['regionIds'][] = (string)$region->id;
            $related['regionId'] ??= (string)$region->id;

            $settlementEffect = $this->applySettlementConsequence((string)$region->id, $focus, $power, $status);
            if ($settlementEffect !== null) {
                $effectParts[] = $settlementEffect['summary'];
                $related['settlementIds'][] = $settlementEffect['id'];
            }

            $resourceEffect = $this->applyResourceConsequence((string)$region->id, $focus, $power, $status);
            if ($resourceEffect !== null) {
                $effectParts[] = $resourceEffect['summary'];
                $related['resourceIds'][] = $resourceEffect['id'];
            }
        }

        $heroEffect = $this->applyHeroConsequence($artifact, $focus, $power, $status);
        if ($heroEffect !== null) {
            $effectParts[] = $heroEffect['summary'];
            $related['heroIds'][] = $heroEffect['id'];
        }

        $landmarkEffect = $this->applyLandmarkConsequence($artifact, $focus, $power, $status);
        if ($landmarkEffect !== null) {
            $effectParts[] = $landmarkEffect['summary'];
            $related['landmarkIds'][] = $landmarkEffect['id'];
        }

        if ($effectParts === []) {
            $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + 1);
            $effectParts[] = "{$artifactName} hummed beyond direct control, raising its instability.";
        }

        if ($status === 'corrupted' || $focus === 'war') {
            $artifact['corruption'] = min(100, (int)($artifact['corruption'] ?? 0) + 2);
        } else {
            $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + max(1, intdiv($power, 3)));
        }

        $summary = "{$artifactName} echoed through {$ownerName} in year {$currentYear}. " . implode(' ', $effectParts);
        $event = $this->recordArtifactEvent(
            $artifact,
            'Divine Artifact Consequence: ' . $artifactName,
            $summary,
            'artifact_consequence',
            $currentYear,
            $related
        );

        return [
            'id' => 'artifact-consequence-' . ($artifact['id'] ?? bin2hex(random_bytes(4))) . '-' . $currentYear,
            'tool' => 'artifact',
            'title' => $event['title'],
            'artifactId' => (string)($artifact['id'] ?? ''),
            'artifactName' => $artifactName,
            'focus' => $focus,
            'focusLabel' => $focusConfig['label'],
            'ownerType' => (string)($artifact['ownerType'] ?? 'unbound'),
            'ownerId' => $artifact['ownerId'] ?? null,
            'ownerName' => $ownerName,
            'year' => $currentYear,
            'summary' => $summary,
            'eventId' => $event['id'],
            'relatedRegionIds' => array_values(array_unique(array_filter($related['regionIds']))),
            'relatedHeroIds' => array_values(array_unique(array_filter($related['heroIds']))),
            'relatedSettlementIds' => array_values(array_unique(array_filter($related['settlementIds'] ?? []))),
            'relatedLandmarkIds' => array_values(array_unique(array_filter($related['landmarkIds']))),
            'relatedResourceIds' => array_values(array_unique(array_filter($related['resourceIds'] ?? []))),
        ];
    }

    private function seedArtifactChain(array $artifact, array $consequence, int $currentYear): ?array
    {
        if ($this->hasActiveChain($artifact['chains'] ?? [], 'artifact')) {
            return null;
        }

        $pressure = $this->artifactChainPressure($artifact);
        if ($pressure < 35) {
            return null;
        }

        $maxSteps = $pressure >= 75 ? 3 : 2;
        $artifactId = (string)($artifact['id'] ?? bin2hex(random_bytes(4)));
        $artifactName = (string)($artifact['name'] ?? 'An artifact');

        return [
            'id' => 'artifact-chain-' . $artifactId . '-' . $currentYear,
            'tool' => 'artifact',
            'status' => 'active',
            'startedYear' => $currentYear,
            'lastAdvancedYear' => null,
            'nextYear' => $currentYear + 1,
            'step' => 0,
            'maxSteps' => $maxSteps,
            'sourceEventId' => (string)($consequence['eventId'] ?? ''),
            'eventIds' => array_values(array_filter([(string)($consequence['eventId'] ?? '')])),
            'title' => 'Artifact Chain: ' . $artifactName,
            'latestSummary' => "{$artifactName} began a {$maxSteps}-step consequence chain after {$consequence['title']}.",
        ];
    }

    private function advanceArtifactChains(array &$artifact, int $currentYear): array
    {
        $chains = is_array($artifact['chains'] ?? null) ? array_values($artifact['chains']) : [];
        $results = [];

        foreach ($chains as $index => $chain) {
            if (!is_array($chain) || !$this->shouldAdvanceChain($chain, $currentYear)) {
                continue;
            }

            [$updatedChain, $result] = $this->resolveArtifactChainStep($artifact, $chain, $currentYear);
            $chains[$index] = $updatedChain;
            $results[] = $result;
        }

        if ($results !== []) {
            $artifact['chains'] = array_slice(array_values($chains), 0, 6);
        }

        return $results;
    }

    private function resolveArtifactChainStep(array &$artifact, array $chain, int $currentYear): array
    {
        $step = max(1, (int)($chain['step'] ?? 0) + 1);
        $maxSteps = max($step, (int)($chain['maxSteps'] ?? 2));
        $focus = $this->normalizeFocus((string)($artifact['focus'] ?? 'protection'));
        $focusConfig = self::FOCUS_OPTIONS[$focus];
        $status = (string)($artifact['status'] ?? 'active');
        $artifactName = (string)($artifact['name'] ?? 'An artifact');
        $chainPower = max(1, intdiv(max(1, (int)($artifact['powerLevel'] ?? 1)) + $step, 2));
        $related = $this->relatedIds($artifact);
        $effectParts = [];

        $region = $this->resolveArtifactRegion($artifact);
        if ($region instanceof Region) {
            $before = [
                'prosperity' => (int)$region->prosperity,
                'chaos' => (int)$region->chaos,
                'dangerLevel' => (int)$region->danger_level,
                'magicAffinity' => (int)$region->magic_affinity,
            ];
            $this->applyRegionalConsequence($region, $focus, $chainPower, $status);
            $region->save();
            $effectParts[] = "Step {$step} shifted {$region->name} from {$before['prosperity']}/{$before['chaos']}/{$before['dangerLevel']}/{$before['magicAffinity']} to {$region->prosperity}/{$region->chaos}/{$region->danger_level}/{$region->magic_affinity}.";
            $related['regionIds'][] = (string)$region->id;
            $related['regionId'] ??= (string)$region->id;

            if ($step % 2 === 1) {
                $settlementEffect = $this->applySettlementConsequence((string)$region->id, $focus, $chainPower, $status);
                if ($settlementEffect !== null) {
                    $effectParts[] = $settlementEffect['summary'];
                    $related['settlementIds'][] = $settlementEffect['id'];
                }
            } else {
                $resourceEffect = $this->applyResourceConsequence((string)$region->id, $focus, $chainPower, $status);
                if ($resourceEffect !== null) {
                    $effectParts[] = $resourceEffect['summary'];
                    $related['resourceIds'][] = $resourceEffect['id'];
                }
            }
        }

        if ($step === $maxSteps) {
            $landmarkEffect = $this->applyLandmarkConsequence($artifact, $focus, $chainPower, $status);
            if ($landmarkEffect !== null) {
                $effectParts[] = $landmarkEffect['summary'];
                $related['landmarkIds'][] = $landmarkEffect['id'];
            }
        } else {
            $heroEffect = $this->applyHeroConsequence($artifact, $focus, $chainPower, $status);
            if ($heroEffect !== null) {
                $effectParts[] = $heroEffect['summary'];
                $related['heroIds'][] = $heroEffect['id'];
            }
        }

        if ($effectParts === []) {
            $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + 1);
            $effectParts[] = "{$artifactName} carried its echo forward without finding a mortal anchor.";
        }

        $artifact['instability'] = min(100, (int)($artifact['instability'] ?? 0) + 1);
        if ($status === 'corrupted' || $focus === 'war') {
            $artifact['corruption'] = min(100, (int)($artifact['corruption'] ?? 0) + 1);
        }

        $finished = $step >= $maxSteps;
        $title = 'Artifact Chain: ' . $artifactName;
        $summary = "{$artifactName}'s consequence chain advanced step {$step}/{$maxSteps} in year {$currentYear}. "
            . implode(' ', $effectParts)
            . ($finished ? ' The chain resolved.' : ' The chain will keep echoing.');
        $event = $this->recordArtifactEvent(
            $artifact,
            $title,
            $summary,
            'artifact_chain',
            $currentYear,
            $related
        );

        $chain['step'] = $step;
        $chain['lastAdvancedYear'] = $currentYear;
        $chain['nextYear'] = $finished ? null : $currentYear + ($step % 2 === 0 ? 2 : 1);
        $chain['status'] = $finished ? 'completed' : 'active';
        $chain['latestSummary'] = $summary;
        $chain['eventIds'] = $this->appendLimited(
            is_array($chain['eventIds'] ?? null) ? $chain['eventIds'] : [],
            $event['id'],
            6
        );

        return [
            $chain,
            [
                'id' => (string)$chain['id'] . '-step-' . $step . '-' . $currentYear,
                'tool' => 'artifact',
                'title' => $title,
                'artifactId' => (string)($artifact['id'] ?? ''),
                'artifactName' => $artifactName,
                'focus' => $focus,
                'focusLabel' => $focusConfig['label'],
                'year' => $currentYear,
                'summary' => $summary,
                'eventId' => $event['id'],
                'chainId' => (string)$chain['id'],
                'chainStep' => $step,
                'chainMaxSteps' => $maxSteps,
                'chainStatus' => (string)$chain['status'],
                'sourceEventId' => (string)($chain['sourceEventId'] ?? ''),
                'nextYear' => $chain['nextYear'],
                'relatedRegionIds' => array_values(array_unique(array_filter($related['regionIds']))),
                'relatedHeroIds' => array_values(array_unique(array_filter($related['heroIds']))),
                'relatedSettlementIds' => array_values(array_unique(array_filter($related['settlementIds'] ?? []))),
                'relatedLandmarkIds' => array_values(array_unique(array_filter($related['landmarkIds']))),
                'relatedResourceIds' => array_values(array_unique(array_filter($related['resourceIds'] ?? []))),
            ],
        ];
    }

    private function resolveArtifactRegion(array $artifact): ?Region
    {
        $regionId = $artifact['ownerRegionId'] ?? null;
        if (is_string($regionId) && $regionId !== '') {
            $region = Region::find($regionId);
            if ($region instanceof Region) {
                return $region;
            }
        }

        if (($artifact['ownerType'] ?? null) === 'region' && is_string($artifact['ownerId'] ?? null)) {
            $region = Region::find((string)$artifact['ownerId']);
            return $region instanceof Region ? $region : null;
        }

        return null;
    }

    private function applyRegionalConsequence(Region $region, string $focus, int $power, string $status): void
    {
        $region->prosperity = $this->addValue((int)$region->prosperity, match ($focus) {
            'prosperity' => 1 + intdiv($power, 2),
            'protection' => 1,
            'war' => -1,
            'knowledge' => 0,
            default => 0,
        });
        $region->chaos = $this->addValue((int)$region->chaos, match ($focus) {
            'war' => 1 + intdiv($power, 2),
            'knowledge' => $status === 'corrupted' ? 2 : 0,
            'protection' => -1,
            default => 0,
        });
        $region->danger_level = $this->addValue((int)$region->danger_level, match ($focus) {
            'protection' => -1 - intdiv($power, 3),
            'war' => 1 + intdiv($power, 2),
            'prosperity' => $status === 'corrupted' ? 1 : -1,
            default => 0,
        });
        $region->magic_affinity = $this->addValue((int)$region->magic_affinity, match ($focus) {
            'knowledge' => 1 + intdiv($power, 2),
            'protection', 'prosperity' => intdiv($power, 4),
            'war' => $status === 'corrupted' ? 1 : 0,
            default => 0,
        });
    }

    private function applySettlementConsequence(string $regionId, string $focus, int $power, string $status): ?array
    {
        $settlement = Settlement::where('region_id', $regionId)->orderByDesc('population')->first();
        if (!$settlement instanceof Settlement) {
            return null;
        }

        $oldProsperity = (int)$settlement->prosperity;
        $oldDefense = (int)$settlement->defensibility;
        $settlement->prosperity = $this->addValue($oldProsperity, match ($focus) {
            'prosperity' => 2 + intdiv($power, 2),
            'protection' => 1,
            'war' => $status === 'corrupted' ? -2 : 0,
            'knowledge' => 1,
            default => 0,
        });
        $settlement->defensibility = $this->addValue($oldDefense, match ($focus) {
            'protection' => 2 + intdiv($power, 2),
            'war' => 1 + intdiv($power, 3),
            'knowledge' => 0,
            default => 0,
        });
        $traits = $settlement->traits ?? [];
        $trait = 'artifact_' . $focus . '_echo';
        if (!in_array($trait, $traits, true)) {
            $traits[] = $trait;
            $settlement->traits = $traits;
        }
        $settlement->save();

        return [
            'id' => (string)$settlement->id,
            'summary' => "{$settlement->name} felt the artifact: prosperity {$oldProsperity}->{$settlement->prosperity}, defense {$oldDefense}->{$settlement->defensibility}.",
        ];
    }

    private function applyResourceConsequence(string $regionId, string $focus, int $power, string $status): ?array
    {
        $resource = ResourceNode::where('region_id', $regionId)->orderByDesc('output')->first();
        if (!$resource instanceof ResourceNode) {
            return null;
        }

        $oldOutput = (int)$resource->output;
        $resource->output = $this->addValue($oldOutput, match ($focus) {
            'prosperity' => 2 + intdiv($power, 2),
            'knowledge' => 1,
            'war' => -1 - intdiv($power, 3),
            'protection' => 1,
            default => 0,
        });
        if ($status === 'corrupted') {
            $resource->status = 'corrupted';
        } elseif ($focus === 'prosperity' && (int)$resource->output >= 75) {
            $resource->status = 'blessed';
        } elseif ($focus === 'war' && (int)$resource->output <= 30) {
            $resource->status = 'contested';
        }
        $resource->save();

        return [
            'id' => (string)$resource->id,
            'summary' => "{$resource->name} shifted under artifact pressure: output {$oldOutput}->{$resource->output}, status {$resource->status}.",
        ];
    }

    private function applyHeroConsequence(array $artifact, string $focus, int $power, string $status): ?array
    {
        if (($artifact['ownerType'] ?? null) !== 'hero' || empty($artifact['ownerId'])) {
            return null;
        }

        $hero = Hero::find((string)$artifact['ownerId']);
        if (!$hero instanceof Hero) {
            return null;
        }

        $feats = $hero->feats ?? [];
        $feat = 'Artifact Echo: ' . ($artifact['name'] ?? 'Unknown Relic') . ' (' . self::FOCUS_OPTIONS[$focus]['label'] . ')';
        if (!in_array($feat, $feats, true)) {
            $feats[] = $feat;
            $hero->feats = $feats;
        }
        if ($focus === 'knowledge' || $focus === 'war') {
            $hero->level = min(100, (int)$hero->level + max(1, intdiv($power, 3)));
        }
        if ($status === 'corrupted') {
            $hero->influence_last_action = 'artifact_corruption_echo';
        }
        $hero->save();

        return [
            'id' => (string)$hero->id,
            'summary' => "{$hero->name} now carries {$feat}.",
        ];
    }

    private function applyLandmarkConsequence(array $artifact, string $focus, int $power, string $status): ?array
    {
        if (($artifact['ownerType'] ?? null) !== 'landmark' || empty($artifact['ownerId'])) {
            return null;
        }

        $landmark = Landmark::find((string)$artifact['ownerId']);
        if (!$landmark instanceof Landmark) {
            return null;
        }

        $oldMagic = (int)$landmark->magic_level;
        $oldDanger = (int)$landmark->danger_level;
        $landmark->magic_level = $this->addValue($oldMagic, $focus === 'knowledge' ? 2 + intdiv($power, 2) : 1);
        $landmark->danger_level = $this->addValue($oldDanger, $status === 'corrupted' || $focus === 'war' ? 2 : 0);
        $traits = $landmark->traits ?? [];
        $trait = 'artifact_' . $focus . '_anchor';
        if (!in_array($trait, $traits, true)) {
            $traits[] = $trait;
            $landmark->traits = $traits;
        }
        $landmark->save();

        return [
            'id' => (string)$landmark->id,
            'summary' => "{$landmark->name} became an artifact anchor: magic {$oldMagic}->{$landmark->magic_level}, danger {$oldDanger}->{$landmark->danger_level}.",
        ];
    }

    private function recordArtifactEvent(
        array $artifact,
        string $title,
        string $description,
        string $type,
        ?int $year = null,
        ?array $relatedOverride = null
    ): array {
        $related = $relatedOverride ?? $this->relatedIds($artifact);
        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'region_id' => $related['regionId'],
            'related_region_ids' => $related['regionIds'],
            'related_hero_ids' => $related['heroIds'],
            'related_settlement_ids' => $related['settlementIds'] ?? [],
            'related_landmark_ids' => $related['landmarkIds'],
            'related_resource_ids' => $related['resourceIds'] ?? [],
            'year' => $year ?? $this->currentYear(),
        ]);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'year' => $event->year,
        ];
    }

    private function relatedIds(array $artifact): array
    {
        $regionIds = [];
        $heroIds = [];
        $landmarkIds = [];
        $settlementIds = [];
        $resourceIds = [];

        if (!empty($artifact['ownerRegionId'])) {
            $regionIds[] = (string)$artifact['ownerRegionId'];
        }
        if (($artifact['ownerType'] ?? null) === 'hero' && !empty($artifact['ownerId'])) {
            $heroIds[] = (string)$artifact['ownerId'];
        }
        if (($artifact['ownerType'] ?? null) === 'region' && !empty($artifact['ownerId'])) {
            $regionIds[] = (string)$artifact['ownerId'];
        }
        if (($artifact['ownerType'] ?? null) === 'landmark' && !empty($artifact['ownerId'])) {
            $landmarkIds[] = (string)$artifact['ownerId'];
        }

        $regionIds = array_values(array_unique($regionIds));

        return [
            'regionId' => $regionIds[0] ?? null,
            'regionIds' => $regionIds,
            'heroIds' => array_values(array_unique($heroIds)),
            'landmarkIds' => array_values(array_unique($landmarkIds)),
            'settlementIds' => array_values(array_unique($settlementIds)),
            'resourceIds' => array_values(array_unique($resourceIds)),
        ];
    }

    private function historyEntry(array $artifact, array $event): array
    {
        return [
            'eventId' => $event['id'],
            'title' => $event['title'],
            'summary' => $event['description'],
            'type' => $event['type'],
            'year' => $event['year'],
            'ownerType' => $artifact['ownerType'] ?? 'unbound',
            'ownerName' => $artifact['ownerName'] ?? 'Unbound',
            'status' => $artifact['status'] ?? 'active',
            'powerLevel' => (int)($artifact['powerLevel'] ?? 1),
            'instability' => (int)($artifact['instability'] ?? 0),
            'corruption' => (int)($artifact['corruption'] ?? 0),
        ];
    }

    private function serializeArtifact(array $artifact): array
    {
        $focus = $this->normalizeFocus((string)($artifact['focus'] ?? 'protection'));
        $instability = (int)($artifact['instability'] ?? 0);
        $corruption = (int)($artifact['corruption'] ?? 0);

        return [
            'id' => (string)($artifact['id'] ?? ''),
            'name' => (string)($artifact['name'] ?? 'Unnamed Artifact'),
            'focus' => $focus,
            'focusLabel' => self::FOCUS_OPTIONS[$focus]['label'],
            'createdYear' => (int)($artifact['createdYear'] ?? 1),
            'powerLevel' => (int)($artifact['powerLevel'] ?? 1),
            'instability' => $instability,
            'corruption' => $corruption,
            'status' => (string)($artifact['status'] ?? 'active'),
            'ownerType' => (string)($artifact['ownerType'] ?? 'unbound'),
            'ownerId' => $artifact['ownerId'] ?? null,
            'ownerName' => (string)($artifact['ownerName'] ?? 'Unbound'),
            'ownerRegionId' => $artifact['ownerRegionId'] ?? null,
            'seeded' => (bool)($artifact['seeded'] ?? false),
            'originSummary' => !empty($artifact['originSummary']) ? (string)$artifact['originSummary'] : null,
            'eventIds' => is_array($artifact['eventIds'] ?? null) ? array_values($artifact['eventIds']) : [],
            'history' => is_array($artifact['history'] ?? null) ? array_values($artifact['history']) : [],
            'consequences' => $this->artifactConsequences($artifact),
            'latestConsequence' => $this->artifactConsequences($artifact)[0] ?? null,
            'chains' => $this->artifactChains($artifact),
            'activeChainCount' => $this->activeChainCount($this->artifactChains($artifact)),
            'lastConsequenceYear' => isset($artifact['lastConsequenceYear']) ? (int)$artifact['lastConsequenceYear'] : null,
            'empowerCost' => $this->empowerCost($artifact),
            'stabilizeCost' => self::STABILIZE_BASE_COST + intdiv($instability + $corruption, 8),
            'riskTier' => $this->riskTier($instability, $corruption),
            'summary' => $this->artifactSummary($artifact),
        ];
    }

    private function artifactSummary(array $artifact): string
    {
        $name = (string)($artifact['name'] ?? 'This artifact');
        $ownerName = (string)($artifact['ownerName'] ?? 'Unbound');
        $status = (string)($artifact['status'] ?? 'active');

        return "{$name} is {$status}, power " . (int)($artifact['powerLevel'] ?? 1)
            . ", held by {$ownerName}.";
    }

    private function artifactConsequences(array $artifact): array
    {
        return is_array($artifact['consequences'] ?? null) ? array_values($artifact['consequences']) : [];
    }

    private function artifactChains(array $artifact): array
    {
        return is_array($artifact['chains'] ?? null) ? array_values($artifact['chains']) : [];
    }

    private function activeChainCount(array $chains): int
    {
        return count(array_filter($chains, fn($chain): bool => is_array($chain) && ($chain['status'] ?? null) === 'active'));
    }

    private function hasActiveChain(mixed $chains, string $tool): bool
    {
        if (!is_array($chains)) {
            return false;
        }

        foreach ($chains as $chain) {
            if (is_array($chain) && ($chain['tool'] ?? null) === $tool && ($chain['status'] ?? null) === 'active') {
                return true;
            }
        }

        return false;
    }

    private function shouldAdvanceChain(array $chain, int $currentYear): bool
    {
        if (($chain['status'] ?? null) !== 'active') {
            return false;
        }

        if ((int)($chain['lastAdvancedYear'] ?? 0) >= $currentYear) {
            return false;
        }

        return (int)($chain['nextYear'] ?? PHP_INT_MAX) <= $currentYear;
    }

    private function artifactChainPressure(array $artifact): int
    {
        $status = (string)($artifact['status'] ?? 'active');
        $pressure = ((int)($artifact['powerLevel'] ?? 1) * 8)
            + intdiv((int)($artifact['instability'] ?? 0), 2)
            + intdiv((int)($artifact['corruption'] ?? 0), 2);

        if ($status === 'corrupted') {
            $pressure += 18;
        }
        if ($status === 'lost') {
            $pressure += 8;
        }

        return max(0, min(100, $pressure));
    }

    private function riskTier(int $instability, int $corruption): string
    {
        $risk = max($instability, $corruption);

        if ($risk >= 80) {
            return 'critical';
        }
        if ($risk >= 60) {
            return 'high';
        }
        if ($risk >= 35) {
            return 'rising';
        }

        return 'low';
    }

    private function summary(array $artifacts): string
    {
        if (count($artifacts) === 0) {
            return 'No divine artifacts have been created.';
        }

        $corrupted = count(array_filter($artifacts, fn(array $artifact): bool => $artifact['status'] === 'corrupted'));
        $highRisk = count(array_filter($artifacts, fn(array $artifact): bool => in_array($artifact['riskTier'], ['high', 'critical'], true)));

        return count($artifacts) . " artifacts exist; {$corrupted} corrupted and {$highRisk} high-risk.";
    }

    private function successResponse(string $message, int $cost, Player $player, array $artifact): array
    {
        return [
            'success' => true,
            'message' => $message,
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'artifact' => $this->serializeArtifact($artifact),
            'status' => $this->status(),
        ];
    }

    private function insufficientFavor(int $cost, Player $player): array
    {
        return [
            'success' => false,
            'message' => 'Insufficient divine favor',
            'cost' => $cost,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
        ];
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }

    private function prependLimited(array $items, array $item, int $limit): array
    {
        array_unshift($items, $item);
        return array_slice(array_values($items), 0, $limit);
    }

    private function appendLimited(array $items, mixed $item, int $limit): array
    {
        $items[] = $item;
        return array_slice(array_values($items), -$limit);
    }

    private function rollPercent(string $seed): int
    {
        return abs(crc32($seed)) % 100;
    }

    private function addValue(int $value, int $delta): int
    {
        return max(0, min(100, $value + $delta));
    }
}
