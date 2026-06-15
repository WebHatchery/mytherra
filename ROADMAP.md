# Mytherra Development Roadmap

Last updated: 2026-06-15

This roadmap is now post-migration. The PHP backend, protected/guest entry, main API parity routes, MySQL persistence, tick runtime, export/status surfaces, dashboard visibility, production game-loop operations, and baseline admin world editing are implemented. The active roadmap is no longer a PHP migration plan; it is a gameplay-depth plan for autonomous outcomes, long-run balance, and stronger world identity systems.

## Roadmap Status

- **Migration status:** Complete. PHP 8.1+ backend routes, shared WebHatchery/guest auth, persistence, Composer entry points, frontend normalization, and protected player flows are in place.
- **Gameplay status:** Playable baseline systems are implemented for resources, settlements, betting resolution/forecasting, hero lifecycle, magic discovery, myths, civilization agendas, AI pantheon pressure, era pressure/rollover/generation, mortal champions, divine artifacts, weather influence, temporal omens, status, export, dashboard inspection, and admin-only world editing.
- **Current focus:** Deepen autonomous gameplay outcomes beyond the completed baselines, especially deeper magic/myth/civilization evolution, broader era generation, descendant identity, multi-step consequence chains, richer pantheon alliance/rival arcs, interactive replay tools, and long-run simulation balancing.

## Current State

### Playable Foundation

- PHP 8.1+ backend with MySQL persistence and Eloquent models.
- React/TypeScript frontend with protected routes and WebHatchery/guest entry.
- Core pages: Events, World Map, Heroes, Artifacts, Weather, Omens, Magic, Myths, Civilization, Pantheon, Betting, Eras, Dashboard, and admin-only World Editor.
- Region, hero, event, settlement, building, landmark, resource node, betting, magic discovery, mythology, civilization behavior, statistics, status, influence, export, and admin world editor backend endpoints.
- Statistics dashboard with hero, region, financial, and summary data.
- Dashboard last-tick panel with changed regions, settlements, resources, heroes, civilization decisions, pantheon interventions, resolved bets, favor recovery, era pressure, era legacy continuity, era rollover readiness/results, queue health, and failures.
- Dashboard chronicles panel with entity history summaries for regions, settlements, landmarks, resources, heroes, and bets.
- Event detail pages linked from the Events feed, dashboard last-tick panel, and dashboard Chronicles panel.
- Timeline filters for event type, status, region ID, hero ID, settlement ID, landmark ID, resource ID, and era.
- Full world snapshot export from the dashboard, including era pressure, era legacy continuity, era rollover context, civilization agendas, and pantheon pressure.
- Divine betting interface with live speculation events, target state, odds factors, active/resolved bet filters, stakes, payouts, and resolution notes.
- Heroes page cards show lifecycle state, region ties, nearby settlements, settlement/landmark interactions when present, timeline links, and recent direct hero history.
- Heroes page champion controls let players designate and cultivate a small roster of mortal champions, with champion rank, bond, focus, quest progress, and event history links surfaced on hero payloads/cards.
- Artifacts page controls let players create named divine artifacts, empower them, stabilize risky power, transfer them to heroes, unbind them, and inspect artifact event history.
- Weather page controls let players spend divine favor on probabilistic climate nudges that affect region pressure, settlement survival, resource output/status, travel risk, conflict pressure, and weather event history.
- Omens page controls let players spend divine favor on non-mutating temporal forecasts for the world, a region, or a hero, with forecast signals and event-linked omen history.
- Magic page controls let players spend divine favor on region, hero, or landmark research to reveal hidden, emerging, and known magic paths, with evidence signals, durable discovery effects, linked events, and betting hooks.
- Myths page controls let players promote major events into durable myths that add regional memory, hero reputation feats, landmark anchor traits, and future culture-pressure signals.
- Civilization page controls let players inspect regional agendas and advance a bounded civic decision for expansion, defense, trade, rivalry, research, or recovery, with score signals, durable entity changes, linked events, and dashboard visibility.
- Pantheon page lets players inspect non-player divine actors, goals, domains, relationships, current pressure targets, political escalation, intervention betting hooks, recent autonomous interventions, and player counterplay.
- Game tick runtime for regions, settlements, resources, heroes, champion quest/rivalry outcomes, civilization behavior, AI pantheon interventions, divine favor recovery, generated events, resource/settlement/hero/landmark/magic/culture/era pressure, era legacy continuity snapshots, automatic eligible-era rollover, threshold events, and active bet resolution.
- Delayed divine-tool consequences from artifacts, weather, and temporal omens now appear in tick runtime, last-tick dashboard state, event history, mythology candidates, and era-legacy carried-myth reasons.
- Admin-only world editor tools can create or update regions, settlements, landmarks, resources, and heroes without exposing those operations as player divine influence.
- Production game-loop runbook and `composer game:health` monitor for tick staleness, failed jobs, queue backlog, and latest tick errors.
- Expanded data model for settlements, landmarks, resource nodes, buildings, divine bets, influence history, and game state.

### Completed PHP Migration and Gameplay Loop Stabilization

- Guest sessions can enter protected routes and fetch regions, heroes, settlements, events, betting, status, statistics, and export data.
- Frontend influence calls now have PHP-compatible `POST /api/influence/region/{id}` and `POST /api/influence/hero/{id}` routes.
- Resource node routes are exposed through the main PHP router.
- Frontend API normalization handles PHP snake_case payloads for primary entities.
- Smoke coverage checks Events, World Map, Heroes, Betting, Dashboard, guest entry, protected API surfaces, and snapshot export wiring.
- API errors from influence actions surface to the UI instead of disappearing into console-only failures.
- The PHP entry point supports both shared workspace Composer dependencies and backend-local `composer install`.
- PHP migration and API parity should no longer be treated as active roadmap blockers.

### Completed Game Loop Runtime

- `GameLoopService` advances `current_year` consistently when run normally.
- Manual ticks are available through `php scripts/runGameTick.php`, `composer game:tick`, and `POST /api/admin/game-loop/tick`.
- Admin start/stop controls are exposed through `POST /api/admin/game-loop/start` and `POST /api/admin/game-loop/stop`.
- Queue tables are part of schema initialization, and `GameTickJob` respects the enabled flag before scheduling the next tick.
- Tick results record processed regions, settlements, resources, heroes, bets, favor recovery, generated events, and errors.
- Tick results now include named change summaries for regions, settlements, resources, heroes, and resolved bets.
- Tick changes and resolved bets include generated event IDs when the tick creates a readable event.
- `/api/status` exposes current year, divine favor, simulation enabled state, last tick result, and queue health.
- `composer game:health` exposes JSON health output and monitoring-friendly exit codes for production checks.
- The production game-loop runbook documents cron cadence, queue-worker supervision, restart steps, Dashboard signals, and alert triage.
- Local verification confirmed ticks changed visible world state, advanced the year, generated events, recovered favor, and resolved bets.

