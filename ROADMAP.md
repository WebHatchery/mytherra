# Mytherra Development Roadmap

Last updated: 2026-06-14

This roadmap reflects the current PHP-backed Mytherra app. The PHP migration, dashboard, export foundation, guest entry, core influence routes, resource node API routes, tick runtime, and state-based betting resolution are now implemented. The near-term focus moves from migration and API parity to making simulation changes more visible, deeper, and easier for players to reason about.

## Current State

### Playable Foundation

- PHP 8.1+ backend with MySQL persistence and Eloquent models.
- React/TypeScript frontend with protected routes and WebHatchery/guest entry.
- Core pages: Events, World Map, Heroes, Betting, and Dashboard.
- Region, hero, event, settlement, building, landmark, resource node, betting, statistics, status, influence, and export backend endpoints.
- Statistics dashboard with hero, region, financial, and summary data.
- Full world snapshot export from the dashboard.
- Divine betting interface with live speculation events, active bets, odds, stakes, payouts, and resolution notes.
- Game tick runtime for regions, settlements, resources, heroes, divine favor recovery, generated events, and active bet resolution.
- Expanded data model for settlements, landmarks, resource nodes, buildings, divine bets, influence history, and game state.

### Completed Gameplay Loop Stabilization

- Guest sessions can enter protected routes and fetch regions, heroes, settlements, events, betting, status, statistics, and export data.
- Frontend influence calls now have PHP-compatible `POST /api/influence/region/{id}` and `POST /api/influence/hero/{id}` routes.
- Resource node routes are exposed through the main PHP router.
- Frontend API normalization handles PHP snake_case payloads for primary entities.
- Smoke coverage checks Events, World Map, Heroes, Betting, Dashboard, guest entry, protected API surfaces, and snapshot export wiring.
- API errors from influence actions surface to the UI instead of disappearing into console-only failures.
- The PHP entry point supports both shared workspace Composer dependencies and backend-local `composer install`.

### Completed Game Loop Runtime

- `GameLoopService` advances `current_year` consistently when run normally.
- Manual ticks are available through `php scripts/runGameTick.php`, `composer game:tick`, and `POST /api/admin/game-loop/tick`.
- Admin start/stop controls are exposed through `POST /api/admin/game-loop/start` and `POST /api/admin/game-loop/stop`.
- Queue tables are part of schema initialization, and `GameTickJob` respects the enabled flag before scheduling the next tick.
- Tick results record processed regions, settlements, resources, heroes, bets, favor recovery, generated events, and errors.
- `/api/status` exposes current year, divine favor, simulation enabled state, last tick result, and queue health.
- Local verification confirmed ticks changed visible world state, advanced the year, generated events, recovered favor, and resolved bets.

### Completed Betting Resolution

- Speculation events are generated from current regions, settlements, heroes, landmarks, and resource nodes instead of static mock fixtures.
- Bet types now include current-world predictions such as settlement growth, landmark discovery, corruption spread, hero milestones, hero death, prosperity thresholds, and resource disruption.
- Odds are recalculated from target state, timeframe, confidence, prosperity, chaos, danger, magic affinity, and relevant hero or settlement stats.
- Tick processing resolves active bets against actual world state and records readable win/loss/expiry notes.
- Influence changes region and hero state, which affects future odds indirectly through the simulation.

### Remaining Risks

- Production worker cadence still needs an operational runbook: supervisor/cron setup, restart behavior, and alerting around failed jobs.
- Resource node routes exist, but the current seeded local world may have no resource nodes; resource seeding and player-facing region UI should make them matter.
- Smoke tests cover route and API wiring; add browser-level interaction tests once the UI flows settle.
- Large simulation systems such as era endings, reincarnation, advanced magic, culture, civilization AI, and long-term legacy remain future work.

---

## Next Priorities

### 1. Make Simulation Changes More Visible

**Goal:** Help players understand what changed and why after each tick.

- Add a concise last-tick panel to the dashboard with changed regions, settlements, heroes, resolved bets, favor recovery, generated events, and failures.
- Link resolved bet notes to the event or entity state that caused the result.
- Add entity history summaries for regions, heroes, settlements, landmarks, and resource nodes.
- Make status changes explain themselves in page copy or event text, especially when prosperity, chaos, danger, or hero status crosses thresholds.

**Acceptance Criteria**

- A player can run or observe a tick and understand the meaningful changes without reading raw JSON.
- Resolved bets show the exact state or event that caused the outcome.
- Dashboard status reflects the same year and tick state as the Events, Betting, Heroes, and World Map pages.

### 2. Deepen Resource and Settlement Gameplay

**Goal:** Make settlements and resources feel like practical reasons to care about regions.

- Seed resource nodes into local worlds and surface them in region detail views.
- Let resource output, status, and disruption influence settlement growth, prosperity, danger, and betting opportunities.
- Expand settlement growth, decline, ruin, recovery, specialization, and defense outcomes.
- Record settlement and resource changes as readable events.

**Acceptance Criteria**

- Every region has visible resource or settlement pressures that can change over time.
- Resource disruption and settlement prosperity can create or resolve bets.
- Region detail views explain why a settlement or resource changed.

### 3. Improve Betting Context and Forecasting

**Goal:** Make betting feel like informed prophecy, not blind odds shopping.

- Show current target state beside each speculation option.
- Explain odds factors: target state, confidence, timeframe, prosperity, chaos, danger, magic affinity, and hero or settlement traits.
- Add resolved bet filters and history summaries.
- Tune payout ranges after observing real tick outcomes.

**Acceptance Criteria**

