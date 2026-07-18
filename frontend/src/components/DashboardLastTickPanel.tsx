import React from 'react';
import { Link } from 'react-router-dom';
import type {
  GameStatus,
  GameTickBetResolution,
  GameTickEntitySummary,
  GameTickDivineToolConsequence,
  GameTickMythEcho,
  GameTickResult,
} from '../api/apiService';
import type { MagicDiscoveryProgression } from '../entities/magicDiscovery';
import type { PantheonIntervention, PantheonRelationshipArc } from '../entities/pantheon';
import type { CivilizationDiplomacy } from '../entities/civilization';

interface DashboardLastTickPanelProps {
  simulation?: GameStatus['simulation'];
}

interface TickSection {
  label: string;
  summary?: GameTickEntitySummary;
  accentClass: string;
}

interface ChangeLedgerEntry {
  id: string;
  category: string;
  title: string;
  summary: string;
  meta?: string;
  details?: string[];
  eventIds: string[];
  toneClass: string;
}

const formatTimestamp = (value?: string | null): string => {
  if (!value) {
    return 'No completed tick';
  }

  return new Date(value).toLocaleString();
};

const countText = (summary?: GameTickEntitySummary): string => {
  if (!summary) {
    return '0 processed';
  }

  return `${summary.processed ?? 0} processed, ${summary.changed ?? 0} changed, ${
    summary.events ?? 0
  } events`;
};

const errorMessages = (tick?: GameTickResult | null): string[] => {
  if (!tick) {
    return [];
  }

  const entityErrors = [tick.regions, tick.settlements, tick.resources, tick.heroes].flatMap(
    section =>
      (section?.errors ?? []).map(error =>
        typeof error === 'string' ? error : (error.message ?? 'Unknown tick error')
      )
  );
  const championErrors = (tick.champions?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown champion error')
  );
  const divineToolErrors = [
    ...(tick.divineTools?.errors ?? []),
    ...(tick.divineTools?.artifacts?.errors ?? []),
    ...(tick.divineTools?.weather?.errors ?? []),
    ...(tick.divineTools?.omens?.errors ?? []),
  ].map(error =>
    typeof error === 'string'
      ? error
      : `${error.tool ? `${error.tool}: ` : ''}${error.message ?? 'Unknown divine tool error'}`
  );
  const civilizationErrors = (tick.civilization?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown civilization error')
  );
  const pantheonErrors = (tick.pantheon?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown pantheon error')
  );
  const magicDiscoveryErrors = (tick.magicDiscovery?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown magic progression error')
  );
  const mythologyErrors = (tick.mythology?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown mythology error')
  );
  const betErrors = (tick.bets?.errors ?? []).map(error =>
    typeof error === 'string' ? error : (error.message ?? 'Unknown bet error')
  );

  return [
    ...(tick.errors ?? []),
    ...entityErrors,
    ...championErrors,
    ...divineToolErrors,
    ...civilizationErrors,
    ...pantheonErrors,
    ...magicDiscoveryErrors,
    ...mythologyErrors,
    ...betErrors,
  ];
};

const eventIdsForChange = (change: { eventId?: string | null; eventIds?: string[] }): string[] => {
  if (change.eventIds && change.eventIds.length > 0) {
    return change.eventIds;
  }

  return change.eventId ? [change.eventId] : [];
};

const eventIdsForDivineTool = (consequence: GameTickDivineToolConsequence): string[] => {
  return Array.from(
    new Set([consequence.sourceEventId, consequence.eventId].filter(Boolean) as string[])
  );
};

const EventLinks: React.FC<{ eventIds: string[] }> = ({ eventIds }) => {
  if (eventIds.length === 0) {
    return null;
  }

  return (
    <div className="text-gray-500 mt-1">
      {eventIds.map(eventId => (
        <Link
          key={eventId}
          to={`/events/${eventId}`}
          className="mr-2 text-blue-300 hover:text-blue-100"
        >
          Event {eventId}
        </Link>
      ))}
    </div>
  );
};

const uniqueEventIds = (eventIds: Array<string | null | undefined>): string[] =>
  Array.from(new Set(eventIds.filter(Boolean) as string[]));

const isLedgerRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

const formatLedgerKey = (key: string): string =>
  key
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/[_-]+/g, ' ')
    .replace(/\b\w/g, letter => letter.toUpperCase());

