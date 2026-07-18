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

class MagicDiscoveryService
{
    private const CONFIG_CATEGORY = 'magic';
    private const CONFIG_KEY_STATE = 'discovery_state';
    private const RESEARCH_COST = 18;
    private const HISTORY_LIMIT = 8;
    private const PROGRESSION_HISTORY_LIMIT = 10;
    private const PROGRESSION_COOLDOWN_YEARS = 4;

    private const PATHS = [
        'ley_weaving' => [
            'label' => 'Ley Weaving',
            'domain' => 'Region',
            'summary' => 'Channels regional ley lines into prosperity, travel, and magic pressure.',
            'hiddenSummary' => 'Look for high magic regions, magical springs, and arcane landmarks.',
            'betType' => 'magic_discovery',
        ],
        'spirit_compacts' => [
            'label' => 'Spirit Compacts',
            'domain' => 'Hero',
            'summary' => 'Binds prophets, sacred groves, and local spirits into durable obligations.',
            'hiddenSummary' => 'Look for prophets, sacred groves, temples, and mystical cultures.',
            'betType' => 'magic_discovery',
        ],
        'ruin_script' => [
            'label' => 'Ruin Script',
            'domain' => 'Landmark',
            'summary' => 'Deciphers ruins, towers, and ancient writing into research breakthroughs.',
            'hiddenSummary' => 'Look for scholars, ancient ruins, towers, and hidden landmarks.',
            'betType' => 'magic_discovery',
        ],
        'storm_rites' => [
            'label' => 'Storm Rites',
            'domain' => 'Region',
            'summary' => 'Turns weather, danger, and volatile magic into repeatable ritual practice.',
            'hiddenSummary' => 'Look for dangerous, chaotic, weather-shaken, or highly magical regions.',
            'betType' => 'magic_discovery',
        ],
        'civic_enchantment' => [
            'label' => 'Civic Enchantment',
            'domain' => 'Settlement',
            'summary' => 'Lets settlements carry culture, trade, and prosperity through enchanted civic works.',
            'hiddenSummary' => 'Look for prosperous settlements, mercantile culture, and agent-of-change heroes.',
            'betType' => 'magic_discovery',
        ],
    ];

    private ?array $stateCache = null;

    public function __construct(
        private ?GameConfigService $configService = null,
        private ?EventRepository $eventRepository = null
    ) {
        $this->configService ??= GameConfigService::getInstance();
        $this->eventRepository ??= new EventRepository();
    }

    public function status(): array
    {
        $state = $this->loadState();
        $paths = array_values(array_map(
            fn(string $key, array $path): array => $this->serializePath($key, $path, $state[$key] ?? []),
            array_keys(self::PATHS),
            self::PATHS
        ));

        return [
            'currentYear' => $this->currentYear(),
            'researchCost' => self::RESEARCH_COST,
            'summary' => $this->summary($paths),
            'pathOptions' => $this->pathOptions(),
            'paths' => $paths,
            'progressionSummary' => $this->progressionSummary($paths, $state),
            'recentProgressions' => $this->recentProgressions($state),
            'targetOptions' => $this->targetOptions(),
            'suggestedTargets' => $this->suggestedTargets($state),
            'bettingHooks' => $this->bettingHooks($paths),
        ];
    }

    public function pathsForTarget(string $targetId): array
    {
        $state = $this->loadState();
        $paths = [];

        foreach (self::PATHS as $key => $path) {
            $record = $state[$key] ?? $this->initialRecord($key);
            $target = $record['lastTarget'] ?? null;
            if (!is_array($target) || (string)($target['id'] ?? '') !== $targetId) {
                continue;
            }

            $paths[] = $this->serializePath($key, $path, $record);
        }

        $statusRank = ['known' => 3, 'emerging' => 2, 'hidden' => 1];
        usort($paths, function (array $left, array $right) use ($statusRank): int {
            $statusCompare = ($statusRank[$right['status']] ?? 0) <=> ($statusRank[$left['status']] ?? 0);
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            return ((int)$right['progress']) <=> ((int)$left['progress']);
        });

        return $paths;
    }

    public function research(array $payload): array
    {
        $targetType = $this->normalizeTargetType((string)($payload['targetType'] ?? 'region'));
        $targetId = trim((string)($payload['targetId'] ?? ''));
        if ($targetId === '') {
            throw new \InvalidArgumentException('A magic research target is required.');
        }

        $target = $this->resolveTarget($targetType, $targetId);
        $requestedPath = (string)($payload['path'] ?? 'auto');
        $pathKey = $requestedPath === 'auto' ? $this->bestPathForTarget($target) : $this->normalizePath($requestedPath);
        $evidence = $this->evidenceForPath($pathKey, $target);
        $player = Player::getSinglePlayer();

        if (!$player->spendDivineFavor(self::RESEARCH_COST)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => self::RESEARCH_COST,
                'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            ];
        }

        $state = $this->loadState();
        $record = $state[$pathKey] ?? $this->initialRecord($pathKey);
        $beforeStatus = (string)($record['status'] ?? 'hidden');
        $roll = abs(crc32($pathKey . ':' . $target['type'] . ':' . $target['id'] . ':' . $this->currentYear())) % 20;
        $progressGain = max(8, min(35, (int)round($evidence['score'] / 4) + $roll));
        $record['progress'] = min(100, (int)($record['progress'] ?? 0) + $progressGain);
        $record['evidenceScore'] = max((int)($record['evidenceScore'] ?? 0), $evidence['score']);
        $record['lastResearchedYear'] = $this->currentYear();
        $record['lastTarget'] = $target;
        $record['signals'] = $evidence['signals'];
        $record['status'] = $this->statusForProgress((int)$record['progress'], $evidence['score']);

        if ($record['status'] === 'known' && $beforeStatus !== 'known') {
            $record['discoveryYear'] = $this->currentYear();
            $this->applyDurableDiscovery($pathKey, $target);
        }

        $eventType = $record['status'] === 'known' && $beforeStatus !== 'known'
            ? 'magic_discovery'
            : 'magic_research';
        $event = $this->recordMagicEvent($pathKey, $record, $target, $eventType);
        $record['eventIds'] = array_values(array_unique(array_merge($record['eventIds'] ?? [], [$event['id']])));
        array_unshift($record['history'], [
            'eventId' => $event['id'],
            'title' => $event['title'],
            'summary' => $event['description'],
            'type' => $event['type'],
            'year' => $event['year'],
            'targetType' => $target['type'],
            'targetId' => $target['id'],
            'targetName' => $target['name'],
            'progress' => (int)$record['progress'],
            'status' => $record['status'],
        ]);
        $record['history'] = array_slice($record['history'], 0, self::HISTORY_LIMIT);
        $state[$pathKey] = $record;
        $this->saveState($state);

        $path = $this->serializePath($pathKey, self::PATHS[$pathKey], $record);

        return [
            'success' => true,
            'message' => $event['description'],
            'cost' => self::RESEARCH_COST,
            'remainingDivineFavor' => (int)$player->fresh()->divine_favor,
            'path' => $path,
            'research' => [
                'target' => $target,
                'evidenceScore' => $evidence['score'],
                'progressGain' => $progressGain,
                'signals' => $evidence['signals'],
                'eventId' => $event['id'],
            ],
            'status' => $this->status(),
        ];
    }