### Completed Resource Visibility Slice

- Fresh SQL initialization and the PHP resource seed script now seed seven resource nodes across all three starting regions.
- Region detail views now include a Resources tab with resource count, productive count, average output, status, output, type, and settlement proximity.
- Region overview cards include resource counts.
- Resource output and disruption now influence regional prosperity/danger drift and settlement growth/prosperity.
- Resource tick results record named output/status changes and readable resource events.

### Completed Entity History Summary Slice

- `GET /api/history/summary` returns recent history summaries for regions, settlements, landmarks, resource nodes, heroes, and divine bets.
- The history summary reports current state, direct event counts, shown event counts, last event title/year/description, recent events, and whether history is direct or regional context.
- The dashboard Chronicles panel surfaces those summaries beside the latest tick and aggregate statistics.
- Bet history summaries include active/resolved counts, recent active bets, recent resolved bets, target names, target types, stakes, odds, payouts, and resolution notes.
- Current schema supports direct event linkage for regions, heroes, settlements, landmarks, and resources; regional context is used only as a fallback when direct links do not exist.

### Completed Event Link Payload Slice

- Game tick changes now retain `eventId`/`eventIds` for region, settlement, resource, and hero changes when a tick event is recorded.
- Resolved bet summaries now include the `eventId` of the generated `Divine Bet Resolved` event.
- The dashboard last-tick panel surfaces event IDs beside linked changes and resolved bets.
- The dashboard last-tick panel links event IDs to event detail pages.

### Completed Event Detail and Timeline Filter Slice

- `GET /api/events` now accepts page/offset pagination plus type, status, region, hero, settlement, landmark, resource, and era filters consistently.
- Region filtering matches both direct `region_id` and JSON `related_region_ids`; hero filtering uses `related_hero_ids`.
- Frontend event payloads are normalized from PHP snake_case into camelCase fields with title, type, status, region, related regions, heroes, settlements, landmarks, and resources.
- Events feed cards, sidebar event log entries, dashboard latest-tick event IDs, and dashboard Chronicles latest events link to `/events/{id}`.
- Event detail pages show event title, description, year, type, status, timestamp, ID, and related timeline filter links.
- The Events page supports URL-backed timeline filters for type, status, region ID, hero ID, settlement ID, landmark ID, resource ID, and era.

### Completed Era Timeline Filter Slice

- `GET /api/events?era=N` now resolves the requested era into the matching 100-year event range.
- The Events page exposes an era filter beside entity, type, and status filters.
- The Era Chronicle links both the current era and completed era entries directly to filtered timelines.
- Backend wiring tests and frontend smoke tests cover era filter plumbing across the repository, API client, event hook, Events page, and Era Chronicle page.

### Completed Direct Entity Event Linkage Slice

- `game_events` now includes direct JSON link arrays for settlements, landmarks, and resource nodes alongside the existing region and hero links.
- A repeatable-safe migration adds the new columns and backfills existing events by entity name where possible.
- Tick-generated settlement events include `related_settlement_ids`.
- Tick-generated resource events include `related_resource_ids` and nearby settlement links when present.
- Resolved bet events link to the resolved target entity type, including settlements, landmarks, and resources.
- Entity history summaries now query direct settlement, landmark, and resource link arrays instead of relying on runtime name matching.
- Full snapshot exports preserve the new event link arrays.

### Completed Simulation Depth Slice

- Resource ticks now support depletion, renewable recovery, contesting, corruption spread, partial cleansing, instability, overwork, flourishing output, and stabilization.
- Settlement ticks now include resource specialization, defensive pressure, defensibility changes, ruin/recovery checks, fortified traits, and threshold-specific event titles.
- Region ticks now combine resource pressure and settlement pressure before updating prosperity, chaos, danger, and status.
- Region events now record threshold crossings for prosperity, chaos, danger, and status changes.
- `GET /api/history/summary` accepts `regionId` so region detail views can request scoped history.
- Region detail views now include a History tab with current-state summaries, direct/context event counts, timeline links, and recent event links for the selected region and its settlements, resources, landmarks, and heroes.

### Completed Divine Resonance Influence Slice

- Region reads now include resonance-derived `influenceActionCosts` and `influenceEffectiveness` metadata.
- Region influence actions spend resonance-adjusted divine favor and apply resonance-scaled prosperity, chaos, danger, and magic affinity changes.
- Region influence events and responses include the resonance summary and exact applied stat changes.
- Region influence controls and region overview panels now show divine resonance tier, cost adjustment, action costs, and effect previews.
- The generic divine influence cost endpoint reports the same resonance effect metadata for region targets.

### Completed Betting Resolution

- Speculation events are generated from current regions, settlements, heroes, landmarks, and resource nodes instead of static mock fixtures.
- Bet types now include current-world predictions such as settlement growth, landmark discovery, corruption spread, hero milestones, hero death, prosperity thresholds, and resource disruption.
- Odds are recalculated from target state, timeframe, confidence, prosperity, chaos, danger, magic affinity, and relevant hero or settlement stats.
- Tick processing resolves active bets against actual world state and records readable win/loss/expiry notes.
- Speculation options expose target state and odds-factor explanations to the frontend.
- My Bets now supports active, won, lost, expired, and all-bet filters.
- Influence changes region and hero state, which affects future odds indirectly through the simulation.

### Completed Betting Forecasting and Portfolio Slice

- New and previewed bets now include capped, confidence-aware payout profiles with gross payout, net profit, implied probability, and risk band.
- Current bet rows expose payout profile metadata while preserving stored payouts for older bets.
- Current-world odds modifiers now cover hero milestone, hero death, region danger, and prosperity threshold bet types instead of falling back to generic odds.
- `GET /api/bets/summary` returns compact bet portfolio totals, active stake exposure, potential payout, resolved net favor, win rate, top bet types, and recent active/resolved bets.
- The Betting page now shows a compact Bet Portfolio panel and payout/risk labels for speculation options and existing bets.

### Completed Hero Lifecycle Visibility Slice

- Hero API payloads now include lifecycle summaries with level category, age category, status, feat count, milestone progress, mortality pressure, alignment summary, and influence costs.
- Hero API payloads now include current region relationship context, nearby settlements, peer hero counts, and recent settlement/landmark interactions when records exist.
- Hero API payloads now include direct hero event counts and recent hero-linked events from `game_events.related_hero_ids`.
- Hero cards now surface lifecycle state, region ties, recent history counts, selected-card event links, and one-click filtered hero timelines.

### Completed Hero and Landmark Civic Pressure Slice

- Settlement ticks now calculate civic pressure from living heroes and known landmarks in the region.
- Hero roles now influence settlement growth, prosperity, defenses, specializations, and traits through bounded tick modifiers.
- Landmark status, magic, danger, sacred traits, and strategic traits now influence settlement prosperity, defenses, specializations, traits, and growth pressure.
- Settlement tick change reasons and generated settlement events now include civic-pressure summaries.
- Settlement events now link relevant hero and landmark IDs so hero, landmark, settlement, and region timelines can expose those relationships.