const formatLedgerValue = (value: unknown): string => {
  if (value === null || value === undefined || value === '') {
    return 'none';
  }

  if (typeof value === 'number') {
    return Number.isInteger(value)
      ? value.toLocaleString()
      : value.toLocaleString(undefined, { maximumFractionDigits: 1 });
  }

  if (typeof value === 'boolean') {
    return value ? 'yes' : 'no';
  }

  if (Array.isArray(value)) {
    return `${value.length} item${value.length === 1 ? '' : 's'}`;
  }

  if (isLedgerRecord(value)) {
    return 'changed';
  }

  return String(value);
};

const stableLedgerValue = (value: unknown): string => {
  if (!isLedgerRecord(value) && !Array.isArray(value)) {
    return String(value);
  }

  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
};

const formatLedgerDelta = (before: unknown, after: unknown): string => {
  if (typeof before === 'number' && typeof after === 'number') {
    const delta = after - before;
    const deltaText =
      delta === 0 ? 'no change' : `${delta > 0 ? '+' : ''}${formatLedgerValue(delta)}`;

    return `${formatLedgerValue(before)} -> ${formatLedgerValue(after)} (${deltaText})`;
  }

  return `${formatLedgerValue(before)} -> ${formatLedgerValue(after)}`;
};

const snapshotDetails = (before: unknown, after: unknown, limit = 4): string[] => {
  if (!isLedgerRecord(before) || !isLedgerRecord(after)) {
    return [];
  }

  const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));

  return keys
    .filter(key => stableLedgerValue(before[key]) !== stableLedgerValue(after[key]))
    .slice(0, limit)
    .map(key => `${formatLedgerKey(key)}: ${formatLedgerDelta(before[key], after[key])}`);
};

const groupedChangeDetails = (changes: unknown, limit = 4): string[] => {
  if (!isLedgerRecord(changes)) {
    return [];
  }

  const sections: Array<[string, string]> = [
    ['regions', 'Region'],
    ['settlements', 'Settlement'],
    ['resources', 'Resource'],
    ['heroes', 'Hero'],
    ['landmarks', 'Landmark'],
  ];
  const details: string[] = [];

  sections.forEach(([key, label]) => {
    if (details.length >= limit) {
      return;
    }

    const changesForSection = changes[key];
    if (!Array.isArray(changesForSection)) {
      return;
    }

    changesForSection.forEach(change => {
      if (details.length >= limit || !isLedgerRecord(change)) {
        return;
      }

      const name =
        typeof change.name === 'string' && change.name.trim() ? change.name.trim() : label;
      const deltas = snapshotDetails(change.before, change.after, 2);

      if (deltas.length > 0) {
        details.push(`${name}: ${deltas.join(', ')}`);
        return;
      }

      if (typeof change.summary === 'string' && change.summary.trim()) {
        details.push(`${label}: ${change.summary.trim()}`);
      }
    });
  });

  return details;
};

const compactDetails = (details: Array<string | null | undefined>, limit = 4): string[] =>
  details.filter((detail): detail is string => Boolean(detail?.trim())).slice(0, limit);

