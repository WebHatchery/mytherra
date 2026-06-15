# Mytherra

Mytherra is a browser-based god simulation game about prophecy, wagers, and subtle divine pressure. You play as a minor deity watching a persistent fantasy world change through autonomous heroes, regions, settlements, resources, magic, myths, civilization agendas, and eras.

You do not command mortals directly. You read the world, spend Divine Favor, cultivate champions, create risky artifacts, shape weather, research magic, promote myths, and bet on what the simulation will do next.

## Gameplay Snapshot

- **Role:** A watching deity with limited power over a living world.
- **Core resource:** Divine Favor, spent on influence, research, champions, artifacts, omens, weather, myths, civilization nudges, and divine bets.
- **Main tension:** Decide when to observe, when to nudge, and when to wager on fate.
- **Session rhythm:** Read events, inspect entities, forecast pressure, spend favor, place bets, run or wait for ticks, then review the consequences.
- **World structure:** Regions contain settlements, landmarks, resources, heroes, histories, magical pressure, cultural drift, and era legacy signals.

## Core Loop

1. **Enter the world** as a guest or through a WebHatchery account.
2. **Read recent events** to understand what changed this year.
3. **Inspect the map, heroes, resources, myths, magic, civilization, and era pressure** for opportunities or risks.
4. **Spend Divine Favor** when a region, hero, champion, artifact, weather pattern, omen, myth, or civic agenda is worth influencing.
5. **Place divine bets** when the visible world state makes an outcome look likely.
6. **Advance the simulation** through ticks, then review generated events, resolved bets, champion outcomes, era pressure, and entity history.
7. **Adapt your strategy** as heroes age, champions build legacies, settlements evolve, resources fluctuate, magic emerges, and eras approach their breaking points.

## What Players Can Do

### Influence Regions

Regions are the strategic map layer. They track prosperity, chaos, danger, magic affinity, cultural influence, climate, population, traits, resources, settlements, landmarks, and divine resonance.

Current region actions include:

- **Bless Region:** Push a region toward prosperity and stability.
- **Corrupt Region:** Push a region toward chaos, volatility, and darker outcomes.
- **Guide Research:** Nudge a region's magical and scholarly development.

Influence costs depend on the target. Divine resonance, chaos, magic, and regional pressure can make the same action cheaper, harder, stronger, or riskier.

### Influence Heroes

Heroes are autonomous mortals who gain levels, build feats, move between regions, age, and eventually die. They can be guided, empowered, revived, or pushed into notable events, but they remain independent actors inside the simulation.

Hero views show role, level, age, region, feats, lifecycle state, mortality pressure, alignment, personality traits, region ties, nearby settlements, peer heroes, and direct timeline links.

### Cultivate Mortal Champions

Players can designate a small roster of mortal champions and cultivate their focus. Champions have rank, bond, quest progress, focus, event history, and latest outcomes.

Champion quests and rivalries can now resolve through world ticks. Their outcomes can mutate heroes, regions, settlements, and landmarks, then feed event history, betting hooks, mythology candidates, and era-legacy signals.

### Place Divine Bets

The Divine Observatory is the prediction layer. Speculation events present possible futures with visible target state, odds factors, confidence, timeframes, stakes, risk bands, and potential payouts.

Betting currently draws from real simulation state, including:

- Settlement growth and transformation
- Landmark discovery and danger
- Cultural shifts
- Region prosperity and danger changes
- Resource disruption
- Hero milestones and mortality
- Champion quest or rivalry outcomes
- Emerging magic paths becoming known

Resolved bets explain why they won, lost, or expired, and link back into event history.

### Shape Artifacts, Weather, Omens, Magic, Myths, Civilization, And Pantheon Pressure

Mytherra now has several divine tools beyond direct region and hero influence:

- **Artifacts:** Create named divine artifacts, empower them, stabilize them, transfer them to heroes, unbind them, and inspect artifact history.
- **Weather:** Nudge regional weather to affect danger, resources, settlements, travel pressure, and conflict pressure.
- **Omens:** Spend favor on world, region, or hero forecasts without mutating the world state.
- **Magic:** Research hidden, emerging, and known magic paths through regions, heroes, and landmarks, then wager on emerging paths becoming known.
- **Myths:** Promote major events into durable myths that shape regional identity, hero reputation, landmark memory, and future pressure.
- **Civilization:** Inspect and advance regional agendas such as expansion, defense, trade, rivalry, research, and recovery.
- **Pantheon:** Watch non-player deities pursue domains such as prosperity, strife, secrets, and entropy, then appease or challenge their pressure with Divine Favor.

### Watch Eras Rise And End