### Completed Production Game Loop Operations Slice

- `composer game:health` now runs `backend/scripts/checkGameLoopHealth.php` and emits a single JSON health object.
- The health check reports simulation state, current year, divine favor, last tick age, queue availability, queued jobs, failed jobs, and tick errors.
- Health check exit codes support monitoring: `0` healthy, `1` warning, and `2` critical.
- `backend/docs/production-game-loop.md` documents cron cadence, queue worker supervision, restart procedure, Dashboard signals, and alert triage.
- `backend/README.md` now points operators to the implemented PHP game-loop commands instead of saying the game loop is not implemented.

### Completed Magic and Culture Pressure Slice

- Region ticks now calculate magic/culture pressure from living heroes, known landmarks, resource nodes, settlements, current magic affinity, prosperity, danger, and chaos.
- Magic/culture pressure can change regional magic affinity, cultural influence, and regional traits through bounded tick modifiers.
- Region tick summaries now include magic affinity, cultural influence, regional traits, and readable magic/culture pressure reasons.
- Region events now use culture and magic-specific titles when appropriate, include magic/culture threshold notes, and link related heroes, settlements, landmarks, and resources.
- Existing cultural-shift bets now have a simulation path through regional cultural influence drift rather than only static seeded data.

### Completed Magic Discovery Baseline Slice

- `MagicDiscoveryService` now tracks hidden, emerging, and known magic paths in game config state.
- `GET /api/magic` and `POST /api/magic/research` expose authenticated magic discovery status and player-facing research actions.
- Research targets include regions, heroes, and landmarks, with path evidence from live magic affinity, chaos, danger, settlement substrate, magical resources, hero roles/levels, and landmark traits.
- Discoveries spend divine favor, record `magic_research` or `magic_discovery` events, preserve path history, and link related regions, heroes, or landmarks.
- First-time known discoveries create durable world changes through regional magic path traits, magic affinity increases, hero feats, or landmark trait/magic increases.
- Emerging paths expose first-class `magic_discovery` betting hooks tied to the researched target.
- `/api/status`, full world export, and the Magic page expose path progress, evidence signals, suggested targets, discovery history, and betting hooks.

### Completed Magic-Specific Betting Slice

- `magic_discovery` is now a supported divine bet type across backend validation, odds, schema initialization, and frontend types.
- Magic speculation events are prioritized in the Betting page when an emerging path has a researched target.
- Magic discovery odds now use visible path status, progress, and evidence instead of falling back to generic target odds.
- Active magic discovery bets resolve during ticks when the tracked path becomes known through the wagered target within the prediction window.
- Resolution notes explain whether the path became known or remains short of a breakthrough.

### Completed Dynamic Mythology Baseline Slice

- `MythologyService` now derives myth candidates from major hero, artifact, magic, weather, omen, era, divine influence, bet, and world-change events.
- `GET /api/myths` and `POST /api/myths/promote` expose authenticated mythology status and player-facing myth promotion actions.
- Promoted myths spend divine favor, persist in game config state, record a `myth_promoted` event, and retain links to source and promotion events.
- Myth promotion creates durable world effects through regional `mythic_memory` / `myth_*` traits, hero `Mythic Reputation` feats, and landmark `mythic_anchor` traits.
- Regional tick pressure now reads mythic regional traits, so myths can influence future culture/magic drift and generated event summaries.
- `/api/status`, full world export, and the Myths page expose promoted myths, candidate legends, influence summaries, source events, and promotion events.

### Completed Civilization Behavior Baseline Slice

- `CivilizationBehaviorService` now scores each region across expansion, defense, trade, rivalry, research, and recovery agendas.
- `GET /api/civilization` and `POST /api/civilization/advance` expose authenticated civilization status and bounded civic decision advancement.
- Civilization agendas use live resources, settlement prosperity/defense, culture, landmarks, and hero roles as decision inputs.
- Civic decisions persist recent history in game config state, record `civilization_behavior` events, link affected entities, and apply durable bounded changes to regions, settlements, resources, heroes, or landmarks.
- Automated ticks now advance one high-pressure civilization agenda and include the decision in the latest tick summary.
- `/api/status`, full world export, the Dashboard, and the Civilization page expose agenda scores, score signals, recent decisions, and linked events.

### Completed Era Pressure Forecasting Slice

- `EraPressureService` now calculates era-ending pressure from current regions, settlements, heroes, landmarks, resource nodes, active divine bets, influence history, player favor, and current year.
- Era pressure tracks the roadmap's named trigger families: cataclysm, collapse, conquest, magical rupture, and divine war.
- `/api/status` now exposes current era pressure, top trigger, trigger signals, warnings, and related entity IDs.
- Game ticks now store an era-pressure snapshot in `last_tick_result` and create `era_pressure` events when dangerous pressure appears or materially changes.
- The Dashboard now has a current Era Pressure panel plus a last-tick era pressure summary with event links when an era warning is generated.
- Full world snapshot exports now include the same era pressure context in `gameState`.

### Completed Era Legacy Forecast Slice

- `EraLegacyService` now forecasts non-destructive continuity candidates from live world state before any era reset occurs.
- The forecast selects likely hero legacies, bloodline seeds, landmark anchors, world scars, carried myths, and era-spanning divine bets.
- `/api/status` now exposes current era legacy continuity score, readiness tier, summary, and related entity IDs.
- Game ticks now store an era legacy snapshot in `last_tick_result`, so continuity can be compared against the latest simulation change.
- Full world snapshot exports now include the same era legacy context in `gameState`.
- The Dashboard now has a current Era Legacy panel plus a last-tick era legacy summary.

### Completed Baseline Era Rollover Slice

- `EraTransitionService` now evaluates whether an era rollover is due from calendar boundaries or breaking era pressure.
- The game loop stores era rollover readiness in `last_tick_result` and automatically applies rollover when the transition is eligible.
- Admins can explicitly apply a rollover through `POST /api/admin/era/transition`, with optional forced closure for controlled testing or operations.
- A completed rollover transforms existing regions, settlements, heroes, landmarks, resources, and active bets rather than only changing the year number.
- Hero legacies can reincarnate into the next era, bloodline/landmark/scar/myth candidates influence carry-forward summaries, and non-spanning active bets expire with the era.
- Completed rollovers create `era_transition` events and persist era-history entries in `game_configs`.
- `/api/status`, last-tick status, full export, and the Dashboard now show era rollover readiness and recorded era history.

### Completed Era Comparison Snapshot Slice