const buildChangeLedger = (tick?: GameTickResult | null): ChangeLedgerEntry[] => {
  if (!tick) {
    return [];
  }

  const entries: ChangeLedgerEntry[] = [];
  const addEntry = (entry: Omit<ChangeLedgerEntry, 'id'> & { id?: string | null }) => {
    if (!entry.summary && !entry.title) {
      return;
    }

    entries.push({
      id:
        entry.id ??
        `${entry.category}-${entries.length}-${entry.title}-${entry.eventIds.join('-')}`,
      category: entry.category,
      title: entry.title,
      summary: entry.summary,
      meta: entry.meta,
      details: entry.details ?? [],
      eventIds: entry.eventIds,
      toneClass: entry.toneClass,
    });
  };

  const addEntityChanges = (
    category: string,
    summary: GameTickEntitySummary | undefined,
    toneClass: string
  ) => {
    (summary?.changes ?? []).slice(0, 4).forEach(change => {
      addEntry({
        id: `${category}-${change.id ?? change.name ?? change.summary}`,
        category,
        title: change.name ?? change.summary ?? `${category} changed`,
        summary: change.summary ?? change.reason ?? 'State changed during the latest tick.',
        meta: change.reason,
        details: snapshotDetails(change.before, change.after),
        eventIds: eventIdsForChange(change),
        toneClass,
      });
    });
  };

  addEntityChanges('Region', tick.regions, 'text-cyan-300');
  addEntityChanges('Settlement', tick.settlements, 'text-emerald-300');
  addEntityChanges('Resource', tick.resources, 'text-amber-300');
  addEntityChanges('Hero', tick.heroes, 'text-violet-300');

  (tick.bets?.resolved ?? []).slice(0, 4).forEach(bet => {
    addEntry({
      id: `bet-${bet.id ?? bet.description}`,
      category: 'Bet',
      title: bet.description ?? 'Divine bet resolved',
      summary: bet.notes ?? `Bet resolved as ${bet.status ?? 'resolved'}.`,
      meta: bet.status ? `Result: ${bet.status}` : undefined,
      eventIds: uniqueEventIds([bet.eventId]),
      toneClass:
        bet.status === 'won'
          ? 'text-emerald-300'
          : bet.status === 'lost'
            ? 'text-red-300'
            : 'text-gray-300',
    });
  });

  (tick.champions?.outcomes ?? []).slice(0, 4).forEach(outcome => {
    addEntry({
      id: `champion-${outcome.id ?? outcome.eventId ?? outcome.summary}`,
      category: 'Champion',
      title: outcome.title ?? outcome.heroName ?? 'Champion outcome',
      summary: outcome.effectSummary ?? outcome.summary,
      meta: `${outcome.heroName} • ${outcome.focusLabel}`,
      eventIds: uniqueEventIds([outcome.eventId]),
      toneClass: 'text-cyan-300',
    });
  });

  (tick.divineTools?.consequences ?? []).slice(0, 5).forEach(consequence => {
    addEntry({
      id: `divine-tool-${consequence.id ?? consequence.eventId ?? consequence.summary}`,
      category: 'Divine Tool',
      title:
        consequence.artifactName ??
        consequence.regionName ??
        consequence.targetName ??
        consequence.title ??
        'Divine tool consequence',
      summary: consequence.summary ?? 'A divine tool consequence resolved.',
      meta: consequence.chainId
        ? `${consequence.tool ?? 'tool'} chain ${consequence.chainStep ?? '?'}/${
            consequence.chainMaxSteps ?? '?'
          }`
        : consequence.tool,
      eventIds: eventIdsForDivineTool(consequence),
      toneClass: 'text-amber-300',
    });
  });

  (tick.civilization?.decisions ?? []).slice(0, 3).forEach(decision => {
    addEntry({
      id: `civilization-${decision.id ?? decision.eventId ?? decision.summary}`,
      category: 'Civilization',
      title: `${decision.regionName} ${decision.behaviorLabel}`,
      summary: decision.effectSummary ?? decision.summary,
      meta: decision.priorityTier,
      details: groupedChangeDetails(decision.changes),
      eventIds: uniqueEventIds([decision.eventId]),
      toneClass: 'text-sky-300',
    });
  });

  (tick.civilization?.diplomacy ?? []).slice(0, 3).forEach(diplomacy => {
    addEntry({
      id: `diplomacy-${diplomacy.id ?? diplomacy.eventId ?? diplomacy.summary}`,
      category: 'Diplomacy',
      title: `${diplomacy.sourceRegionName} and ${diplomacy.targetRegionName}`,
      summary: diplomacy.effectSummary ?? diplomacy.summary,
      meta: diplomacy.kindLabel,
      details: groupedChangeDetails(diplomacy.changes),
      eventIds: eventIdsForChange(diplomacy),
      toneClass: diplomacy.kind === 'rivalry_front' ? 'text-red-300' : 'text-emerald-300',
    });
  });

  (tick.pantheon?.interventions ?? []).slice(0, 3).forEach(intervention => {
    addEntry({
      id: `pantheon-${intervention.id ?? intervention.eventId ?? intervention.summary}`,
      category: 'Pantheon',
      title: intervention.title ?? intervention.deityName,
      summary: intervention.summary,
      meta: `${intervention.deityName} • ${intervention.domain}`,
      details: groupedChangeDetails(intervention.changes),
      eventIds: uniqueEventIds([intervention.eventId]),
      toneClass: 'text-amber-300',
    });
  });

  (tick.pantheon?.arcs ?? []).slice(0, 3).forEach(arc => {
    addEntry({
      id: `pantheon-arc-${arc.id ?? arc.eventId ?? arc.summary}`,
      category: 'Pantheon Arc',
      title: `${arc.sourceName} to ${arc.targetName}`,
      summary: arc.summary,
      meta: `${arc.stance} • ${arc.stage}`,
      details: groupedChangeDetails(arc.changes),
      eventIds: arc.eventId ? [arc.eventId] : (arc.eventIds ?? []),
      toneClass: 'text-violet-300',
    });
  });

  (tick.magicDiscovery?.progressions ?? []).slice(0, 3).forEach(progression => {
    addEntry({
      id: `magic-${progression.id ?? progression.eventId ?? progression.summary}`,
      category: 'Magic',
      title: progression.pathLabel,
      summary: progression.summary,
      meta: `${progression.status} • ${progression.progress}% progress`,
      details: compactDetails([
        progression.progressGain
          ? `Progress: ${formatLedgerDelta(
              progression.progress - progression.progressGain,
              progression.progress
            )}`
          : null,
        progression.maturityGain
          ? `Maturity: ${formatLedgerDelta(
              progression.maturity - progression.maturityGain,
              progression.maturity
            )}`
          : null,
        ...groupedChangeDetails(progression.changes, 2),
      ]),
      eventIds: uniqueEventIds([progression.eventId]),
      toneClass: 'text-cyan-300',
    });
  });

  (tick.mythology?.echoes ?? []).slice(0, 3).forEach(echo => {
    addEntry({
      id: `myth-${echo.id ?? echo.eventId ?? echo.summary}`,
      category: 'Myth',
      title: echo.title ?? 'Myth echo',
      summary: echo.summary ?? 'A promoted myth echoed through the world.',
      meta: `${echo.resonance ?? 0}/100 resonance`,
      eventIds: uniqueEventIds([echo.eventId]),
      toneClass: 'text-fuchsia-300',
    });
  });

  if (tick.eraPressure?.eventId) {
    addEntry({
      id: `era-pressure-${tick.eraPressure.eventId}`,
      category: 'Era',
      title: tick.eraPressure.highestTrigger?.label ?? 'Era pressure changed',
      summary: tick.eraPressure.summary,
      meta: `${tick.eraPressure.pressureScore}/100 pressure`,
      eventIds: [tick.eraPressure.eventId],
      toneClass: 'text-orange-300',
    });
  }

  if (tick.eraTransition?.eventId || tick.eraTransition?.summary) {
    addEntry({
      id: `era-transition-${tick.eraTransition.eventId ?? tick.currentYear}`,
      category: 'Era Rollover',
      title: 'Era transition',
      summary: tick.eraTransition.summary,
      meta: tick.eraTransition.triggerReason,
      eventIds: uniqueEventIds([tick.eraTransition.eventId]),
      toneClass: 'text-red-300',
    });
  }

  return entries.slice(0, 14);
};