    public function advanceWorld(int $currentYear, int $limit = 2): array
    {
        $summary = [
            'processed' => 0,
            'changed' => 0,
            'events' => 0,
            'progressions' => [],
            'errors' => [],
        ];

        $state = $this->loadState();
        foreach (array_slice($this->progressionCandidates($state, $currentYear), 0, max(1, $limit)) as $candidate) {
            $summary['processed']++;

            try {
                $pathKey = (string)$candidate['pathKey'];
                $record = $state[$pathKey] ?? $this->initialRecord($pathKey);
                $beforeStatus = (string)($record['status'] ?? 'hidden');
                $target = $candidate['target'];
                $evidence = $candidate['evidence'];
                $eventType = 'magic_progression';
                $progressGain = 0;
                $maturityGain = 0;
                $changes = $this->emptyChanges();

                if ($beforeStatus === 'known') {
                    $maturityGain = $this->progressionMaturityGain($pathKey, $record, $evidence);
                    $record['maturity'] = min(100, (int)($record['maturity'] ?? 0) + $maturityGain);
                    $record['lastProgressionYear'] = $currentYear;
                    $record['lastTarget'] = $target;
                    $record['signals'] = $evidence['signals'];
                    $record['evidenceScore'] = max((int)($record['evidenceScore'] ?? 0), (int)$evidence['score']);
                    $changes = $this->applyProgressionEffects($pathKey, $target, $currentYear);
                } else {
                    $progressGain = $this->autonomousProgressGain($pathKey, $record, $evidence);
                    $record['progress'] = min(100, (int)($record['progress'] ?? 0) + $progressGain);
                    $record['lastProgressionYear'] = $currentYear;
                    $record['lastTarget'] = $target;
                    $record['signals'] = $evidence['signals'];
                    $record['evidenceScore'] = max((int)($record['evidenceScore'] ?? 0), (int)$evidence['score']);
                    $record['status'] = $this->statusForProgress((int)$record['progress'], (int)$evidence['score']);

                    if ((string)$record['status'] === 'known' && $beforeStatus !== 'known') {
                        $eventType = 'magic_discovery';
                        $record['discoveryYear'] = $currentYear;
                        $this->applyDurableDiscovery($pathKey, $target);
                    }
                }

                $event = $this->recordProgressionEvent(
                    $pathKey,
                    $record,
                    $target,
                    $eventType,
                    $currentYear,
                    $changes,
                    $progressGain,
                    $maturityGain
                );
                $progression = $this->progressionRecord(
                    $pathKey,
                    $record,
                    $target,
                    $event,
                    $changes,
                    $progressGain,
                    $maturityGain
                );
                $record['eventIds'] = array_values(array_unique(array_merge($record['eventIds'] ?? [], [$event['id']])));
                array_unshift($record['history'], $this->historyEntryFromProgression($progression));
                $record['history'] = array_slice($record['history'], 0, self::HISTORY_LIMIT);
                $state[$pathKey] = $record;
                $state['recentProgressions'] = $this->prependProgression($state, $progression);
                $this->saveState($state);

                $summary['progressions'][] = $progression;
                $summary['changed'] += $this->changeCount($changes);
                $summary['events']++;
            } catch (\Throwable $error) {
                $summary['errors'][] = [
                    'pathKey' => $candidate['pathKey'] ?? null,
                    'message' => $error->getMessage(),
                ];
            }
        }

        return $summary;
    }

    private function loadState(): array
    {
        if ($this->stateCache !== null) {
            return $this->stateCache;
        }

        $value = $this->configService->getConfig(self::CONFIG_CATEGORY, self::CONFIG_KEY_STATE, []);
        $state = is_array($value) ? $value : [];
        foreach (self::PATHS as $key => $_path) {
            if (!isset($state[$key]) || !is_array($state[$key])) {
                $state[$key] = $this->initialRecord($key);
                continue;
            }

            $state[$key] = array_merge($this->initialRecord($key), $state[$key]);
            $state[$key]['signals'] = is_array($state[$key]['signals']) ? $state[$key]['signals'] : [];
            $state[$key]['eventIds'] = is_array($state[$key]['eventIds']) ? $state[$key]['eventIds'] : [];
            $state[$key]['history'] = is_array($state[$key]['history']) ? $state[$key]['history'] : [];
        }
        $state['recentProgressions'] = $this->recentProgressions($state);

        $this->stateCache = $state;
        return $this->stateCache;
    }

