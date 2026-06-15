import React from 'react';
import { Link } from 'react-router-dom';
import type {
  EraLegacyBet,
  EraLegacyBloodline,
  EraLegacyHero,
  EraLegacyLandmark,
  EraLegacyMyth,
  EraLegacyScar,
  EraLegacySummary,
} from '../api/apiService';

interface DashboardEraLegacyPanelProps {
  eraLegacy?: EraLegacySummary | null;
}

const tierTone = (tier?: string): string => {
  switch (tier) {
    case 'mythic':
      return 'text-fuchsia-200 bg-fuchsia-950/30 border-fuchsia-500/60';
    case 'strong':
      return 'text-sky-200 bg-sky-950/30 border-sky-500/50';
    case 'forming':
      return 'text-amber-200 bg-amber-950/30 border-amber-500/50';
    default:
      return 'text-gray-200 bg-[#16161e] border-[#2f334d]';
  }
};

const labelize = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ');
};

const timelinePath = (params: Record<string, string | undefined | null>): string | null => {
  const entry = Object.entries(params).find(([, value]) => Boolean(value));
  if (!entry) return null;

  const [key, value] = entry;
  return `/events?${key}=${encodeURIComponent(String(value))}`;
};

const TimelineLink: React.FC<{ to: string | null; label?: string }> = ({ to, label = 'Timeline' }) => {
  if (!to) return null;

  return (
    <Link to={to} className="text-xs text-blue-300 hover:text-blue-100">
      {label}
    </Link>
  );
};

const Row: React.FC<{
  title: string;
  meta: string;
  reason: string;
  link?: string | null;
}> = ({ title, meta, reason, link }) => (
  <div className="border-t border-[#2f334d] py-3 first:border-t-0 first:pt-0 last:pb-0">
    <div className="flex items-start justify-between gap-3">
      <div>
        <div className="text-sm font-semibold text-gray-100">{title}</div>
        <div className="mt-1 text-xs text-gray-500 capitalize">{meta}</div>
      </div>
      <TimelineLink to={link ?? null} />
    </div>
    <div className="mt-2 text-xs text-gray-400">{reason}</div>
  </div>
);

const heroPath = (hero: EraLegacyHero): string | null =>
  timelinePath({ heroId: hero.heroId, regionId: hero.regionId });

const bloodlinePath = (bloodline: EraLegacyBloodline): string | null =>
  timelinePath({
    heroId: bloodline.sourceHeroId,
    settlementId: bloodline.settlementId,
    regionId: bloodline.regionId,
  });

const landmarkPath = (landmark: EraLegacyLandmark): string | null =>
  timelinePath({ landmarkId: landmark.landmarkId, regionId: landmark.regionId });

const scarPath = (scar: EraLegacyScar): string | null =>
  timelinePath({
    settlementId: scar.settlementId,
    landmarkId: scar.landmarkId,
    resourceId: scar.resourceId,
    regionId: scar.regionId,
  });

const mythPath = (myth: EraLegacyMyth): string => `/events/${myth.eventId}`;

