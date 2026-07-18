# Mytherra Development Roadmap

Last updated: 2026-06-16

This roadmap is post-migration. The PHP backend, protected/guest entry, main API parity routes, MySQL persistence, tick runtime, export/status surfaces, dashboard visibility, production game-loop operations, and baseline admin world editing are implemented. The active roadmap is now a gameplay-depth plan for autonomous outcomes, long-run balance, and stronger world identity systems.

## Roadmap Status

- **Migration status:** Complete. PHP 8.1+ backend routes, shared WebHatchery/guest auth, persistence, Composer entry points, frontend normalization, and protected player flows are in place.
- **Gameplay status:** Playable baselines exist for resources, settlements, betting, hero lifecycle, magic, mythology, civilization behavior, pantheon pressure, era rollover, champions, divine artifacts, weather, omens, dashboard inspection, chronicle sharing, and admin world editing.
- **Current focus:** Deepen autonomous gameplay outcomes beyond the completed baselines, especially magic progression balance, civilization diplomacy balance, inter-region strategy, mythology echo balance, broader era generation, descendant variety, divine-tool chain variety, pantheon arc variety, replay/share polish, and long-run simulation balance.

## Completed Work Archive

- Completed and baseline-implemented roadmap items are archived in [docs/archive/COMPLETED_ROADMAP.md](docs/archive/COMPLETED_ROADMAP.md).
- Keep `ROADMAP.md` focused on active priorities, future gameplay depth, balancing, and remaining acceptance criteria.

## Current State Summary

- The PHP migration is complete.
- Mytherra has playable baseline systems for world simulation, betting, magic, mythology, civilization behavior, pantheon pressure, era rollover, champions, divine artifacts, weather, omens, dashboard inspection, chronicle sharing, and admin world editing.
- Remaining roadmap work should deepen autonomous outcomes, long-run balance, replay/share polish, procedural variety, and stronger world identity instead of reopening completed migration work.

## Next Priorities

### 1. Keep Simulation Changes Visible

The visibility baseline is in place. Maintain it as new autonomous systems create richer tick results and more entity history.

- Add live-browser CI coverage beyond rendered workflow tests and manual published-page passes if the frontend adopts a browser runner.
- Continue refining status explanations as champion outcomes, divine-tool consequence chains, time effects, pantheon arc balance, and advanced divine thresholds deepen.
- Keep the dashboard last-tick panel and Change Ledger updated whenever new tick payloads, entity snapshots, or consequence chains are added.

### 2. Deepen Resource and Settlement Gameplay

Resource and settlement systems are playable, but long-run balance and richer multi-region strategy remain open.

- Balance resource output, status, scarcity, recovery, disruption, and corruption after longer tick runs.
- Expand settlement growth, decline, ruin, recovery, specialization, defense, and civic behavior into richer long-run patterns.
- Add clearer threshold events as magic, culture, diplomacy, pantheon pressure, and era systems become stronger inputs.
- Expand resource and settlement bet generation beyond the current baseline target types.

### 3. Improve Betting Context and Forecasting

Betting has live target state, odds factors, risk bands, and portfolio summaries. The next step is better forecasting from future systems and longer observed simulations.

- Add more target-specific forecasting signals as heroes, landmarks, magic, culture, pantheon pressure, divine artifacts, and civilization strategy deepen.
- Tune payout ranges, risk bands, and resolution windows after longer tick runs.
- Add richer multi-region strategy forecasts for diplomacy, trade, rivalry, research, and recovery.
- Keep odds explanations connected to visible world state instead of hidden formulas.

### 4. Deepen Divine Tool Consequences

Champions, divine artifacts, weather, and omens all have baseline player-facing systems and delayed consequences. The next work is variety, pacing, and long-run readability.

- Tune champion quest and rivalry pacing, then expand deeper multi-champion relationships.
- Add more divine artifact archetypes, powers, ownership complications, and chained consequences beyond the starter relic baseline.
- Tune artifact risk, weather changes, and temporal omen chains so recurring outcomes remain legible and balanced over long simulations.
- Tune major artifact, weather, and omen outcomes so mythology and era-continuity reasons stay readable after long simulations.

## Phase 1: Simulation Depth

### 1.1 Settlement Evolution

- Tune long-run growth, decline, ruin, recovery, specialization, and defensive changes.
- Expand multi-region civic strategy so settlement outcomes respond to diplomacy, trade, rivalry, culture, and pantheon pressure.
- Add richer alignment, personality, hero-role, and landmark effects on settlement identity.

### 1.2 Resource Scarcity

- Balance depletion, contesting, corruption, recovery, productivity, and disruption across long simulations.
- Expand resources as clearer inputs for conflict, regional danger, settlement survival, and new betting opportunities.
- Add more resource history and threshold events when resource pressure becomes strategically important.

### 1.3 Region Systemic Changes

- Tune prosperity, chaos, danger, magic affinity, status, and divine resonance over longer runs.
- Add more region threshold events as magic, culture, civilization strategy, artifacts, weather, and pantheon pressure become stronger inputs.
- Keep region change explanations player-readable through event links, dashboard summaries, and history panels.

### 1.4 Hero Lifecycle

- Expand alignment, personality, quests, relationships, rivalries, and long-term reputation effects.
- Add richer hero-region and hero-settlement relationships beyond the current lifecycle baseline.
- Keep hero outcomes inspectable through hero cards, event history, bets, myths, and era legacy.

## Phase 2: Magic, Culture, and World Identity

### 2.1 Magic Discovery and Research

- Balance magic path discovery, progression rates, maturity, and durable world effects.
- Add more path variety and late-stage effects after the current five-path baseline is tuned.
- Keep magic discoveries tied to visible evidence, readable events, and betting hooks.