- `EraComparisonService` now builds current world snapshots from regions, settlements, heroes, landmarks, resources, and divine bets.
- Era rollover history entries now store before/after snapshots, metric deltas, and a short comparison summary when a transition is completed.
- `/api/status` and full world exports now include current era comparison context, including the latest completed transition comparison when available.
- The Dashboard now has an Era Comparison panel showing current world metrics, latest rollover deltas, and post-rollover drift.

### Completed Era Chronicle Page Slice

- The frontend now has a first-class `/eras` page reachable from the main navigation.
- The Era Chronicle page uses live status data to switch between completed era chronicle entries, continuity seeds, and era-pressure triggers.
- Completed era entries can show event links and recorded transition metric deltas when history snapshots exist.
- Worlds without completed transitions still show the current era baseline, so players can inspect what will become the next rollover comparison.

### Completed Era-Born Foundation Generation Slice

- `EraGenerationService` now adds new era-born settlements, heroes, landmarks, and resources during completed rollovers.
- New foundations are targeted from legacy/pressure-linked regions first, then from strong surviving regions, so the new era grows from actual prior-world state.
- Generated era-born content records a dedicated `era_generation` event with related region, hero, settlement, landmark, and resource IDs.
- Era transition history now stores generated content summaries and generation counts, and the Dashboard/Era Chronicle views expose those foundations to players.

### Completed Mortal Champion Baseline Slice

- `ChampionService` now maintains a small player-facing champion roster in game config state.
- `GET /api/champions`, `POST /api/heroes/{id}/champion`, and `POST /api/heroes/{id}/champion/cultivate` expose roster status, designation, and cultivation actions through authenticated PHP routes.
- Hero API payloads now include champion status, eligibility, costs, focus options, and current champion profile data.
- Champion designation and cultivation spend divine favor, update hero feats/level/alignment where appropriate, and record `champion_designated` / `champion_cultivated` events linked to the hero and region.
- Champion designation and cultivation events are eligible mythology candidates, so champion stories can become promoted heroic legends.
- Champion cultivation increases hero feats/levels, which indirectly improves era legacy and reincarnation candidacy through existing hero continuity scoring.
- The Heroes page now has a Mortal Champion Bond panel for selecting, designating, and cultivating champions, while hero cards show champion rank, bond, focus, and current quest progress.

### Completed Champion Quest Outcome Slice

- Automated ticks now advance champion quest progress and can resolve quest, defense, research, and rivalry outcomes without same-turn player cultivation.
- Completed champion outcomes record `champion_quest_completed`, `champion_rivalry_resolved`, or `champion_rivalry_escalated` events linked to the hero, region, and affected settlement or landmark when present.
- Champion outcomes mutate bounded world state through hero feats/levels, regional prosperity/chaos/danger/magic, settlement defenses, landmark magic, and rivalry regional traits.
- Champion status now exposes recent outcomes, betting hooks, and legacy hooks through `GET /api/champions`, `/api/status`, full export, and `export/champions`.
- Betting speculation now includes champion trial/rivalry hooks, and active hero milestone or region danger bets can resolve from champion outcome events.
- Mythology candidates and era legacy scoring now treat champion quest/rivalry events as explicit heroic or rivalry continuity signals.
- The Heroes page and Dashboard last-tick panel now show recent champion outcomes and event links.

### Completed Divine Artifact Baseline Slice

- `ArtifactService` now maintains a limited player-facing artifact roster in game config state.
- `GET /api/artifacts`, `POST /api/artifacts`, `POST /api/artifacts/{id}/empower`, `POST /api/artifacts/{id}/transfer`, and `POST /api/artifacts/{id}/stabilize` expose authenticated artifact status and actions.
- Artifact creation requires a player-provided name and focus, then spends divine favor and records an `artifact_created` event.
- Artifact empowerment raises power while increasing instability/corruption and can trigger `artifact_corrupted` or `artifact_stolen` backlash events.
- Artifact transfer and stabilization spend divine favor and record `artifact_transferred` / `artifact_stabilized` events, preserving artifact history entries with event links.
- `/api/status`, full world export, and the Artifacts page expose artifact roster state, risk tier, owner, costs, and history.

### Completed Weather and Environmental Influence Baseline Slice

- `WeatherInfluenceService` now maintains recent divine weather influence history in game config state.
- `GET /api/weather` and `POST /api/weather/nudge` expose authenticated weather status and player-facing climate nudges.
- Weather patterns include gentle rains, drought, protective winds, tempest, and arcane mist, each with intensity-scaled divine favor costs and deterministic probabilistic fallout.
- Weather nudges mutate regional prosperity, chaos, danger, magic affinity, status, settlement prosperity/defensibility/population/status, and resource output/status.
- Every nudge records a `weather_influence` event linked to the affected region, settlements, and resource nodes.
- `/api/status`, full world export, and the Weather page expose climate history, travel effects, conflict effects, backlash summaries, and event links.

### Completed Temporal Omens Baseline Slice

- `TemporalOmenService` now maintains recent non-mutating time-omen history in game config state.
- `GET /api/omens` and `POST /api/omens` expose authenticated omen status and player-facing future readings.
- Omen targets include the world, a region, or a hero; horizons include near future, generation-scale, and era-edge readings.
- Omen readings spend divine favor, record a `time_omen` event, and forecast current trajectories from live regions, settlements, heroes, resources, active bets, and era pressure.
- Omen responses include forecast signals, prediction risk scores, confidence bands, related entity IDs, and a consistency note that the action does not rewind, branch, or mutate world state.
- `/api/status`, full world export, and the Omens page expose temporal omen history, forecast summaries, and event links.

### Completed Divine Tool Consequence Slice

- Divine artifacts now advance during world ticks after immediate player actions, producing delayed `artifact_consequence` events when power, instability, corruption, or lost/corrupted status makes an artifact echo through the world.
- Artifact consequences can affect owner-linked regions, settlements, resources, heroes, or landmarks, then persist event-linked artifact history and latest consequence data.
- Prior weather nudges can now produce delayed `weather_consequence` events that continue affecting region pressure, settlements, and resources after the original weather action.
- Temporal omens now receive non-mutating `time_omen_followup` events when the forecast target year arrives, recording whether the timeline was fulfilled, darkened, or averted.
- The game loop stores these delayed results under `lastTickResult.divineTools`, and the Dashboard now surfaces a Divine Tool Consequences panel with event links.
- Artifact, weather, and omen follow-up events feed mythology candidates and era legacy carried-myth reasons as relic, weather-scar, or prophecy continuity.
- Current implementation is a baseline; deeper chained consequences, stronger balancing, and richer era-transition transformations remain future work.

### Completed AI Pantheon Baseline Slice