const DashboardEraLegacyPanel: React.FC<DashboardEraLegacyPanelProps> = ({ eraLegacy }) => {
  if (!eraLegacy) {
    return null;
  }

  const filledSegments = Math.max(0, Math.min(10, Math.ceil(eraLegacy.continuityScore / 10)));
  const heroes = eraLegacy.heroLegacies.slice(0, 3);
  const bloodlines = eraLegacy.bloodlineSeeds.slice(0, 2);
  const landmarks = eraLegacy.landmarkLegacies.slice(0, 3);
  const scars = eraLegacy.worldScars.slice(0, 4);
  const myths = eraLegacy.carriedMyths.slice(0, 3);
  const bets = eraLegacy.eraSpanningBets.slice(0, 3);

  return (
    <section className="mb-8 bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Era Legacy</h2>
          <div className="mt-1 text-sm text-gray-400">
            Era {eraLegacy.currentEra} ends in year {eraLegacy.eraEndYear}; next era begins year{' '}
            {eraLegacy.nextEraYear}
          </div>
        </div>
        <div className={`min-w-48 rounded border px-4 py-3 ${tierTone(eraLegacy.readinessTier)}`}>
          <div className="text-xs uppercase text-gray-400">Continuity</div>
          <div className="text-2xl font-bold">{eraLegacy.continuityScore}/100</div>
          <div className="text-sm capitalize">{eraLegacy.readinessLabel}</div>
        </div>
      </div>

      <div className="mt-5">
        <div className="grid grid-cols-10 gap-1" aria-hidden="true">
          {Array.from({ length: 10 }).map((_, index) => (
            <div
              key={`era-legacy-segment-${index}`}
              className={`h-2 rounded ${index < filledSegments ? 'bg-sky-400' : 'bg-[#10111a]'}`}
            />
          ))}
        </div>
        <p className="mt-3 text-sm text-gray-300">{eraLegacy.summary}</p>
      </div>

      <div className="mt-5 grid grid-cols-2 gap-3 text-sm md:grid-cols-6">
        <div>
          <div className="text-gray-500">Heroes</div>
          <div className="text-lg font-bold text-sky-300">{eraLegacy.heroLegacies.length}</div>
        </div>
        <div>
          <div className="text-gray-500">Bloodlines</div>
          <div className="text-lg font-bold text-emerald-300">{eraLegacy.bloodlineSeeds.length}</div>
        </div>
        <div>
          <div className="text-gray-500">Landmarks</div>
          <div className="text-lg font-bold text-violet-300">{eraLegacy.landmarkLegacies.length}</div>
        </div>
        <div>
          <div className="text-gray-500">Scars</div>
          <div className="text-lg font-bold text-red-300">{eraLegacy.worldScars.length}</div>
        </div>
        <div>
          <div className="text-gray-500">Myths</div>
          <div className="text-lg font-bold text-amber-300">{eraLegacy.carriedMyths.length}</div>
        </div>
        <div>
          <div className="text-gray-500">Bets</div>
          <div className="text-lg font-bold text-fuchsia-300">{eraLegacy.eraSpanningBets.length}</div>
        </div>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Hero Continuity</h3>
          {heroes.map(hero => (
            <Row
              key={hero.heroId}
              title={hero.name}
              meta={`${labelize(hero.legacyType)} - ${hero.strength}`}
              reason={hero.reason}
              link={heroPath(hero)}
            />
          ))}
          {bloodlines.map(bloodline => (
            <Row
              key={bloodline.id}
              title={bloodline.name}
              meta={`${labelize(bloodline.lineageType)} - ${bloodline.strength}`}
              reason={bloodline.reason}
              link={bloodlinePath(bloodline)}
            />
          ))}
          {heroes.length === 0 && bloodlines.length === 0 && (
            <div className="text-xs text-gray-500">No durable hero continuity is visible yet.</div>
          )}
        </div>

        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Places and Scars</h3>
          {landmarks.map(landmark => (
            <Row
              key={landmark.landmarkId}
              title={landmark.name}
              meta={`${labelize(landmark.legacyType)} - ${landmark.strength}`}
              reason={landmark.reason}
              link={landmarkPath(landmark)}
            />
          ))}
          {scars.map(scar => (
            <Row
              key={scar.id}
              title={scar.targetName}
              meta={`${labelize(scar.scarType)} - ${scar.severity}`}
              reason={scar.reason}
              link={scarPath(scar)}
            />
          ))}
          {landmarks.length === 0 && scars.length === 0 && (
            <div className="text-xs text-gray-500">No enduring places or scars are visible yet.</div>
          )}
        </div>

        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Myths and Wagers</h3>
          {myths.map(myth => (
            <Row
              key={myth.eventId}
              title={myth.title}
              meta={`${labelize(myth.mythType)} - year ${myth.year ?? '?'}`}
              reason={myth.reason}
              link={mythPath(myth)}
            />
          ))}
          {bets.map((bet: EraLegacyBet) => (
            <Row
              key={bet.betId}
              title={bet.description}
              meta={`${labelize(bet.betType)} - expires year ${bet.expiresYear}`}
              reason={bet.reason}
            />
          ))}
          {myths.length === 0 && bets.length === 0 && (
            <div className="text-xs text-gray-500">No carried myths or era-spanning wagers are visible yet.</div>
          )}
        </div>
      </div>
    </section>
  );
};

export default DashboardEraLegacyPanel;