    private function saveState(array $state): void
    {
        $this->stateCache = $state;
        $this->configService->setConfig(
            self::CONFIG_CATEGORY,
            self::CONFIG_KEY_STATE,
            $state,
            'array',
            'Hidden, emerging, and known magic discovery paths.'
        );
    }

    private function initialRecord(string $pathKey): array
    {
        return [
            'path' => $pathKey,
            'status' => 'hidden',
            'progress' => 0,
            'evidenceScore' => 0,
            'discoveryYear' => null,
            'lastResearchedYear' => null,
            'lastProgressionYear' => null,
            'lastTarget' => null,
            'maturity' => 0,
            'signals' => [],
            'eventIds' => [],
            'history' => [],
        ];
    }

    private function serializePath(string $key, array $path, array $record): array
    {
        $status = (string)($record['status'] ?? 'hidden');

        return [
            'key' => $key,
            'label' => $path['label'],
            'domain' => $path['domain'],
            'summary' => $path['summary'],
            'hiddenSummary' => $path['hiddenSummary'],
            'status' => $status,
            'progress' => (int)($record['progress'] ?? 0),
            'evidenceScore' => (int)($record['evidenceScore'] ?? 0),
            'maturity' => (int)($record['maturity'] ?? 0),
            'discoveryYear' => $record['discoveryYear'] ?? null,
            'lastResearchedYear' => $record['lastResearchedYear'] ?? null,
            'lastProgressionYear' => $record['lastProgressionYear'] ?? null,
            'lastTarget' => $record['lastTarget'] ?? null,
            'signals' => is_array($record['signals'] ?? null) ? array_values($record['signals']) : [],
            'eventIds' => is_array($record['eventIds'] ?? null) ? array_values($record['eventIds']) : [],
            'history' => is_array($record['history'] ?? null) ? array_values($record['history']) : [],
            'bettingHook' => $this->pathBettingHook($key, $record),
            'visibilitySummary' => $this->visibilitySummary($path, $status),
        ];
    }

    private function evidenceForPath(string $pathKey, array $target): array
    {
        $signals = [];
        $score = 12;

        $add = function (string $label, int $value, string $summary) use (&$score, &$signals): void {
            if ($value <= 0) {
                return;
            }
            $score += $value;
            $signals[] = [
                'label' => $label,
                'value' => '+' . $value,
                'summary' => $summary,
            ];
        };

        $region = $target['regionId'] ? Region::find($target['regionId']) : null;
        if ($region instanceof Region) {
            $add('Regional magic', intdiv((int)$region->magic_affinity, 5), "{$region->name} has magic affinity {$region->magic_affinity}.");
            if (in_array($region->status, ['mysterious', 'blessed', 'cursed'], true)) {
                $add('Strange status', 10, "{$region->name} is {$region->status}.");
            }
        }

        if ($target['type'] === 'region') {
            $settlementCount = Settlement::where('region_id', $target['id'])->count();
            $magicalResources = ResourceNode::where('region_id', $target['id'])
                ->whereIn('type', ['magical_spring', 'herb_garden'])
                ->count();
            $add('Settlement substrate', min(12, $settlementCount * 4), "{$settlementCount} settlements can carry research.");
            $add('Magical resources', min(20, $magicalResources * 10), "{$magicalResources} magical resource nodes are nearby.");
        }

        if ($target['type'] === 'hero') {
            $hero = Hero::find($target['id']);
            if ($hero instanceof Hero) {
                if ($hero->role === 'scholar') {
                    $add('Scholar role', 18, "{$hero->name} is a scholar.");
                }
                if ($hero->role === 'prophet') {
                    $add('Prophet role', 18, "{$hero->name} is a prophet.");
                }
                if ($hero->role === 'agent of change') {
                    $add('Agent role', 12, "{$hero->name} can reshape civic patterns.");
                }
                $add('Hero level', min(20, (int)$hero->level * 3), "{$hero->name} is level {$hero->level}.");
            }
        }

        if ($target['type'] === 'landmark') {
            $landmark = Landmark::find($target['id']);
            if ($landmark instanceof Landmark) {
                $add('Landmark magic', intdiv((int)$landmark->magic_level, 4), "{$landmark->name} has magic {$landmark->magic_level}.");
                if (in_array($landmark->type, ['ruin', 'tower', 'temple', 'grove'], true)) {
                    $add('Arcane landmark', 16, "{$landmark->name} is a {$landmark->type}.");
                }
                if (in_array('hidden', $landmark->traits ?? [], true) || in_array('ancient', $landmark->traits ?? [], true)) {
                    $add('Ancient signs', 12, "{$landmark->name} carries hidden or ancient traits.");
                }
            }
        }

        $pathBonus = match ($pathKey) {
            'ley_weaving' => $target['type'] === 'region' ? 12 : 0,
            'spirit_compacts' => $target['type'] === 'hero' ? 10 : 0,
            'ruin_script' => $target['type'] === 'landmark' ? 12 : 0,
            'storm_rites' => $region instanceof Region ? intdiv((int)$region->chaos + (int)$region->danger_level, 8) : 0,
            'civic_enchantment' => $target['type'] === 'region' ? min(15, Settlement::where('region_id', $target['id'])->count() * 5) : 0,
            default => 0,
        };
        $add('Path resonance', $pathBonus, self::PATHS[$pathKey]['label'] . ' resonates with this target.');

        return [
            'score' => max(0, min(100, $score)),
            'signals' => $signals,
        ];
    }