- `PantheonService` now defines non-player divine actors with domains, goals, strategies, allies, and rivals.
- `GET /api/pantheon` exposes authenticated pantheon status with current pressure scores, target regions, deity roster data, relationships, and recent interventions.
- Automated ticks now advance one high-pressure pantheon intervention, producing bounded world mutations across regions, settlements, resources, heroes, or landmarks.
- Pantheon interventions record `pantheon_intervention` events linked to affected entities and persist recent intervention history in game config state.
- Pantheon intervention events feed mythology candidates and era-legacy carried-myth reasons as direct divine-intervention continuity.
- `/api/status`, full world export, the Dashboard, last-tick summaries, and the Pantheon page expose pantheon pressure and event-linked intervention history.
- Current implementation is a baseline; first-class intervention betting hooks and derived political escalation are now implemented, while richer alliance/rival chains and long-run balancing remain future work.

### Completed Pantheon Counterplay Slice

- `POST /api/pantheon/{id}/counterplay` lets players spend Divine Favor to appease or challenge an AI deity.
- Counterplay persists appeasement/defiance state per deity, decays with time, and reduces near-term pantheon pressure with visible pressure signals.
- Counterplay records `pantheon_counterplay` events linked to the current target region when available.
- The Pantheon page exposes action buttons, favor costs, active pressure reduction, relationship tension, and recent counterplay event links.
- Backend wiring tests and frontend smoke tests cover counterplay route, service, API client, entity types, and page controls.

### Completed Pantheon Politics and Betting Slice

- `GET /api/pantheon` now includes derived political escalation state for ally/rival fronts, pressure scores, target regions, intervention counts, and summary text.
- Pantheon status publishes `pantheon_intervention` betting hooks from current divine pressure, including target region, deity, pressure tier, confidence, prediction window, and risk summary.
- Divine betting recognizes `pantheon_intervention` as a first-class bet type with model constants, migration support, production SQL enum alignment, odds modifiers, speculation events, and frontend typing.
- Active pantheon intervention bets resolve from real `pantheon_intervention` events recorded against the wagered region during the prediction window.
- The Pantheon page exposes Pantheon Betting Hooks and political escalation cards, and smoke/wiring tests cover the end-to-end route from status to UI and bet resolution.

### Completed Admin World Editor Baseline Slice

- `GET /api/admin/world-editor`, `POST /api/admin/world-editor/{entityType}`, and `PUT /api/admin/world-editor/{entityType}/{id}` are registered behind the shared admin middleware.
- The admin editor supports controlled creation and editing for regions, settlements, landmarks, resource nodes, and heroes.
- Validation checks required names, stable IDs, existing region references, settlement-region compatibility, enum values, bounded numeric ranges, hex colors, list fields, and hero alignment shape.
- Admin edits record `admin_world_edit` events with direct entity links so manual operations remain visible in history and export/timeline surfaces.
- The `/admin/world-editor` page is hidden from non-admin navigation, shows an access-required state for non-admin users, and is separate from player-facing Divine Favor actions.
- Backend wiring tests, route-security coverage, frontend smoke tests, and frontend type-check cover the baseline editor route/API/page integration.

### Completed Chronicle Share Export Slice

- `GET /api/export/chronicle-share` exports a curated share package instead of a full raw world dump.
- Chronicle packages include a headline, copy-ready share text, summary counts, era pressure context, event-type themes, highlighted major events, linked timeline cards, entity spotlights, betting highlights, and current filters.
- Share exports support optional era, region, hero, settlement, landmark, resource, and limit filters so players can capture focused world-history slices.
- The Dashboard exposes an `Export Chronicle` button alongside the full snapshot export.
- Backend wiring tests and frontend smoke tests cover the authenticated route, controller, service package shape, dashboard button, filename, and export path.

### Remaining Risks

- Resource, settlement, hero, civilization, and betting systems are deeper than baseline drift, but long-term balancing and more varied civilization behavior still need repeated tick observation.
- Divine resonance now affects player-facing region influence, but its multipliers still need tuning after longer play sessions.
- Smoke tests cover route and API wiring; add browser-level interaction tests once the UI flows settle.
- Champion quest/rivalry outcomes, direct champion betting hooks, mythology signals, and era-legacy reasons are implemented as a baseline; explicit reincarnated champion descendant identity and deeper multi-champion relationship arcs remain future work.
- The world editor is a baseline create/update tool; bulk edits, rollback workflows, audit browsing, import/export authoring, and richer compatibility previews remain future work.
- Replay/share has a baseline chronicle export; interactive replay playback, public share pages, and richer curated presentation remain future work.
- Large simulation systems such as deeper magic progression, deeper autonomous mythology, richer reincarnation identity, deeper civilization AI, richer pantheon alliance/rival chains, multi-step artifact/weather/omen consequence chains, deeper time manipulation, and broader procedural era variety remain future work.

---

## Next Priorities

### 1. Make Simulation Changes More Visible

**Status:** Partially implemented. The dashboard now surfaces the latest tick, entity history summaries, event IDs for tick-linked changes, direct entity-event links, clickable event detail pages, URL-backed timeline filtering, threshold explanations, region-detail history placement, current era pressure, last-tick era pressure, current era legacy, last-tick era legacy, era rollover readiness, era history, cross-era comparison snapshots, civilization agenda decisions, and pantheon interventions. A dedicated Era Chronicle page now exposes chronicle, legacy, and pressure views. Hero cards now surface lifecycle, direct history, and champion context, the Artifacts page exposes artifact history, the Weather page exposes climate influence history, the Omens page exposes temporal forecast history, the Magic page exposes discovery history, the Myths page exposes promoted myth history, the Civilization page exposes civic agenda history, the Pantheon page exposes AI deity pressure, political escalation, intervention betting hooks, and counterplay, and region tick summaries now expose magic/culture pressure. Broader browser-level interaction coverage remains open.

- Keep expanding the dashboard last-tick panel as implemented systems gain new autonomous outcomes.
- Continue adding status explanations as champion outcomes, divine-tool consequence chains, time effects, richer pantheon alliance/rival arcs, and advanced divine thresholds deepen.

**Acceptance Criteria**

