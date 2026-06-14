# Mytherra

Mytherra is a web-based god simulation game about reading fate, spending divine power, and watching a shared fantasy world change over time.

You play as a minor deity. You do not issue direct orders to mortals. Instead, you observe regions, heroes, settlements, landmarks, and world events, then spend Divine Favor to tilt the odds. The game is built around prediction, subtle influence, and long-running consequences rather than direct control.

## Gameplay Snapshot

- **Role:** A watching deity with limited influence over a living world.
- **Core resource:** Divine Favor, spent on influence actions and divine bets.
- **Main tension:** Predict what the simulation will do, then decide whether to nudge it, wager on it, or let it unfold.
- **Session rhythm:** Read the event log, inspect the world, choose a target, spend favor, place bets, and return as the world advances.
- **World structure:** Regions contain settlements, landmarks, resources, heroes, and histories.

## Core Loop

1. **Enter the world** as a guest or through a WebHatchery account.
2. **Read recent events** to understand what changed in the current era.
3. **Inspect regions and heroes** for opportunity, danger, corruption, prosperity, magic, and hero growth.
4. **Spend Divine Favor** on influence actions when a region or hero is worth nudging.
5. **Place divine bets** on speculation events when you think fate is readable.
6. **Track outcomes** through the event log, dashboard, and active bet list.
7. **Adapt your strategy** as heroes age, settlements evolve, regions shift, and bets resolve.

## What Players Can Do

### Influence Regions

Regions are the strategic map layer. Each region tracks values such as prosperity, chaos, magic affinity, status, population, traits, climate, trade connections, danger, and divine resonance.

Current region actions include:

- **Bless Region:** Push a region toward prosperity and stability.
- **Corrupt Region:** Push a region toward chaos, volatility, and darker outcomes.
- **Guide Research:** Nudge a region's magical and scholarly development.

Influence costs depend on the target and its resistance. A region with high magic, high chaos, or unusual divine resonance may respond differently from a quiet, ordinary province.

### Influence Heroes

Heroes are autonomous mortals who gain levels, build feats, move between regions, age, and eventually die. They can be scholars, warriors, prophets, agents of change, or undecided figures still finding their role.

Current hero actions include:

- **Guide Hero:** Encourage a hero toward useful action.
- **Empower Hero:** Increase a hero's ability to survive and shape events.
- **Start Notable Event:** Push a living hero into a major story moment.
- **Revive Hero:** Spend favor to return a fallen hero to play.

Hero details include role, level, age, region, feats, life status, death reason, personality traits, and alignment data where available.

### Place Divine Bets

The Divine Observatory is the prediction layer. Speculation events present possible futures with odds, timeframes, minimum stakes, and potential payouts.

Betting currently supports outcomes such as:

- Settlement growth
- Landmark discovery
- Cultural shifts
- Hero and settlement bonds
- Hero location visits
- Settlement transformation
- Corruption spread

Each bet records its target, stake, confidence, timeframe, current odds, potential payout, placed year, and final resolution. Winning bets reward foresight; losing bets are part of the cost of reading fate badly.

## World Systems

### Regions

Regions are defined by prosperity, chaos, magic affinity, status, traits, climate, population, cultural influence, danger, and divine resonance. They are the main targets for map-level divine strategy.

### Settlements

Settlements belong to regions and track population, prosperity, defensibility, type, status, specializations, founded year, traits, and related events. Settlement evolution is one of the major ways the world visibly changes.

### Landmarks

Landmarks are temples, ruins, forests, mountains, rivers, monuments, dungeons, towers, battlefields, groves, and other places with magic, danger, status, traits, and event history.

### Resources

Resource nodes such as mines, quarries, forests, farmland, fishing grounds, and magical springs affect regional value and future simulation depth.

### Heroes

Heroes are the most readable individual agents in the world. Their levels, feats, movement, mortality, and alignment make them strong targets for both influence and bets.

### Events

The event log is the main narrative feed. It records world events by year and can be filtered by selected region or hero. The game story is not pre-written; it emerges from simulation updates, hero actions, and divine nudges.

### Divine Economy

Divine Favor is spent on influence and wagers. The dashboard tracks financial stats such as total favor wagered, active bets, wins, losses, and payout ratio.

## Main Screens

- **Events:** The default view and narrative feed for world history.
- **World Map:** Select regions, inspect regional details, and apply regional influence.
- **Heroes:** Select heroes, inspect their status, and apply heroic influence.
- **Betting:** Browse speculation events, place bets, review active bets, and inspect odds.
- **Dashboard:** Review era, year, hero distribution, regional status, population, active bets, and divine economy stats.

## Strategy Notes

- Do not spend favor just because it is available. Watch the event log first.
- Prosperous regions are better long-term bets, but chaotic regions create bigger swings.
- Heroes with rising levels or unusual feats are strong candidates for both guidance and speculation.
- Bet before you influence when you already believe an outcome is likely.
- Influence after betting when the target needs a push and the payout justifies the cost.
- A dead hero is not always finished; revival can preserve an important narrative thread.
- Region stats matter together. Prosperity, chaos, magic affinity, danger, and divine resonance all change how attractive a target is.

## Current Status

Playable foundations are in place:

- Guest and WebHatchery account entry
- World event log
- Region map and region detail tabs
- Hero list and hero details
- Divine influence UI and backend services
- Divine betting interface
- Dashboard statistics
- Settlements, landmarks, resources, buildings, and export APIs
- PHP backend with MySQL persistence

Still in active development:

- Production game-loop scheduling and monitoring
- Resource seeding and richer resource UI
- Era-ending cataclysms
- Reincarnation and legacy mechanics
- Richer magic discovery
- More complex settlement, culture, and civilization behavior
- Better betting context, history, and long-term consequences

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
```

## Repository Map

- `frontend/` - React, TypeScript, Vite, Tailwind UI for the game.
- `backend/` - PHP API, Eloquent models, game actions, services, auth, and persistence.
- `bruno/` - API request collection for manual backend testing.
- `ROADMAP.md` - Longer-term feature plan and implementation phases.

Part of the WebHatchery game collection.