- Players can compare at least three meaningful signals before placing a bet.
- Active and resolved bets remain easy to scan after multiple ticks.
- Odds changes feel explainable from visible world state.

---

## Phase 1: Simulation Depth

### 1.1 Settlement Evolution

- Deepen growth, decline, ruin, recovery, specialization, and defensive changes.
- Connect settlement evolution to region prosperity, chaos, resources, landmarks, and hero presence.
- Surface settlement change history in region detail views.

### 1.2 Resource Scarcity

- Seed resource nodes consistently and expose them through frontend region views.
- Add depletion, contesting, corruption, recovery, and productivity effects.
- Use resources as inputs for settlement growth, conflict, and betting opportunities.

### 1.3 Region Systemic Changes

- Deepen prosperity, chaos, danger, magic affinity, and status changes beyond the current baseline tick drift.
- Make divine resonance affect influence cost/effectiveness in ways visible to players.
- Generate region events when major thresholds are crossed.

### 1.4 Hero Lifecycle

- Expand the current hero aging, movement, leveling, feats, mortality, and revival loop.
- Add clearer hero event history and region relationships.
- Expand alignment and personality effects once the baseline lifecycle is stable.

---

## Phase 2: Magic, Culture, and World Identity

### 2.1 Magic Discovery and Research

- Add discoverable magic paths tied to regions, heroes, landmarks, and research guidance.
- Track known, hidden, and emerging magical systems.
- Make magical discoveries create durable world changes and new betting opportunities.

### 2.2 Cultural Evolution

- Add culture traits and cultural pressure between regions.
- Connect culture to settlement specialization, hero roles, events, and divine influence.
- Surface cultural drift in dashboard and region views.

### 2.3 Dynamic Mythology

- Promote major hero feats, disasters, discoveries, and divine interventions into myths.
- Let myths affect region identity, hero reputation, and future events.
- Add a mythology or chronicles view once enough data exists.

### 2.4 Civilization Behavior

- Add higher-level AI behavior for settlements and regions: expansion, defense, trade, rivalry, research, and recovery.
- Use resources, culture, landmarks, and heroes as inputs to decisions.
- Keep behavior explainable through events and dashboard stats.

---

## Phase 3: Era and Legacy Systems

### 3.1 Era-Ending Conditions

- Define world-state triggers for cataclysms, collapse, conquest, magical rupture, or divine war.
- Show era pressure to players before the end arrives.
- Generate an era summary from actual events and statistics.

### 3.2 Reincarnation and Continuity

- Select heroes, bloodlines, landmarks, myths, and scars that persist across eras.
- Let some divine bets span era boundaries.
- Preserve enough history for the next era to reference the previous one.

### 3.3 New Era Generation

- Reset or transform regions, settlements, resources, heroes, and magic rules.
- Carry forward legacies without making the new era feel predetermined.
- Add player-facing era history and comparison tools.

---

## Phase 4: Deeper Divine Gameplay

### 4.1 Mortal Champion System

- Let players designate or cultivate champions without direct control.
- Add champion quests, rivalries, and higher-impact influence actions.
- Connect champions to bets, myths, reincarnation, and era legacy.

### 4.2 Divine Artifacts

- Allow limited artifact creation or empowerment.
- Make artifacts transferable, stealable, corruptible, and historically traceable.
- Use artifacts as high-risk tools that can outlive their intended purpose.

### 4.3 Weather and Environmental Influence

- Add divine weather or climate nudges that affect resources, settlement survival, travel, and conflict.
- Keep effects probabilistic and event-driven rather than direct city-builder controls.

---

## Phase 5: Advanced Systems

### 5.1 Time Manipulation

- Prototype limited temporal mechanics before full rollback or branching history.
- Start with previews, delayed omens, or accelerated local simulation.
- Avoid anything that undermines persistent world consistency.

### 5.2 AI Pantheon

- Add non-player divine actors after the mortal world simulation is stable.
- Give AI deities clear goals, domains, relationships, and visible interventions.
- Use pantheon politics to create multiplayer-like pressure without requiring live players.

### 5.3 Replay and Timeline Tools

- Build timeline views from event history, bets, and major entity state changes.
- Support filtering by hero, region, settlement, landmark, resource, and era.
- Use this as the foundation for sharing world histories.

### 5.4 World Editor and Admin Tools

- Add controlled creation/editing tools for regions, settlements, landmarks, resources, and heroes.
- Keep admin tools separate from player-facing divine influence.
- Include validation so edited worlds remain simulation-compatible.

---

## Implementation Principles

- Stabilize the current loop before adding new systems.
- Favor systems that create readable events and meaningful bets.
- Keep divine actions probabilistic; players should influence fate, not command it.
- Make every major simulation change inspectable through events, dashboard data, or entity history.
- Treat automated ticks, betting resolution, and event generation as the core technical spine.
- Prefer small vertical slices over broad model additions that are not visible in gameplay.

## Suggested Order

1. Dashboard last-tick visibility and resolved bet explanations.
2. Resource seeding and region-view resource UI.
3. Settlement/resource/region simulation depth.
4. Betting context, odds explanations, and history filters.
5. Hero lifecycle history and relationship visibility.
6. Production tick worker runbook and monitoring.
7. Magic and culture.
8. Era-ending and legacy.
9. Champions, artifacts, and advanced divine powers.

## Success Metrics

- A guest can play a complete session without developer setup beyond running the app.
- At least one automated tick changes visible world state and records readable events.
- Bets resolve from real simulation state and explain their outcomes.
- Players can understand why the last tick changed important entities.
- Every primary entity type has a player-visible reason to matter.
- Roadmap phases produce playable changes, not just backend-only data expansion.