- A player can run or observe a tick and understand the meaningful changes without reading raw JSON. **Implemented for the latest tick.**
- Resolved bets show the state or event that caused the outcome. **Implemented with readable notes, generated event IDs, and clickable event details from latest-tick summaries.**
- Entity history summaries exist for regions, heroes, settlements, landmarks, resource nodes, and bets. **Implemented with direct event links where events exist; regional context remains a fallback.**
- Hero lifecycle and direct hero history can be inspected without leaving the Heroes page. **Implemented with hero card summaries, selected-card event links, and filtered timeline links.**
- Champion designation and cultivation state can be inspected without leaving the Heroes page. **Implemented with the Mortal Champion Bond panel and hero-card champion summaries.**
- Artifact creation, ownership, risk, and history can be inspected from a dedicated player page. **Implemented with the Artifacts page and artifact event links.**
- Weather influence, travel pressure, conflict pressure, and affected settlements/resources can be inspected from a dedicated player page. **Implemented with the Weather page and linked weather events.**
- Temporal forecasts can be inspected without changing world state. **Implemented with the Omens page, forecast signals, risk bands, and linked omen events.**
- Magic discovery progress, evidence, durable discovery history, and betting hooks can be inspected from a dedicated player page. **Implemented with the Magic page and linked magic research/discovery events.**
- Myth candidates, promoted myths, source events, and durable myth effects can be inspected from a dedicated player page. **Implemented with the Myths page and linked source/promotion events.**
- Civilization agendas, score signals, recent decisions, and durable civic effects can be inspected from a dedicated player page. **Implemented with the Civilization page and linked civilization events.**
- Pantheon actors, pressure targets, relationships, political escalation, intervention betting hooks, recent interventions, and counterplay can be inspected from a dedicated player page. **Implemented with the Pantheon page, current dashboard panel, last-tick intervention summaries, linked `pantheon_intervention` events, `pantheon_intervention` betting hooks, and player `pantheon_counterplay` events.**
- Era pressure can be inspected before an ending occurs. **Implemented with current status and last-tick dashboard panels.**
- Era continuity candidates can be inspected before an ending occurs. **Implemented with current status, last-tick dashboard panels, and full export context.**
- Era rollover readiness and completed transitions can be inspected by players. **Implemented with status, export, Dashboard Era Rollover, and last-tick rollover summaries.**
- Cross-era before/after metrics can be inspected by players after rollovers. **Implemented with status, export, transition snapshots, and the Dashboard Era Comparison panel.**
- Era chronicle, continuity, and pressure context can be inspected outside the statistics dashboard. **Implemented with the `/eras` page.**
- New era foundations can be inspected after a completed rollover. **Implemented with era-born generation history, generation events, Dashboard history rows, and the Era Chronicle page.**
- Dashboard status reflects the same year and tick state as the Events, Betting, Heroes, and World Map pages.

### 2. Deepen Resource and Settlement Gameplay

**Status:** Partially implemented. Resource nodes are seeded, visible in region views, affect region/settlement drift, and now produce scarcity/recovery/contesting/corruption outcomes. Settlements now gain specialization, defensive outcomes, bounded hero/landmark civic pressure, and baseline civilization agenda behavior, but long-run balance and richer civilization behavior remain open.

- Balance resource output, status, and disruption after longer tick runs.
- Expand settlement growth, decline, ruin, recovery, specialization, and defense into richer long-run civic behavior.
- Record additional threshold events as magic, culture, and deeper civilization AI become stronger inputs.

**Acceptance Criteria**

- Every region has visible resource or settlement pressures that can change over time. **Implemented for seeded regions and tick drift.**
- Resource disruption and settlement prosperity can create or resolve bets. **Partially implemented through resource-targeted corruption and prosperity bets.**
- Region detail views explain why a settlement or resource changed. **Implemented with scoped region history and readable tick reasons.**

### 3. Improve Betting Context and Forecasting

**Status:** Partially implemented. Speculation events now expose target state, odds-factor explanations, tuned payout profiles, and risk labels. Bet history can be filtered by status and now has a compact portfolio summary. Richer forecasting signals remain open as future simulation systems come online.

- Add more target-specific forecasting signals as heroes, landmarks, magic, culture, and civilization AI become stronger inputs.
- Keep tuning payout ranges after longer observed tick runs.

**Acceptance Criteria**

- Players can compare at least three meaningful signals before placing a bet. **Implemented.**
- Active and resolved bets remain easy to scan after multiple ticks. **Implemented with status filters and the Bet Portfolio summary.**
- Odds changes feel explainable from visible world state. **Implemented for current target state, odds factors, payout profile, and risk band; deeper future-system signals remain open.**

### 4. Deepen Divine Tool Consequences

**Status:** Baseline player-facing systems are implemented for champions, artifacts, weather, and omens. Champions now have autonomous quest/rivalry outcomes, betting hooks, myth candidates, and era-legacy signals. Artifacts, weather, and omens now have baseline delayed tick follow-through, dashboard visibility, event-linked histories, mythology candidates, and era-legacy carried-myth reasons. Stronger multi-step consequence chains still need work.

- Tune champion quest/rivalry pacing and expand deeper multi-champion relationships after longer tick observation.
- Expand artifact risk, weather changes, and temporal omens from baseline delayed consequences into richer recurring or chained outcomes.
- Tune major artifact, weather, and omen outcomes so mythology and era continuity reasons remain legible after long simulations.

**Acceptance Criteria**

- Champion quest or rivalry outcomes can happen without direct player cultivation on the same turn. **Implemented with tick-driven champion outcome resolution.**
- Champion-driven speculation appears in the betting interface with odds factors tied to visible champion state. **Implemented with champion betting hooks and champion-aware hero target state.**
- Delayed artifact/weather/omen consequences create event-linked dashboard and entity-history entries. **Implemented as a baseline through `artifact_consequence`, `weather_consequence`, `time_omen_followup`, last-tick `divineTools`, and direct event links.**
- Era legacy explains when a champion, artifact, omen, or weather scar is shaping the next era. **Implemented as a baseline for champion rank/bond/outcomes plus artifact/weather/omen carried-myth reasons; richer era-transition transformations remain future work.**

---

## Phase 1: Simulation Depth

### 1.1 Settlement Evolution

- Deepen growth, decline, ruin, recovery, specialization, and defensive changes. **Implemented for resource pressure, defense pressure, hero/landmark civic pressure, ruin/recovery checks, specialization, and baseline civilization agendas; richer long-run civic AI remains future work.**
- Expand the current settlement growth/prosperity drift so resources, landmarks, and hero presence create clearer outcomes. **Implemented for resources, defense, living hero roles, known landmark traits/status, and bounded civilization decisions; deeper multi-region civilization strategy remains future work.**
- Surface settlement change history in region detail views. **Implemented through the region History tab.**

### 1.2 Resource Scarcity

- Keep resource nodes consistently seeded through SQL and PHP seed scripts. **Implemented for the starting world.**
- Deepen depletion, contesting, corruption, recovery, and productivity effects beyond the current output/status drift. **Implemented for tick outcomes; long-run tuning remains open.**
- Expand resources as inputs for settlement growth, regional danger, conflict, and betting opportunities. **Implemented for growth, prosperity, danger, conflict-style statuses, and existing resource bets; richer bet generation remains open.**

### 1.3 Region Systemic Changes

- Deepen prosperity, chaos, danger, magic affinity, and status changes beyond the current baseline tick drift. **Implemented for resource pressure, settlement pressure, hero/landmark civic pressure, and magic/culture pressure; long-run balancing remains open.**
- Make divine resonance affect influence cost/effectiveness in ways visible to players. **Implemented for region influence actions, generic cost estimates, and player-facing region panels; long-run balance remains open.**
- Generate region events when major thresholds are crossed. **Implemented for prosperity, chaos, danger, and status thresholds.**

### 1.4 Hero Lifecycle