const DashboardLastTickPanel: React.FC<DashboardLastTickPanelProps> = ({ simulation }) => {
  const tick = simulation?.lastTickResult;
  const sections: TickSection[] = [
    { label: 'Regions', summary: tick?.regions, accentClass: 'text-cyan-300' },
    { label: 'Settlements', summary: tick?.settlements, accentClass: 'text-emerald-300' },
    { label: 'Resources', summary: tick?.resources, accentClass: 'text-amber-300' },
    { label: 'Heroes', summary: tick?.heroes, accentClass: 'text-violet-300' },
  ];
  const errors = errorMessages(tick);
  const resolvedBets: GameTickBetResolution[] = tick?.bets?.resolved ?? [];
  const divineToolConsequences: GameTickDivineToolConsequence[] =
    tick?.divineTools?.consequences ?? [];
  const divineToolChainCount = tick?.divineTools?.chains?.length ?? 0;
  const pantheonInterventions: PantheonIntervention[] = tick?.pantheon?.interventions ?? [];
  const pantheonArcs: PantheonRelationshipArc[] = tick?.pantheon?.arcs ?? [];
  const magicProgressions: MagicDiscoveryProgression[] = tick?.magicDiscovery?.progressions ?? [];
  const civicDiplomacy: CivilizationDiplomacy[] = tick?.civilization?.diplomacy ?? [];
  const mythEchoes: GameTickMythEcho[] = tick?.mythology?.echoes ?? [];
  const changeLedger = buildChangeLedger(tick);

  return (
    <section className="mb-8 bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between mb-5">
        <div>
          <h2 className="text-xl font-bold text-white">Last Tick</h2>
          <div className="text-sm text-gray-400">
            Year {tick?.previousYear ?? '?'} to {tick?.currentYear ?? '?'} •{' '}
            {formatTimestamp(simulation?.lastTickAt ?? tick?.completedAt)}
          </div>
        </div>
        <div className="grid grid-cols-2 gap-3 text-sm md:text-right">
          <div>
            <div className="text-gray-400">Simulation</div>
            <div className={simulation?.enabled ? 'text-green-300' : 'text-orange-300'}>
              {simulation?.enabled ? 'Enabled' : 'Paused'}
            </div>
          </div>
          <div>
            <div className="text-gray-400">Queue</div>
            <div className={simulation?.queue.available ? 'text-green-300' : 'text-gray-300'}>
              {simulation?.queue.available
                ? `${simulation.queue.jobs ?? 0} jobs, ${simulation.queue.failedJobs ?? 0} failed`
                : 'Unavailable'}
            </div>
          </div>
        </div>
      </div>

      {!tick ? (
        <div className="text-sm text-gray-400">No completed tick has been recorded yet.</div>
      ) : (
        <>
          <div className="mb-5 rounded border border-[#2f334d] bg-[#16161e] p-4">
            <div className="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
              <h3 className="text-sm font-semibold text-gray-200">Change Ledger</h3>
              <span className="text-xs text-gray-500">
                {changeLedger.length} highlighted change{changeLedger.length === 1 ? '' : 's'}
              </span>
            </div>
            {changeLedger.length === 0 ? (
              <div className="text-xs text-gray-500">
                No highlighted simulation changes were recorded for this tick.
              </div>
            ) : (
              <div className="grid grid-cols-1 gap-3 xl:grid-cols-2">
                {changeLedger.map(entry => (
                  <div key={entry.id} className="rounded bg-gray-900/60 px-3 py-2 text-xs">
                    <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <div className="flex flex-wrap items-center gap-2">
                          <span className={`font-semibold ${entry.toneClass}`}>
                            {entry.category}
                          </span>
                          {entry.meta && <span className="text-gray-600">{entry.meta}</span>}
                        </div>
                        <div className="mt-1 font-semibold text-gray-200">{entry.title}</div>
                      </div>
                    </div>
                    <div className="mt-1 text-gray-500">{entry.summary}</div>
                    {entry.details && entry.details.length > 0 && (
                      <div className="mt-2 flex flex-wrap gap-2" aria-label="Change details">
                        {entry.details.map(detail => (
                          <span
                            key={`${entry.id}-${detail}`}
                            className="max-w-full rounded bg-gray-800 px-2 py-1 text-[11px] leading-snug text-gray-300 break-words"
                          >
                            {detail}
                          </span>
                        ))}
                      </div>
                    )}
                    <EventLinks eventIds={entry.eventIds} />
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-4">
            {sections.map(section => (
              <div key={section.label} className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
                <div className="flex items-center justify-between gap-3 mb-2">
                  <h3 className="text-sm font-semibold text-gray-200">{section.label}</h3>
                  <span className={`text-sm font-bold ${section.accentClass}`}>
                    {section.summary?.changed ?? 0}
                  </span>
                </div>
                <div className="text-xs text-gray-400 mb-3">{countText(section.summary)}</div>
                <div className="space-y-2">
                  {(section.summary?.changes ?? []).slice(0, 3).map(change => (
                    <div
                      key={`${section.label}-${change.id}-${change.summary}`}
                      className="text-xs"
                    >
                      <div className="text-gray-200">{change.summary ?? change.name}</div>
                      {change.reason && <div className="text-gray-500 mt-1">{change.reason}</div>}
                      <EventLinks eventIds={eventIdsForChange(change)} />
                    </div>
                  ))}
                  {(section.summary?.changes ?? []).length === 0 && (
                    <div className="text-xs text-gray-500">No notable changes.</div>
                  )}
                </div>
              </div>
            ))}
          </div>

          <div className="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Resolved Bets</h3>
              <div className="text-xs text-gray-400 mb-3">
                {(tick.bets?.processed ?? 0).toLocaleString()} processed • {tick.bets?.won ?? 0} won
                • {tick.bets?.lost ?? 0} lost • {tick.bets?.expired ?? 0} expired
              </div>
              <div className="space-y-2">
                {resolvedBets.slice(0, 4).map(bet => (
                  <div key={bet.id} className="text-xs">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{bet.description}</span>
                      <span
                        className={
                          bet.status === 'won'
                            ? 'text-green-300'
                            : bet.status === 'lost'
                              ? 'text-red-300'
                              : 'text-gray-300'
                        }
                      >
                        {bet.status}
                      </span>
                    </div>
                    {bet.notes && <div className="text-gray-500 mt-1">{bet.notes}</div>}
                    <EventLinks eventIds={bet.eventId ? [bet.eventId] : []} />
                  </div>
                ))}
                {resolvedBets.length === 0 && (
                  <div className="text-xs text-gray-500">No bets resolved in this tick.</div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Favor and Failures</h3>
              <div className="text-xs text-gray-400 mb-3">
                Favor {tick.divineFavor?.before ?? 0} to {tick.divineFavor?.after ?? 0} • +
                {tick.divineFavor?.recovered ?? 0} recovered
              </div>
              <div className="space-y-2">
                {errors.slice(0, 4).map((error, index) => (
                  <div key={`${error}-${index}`} className="text-xs text-red-300">
                    {error}
                  </div>
                ))}
                {errors.length === 0 && (
                  <div className="text-xs text-gray-500">No failures recorded.</div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Champion Outcomes</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.champions?.processed ?? 0} processed • {tick.champions?.events ?? 0} events
              </div>
              <div className="space-y-2">
                {(tick.champions?.outcomes ?? []).slice(0, 4).map(outcome => (
                  <div key={outcome.id ?? outcome.eventId ?? outcome.summary} className="text-xs">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{outcome.heroName}</span>
                      <span className="font-semibold text-cyan-300">{outcome.focusLabel}</span>
                    </div>
                    <div className="mt-1 text-gray-500">{outcome.summary}</div>
                    <EventLinks eventIds={outcome.eventId ? [outcome.eventId] : []} />
                  </div>
                ))}
                {(tick.champions?.outcomes ?? []).length === 0 && (
                  <div className="text-xs text-gray-500">
                    No champion outcome resolved in this tick.
                  </div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Divine Tool Consequences</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.divineTools?.processed ?? 0} processed • {tick.divineTools?.events ?? 0}{' '}
                events • {divineToolChainCount} chains
              </div>
              <div className="space-y-2">
                {divineToolConsequences.slice(0, 4).map(consequence => (
                  <div
                    key={consequence.id ?? consequence.eventId ?? consequence.summary}
                    className="text-xs"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">
                        {consequence.artifactName ??
                          consequence.regionName ??
                          consequence.targetName ??
                          consequence.title ??
                          'Divine tool'}
                      </span>
                      <span className="font-semibold text-amber-300 capitalize">
                        {consequence.chainId
                          ? `${consequence.tool ?? 'tool'} chain ${consequence.chainStep ?? '?'}/${
                              consequence.chainMaxSteps ?? '?'
                            }`
                          : (consequence.tool ?? 'tool')}
                      </span>
                    </div>
                    <div className="mt-1 text-gray-500">{consequence.summary}</div>
                    {consequence.chainStatus && (
                      <div className="mt-1 text-gray-600">
                        Chain {consequence.chainStatus}
                        {consequence.nextYear ? ` • next check year ${consequence.nextYear}` : ''}
                      </div>
                    )}
                    <EventLinks eventIds={eventIdsForDivineTool(consequence)} />
                  </div>
                ))}
                {divineToolConsequences.length === 0 && (
                  <div className="text-xs text-gray-500">
                    No delayed artifact, weather, omen, or chain consequence resolved in this tick.
                  </div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Civilization</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.civilization?.processed ?? 0} processed • {tick.civilization?.events ?? 0}{' '}
                events
              </div>
              <div className="space-y-2">
                {(tick.civilization?.decisions ?? []).slice(0, 3).map(decision => (
                  <div
                    key={decision.id ?? decision.eventId ?? decision.summary}
                    className="text-xs"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{decision.regionName}</span>
                      <span className="font-semibold text-cyan-300">{decision.behaviorLabel}</span>
                    </div>
                    <div className="mt-1 text-gray-500">{decision.effectSummary}</div>
                    <EventLinks eventIds={decision.eventId ? [decision.eventId] : []} />
                  </div>
                ))}
                {(tick.civilization?.decisions ?? []).length === 0 && (
                  <div className="text-xs text-gray-500">
                    No civic agenda advanced in this tick.
                  </div>
                )}
                <div className="pt-2">
                  <div className="mb-2 text-xs font-semibold uppercase text-gray-500">
                    Civic Diplomacy
                  </div>
                  {civicDiplomacy.slice(0, 2).map(diplomacy => (
                    <div
                      key={diplomacy.id ?? diplomacy.eventId ?? diplomacy.pactKey}
                      className="text-xs"
                    >
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-gray-200">
                          {diplomacy.sourceRegionName} and {diplomacy.targetRegionName}
                        </span>
                        <span
                          className={`font-semibold ${
                            diplomacy.kind === 'rivalry_front' ? 'text-red-300' : 'text-emerald-300'
                          }`}
                        >
                          {diplomacy.kindLabel}
                        </span>
                      </div>
                      <div className="mt-1 text-gray-500">{diplomacy.effectSummary}</div>
                      <EventLinks eventIds={eventIdsForChange(diplomacy)} />
                    </div>
                  ))}
                  {civicDiplomacy.length === 0 && (
                    <div className="text-xs text-gray-500">
                      No trade compact or rivalry front advanced in this tick.
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Pantheon Interventions</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.pantheon?.processed ?? 0} processed • {tick.pantheon?.events ?? 0} events
              </div>
              <div className="space-y-2">
                {pantheonInterventions.slice(0, 3).map(intervention => (
                  <div
                    key={intervention.id ?? intervention.eventId ?? intervention.summary}
                    className="text-xs"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{intervention.deityName}</span>
                      <span className="font-semibold text-amber-300 capitalize">
                        {intervention.domain}
                      </span>
                    </div>
                    <div className="mt-1 text-gray-500">{intervention.summary}</div>
                    <EventLinks eventIds={intervention.eventId ? [intervention.eventId] : []} />
                  </div>
                ))}
                {pantheonInterventions.length === 0 && (
                  <div className="text-xs text-gray-500">
                    No AI pantheon intervention resolved in this tick.
                  </div>
                )}
                <div className="pt-2">
                  <div className="mb-2 text-xs font-semibold uppercase text-gray-500">
                    Pantheon Arcs
                  </div>
                  {pantheonArcs.slice(0, 2).map(arc => (
                    <div key={arc.id ?? arc.eventId ?? arc.summary} className="text-xs">
                      <div className="flex items-center justify-between gap-3">
                        <span className="text-gray-200">
                          {arc.sourceName} to {arc.targetName}
                        </span>
                        <span className="font-semibold text-violet-300 capitalize">
                          {arc.stance}
                        </span>
                      </div>
                      <div className="mt-1 text-gray-500">{arc.summary}</div>
                      <EventLinks eventIds={arc.eventId ? [arc.eventId] : (arc.eventIds ?? [])} />
                    </div>
                  ))}
                  {pantheonArcs.length === 0 && (
                    <div className="text-xs text-gray-500">
                      No alliance or rivalry arc advanced in this tick.
                    </div>
                  )}
                </div>
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Magic Progression</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.magicDiscovery?.processed ?? 0} processed • {tick.magicDiscovery?.events ?? 0}{' '}
                events
              </div>
              <div className="space-y-2">
                {magicProgressions.slice(0, 3).map(progression => (
                  <div
                    key={progression.id ?? progression.eventId ?? progression.summary}
                    className="text-xs"
                  >
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{progression.pathLabel}</span>
                      <span className="font-semibold text-cyan-300">
                        {progression.progress}% / {progression.maturity}
                      </span>
                    </div>
                    <div className="mt-1 text-gray-500">{progression.summary}</div>
                    <EventLinks eventIds={progression.eventId ? [progression.eventId] : []} />
                  </div>
                ))}
                {magicProgressions.length === 0 && (
                  <div className="text-xs text-gray-500">
                    No magic path progressed autonomously in this tick.
                  </div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Myth Echoes</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.mythology?.processed ?? 0} processed • {tick.mythology?.events ?? 0} events
              </div>
              <div className="space-y-2">
                {mythEchoes.slice(0, 3).map(echo => (
                  <div key={echo.id ?? echo.eventId ?? echo.summary} className="text-xs">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{echo.title ?? 'Myth echo'}</span>
                      <span className="font-semibold text-fuchsia-300">
                        {echo.resonance ?? 0}/100
                      </span>
                    </div>
                    <div className="mt-1 text-gray-500">{echo.summary}</div>
                    <div className="mt-1 text-gray-500">
                      {echo.affected?.regions ?? 0} regions • {echo.affected?.heroes ?? 0} heroes •{' '}
                      {echo.affected?.landmarks ?? 0} landmarks
                    </div>
                    <EventLinks eventIds={echo.eventId ? [echo.eventId] : []} />
                  </div>
                ))}
                {mythEchoes.length === 0 && (
                  <div className="text-xs text-gray-500">
                    No promoted myth echoed autonomously in this tick.
                  </div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Era Pressure</h3>
              {tick.eraPressure ? (
                <div className="space-y-2 text-xs">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-gray-400">Pressure</span>
                    <span className="font-bold text-amber-300">
                      {tick.eraPressure.pressureScore}/100
                    </span>
                  </div>
                  <div className="text-gray-300">{tick.eraPressure.summary}</div>
                  <div className="text-gray-500">
                    Primary risk: {tick.eraPressure.highestTrigger?.label ?? 'Unknown'}
                  </div>
                  <EventLinks
                    eventIds={tick.eraPressure.eventId ? [tick.eraPressure.eventId] : []}
                  />
                </div>
              ) : (
                <div className="text-xs text-gray-500">No era pressure snapshot recorded.</div>
              )}
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Era Legacy</h3>
              {tick.eraLegacy ? (
                <div className="space-y-2 text-xs">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-gray-400">Continuity</span>
                    <span className="font-bold text-sky-300">
                      {tick.eraLegacy.continuityScore}/100
                    </span>
                  </div>
                  <div className="text-gray-300">{tick.eraLegacy.summary}</div>
                  <div className="text-gray-500 capitalize">
                    {tick.eraLegacy.readinessLabel} - next era year {tick.eraLegacy.nextEraYear}
                  </div>
                </div>
              ) : (
                <div className="text-xs text-gray-500">No era legacy snapshot recorded.</div>
              )}
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Era Rollover</h3>
              {tick.eraTransition ? (
                <div className="space-y-2 text-xs">
                  <div className="flex items-center justify-between gap-3">
                    <span className="text-gray-400">Status</span>
                    <span
                      className={
                        tick.eraTransition.eligible
                          ? 'font-bold text-amber-300'
                          : 'font-bold text-sky-300'
                      }
                    >
                      {tick.eraTransition.eligible ? 'Ready' : 'Watching'}
                    </span>
                  </div>
                  <div className="text-gray-300">{tick.eraTransition.summary}</div>
                  <div className="text-gray-500">
                    Era {tick.eraTransition.transitionEra ?? tick.eraTransition.completedEra} to Era{' '}
                    {tick.eraTransition.nextEra}
                  </div>
                  {tick.eraTransition.historyEntry?.generated?.descendants &&
                    tick.eraTransition.historyEntry.generated.descendants.length > 0 && (
                      <div className="text-gray-500">
                        {tick.eraTransition.historyEntry.generated.descendants.length} descendant
                        {tick.eraTransition.historyEntry.generated.descendants.length === 1
                          ? ''
                          : 's'}{' '}
                        emerged
                      </div>
                    )}
                  <EventLinks
                    eventIds={tick.eraTransition.eventId ? [tick.eraTransition.eventId] : []}
                  />
                </div>
              ) : (
                <div className="text-xs text-gray-500">No era rollover snapshot recorded.</div>
              )}
            </div>
          </div>
        </>
      )}
    </section>
  );
};

export default DashboardLastTickPanel;