    private function bestPathForTarget(array $target): string
    {
        $bestPath = 'ley_weaving';
        $bestScore = -1;
        foreach (array_keys(self::PATHS) as $pathKey) {
            $score = $this->evidenceForPath($pathKey, $target)['score'];
            if ($score > $bestScore) {
                $bestPath = $pathKey;
                $bestScore = $score;
            }
        }

        return $bestPath;
    }

    private function applyDurableDiscovery(string $pathKey, array $target): void
    {
        $trait = 'magic_path_' . $pathKey;
        if ($target['regionId']) {
            $region = Region::find($target['regionId']);
            if ($region instanceof Region) {
                $region->magic_affinity = min(100, (int)$region->magic_affinity + 3);
                $region->addTrait($trait);
                $region->save();
            }
        }

        if ($target['type'] === 'hero') {
            $hero = Hero::find($target['id']);
            if ($hero instanceof Hero) {
                $feats = $hero->feats ?? [];
                $feat = 'Revealed ' . self::PATHS[$pathKey]['label'];
                if (!in_array($feat, $feats, true)) {
                    $feats[] = $feat;
                    $hero->feats = $feats;
                    $hero->save();
                }
            }
        }

        if ($target['type'] === 'landmark') {
            $landmark = Landmark::find($target['id']);
            if ($landmark instanceof Landmark) {
                $traits = $landmark->traits ?? [];
                if (!in_array($trait, $traits, true)) {
                    $traits[] = $trait;
                    $landmark->traits = $traits;
                    $landmark->magic_level = min(100, (int)$landmark->magic_level + 4);
                    $landmark->save();
                }
            }
        }
    }