### 2.2 Cultural Evolution

- Balance local and inter-region cultural pressure after longer simulations.
- Deepen divine influence hooks into culture without making culture fully controllable.
- Expand culture effects on settlement specialization, hero roles, myths, diplomacy, and betting.

### 2.3 Dynamic Mythology

- Tune autonomous myth echo frequency, cooldowns, resonance, and bounded effects.
- Add more myth outcome variety tied to heroes, landmarks, disasters, magic discoveries, divine artifacts, weather scars, omens, pantheon interventions, and era transitions.
- Expand chronicles and mythology views if long-running worlds produce enough story density.

### 2.4 Civilization Behavior

- Deepen inter-region strategy for expansion, defense, trade, rivalry, research, and recovery.
- Tune civilization agenda scoring, decision cadence, and diplomacy effects across long runs.
- Keep civic behavior explainable through events, dashboard panels, region views, and betting factors.

## Phase 3: Era and Legacy Systems

### 3.1 Era-Ending Conditions

- Add fuller statistical end-of-era chronicles from actual events and world metrics.
- Tune era-pressure scoring so cataclysm, collapse, conquest, magical rupture, and divine war triggers feel earned.
- Keep era-ending pressure visible before rollover.

### 3.2 Reincarnation and Continuity

- Broaden descendant identity, lineage variety, and carry-forward logic.
- Add richer era-history views and references from the new era back to the previous one.
- Tune cross-era bets and legacy candidates so rollover preserves history without making the new era predetermined.

### 3.3 New Era Generation

- Add broader procedural variety for era-born regions, settlements, resources, heroes, landmarks, and magic rules.
- Add richer transformations for champions, divine artifacts, weather scars, omens, myths, and pantheon arcs during era rollover.
- Keep cross-era comparison tools readable as transition variety increases.

## Phase 4: Deeper Divine Gameplay

### 4.1 Mortal Champion System

- Tune champion quest and rivalry pacing after longer tick observation.
- Expand multi-champion relationships, rivalries, succession, lineage, and era-crossing legacy.
- Add higher-impact champion influence actions that remain indirect and event-driven.

### 4.2 Divine Artifacts

- Add more divine artifact archetypes beyond starter relics, with distinct powers, risks, and world-state hooks.
- Expand artifact transfer, theft, corruption, stabilization, empowerment, and ownership complications.
- Add more artifact chain variety so artifacts can outlive their intended purpose in surprising but readable ways.

### 4.3 Weather and Environmental Influence

- Tune climate nudge risk, backlash, delayed effects, and multi-step chain outcomes.
- Expand weather scars that affect resources, settlement survival, travel, conflict, mythology, and era legacy.
- Keep climate effects probabilistic and event-driven rather than direct city-builder controls.

## Phase 5: Advanced Systems

### 5.1 Time Manipulation

- Expand temporal omens only if previews and delayed follow-ups remain consistent with persistent world history.
- Explore limited local acceleration, prophecy pressure, or omen escalation before considering rollback or branching history.
- Avoid mechanics that undermine permanent events, bets, or chronicle replay.

### 5.2 AI Pantheon

- Tune non-player deity pressure, intervention cadence, counterplay costs, and relationship arcs.
- Add more alliance/rival arc variety and longer-running divine politics.
- Keep pantheon politics visible enough to feel like multiplayer-like pressure without requiring live players.

### 5.3 Replay and Timeline Tools

- Polish chronicle replay pacing, navigation, event context, and public presentation.
- Add deeper share governance, retention management, moderation/admin controls, and analytics if public sharing grows.
- Keep timeline filters, entity history, dashboard chronicles, and public replay pages aligned as event volume grows.

### 5.4 World Editor and Admin Tools

- Add bulk workflows, import/export authoring, rollback-safe operations, and stronger preview workflows.
- Keep admin tools separate from player-facing divine influence.
- Keep manual operations inspectable through audit events, audit browsing, and timeline filters.

## Implementation Principles

- Stabilize the current loop before adding new systems.
- Favor systems that create readable events and meaningful bets.
- Keep divine actions probabilistic; players should influence fate, not command it.
- Make every major simulation change inspectable through events, dashboard data, or entity history.
- Treat automated ticks, betting resolution, and event generation as the core technical spine.
- Prefer small vertical slices over broad model additions that are not visible in gameplay.

## Suggested Order

1. Magic progression balance and variety, mythology echo balance, civilization diplomacy balance/deeper strategy, pantheon arc balance and variety, and long-run balancing.
2. Broader new-era procedural variety, descendant variety/polish, and richer era-transition transformations for champions and divine tools.
3. Tune artifact/weather/omen chain balance, variety, and era-transition transformations now that bounded multi-step chains are implemented.
4. Extend replay/share tools and admin world editing with deeper replay polish, advanced share governance, bulk workflows, import/export authoring, and rollback-safe operations.
5. Tune champion pacing and expand deeper multi-champion relationships after longer tick observation.

## Remaining Success Metrics

- A guest can play a complete session without developer setup beyond running the app.
- At least one automated tick changes visible world state and records readable events.
- Production operators can monitor tick freshness and queue failures without opening the app UI.
- Bets resolve from real simulation state and explain their outcomes.
- Players can understand why the last tick changed important entities.
- Players can scan recent history by primary entity type from the dashboard.
- Players can open a readable event detail page from a tick, entity history, or event feed entry.
- Players can filter event timelines by region, hero, settlement, landmark, resource, and era.
- Every primary entity type has a player-visible reason to matter.
- Roadmap phases produce playable changes, not just backend-only data expansion.