- Expand the current hero aging, movement, leveling, feats, mortality, and revival loop. **Baseline implemented for ticks, influence, revival costs, and player-visible lifecycle summaries; richer quest/relationship mechanics remain future work.**
- Add clearer hero event history and region relationships. **Implemented for hero cards with direct event counts, recent event links, filtered timelines, current region context, nearby settlements, peer hero counts, and settlement/landmark interactions when present.**
- Expand alignment and personality effects once the baseline lifecycle is stable.

---

## Phase 2: Magic, Culture, and World Identity

### 2.1 Magic Discovery and Research

- Add discoverable magic paths tied to regions, heroes, landmarks, and research guidance. **Implemented as a baseline player-facing research system with five paths, suggested targets, and evidence signals from live world state.**
- Track known, hidden, and emerging magical systems. **Implemented in persisted magic discovery state and surfaced through `/api/magic`, `/api/status`, full export, and the Magic page.**
- Make magical discoveries create durable world changes and new betting opportunities. **Implemented as a baseline through `magic_research` / `magic_discovery` events, regional path traits, magic affinity increases, hero feats, landmark trait/magic increases, first-class `magic_discovery` speculation hooks, magic-specific odds, and tick-time magic bet resolution.**

### 2.2 Cultural Evolution

- Add culture traits and cultural pressure between regions. **Partially implemented within each region through cultural influence drift and regional trait signals; inter-region cultural pressure remains future work.**
- Connect culture to settlement specialization, hero roles, events, and divine influence. **Implemented for settlement specialization, hero roles, landmarks, resources, and region tick events; deeper divine influence hooks remain future work.**
- Surface cultural drift in dashboard and region views. **Implemented through latest-tick summaries, region events, timeline links, and existing region detail culture/trait fields.**

### 2.3 Dynamic Mythology

- Promote major hero feats, disasters, discoveries, and divine interventions into myths. **Implemented as a baseline candidate/promotion system from major existing events.**
- Let myths affect region identity, hero reputation, and future events. **Implemented as regional myth traits, hero reputation feats, landmark anchor traits, and mythic regional pressure that can affect future tick events; deeper autonomous myth evolution remains future work.**
- Add a mythology or chronicles view once enough data exists. **Implemented with the `/myths` page for promoted myths and candidate legends.**

### 2.4 Civilization Behavior

- Add higher-level AI behavior for settlements and regions: expansion, defense, trade, rivalry, research, and recovery. **Implemented as baseline regional agenda scoring plus bounded civic decisions.**
- Use resources, culture, landmarks, and heroes as inputs to decisions. **Implemented through live agenda scores using resource output/status, settlement prosperity/defense, culture, landmark signals, and hero roles; deeper inter-region strategy remains future work.**
- Keep behavior explainable through events and dashboard stats. **Implemented with `civilization_behavior` events, Dashboard current/last-tick panels, status/export payloads, and the Civilization page.**

---

## Phase 3: Era and Legacy Systems

### 3.1 Era-Ending Conditions

- Define world-state triggers for cataclysms, collapse, conquest, magical rupture, or divine war. **Implemented as era-pressure scoring from current world state; breaking pressure can now trigger actual rollover.**
- Show era pressure to players before the end arrives. **Implemented in `/api/status`, Dashboard current Era Pressure, and last-tick era summaries.**
- Generate an era summary from actual events and statistics. **Partially implemented as trigger summaries/signals, era-pressure warning events, era-transition history entries, and transition events; fuller statistical end-of-era chronicles remain future work.**

### 3.2 Reincarnation and Continuity

- Select heroes, bloodlines, landmarks, myths, and scars that persist across eras. **Implemented as a continuity forecast and baseline carry-forward inputs during actual rollover; richer generated descendants remain future work.**
- Let some divine bets span era boundaries. **Implemented for active bets selected by the era legacy forecast; non-spanning active bets expire during rollover.**
- Preserve enough history for the next era to reference the previous one. **Partially implemented through carried myth candidates, direct entity history, era pressure, transition events, era-history config entries, full export, and last-tick snapshots; richer era-history views remain future work.**

### 3.3 New Era Generation

- Reset or transform regions, settlements, resources, heroes, and magic rules. **Implemented as existing-world transformation plus era-born settlements, heroes, landmarks, and resources during eligible or admin-forced rollover; broader procedural variety remains future work.**
- Carry forward legacies without making the new era feel predetermined. **Implemented with bounded stochastic transformations, legacy candidates, scars, era pressure, and new foundations; deeper descendant identity remains future work.**
- Add player-facing era history and comparison tools. **Implemented as current-era pressure, current-era legacy, era rollover readiness/history, event-linked timelines, full export context, Dashboard cross-era comparison snapshots, and a dedicated Era Chronicle page.**

---

## Phase 4: Deeper Divine Gameplay

### 4.1 Mortal Champion System

- Let players designate or cultivate champions without direct control. **Implemented as a baseline roster, designation action, focus cultivation, divine favor costs, hero mutation, event creation, and Heroes page controls.**
- Add champion quests, rivalries, and higher-impact influence actions. **Implemented as a baseline with tick-driven quest progress, quest completion, defense/research/rivalry outcomes, and bounded world mutations; deeper multi-champion arcs remain future work.**
- Connect champions to bets, myths, reincarnation, and era legacy. **Implemented as a baseline with champion betting hooks, champion outcome bet resolution, mythology candidates, and champion-specific era legacy reasons; explicit reincarnated champion descendant identity remains future work.**

### 4.2 Divine Artifacts

- Allow limited artifact creation or empowerment. **Implemented as a limited artifact roster with player-provided names, focus selection, divine favor creation, and empowerment costs.**
- Make artifacts transferable, stealable, corruptible, and historically traceable. **Implemented with hero transfer, unbinding, instability/corruption risk, theft/corruption backlash, and artifact history entries linked to events.**
- Use artifacts as high-risk tools that can outlive their intended purpose. **Implemented as a baseline through persistent artifact state, empowerment backlash, and delayed tick-time artifact consequences; deeper chained relic behavior remains future work.**

### 4.3 Weather and Environmental Influence

- Add divine weather or climate nudges that affect resources, settlement survival, travel, and conflict. **Implemented as player-facing weather nudges that mutate region pressure, settlement survival stats, resource output/status, and travel/conflict summaries.**
- Keep effects probabilistic and event-driven rather than direct city-builder controls. **Implemented with deterministic risk rolls, backlash summaries, `weather_influence` events, event-linked Weather page history, and delayed tick-time climate consequences; deeper climate chains remain future work.**

---

## Phase 5: Advanced Systems

### 5.1 Time Manipulation

- Prototype limited temporal mechanics before full rollback or branching history. **Implemented as a baseline Temporal Omens system rather than rollback or branching history.**
- Start with previews, delayed omens, or accelerated local simulation. **Implemented with world, region, and hero omen forecasts across near, generation, and era-edge horizons plus non-mutating follow-up events when forecast years arrive.**
- Avoid anything that undermines persistent world consistency. **Implemented: omen readings spend favor and record events/history, but do not mutate world entities, rewind, or branch state.**