    private function recordMagicEvent(string $pathKey, array $record, array $target, string $eventType): array
    {
        $path = self::PATHS[$pathKey];
        $isDiscovery = $eventType === 'magic_discovery';
        $title = $isDiscovery ? 'Magic Path Discovered: ' . $path['label'] : 'Magic Research Advances';
        $description = $isDiscovery
            ? "{$path['label']} became known through {$target['name']}."
            : "{$path['label']} research advanced through {$target['name']} to {$record['progress']}% progress.";

        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $description,
            'type' => $eventType,
            'region_id' => $target['regionId'],
            'related_region_ids' => $target['regionId'] ? [$target['regionId']] : [],
            'related_hero_ids' => $target['type'] === 'hero' ? [$target['id']] : [],
            'related_landmark_ids' => $target['type'] === 'landmark' ? [$target['id']] : [],
            'year' => $this->currentYear(),
        ]);

        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'type' => $event->type,
            'year' => $event->year,
        ];
    }

    private function progressionCandidates(array $state, int $currentYear): array
    {
        $candidates = [];
        foreach (array_keys(self::PATHS) as $pathKey) {
            $record = $state[$pathKey] ?? $this->initialRecord($pathKey);
            $target = $this->targetForProgression($pathKey, $record, $state);
            if ($target === null) {
                continue;
            }

            $lastProgressionYear = (int)($record['lastProgressionYear'] ?? 0);
            if ($lastProgressionYear > 0 && $currentYear - $lastProgressionYear < self::PROGRESSION_COOLDOWN_YEARS) {
                continue;
            }

            $evidence = $this->evidenceForPath($pathKey, $target);
            $status = (string)($record['status'] ?? 'hidden');
            $progress = (int)($record['progress'] ?? 0);
            $maturity = (int)($record['maturity'] ?? 0);
            $evidenceScore = (int)$evidence['score'];
            $priority = match ($status) {
                'known' => $maturity >= 100 ? 0 : 70 + $evidenceScore + (100 - $maturity),
                'emerging' => 120 + $progress + $evidenceScore,
                default => $evidenceScore >= 65 ? 90 + $evidenceScore + $progress : 0,
            };

            if ($priority <= 0) {
                continue;
            }

            $candidates[] = [
                'pathKey' => $pathKey,
                'target' => $target,
                'evidence' => $evidence,
                'priority' => $priority,
            ];
        }

        usort(
            $candidates,
            fn(array $left, array $right): int => ((int)$right['priority'] <=> (int)$left['priority'])
                ?: strcmp((string)$left['pathKey'], (string)$right['pathKey'])
        );

        return $candidates;
    }

    private function targetForProgression(string $pathKey, array $record, array $state): ?array
    {
        $storedTarget = $record['lastTarget'] ?? null;
        if (is_array($storedTarget)) {
            $resolved = $this->resolveStoredTarget($storedTarget);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $matches = [];
        foreach ($this->suggestedTargets($state) as $target) {
            if (($target['bestPath'] ?? null) !== $pathKey) {
                continue;
            }

            $resolved = $this->resolveStoredTarget($target);
            if ($resolved !== null) {
                $matches[] = array_merge($resolved, [
                    'evidenceScore' => (int)$this->evidenceForPath($pathKey, $resolved)['score'],
                ]);
            }
        }

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            fn(array $left, array $right): int => ((int)$right['evidenceScore'] <=> (int)$left['evidenceScore'])
                ?: strcmp((string)$left['name'], (string)$right['name'])
        );
        unset($matches[0]['evidenceScore']);

        return $matches[0];
    }

    private function resolveStoredTarget(array $target): ?array
    {
        try {
            $type = $this->normalizeTargetType((string)($target['type'] ?? 'region'));
            $id = (string)($target['id'] ?? '');
            if ($id === '') {
                return null;
            }

            return $this->resolveTarget($type, $id);
        } catch (\Throwable) {
            return null;
        }
    }

    private function progressionMaturityGain(string $pathKey, array $record, array $evidence): int
    {
        $base = intdiv((int)$evidence['score'], 10) + intdiv((int)($record['progress'] ?? 100), 25);
        $variance = (int)(crc32($pathKey . ':' . (string)($record['lastProgressionYear'] ?? 0)) % 4);

        return max(4, min(16, $base + $variance));
    }

    private function autonomousProgressGain(string $pathKey, array $record, array $evidence): int
    {
        $status = (string)($record['status'] ?? 'hidden');
        $base = intdiv((int)$evidence['score'], 8) + ($status === 'emerging' ? 6 : 2);
        $variance = (int)(crc32($pathKey . ':' . (string)($record['progress'] ?? 0)) % 5);

        return max(5, min(24, $base + $variance));
    }

    private function applyProgressionEffects(string $pathKey, array $target, int $currentYear): array
    {
        $changes = $this->emptyChanges();
        $region = is_string($target['regionId'] ?? null) ? Region::find((string)$target['regionId']) : null;

        if ($pathKey === 'ley_weaving' && $region instanceof Region) {
            $this->changeRegion($changes, $region, function (Region $targetRegion): void {
                $targetRegion->magic_affinity = $this->clamp((int)$targetRegion->magic_affinity + 2);
                $targetRegion->prosperity = $this->clamp((int)$targetRegion->prosperity + 1);
                $targetRegion->addTrait('magic_progression_ley_weaving');
            });

            $node = ResourceNode::where('region_id', $region->id)
                ->whereIn('type', ['magical_spring', 'herb_garden'])
                ->orderByDesc('output')
                ->first();
            if ($node instanceof ResourceNode) {
                $this->changeResource($changes, $node, function (ResourceNode $resource): void {
                    $resource->output = $this->clamp((int)$resource->output + 3);
                    $resource->status = (int)$resource->output >= 75 ? 'status-flourishing' : 'active';
                });
            }
        } elseif ($pathKey === 'spirit_compacts') {
            $hero = $target['type'] === 'hero'
                ? Hero::find((string)$target['id'])
                : ($region instanceof Region
                    ? Hero::where('region_id', $region->id)->where('is_alive', true)->orderByDesc('level')->first()
                    : null);
            if ($hero instanceof Hero) {
                $this->changeHero($changes, $hero, function (Hero $targetHero) use ($currentYear): void {
                    $targetHero->level = min(100, (int)$targetHero->level + 1);
                    $targetHero->feats = $this->addUnique($targetHero->feats ?? [], "Renewed spirit compact in year {$currentYear}");
                });
            }
        } elseif ($pathKey === 'ruin_script') {
            $landmark = $target['type'] === 'landmark'
                ? Landmark::find((string)$target['id'])
                : ($region instanceof Region
                    ? Landmark::where('region_id', $region->id)->orderByDesc('magic_level')->first()
                    : null);
            if ($landmark instanceof Landmark) {
                $this->changeLandmark($changes, $landmark, function (Landmark $targetLandmark): void {
                    $targetLandmark->magic_level = $this->clamp((int)$targetLandmark->magic_level + 3);
                    $targetLandmark->traits = $this->addUnique($targetLandmark->traits ?? [], 'magic_progression_ruin_script');
                    if ((int)$targetLandmark->magic_level >= 75) {
                        $targetLandmark->status = 'awakened';
                    }
                });
            }
        } elseif ($pathKey === 'storm_rites' && $region instanceof Region) {
            $this->changeRegion($changes, $region, function (Region $targetRegion): void {
                $targetRegion->magic_affinity = $this->clamp((int)$targetRegion->magic_affinity + 2);
                $targetRegion->chaos = $this->clamp((int)$targetRegion->chaos - 1);
                $targetRegion->danger_level = $this->clamp((int)$targetRegion->danger_level + 1);
                $targetRegion->addTrait('magic_progression_storm_rites');
            });
        } elseif ($pathKey === 'civic_enchantment' && $region instanceof Region) {
            $settlement = Settlement::where('region_id', $region->id)
                ->orderByDesc('prosperity')
                ->first();
            if ($settlement instanceof Settlement) {
                $this->changeSettlement($changes, $settlement, function (Settlement $targetSettlement): void {
                    $targetSettlement->prosperity = $this->clamp((int)$targetSettlement->prosperity + 3);
                    $targetSettlement->defensibility = $this->clamp((int)$targetSettlement->defensibility + 1);
                    $targetSettlement->traits = $this->addUnique($targetSettlement->traits ?? [], 'magic_progression_civic_enchantment');
                });
            }
        }

        return $changes;
    }

    private function recordProgressionEvent(
        string $pathKey,
        array $record,
        array $target,
        string $eventType,
        int $currentYear,
        array $changes,
        int $progressGain,
        int $maturityGain
    ): array {
        $path = self::PATHS[$pathKey];
        $related = $this->relatedIdsFromProgression($changes, $target);
        $isDiscovery = $eventType === 'magic_discovery';
        $title = $isDiscovery ? 'Magic Path Discovered: ' . $path['label'] : 'Magic Progression: ' . $path['label'];
        $description = $isDiscovery
            ? "{$path['label']} became known through {$target['name']} as autonomous evidence crossed the discovery threshold."
            : ($maturityGain > 0
                ? "{$path['label']} matured through {$target['name']} to {$record['maturity']}/100 maturity."
                : "{$path['label']} advanced autonomously through {$target['name']} to {$record['progress']}% progress.");
        if ($progressGain > 0) {
            $description .= " Progress gained {$progressGain}.";
        }
        if ($maturityGain > 0) {
            $description .= " Maturity gained {$maturityGain}.";
        }
        $description .= ' ' . $this->effectSummary($changes);

        $event = $this->eventRepository->createEvent([
            'title' => $title,
            'description' => $description,
            'type' => $eventType,
            'region_id' => $target['regionId'],
            'related_region_ids' => $related['regions'],
            'related_hero_ids' => $related['heroes'],
            'related_landmark_ids' => $related['landmarks'],
            'related_settlement_ids' => $related['settlements'],
            'related_resource_ids' => $related['resources'],
            'year' => $currentYear,
        ]);

        return [
            'id' => (string)$event->id,
            'title' => (string)$event->title,
            'description' => (string)$event->description,
            'type' => (string)$event->type,
            'year' => (int)$event->year,
        ];
    }

    private function progressionRecord(
        string $pathKey,
        array $record,
        array $target,
        array $event,
        array $changes,
        int $progressGain,
        int $maturityGain
    ): array {
        return [
            'id' => 'magic-progression-' . $pathKey . '-' . (string)$event['year'] . '-' . (string)$event['id'],
            'path' => $pathKey,
            'pathLabel' => (string)self::PATHS[$pathKey]['label'],
            'status' => (string)($record['status'] ?? 'hidden'),
            'progress' => (int)($record['progress'] ?? 0),
            'maturity' => (int)($record['maturity'] ?? 0),
            'progressGain' => $progressGain,
            'maturityGain' => $maturityGain,
            'targetType' => (string)$target['type'],
            'targetId' => (string)$target['id'],
            'targetName' => (string)$target['name'],
            'regionId' => $target['regionId'] ?? null,
            'year' => (int)$event['year'],
            'eventId' => (string)$event['id'],
            'title' => (string)$event['title'],
            'summary' => (string)$event['description'],
            'type' => (string)$event['type'],
            'changes' => $changes,
        ];
    }

    private function historyEntryFromProgression(array $progression): array
    {
        return [
            'eventId' => (string)$progression['eventId'],
            'title' => (string)$progression['title'],
            'summary' => (string)$progression['summary'],
            'type' => (string)$progression['type'],
            'year' => (int)$progression['year'],
            'targetType' => (string)$progression['targetType'],
            'targetId' => (string)$progression['targetId'],
            'targetName' => (string)$progression['targetName'],
            'progress' => (int)$progression['progress'],
            'status' => (string)$progression['status'],
        ];
    }

    private function prependProgression(array $state, array $progression): array
    {
        $history = $this->recentProgressions($state);
        array_unshift($history, $progression);

        return array_slice($history, 0, self::PROGRESSION_HISTORY_LIMIT);
    }

    private function recentProgressions(array $state): array
    {
        $items = $state['recentProgressions'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $progressions = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pathKey = (string)($item['path'] ?? '');
            $progressions[] = [
                'id' => (string)($item['id'] ?? 'magic-progression-' . $pathKey),
                'path' => $pathKey,
                'pathLabel' => (string)($item['pathLabel'] ?? $item['path_label'] ?? (self::PATHS[$pathKey]['label'] ?? $pathKey)),
                'status' => (string)($item['status'] ?? 'hidden'),
                'progress' => (int)($item['progress'] ?? 0),
                'maturity' => (int)($item['maturity'] ?? 0),
                'progressGain' => (int)($item['progressGain'] ?? 0),
                'maturityGain' => (int)($item['maturityGain'] ?? 0),
                'targetType' => (string)($item['targetType'] ?? $item['target_type'] ?? 'region'),
                'targetId' => (string)($item['targetId'] ?? $item['target_id'] ?? ''),
                'targetName' => (string)($item['targetName'] ?? $item['target_name'] ?? ''),
                'regionId' => isset($item['regionId']) ? (string)$item['regionId'] : null,
                'year' => (int)($item['year'] ?? 0),
                'eventId' => isset($item['eventId']) ? (string)$item['eventId'] : null,
                'title' => (string)($item['title'] ?? ''),
                'summary' => (string)($item['summary'] ?? ''),
                'type' => (string)($item['type'] ?? 'magic_progression'),
                'changes' => $this->normalizeChangeGroups($item['changes'] ?? []),
            ];
        }

        return array_slice($progressions, 0, self::PROGRESSION_HISTORY_LIMIT);
    }

    private function progressionSummary(array $paths, array $state): string
    {
        $matured = count(array_filter($paths, fn(array $path): bool => (int)($path['maturity'] ?? 0) > 0));
        $recent = count($this->recentProgressions($state));
        if ($recent === 0) {
            return 'No autonomous magic progression has resolved yet.';
        }

        return "{$matured} magic path(s) have matured through {$recent} recent autonomous progression event(s).";
    }

    private function resolveTarget(string $targetType, string $targetId): array
    {
        if ($targetType === 'region') {
            $region = Region::find($targetId);
            if (!$region instanceof Region) {
                throw new \InvalidArgumentException("Region not found: {$targetId}");
            }
            return $this->target('region', (string)$region->id, (string)$region->name, (string)$region->id);
        }

        if ($targetType === 'hero') {
            $hero = Hero::find($targetId);
            if (!$hero instanceof Hero) {
                throw new \InvalidArgumentException("Hero not found: {$targetId}");
            }
            return $this->target('hero', (string)$hero->id, (string)$hero->name, $hero->region_id ? (string)$hero->region_id : null);
        }

        $landmark = Landmark::find($targetId);
        if (!$landmark instanceof Landmark) {
            throw new \InvalidArgumentException("Landmark not found: {$targetId}");
        }
        return $this->target('landmark', (string)$landmark->id, (string)$landmark->name, $landmark->region_id ? (string)$landmark->region_id : null);
    }

    private function target(string $type, string $id, string $name, ?string $regionId): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'regionId' => $regionId,
        ];
    }

    private function suggestedTargets(array $state): array
    {
        $targets = [];
        foreach (Region::orderByDesc('magic_affinity')->take(3)->get() as $region) {
            $target = $this->target('region', (string)$region->id, (string)$region->name, (string)$region->id);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "Magic {$region->magic_affinity}, chaos {$region->chaos}, status {$region->status}.",
            ]);
        }
        foreach (Hero::where('is_alive', true)->orderByDesc('level')->take(2)->get() as $hero) {
            $target = $this->target('hero', (string)$hero->id, (string)$hero->name, $hero->region_id ? (string)$hero->region_id : null);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "{$hero->role}, level {$hero->level}.",
            ]);
        }
        foreach (Landmark::orderByDesc('magic_level')->take(2)->get() as $landmark) {
            $target = $this->target('landmark', (string)$landmark->id, (string)$landmark->name, $landmark->region_id ? (string)$landmark->region_id : null);
            $targets[] = array_merge($target, [
                'bestPath' => $this->bestPathForTarget($target),
                'reason' => "{$landmark->type}, magic {$landmark->magic_level}, status {$landmark->status}.",
            ]);
        }

        return array_slice($targets, 0, 6);
    }

    private function bettingHooks(array $paths): array
    {
        return array_values(array_filter(array_map(
            fn(array $path): ?array => $path['bettingHook'],
            $paths
        )));
    }

    private function pathBettingHook(string $pathKey, array $record): ?array
    {
        $target = $record['lastTarget'] ?? null;
        if (!is_array($target) || empty($target['id']) || ($record['status'] ?? 'hidden') !== 'emerging') {
            return null;
        }

        $progress = (int)($record['progress'] ?? 0);
        $pathLabel = self::PATHS[$pathKey]['label'];

        return [
            'id' => 'magic-hook-' . $pathKey,
            'path' => $pathKey,
            'title' => $pathLabel . ' Breakthrough',
            'summary' => "Will {$pathLabel} become known through " . ($target['name'] ?? 'the target') . '?',
            'betType' => self::PATHS[$pathKey]['betType'],
            'targetId' => (string)$target['id'],
            'targetType' => (string)$target['type'],
            'regionId' => $target['regionId'] ?? null,
            'progress' => $progress,
            'evidenceScore' => (int)($record['evidenceScore'] ?? 0),
            'minimumYears' => 1,
            'maximumYears' => $progress >= 70 ? 4 : 8,
            'confidence' => $progress >= 70 ? 'likely' : 'possible',
        ];
    }

    private function pathOptions(): array
    {
        return array_map(
            fn(string $key, array $path): array => [
                'key' => $key,
                'label' => $path['label'],
                'domain' => $path['domain'],
                'summary' => $path['summary'],
                'hiddenSummary' => $path['hiddenSummary'],
            ],
            array_keys(self::PATHS),
            self::PATHS
        );
    }

    private function targetOptions(): array
    {
        return [
            ['key' => 'region', 'label' => 'Region'],
            ['key' => 'hero', 'label' => 'Hero'],
            ['key' => 'landmark', 'label' => 'Landmark'],
        ];
    }

    private function statusForProgress(int $progress, int $evidenceScore): string
    {
        if ($progress >= 100 || $evidenceScore >= 82) {
            return 'known';
        }
        if ($progress >= 35 || $evidenceScore >= 55) {
            return 'emerging';
        }
        return 'hidden';
    }

    private function visibilitySummary(array $path, string $status): string
    {
        if ($status === 'known') {
            return $path['summary'];
        }
        if ($status === 'emerging') {
            return 'Emerging signs: ' . $path['hiddenSummary'];
        }
        return 'Hidden: ' . $path['hiddenSummary'];
    }

    private function summary(array $paths): string
    {
        $known = count(array_filter($paths, fn(array $path): bool => $path['status'] === 'known'));
        $emerging = count(array_filter($paths, fn(array $path): bool => $path['status'] === 'emerging'));
        return "{$known} magic paths known, {$emerging} emerging, " . count($paths) . ' tracked.';
    }

    private function normalizeTargetType(string $targetType): string
    {
        $normalized = strtolower(trim($targetType));
        if (!in_array($normalized, ['region', 'hero', 'landmark'], true)) {
            throw new \InvalidArgumentException("Unsupported magic research target type: {$targetType}");
        }
        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        $normalized = strtolower(trim($path));
        if (!isset(self::PATHS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported magic path: {$path}");
        }
        return $normalized;
    }

    private function emptyChanges(): array
    {
        return [
            'regions' => [],
            'settlements' => [],
            'resources' => [],
            'heroes' => [],
            'landmarks' => [],
        ];
    }

    private function changeRegion(array &$changes, Region $region, callable $mutate): void
    {
        $before = $this->regionSnapshot($region);
        $mutate($region);
        $region->save();
        $after = $this->regionSnapshot($region->fresh() ?? $region);
        $this->appendChange($changes, 'regions', $before, $after);
    }

    private function changeSettlement(array &$changes, Settlement $settlement, callable $mutate): void
    {
        $before = $this->settlementSnapshot($settlement);
        $mutate($settlement);
        $settlement->save();
        $after = $this->settlementSnapshot($settlement->fresh() ?? $settlement);
        $this->appendChange($changes, 'settlements', $before, $after);
    }

    private function changeResource(array &$changes, ResourceNode $resource, callable $mutate): void
    {
        $before = $this->resourceSnapshot($resource);
        $mutate($resource);
        $resource->save();
        $after = $this->resourceSnapshot($resource->fresh() ?? $resource);
        $this->appendChange($changes, 'resources', $before, $after);
    }

    private function changeHero(array &$changes, Hero $hero, callable $mutate): void
    {
        $before = $this->heroSnapshot($hero);
        $mutate($hero);
        $hero->save();
        $after = $this->heroSnapshot($hero->fresh() ?? $hero);
        $this->appendChange($changes, 'heroes', $before, $after);
    }

    private function changeLandmark(array &$changes, Landmark $landmark, callable $mutate): void
    {
        $before = $this->landmarkSnapshot($landmark);
        $mutate($landmark);
        $landmark->save();
        $after = $this->landmarkSnapshot($landmark->fresh() ?? $landmark);
        $this->appendChange($changes, 'landmarks', $before, $after);
    }

    private function appendChange(array &$changes, string $type, array $before, array $after): void
    {
        if ($before === $after) {
            return;
        }

        $changes[$type][] = [
            'id' => $after['id'],
            'name' => $after['name'],
            'before' => $before,
            'after' => $after,
            'summary' => $this->changeSummary($type, $before, $after),
        ];
    }

    private function changeSummary(string $type, array $before, array $after): string
    {
        $labels = [
            'regions' => ['prosperity', 'chaos', 'dangerLevel', 'magicAffinity'],
            'settlements' => ['prosperity', 'defensibility', 'status'],
            'resources' => ['output', 'status'],
            'heroes' => ['level', 'role', 'status'],
            'landmarks' => ['magicLevel', 'dangerLevel', 'status'],
        ];
        $parts = [];

        foreach ($labels[$type] ?? [] as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $parts[] = "{$key} {$before[$key]}->{$after[$key]}";
            }
        }

        return $after['name'] . ': ' . ($parts === [] ? 'magic markers changed' : implode(', ', $parts)) . '.';
    }

    private function regionSnapshot(Region $region): array
    {
        return [
            'id' => (string)$region->id,
            'name' => (string)$region->name,
            'prosperity' => (int)$region->prosperity,
            'chaos' => (int)$region->chaos,
            'dangerLevel' => (int)$region->danger_level,
            'magicAffinity' => (int)$region->magic_affinity,
            'traits' => $region->regional_traits ?? [],
        ];
    }

    private function settlementSnapshot(Settlement $settlement): array
    {
        return [
            'id' => (string)$settlement->id,
            'name' => (string)$settlement->name,
            'regionId' => (string)$settlement->region_id,
            'prosperity' => (int)$settlement->prosperity,
            'defensibility' => (int)$settlement->defensibility,
            'status' => (string)$settlement->status,
            'traits' => $settlement->traits ?? [],
        ];
    }

    private function resourceSnapshot(ResourceNode $resource): array
    {
        return [
            'id' => (string)$resource->id,
            'name' => (string)$resource->name,
            'regionId' => (string)$resource->region_id,
            'type' => (string)$resource->type,
            'output' => (int)$resource->output,
            'status' => (string)$resource->status,
        ];
    }

    private function heroSnapshot(Hero $hero): array
    {
        return [
            'id' => (string)$hero->id,
            'name' => (string)$hero->name,
            'regionId' => (string)$hero->region_id,
            'role' => (string)$hero->role,
            'level' => (int)$hero->level,
            'status' => (string)$hero->status,
            'feats' => $hero->feats ?? [],
        ];
    }

    private function landmarkSnapshot(Landmark $landmark): array
    {
        return [
            'id' => (string)$landmark->id,
            'name' => (string)$landmark->name,
            'regionId' => (string)$landmark->region_id,
            'type' => (string)$landmark->type,
            'magicLevel' => (int)$landmark->magic_level,
            'dangerLevel' => (int)$landmark->danger_level,
            'status' => (string)$landmark->status,
            'traits' => $landmark->traits ?? [],
        ];
    }

    private function relatedIdsFromProgression(array $changes, array $target): array
    {
        return [
            'regions' => $this->ids(array_merge(
                isset($target['regionId']) ? [$target['regionId']] : [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['regions'])
            )),
            'settlements' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['settlements'])),
            'resources' => $this->ids(array_map(fn(array $change): string => (string)$change['id'], $changes['resources'])),
            'heroes' => $this->ids(array_merge(
                (string)($target['type'] ?? '') === 'hero' ? [$target['id']] : [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['heroes'])
            )),
            'landmarks' => $this->ids(array_merge(
                (string)($target['type'] ?? '') === 'landmark' ? [$target['id']] : [],
                array_map(fn(array $change): string => (string)$change['id'], $changes['landmarks'])
            )),
        ];
    }

    private function effectSummary(array $changes): string
    {
        $labels = [
            'regions' => 'region',
            'settlements' => 'settlement',
            'resources' => 'resource',
            'heroes' => 'hero',
            'landmarks' => 'landmark',
        ];
        $parts = [];

        foreach ($labels as $key => $label) {
            $count = count($changes[$key] ?? []);
            if ($count > 0) {
                $parts[] = "{$count} {$label}" . ($count === 1 ? '' : 's');
            }
        }

        return $parts === []
            ? 'No durable world effect was needed yet.'
            : implode(', ', $parts) . ' changed as the magic path matured.';
    }

    private function changeCount(array $changes): int
    {
        return array_sum(array_map(
            fn($items): int => is_array($items) ? count($items) : 0,
            $changes
        ));
    }

    private function normalizeChangeGroups(mixed $value): array
    {
        $changes = $this->emptyChanges();
        if (!is_array($value)) {
            return $changes;
        }

        foreach (array_keys($changes) as $key) {
            if (isset($value[$key]) && is_array($value[$key])) {
                $changes[$key] = array_values(array_filter($value[$key], fn($item): bool => is_array($item)));
            }
        }

        return $changes;
    }

    private function addUnique(array $values, string $value): array
    {
        if (!in_array($value, $values, true)) {
            $values[] = $value;
        }

        return array_values(array_unique(array_map(fn($item): string => (string)$item, $values)));
    }

    private function ids(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn($value): string => (string)$value,
            $values
        ))));
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return (int)max($min, min($max, round($value)));
    }

    private function currentYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}