Era pressure tracks long-run world risk from collapse, conquest, cataclysm, magical rupture, divine war, and other ending conditions. The game surfaces continuity forecasts showing which heroes, places, scars, myths, and bets may matter after an era boundary.

Era transitions can transform the existing world, create new era-born foundations, preserve selected legacies, expire or carry bets, and record comparison snapshots for the dashboard.

## Main Screens

- **Events:** Timeline feed, event details, entity filters, and era filters.
- **World Map:** Region selection, region details, influence, resources, history, settlements, landmarks, and pressure signals.
- **Heroes:** Hero list, hero lifecycle, direct timelines, influence actions, and champion controls.
- **Artifacts:** Divine artifact creation, empowerment, stabilization, transfer, unbinding, and history.
- **Weather:** Regional weather nudges and effect history.
- **Omens:** Temporal forecasts for world, region, and hero targets.
- **Magic:** Research paths, evidence signals, discovered systems, magic history, and discovery betting hooks.
- **Myths:** Candidate legends, promoted myths, source events, and world effects.
- **Civilization:** Regional agenda scores, civic decisions, recent behavior, and linked events.
- **Pantheon:** AI deity goals, domains, rivalries, pressure targets, counterplay, and recent interventions.
- **Betting:** Speculation events, odds factors, portfolio summary, active bets, and resolved bets.
- **Eras:** Era pressure, legacy continuity, rollover readiness, era history, and comparison data.
- **Dashboard:** Current status, last tick results, champion outcomes, resolved bets, era panels, chronicles, statistics, full world export, and chronicle share export.
- **Admin World Editor:** Admin-only creation and editing for regions, settlements, landmarks, resources, and heroes.

## Strategy Notes

- Read the latest events before spending favor. A good nudge starts with knowing what the world is already doing.
- Bet before influencing when the visible odds already favor your read.
- Influence after betting only when the payout justifies the favor cost.
- Champions are long-term investments. Their outcomes can become myths, betting targets, and era-legacy material.
- Omens do not change the world, but they can help decide where to spend favor next.
- Artifacts and weather create stronger direct pressure, but their risks are easier to misread.
- Pantheon pressure can help or complicate your plans; you can inspect political escalation, bet on direct interventions, and appease or challenge a deity to suppress near-term pressure.
- Prosperity, chaos, danger, magic affinity, culture, resources, hero presence, landmarks, civilization agendas, and pantheon actors matter together.
- Era pressure changes what "winning" means. Sometimes preserving a legacy matters more than stabilizing the current year.

## Current Status

Playable foundations are in place:

- Guest and WebHatchery account entry
- PHP backend with MySQL persistence
- Protected gameplay API routes
- Event timeline and event detail pages
- Region map, region tabs, resources, settlements, landmarks, and scoped history
- Hero lifecycle, hero influence, direct hero timelines, and mortal champions
- Divine betting, odds explanations, payout previews, portfolio summary, and real tick resolution
- Artifacts, weather, temporal omens, magic discovery, mythology, civilization behavior, AI pantheon pressure/politics, and era systems
- Dashboard last-tick inspection, champion outcomes, pantheon interventions, resolved bets, entity chronicles, era panels, chronicle share export, and export/status surfaces
- Admin-only world editing for primary simulation entities
- Production game-loop commands and health monitoring

Active development now focuses on richer magic/myth/civilization evolution, richer pantheon alliance/rival arcs, broader new-era variety, descendant identity, multi-step divine-tool consequence chains, interactive replay/share presentation, safer bulk admin workflows, and long-run simulation balance.

## Running Locally

### Requirements

- Node.js and npm
- PHP 8.1+
- Composer
- MySQL

### Backend

```bash
cd backend
composer install
copy .env.example .env
php scripts/initializeDatabase.php
composer start
```

The backend starts at `http://localhost:5002` by default.

### Frontend

Create or update `frontend/.env` with:

```bash
VITE_API_BASE_URL=http://localhost:5002/api
VITE_WEB_HATCHERY_SIGNUP_URL=http://127.0.0.1/auth/register
```

Then run:

```bash
cd frontend
npm install
npm run dev
```

Vite serves the frontend at `http://localhost:5173` by default.

## Useful Commands

```bash
# Root smoke tests
npm run test:run

# Frontend checks
cd frontend
npm run lint
npm run type-check
npm run test:run
npm run build

# Backend tests
cd backend
composer test

# Run one simulation tick
composer game:tick

# Check tick/runtime health
composer game:health
```

## Repository Map

- `frontend/` - React, TypeScript, Vite, and Tailwind UI for the game.
- `backend/` - PHP API, actions, services, models, auth, persistence, and simulation runtime.
- `bruno/` - API request collection for manual backend testing.
- `ROADMAP.md` - Current gameplay-depth roadmap.

Part of the WebHatchery game collection.