### 5.2 AI Pantheon

- Add non-player divine actors after the mortal world simulation is stable. **Implemented as a baseline `PantheonService` roster with four autonomous deities.**
- Give AI deities clear goals, domains, relationships, and visible interventions. **Implemented with `/api/pantheon`, the Pantheon page, Dashboard panel, tick summaries, and `pantheon_intervention` events.**
- Use pantheon politics to create multiplayer-like pressure without requiring live players. **Baseline implemented with allies, rivals, pressure scoring, derived political escalation, pantheon intervention betting hooks, automated interventions, and player appease/challenge counterplay; richer multi-step alliance/rival arcs remain future work.**

### 5.3 Replay and Timeline Tools

- Build timeline views from event history, bets, latest tick summaries, and major entity state changes. **Partially implemented through Events, event detail pages, Dashboard Chronicles, entity history summaries, the Era Chronicle, and curated chronicle share packages; interactive playback remains future work.**
- Support filtering by hero, region, settlement, landmark, resource, and era. **Implemented for entity and era filters.**
- Use this as the foundation for sharing world histories. **Implemented as a baseline through full snapshot export, readable event/entity timelines, and Dashboard chronicle share export packages; public share pages and replay presentation remain future work.**

### 5.4 World Editor and Admin Tools

- Add controlled creation/editing tools for regions, settlements, landmarks, resources, and heroes. **Implemented as an admin-only baseline API and `/admin/world-editor` page.**
- Keep admin tools separate from player-facing divine influence. **Implemented with shared admin middleware and hidden admin navigation; non-admin users see an access-required state.**
- Include validation so edited worlds remain simulation-compatible. **Implemented for required references, bounded stats, enum values, list fields, colors, settlement-region compatibility, and hero alignment; richer preview/rollback workflows remain future work.**

---

## Implementation Principles

- Stabilize the current loop before adding new systems.
- Favor systems that create readable events and meaningful bets.
- Keep divine actions probabilistic; players should influence fate, not command it.
- Make every major simulation change inspectable through events, dashboard data, or entity history.
- Treat automated ticks, betting resolution, and event generation as the core technical spine.
- Prefer small vertical slices over broad model additions that are not visible in gameplay.

## Suggested Order

1. Deeper magic progression, autonomous mythology evolution, deeper civilization AI, richer pantheon alliance/rival chains, and long-run balancing.
2. Broader new-era procedural variety, descendant identity polish, and richer era-transition transformations for champions and divine tools.
3. Expand artifact/weather/omen follow-through from baseline delayed effects into multi-step consequence chains.
4. Extend replay/share tools and admin world editing with interactive replay playback, public share pages, bulk workflows, audit views, and rollback-safe operations.
5. Tune champion pacing and expand deeper multi-champion relationships after longer tick observation.

## Success Metrics

- A guest can play a complete session without developer setup beyond running the app.
- At least one automated tick changes visible world state and records readable events.
- Production operators can monitor tick freshness and queue failures without opening the app UI.
- Bets resolve from real simulation state and explain their outcomes.
- Players can understand why the last tick changed important entities.
- Players can see era-ending pressure before an ending occurs. **Implemented for forecast pressure and eligible rollover readiness.**
- Players can see which heroes, places, scars, myths, and bets could survive an era boundary. **Implemented as a continuity forecast and baseline actual carry-forward during rollover.**
- An eligible era transition can transform the world and record a readable history entry. **Implemented for calendar or breaking-pressure rollover, including era-born foundation generation.**
- An eligible era transition can create new era-born foundations. **Implemented for settlements, heroes, landmarks, resources, and generation events.**
- Players can compare headline world metrics before and after a completed era transition. **Implemented through era comparison snapshots and Dashboard deltas.**
- Players can inspect era chronicle, legacy, and pressure data from a dedicated page. **Implemented with `/eras`.**
- Players can designate and cultivate mortal champions from the Heroes page. **Implemented with champion roster status, divine favor costs, focus cultivation, and linked champion events.**
- Champion quest/rivalry outcomes can resolve autonomously and feed bets, myths, and era legacy. **Implemented as a baseline with tick outcomes, direct betting hooks, mythology candidates, and champion-specific era legacy reasons.**
- Delayed artifact/weather/omen consequences can resolve autonomously and feed dashboard history, myths, and era legacy. **Implemented as a baseline with tick-time artifact consequences, weather follow-through, omen follow-up events, last-tick `divineTools`, mythology candidates, and explicit carried-myth reasons.**
- Players can create and manage divine artifacts from a dedicated page. **Implemented with artifact creation, empowerment, stabilization, hero transfer, unbinding, and event-linked history.**
- Players can influence regional weather from a dedicated page and inspect affected regions, settlements, resources, travel pressure, conflict pressure, and linked events. **Implemented with `/weather`, `weather_influence` events, and status/export weather history.**
- Players can preview future pressure without changing world state. **Implemented with `/omens`, `time_omen` events, and status/export temporal omen history.**
- Players can discover magical systems from live world evidence and inspect the resulting path history. **Implemented with `/magic`, `magic_research` / `magic_discovery` events, status/export magic discovery state, durable world changes, first-class magic discovery bets, and tick-time bet resolution.**
- Players can promote major events into durable myths and inspect their world effects. **Implemented with `/myths`, `myth_promoted` events, status/export mythology state, regional identity traits, hero reputation feats, landmark anchor traits, and future culture-pressure signals.**
- Players can inspect and advance higher-level civilization agendas. **Implemented with `/civilization`, `civilization_behavior` events, status/export civilization state, dashboard agenda stats, automated tick decisions, and durable bounded civic effects.**
- Players can inspect AI pantheon pressure, counterplay against deity pressure, bet on direct deity interventions, and see autonomous deity interventions affect the world. **Implemented with `/pantheon`, `pantheon_intervention` and `pantheon_counterplay` events, `pantheon_intervention` betting hooks and resolution, status/export pantheon state, dashboard pressure stats, last-tick summaries, mythology candidates, and era-legacy carried-myth reasons.**
- Admins can create and update primary world entities without exposing those controls as player divine influence. **Implemented with admin-only world editor routes/page, validation, and `admin_world_edit` events.**
- Players can scan recent history by primary entity type from the dashboard.
- Players can open a readable event detail page from a tick, entity history, or event feed entry.
- Players can filter event timelines by region, hero, settlement, landmark, resource, and era.
- Every primary entity type has a player-visible reason to matter. **Partially true now that resource nodes, hero lifecycle context, hero/landmark civic pressure, magic/culture pressure, magic discovery targets, mythology effects, civilization agendas, pantheon pressure, era pressure, and direct entity histories are visible.**
- Roadmap phases produce playable changes, not just backend-only data expansion.
