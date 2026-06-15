import React from 'react';
import { Link } from 'react-router-dom';
import type {
  GameStatus,
  GameTickBetResolution,
  GameTickEntitySummary,
  GameTickDivineToolConsequence,
  GameTickResult,
} from '../api/apiService';
import type { PantheonIntervention } from '../entities/pantheon';

interface DashboardLastTickPanelProps {
  simulation?: GameStatus['simulation'];
}

interface TickSection {
  label: string;
  summary?: GameTickEntitySummary;
  accentClass: string;
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
    ...betErrors,
  ];
};

const eventIdsForChange = (change: { eventId?: string; eventIds?: string[] }): string[] => {
  if (change.eventIds && change.eventIds.length > 0) {
    return change.eventIds;
  }

  return change.eventId ? [change.eventId] : [];
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
  const pantheonInterventions: PantheonIntervention[] = tick?.pantheon?.interventions ?? [];

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
              <h3 className="text-sm font-semibold text-gray-200 mb-2">
                Divine Tool Consequences
              </h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.divineTools?.processed ?? 0} processed • {tick.divineTools?.events ?? 0}{' '}
                events
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
                        {consequence.tool ?? 'tool'}
                      </span>
                    </div>
                    <div className="mt-1 text-gray-500">{consequence.summary}</div>
                    <EventLinks eventIds={consequence.eventId ? [consequence.eventId] : []} />
                  </div>
                ))}
                {divineToolConsequences.length === 0 && (
                  <div className="text-xs text-gray-500">
                    No delayed artifact, weather, or omen consequence resolved in this tick.
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
                  <div key={decision.id ?? decision.eventId ?? decision.summary} className="text-xs">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-gray-200">{decision.regionName}</span>
                      <span className="font-semibold text-cyan-300">{decision.behaviorLabel}</span>
                    </div>
                    <div className="mt-1 text-gray-500">{decision.effectSummary}</div>
                    <EventLinks eventIds={decision.eventId ? [decision.eventId] : []} />
                  </div>
                ))}
                {(tick.civilization?.decisions ?? []).length === 0 && (
                  <div className="text-xs text-gray-500">No civic agenda advanced in this tick.</div>
                )}
              </div>
            </div>

            <div className="bg-[#16161e] rounded p-4 border border-[#2f334d]">
              <h3 className="text-sm font-semibold text-gray-200 mb-2">Pantheon Interventions</h3>
              <div className="text-xs text-gray-400 mb-3">
                {tick.pantheon?.processed ?? 0} processed • {tick.pantheon?.events ?? 0} events
              </div>
              <div className="space-y-2">
                {pantheonInterventions.slice(0, 3).map(intervention => (
                  <div key={intervention.id ?? intervention.eventId ?? intervention.summary} className="text-xs">
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
                  <EventLinks eventIds={tick.eraPressure.eventId ? [tick.eraPressure.eventId] : []} />
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
                        tick.eraTransition.eligible ? 'font-bold text-amber-300' : 'font-bold text-sky-300'
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
                  <EventLinks eventIds={tick.eraTransition.eventId ? [tick.eraTransition.eventId] : []} />
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
