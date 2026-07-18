import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const __dirname = dirname(fileURLToPath(import.meta.url));
const src = (...parts) => resolve(__dirname, '..', 'src', ...parts);

describe('frontend gameplay smoke wiring', () => {
  it('registers the primary protected game routes', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');

    for (const route of [
      '"/"',
      '"/dashboard"',
      '"/events"',
      '"/events/:id"',
      '"/world-map"',
      '"/heroes"',
      '"/artifacts"',
      '"/weather"',
      '"/omens"',
      '"/magic"',
      '"/myths"',
      '"/civilization"',
      '"/pantheon"',
      '"/betting"',
      '"/eras"',
      '"/admin/world-editor"',
    ]) {
      expect(appSource).toContain(`path=${route}`);
    }
  });

  it('offers guest entry from the protected route gate', () => {
    const protectedRouteSource = readFileSync(src('components', 'ProtectedRoute.tsx'), 'utf8');

    expect(protectedRouteSource).toContain('Continue as Guest');
    expect(protectedRouteSource).toContain('Login with WebHatchery');
    expect(protectedRouteSource).toContain('continueAsGuest');
  });

  it('keeps gameplay API helpers wired to backend routes', () => {
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');

    for (const endpoint of [
      'regions',
      'heroes',
      'artifacts',
      '`artifacts/${artifactId}/empower`',
      '`artifacts/${artifactId}/transfer`',
      '`artifacts/${artifactId}/stabilize`',
      'weather',
      'weather/nudge',
      'omens',
      'magic',
      'magic/research',
      'myths',
      'myths/promote',
      'civilization',
      'civilization/advance',
      'pantheon',
      'champions',
      'admin/world-editor',
      '`heroes/${heroId}/champion`',
      '`heroes/${heroId}/champion/cultivate`',
      '`events?${params.toString()}`',
      '`events/${id}`',
      'settlements',
      'landmarks',
      'resource-nodes',
      '`history/summary${suffix}`',
      'bets',
      'bets/summary',
      'speculation-events',
      'betting-odds',
      'influence/region/${payload.entityId}',
      'influence/hero/${payload.entityId}',
    ]) {
      expect(apiSource).toContain(endpoint);
    }
  });

  it('keeps dashboard statistics and export wired', () => {
    const dashboardSource = readFileSync(src('pages', 'Dashboard.tsx'), 'utf8');
    const eraPanelSource = readFileSync(src('components', 'DashboardEraPressurePanel.tsx'), 'utf8');
    const legacyPanelSource = readFileSync(
      src('components', 'DashboardEraLegacyPanel.tsx'),
      'utf8'
    );
    const transitionPanelSource = readFileSync(
      src('components', 'DashboardEraTransitionPanel.tsx'),
      'utf8'
    );
    const comparisonPanelSource = readFileSync(
      src('components', 'DashboardEraComparisonPanel.tsx'),
      'utf8'
    );
    const replayPanelSource = readFileSync(
      src('components', 'DashboardChronicleReplayPanel.tsx'),
      'utf8'
    );
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const sharePageSource = readFileSync(src('pages', 'ChronicleSharePage.tsx'), 'utf8');
    const headerSource = readFileSync(src('components', 'Header.tsx'), 'utf8');
    const lastTickSource = readFileSync(src('components', 'DashboardLastTickPanel.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');

    expect(dashboardSource).toContain('statisticsService.getSummary()');
    expect(dashboardSource).toContain('statisticsService.getHeroStats()');
    expect(dashboardSource).toContain('statisticsService.getRegionStats()');
    expect(dashboardSource).toContain('statisticsService.getFinancialStats()');
    expect(dashboardSource).toContain('dashboardCurrentYear');
    expect(dashboardSource).toContain('gameStatus?.currentYear ?? summary?.currentYear');
    expect(dashboardSource).toContain("downloadExport('/export/full'");
    expect(dashboardSource).toContain("'/export/chronicle-share'");
    expect(dashboardSource).toContain('publishChronicleShare({ limit: 40 })');
    expect(dashboardSource).toContain('getChronicleShareManagement()');
    expect(dashboardSource).toContain('revokeChronicleShare');
    expect(dashboardSource).toContain('Create Share Page');
    expect(dashboardSource).toContain('Chronicle Share Links');
    expect(dashboardSource).toContain('shareStatusLabel');
    expect(dashboardSource).toContain('Active public link');
    expect(dashboardSource).toContain('Policy:');
    expect(dashboardSource).toContain('Revoke');
    expect(dashboardSource).toContain('getChronicleReplay({ limit: 16 })');
    expect(dashboardSource).toContain('Export Chronicle');
    expect(dashboardSource).toContain('mytherra-chronicle-share.json');
    expect(dashboardSource).toContain('DashboardEraPressurePanel');
    expect(dashboardSource).toContain('DashboardEraLegacyPanel');
    expect(dashboardSource).toContain('DashboardEraTransitionPanel');
    expect(dashboardSource).toContain('DashboardEraComparisonPanel');
    expect(dashboardSource).toContain('DashboardCivilizationPanel');
    expect(dashboardSource).toContain('DashboardPantheonPanel');
    expect(dashboardSource).toContain('DashboardChronicleReplayPanel');
    expect(apiSource).toContain('ChronicleReplayResponse');
    expect(apiSource).toContain('ChronicleSharePackage');
    expect(apiSource).toContain('ChronicleShareGovernance');
    expect(apiSource).toContain('PublishedChronicleShareResponse');
    expect(apiSource).toContain('ChronicleShareManagementResponse');
    expect(apiSource).toContain('ChronicleShareRevokeResponse');
    expect(apiSource).toContain('publishChronicleShare');
    expect(apiSource).toContain('getChronicleShareManagement');
    expect(apiSource).toContain('revokeChronicleShare');
    expect(apiSource).toContain('getPublicChronicleShare');
    expect(apiSource).toContain('getChronicleReplay');
    expect(apiSource).toContain('export/chronicle-share/public');
    expect(apiSource).toContain('public/chronicle-share');
    expect(apiSource).toContain('export/chronicle-replay');
    expect(appSource).toContain('/chronicle-share/:shareId');
    expect(appSource).toContain('ChronicleSharePage');
    expect(sharePageSource).toContain('Shared Chronicle');
    expect(sharePageSource).toContain('Share Policy');
    expect(sharePageSource).toContain('Betting Highlights');
    expect(sharePageSource).toContain('getPublicChronicleShare');
    expect(sharePageSource).toContain('Public Chronicle Replay');
    expect(sharePageSource).toContain('setActiveReplayIndex');
    expect(sharePageSource).toContain('Running Context');
    expect(sharePageSource).toContain('Replay Themes');
    expect(replayPanelSource).toContain('Chronicle Replay');
    expect(replayPanelSource).toContain('setActiveIndex');
    expect(replayPanelSource).toContain('Open event');
    expect(headerSource).toContain('Simulation');
    expect(headerSource).toContain('Last Tick:');
    expect(headerSource).toContain('lastTickResult');
    expect(headerSource).toContain('simulation.queue.available');
    expect(eraPanelSource).toContain('Era Pressure');
    expect(eraPanelSource).toContain('eraPressure.triggers');
    expect(eraPanelSource).toContain('Timeline');
    expect(legacyPanelSource).toContain('Era Legacy');
    expect(legacyPanelSource).toContain('heroLegacies');
    expect(legacyPanelSource).toContain('eraSpanningBets');
    expect(transitionPanelSource).toContain('Era Rollover');
    expect(transitionPanelSource).toContain('transitionEra');
    expect(transitionPanelSource).toContain('Era History');
    expect(transitionPanelSource).toContain('New foundations');
    expect(comparisonPanelSource).toContain('Era Comparison');
    expect(comparisonPanelSource).toContain('currentSnapshot');
    expect(comparisonPanelSource).toContain('No completed era comparison has been recorded.');
    expect(lastTickSource).toContain('tick.eraPressure');
    expect(lastTickSource).toContain('tick.eraLegacy');
    expect(lastTickSource).toContain('tick.eraTransition');
    expect(lastTickSource).toContain('descendant');
    expect(lastTickSource).toContain('tick.civilization');
    expect(lastTickSource).toContain('tick.pantheon');
    expect(lastTickSource).toContain('tick.magicDiscovery');
    expect(lastTickSource).toContain('tick.mythology');
    expect(lastTickSource).toContain('tick.champions');
    expect(lastTickSource).toContain('tick.divineTools');
    expect(lastTickSource).toContain('buildChangeLedger');
    expect(lastTickSource).toContain('snapshotDetails');
    expect(lastTickSource).toContain('formatLedgerDelta');
    expect(lastTickSource).toContain('groupedChangeDetails');
    expect(lastTickSource).toContain('Change Ledger');
    expect(lastTickSource).toContain('Change details');
    expect(lastTickSource).toContain('highlighted change');
    expect(lastTickSource).toContain('Champion Outcomes');
    expect(lastTickSource).toContain('Divine Tool Consequences');
    expect(lastTickSource).toContain('divineToolChainCount');
    expect(lastTickSource).toContain('chainStatus');
    expect(lastTickSource).toContain('Pantheon Interventions');
    expect(lastTickSource).toContain('Pantheon Arcs');
    expect(lastTickSource).toContain('Magic Progression');
    expect(lastTickSource).toContain('Myth Echoes');
    expect(apiSource).toContain('EraPressureSummary');
    expect(apiSource).toContain('EraLegacySummary');
    expect(apiSource).toContain('EraTransitionSummary');
    expect(apiSource).toContain('EraComparisonSummary');
    expect(apiSource).toContain('EraGeneratedContent');
    expect(apiSource).toContain('descendants?: EraGeneratedEntity[]');
    expect(apiSource).toContain('sourceHeroId');
    expect(apiSource).toContain('CivilizationStatusResponse');
    expect(apiSource).toContain('PantheonStatusResponse');
    expect(apiSource).toContain('PantheonTickSummary');
    expect(apiSource).toContain('MagicDiscoveryTickSummary');
    expect(apiSource).toContain('GameTickMythologySummary');
    expect(apiSource).toContain('GameTickDivineToolsSummary');
    expect(apiSource).toContain('chains?: GameTickDivineToolConsequence[]');
  });

  it('keeps admin world editor separate and wired to admin routes', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const pageSource = readFileSync(src('pages', 'AdminWorldEditorPage.tsx'), 'utf8');
    const eventsSource = readFileSync(src('pages', 'EventsPage.tsx'), 'utf8');

    expect(appSource).toContain('AdminWorldEditorPage');
    expect(navSource).toContain("path: '/admin/world-editor'");
    expect(navSource).toContain('isAdmin()');
    expect(apiSource).toContain('getAdminWorldEditor');
    expect(apiSource).toContain('createAdminWorldEntity');
    expect(apiSource).toContain('updateAdminWorldEntity');
    expect(apiSource).toContain('previewAdminWorldEntity');
    expect(apiSource).toContain('AdminWorldEditorPreviewResponse');
    expect(apiSource).toContain('admin/world-editor/${encodeURIComponent(entityType)}/preview');
    expect(apiSource).toContain('AdminWorldEditorAuditEntry');
    expect(apiSource).toContain('auditLog: AdminWorldEditorAuditEntry[]');
    expect(apiSource).toContain('putData');
    expect(pageSource).toContain('World Editor');
    expect(pageSource).toContain('Preview Compatibility');
    expect(pageSource).toContain('Compatibility Preview');
    expect(pageSource).toContain('previewRiskClass');
    expect(pageSource).toContain('Audit Log');
    expect(pageSource).toContain('All Admin Edits');
    expect(pageSource).toContain('auditEntityLabel');
    expect(pageSource).toContain('Admin Access Required');
    expect(pageSource).toContain('Create ${ENTITY_SINGULAR[selectedType]}');
    expect(pageSource).toContain('Save ${ENTITY_SINGULAR[selectedType]}');
    expect(pageSource).toContain('ENTITY_TYPES');
    expect(eventsSource).toContain('admin_world_edit');
  });

  it('keeps the era chronicle page wired to status data', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const erasSource = readFileSync(src('pages', 'ErasPage.tsx'), 'utf8');

    expect(appSource).toContain('ErasPage');
    expect(navSource).toContain("path: '/eras'");
    expect(navSource).toContain("'eras'");
    expect(erasSource).toContain('Era Chronicle');
    expect(erasSource).toContain('selectedView');
    expect(erasSource).toContain('getGameStatus');
    expect(erasSource).toContain('transitionDelta');
    expect(erasSource).toContain('New Era Foundations');
    expect(erasSource).toContain('Descendants');
    expect(erasSource).toContain('Lineage:');
    expect(erasSource).toContain('Generation Event');
  });

  it('keeps era timeline filtering wired across events and eras', () => {
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const hookSource = readFileSync(src('hooks', 'useEvents.ts'), 'utf8');
    const eventsSource = readFileSync(src('pages', 'EventsPage.tsx'), 'utf8');
    const erasSource = readFileSync(src('pages', 'ErasPage.tsx'), 'utf8');

    expect(apiSource).toContain('era?: string');
    expect(hookSource).toContain('resourceId, era, type, status');
    expect(eventsSource).toContain("searchParams.get('era')");
    expect(eventsSource).toContain("updateFilter('era'");
    expect(eventsSource).toContain('Era ${era}');
    expect(erasSource).toContain('Current Era Timeline');
    expect(erasSource).toContain('/events?era=${transition?.currentEra ?? 1}');
    expect(erasSource).toContain('/events?era=${entry.completedEra}');
  });

  it('keeps divine artifact gameplay wired to the artifacts page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const artifactEntitySource = readFileSync(src('entities', 'artifact.ts'), 'utf8');
    const artifactsPageSource = readFileSync(src('pages', 'ArtifactsPage.tsx'), 'utf8');

    expect(appSource).toContain('ArtifactsPage');
    expect(navSource).toContain("path: '/artifacts'");
    expect(apiSource).toContain('getArtifacts');
    expect(apiSource).toContain('createArtifact');
    expect(apiSource).toContain('empowerArtifact');
    expect(apiSource).toContain('transferArtifact');
    expect(apiSource).toContain('stabilizeArtifact');
    expect(apiSource).toContain('normalizeDivineArtifact');
    expect(apiSource).toContain(
      'artifactLimit: Number(source.artifactLimit ?? source.artifact_limit ?? 9)'
    );
    expect(apiSource).toContain('originSummary');
    expect(artifactEntitySource).toContain('DivineArtifact');
    expect(artifactEntitySource).toContain('ArtifactHistoryEntry');
    expect(artifactEntitySource).toContain('seeded?: boolean');
    expect(artifactsPageSource).toContain('Divine Artifacts');
    expect(artifactsPageSource).toContain('Forge Artifact');
    expect(artifactsPageSource).toContain('Artifact History');
    expect(artifactsPageSource).toContain('artifactStatus?.artifactLimit ?? 9');
    expect(artifactsPageSource).toContain('starter relic');
  });

  it('keeps divine weather gameplay wired to the weather page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const weatherEntitySource = readFileSync(src('entities', 'weather.ts'), 'utf8');
    const weatherPageSource = readFileSync(src('pages', 'WeatherPage.tsx'), 'utf8');

    expect(appSource).toContain('WeatherPage');
    expect(navSource).toContain("path: '/weather'");
    expect(apiSource).toContain('getWeatherStatus');
    expect(apiSource).toContain('nudgeWeather');
    expect(apiSource).toContain('normalizeWeatherInfluenceEntry');
    expect(apiSource).toContain('normalizeWeatherConsequenceChain');
    expect(weatherEntitySource).toContain('WeatherInfluenceEntry');
    expect(weatherEntitySource).toContain('WeatherStatusResponse');
    expect(weatherEntitySource).toContain('WeatherConsequenceChain');
    expect(weatherPageSource).toContain('Divine Weather');
    expect(weatherPageSource).toContain('Weather Pattern');
    expect(weatherPageSource).toContain('Consequence Chains');
    expect(weatherPageSource).toContain('Settlement Effects');
    expect(weatherPageSource).toContain('Resource Effects');
  });

  it('keeps temporal omen gameplay wired to the omens page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const omenEntitySource = readFileSync(src('entities', 'temporalOmen.ts'), 'utf8');
    const omensPageSource = readFileSync(src('pages', 'TemporalOmensPage.tsx'), 'utf8');

    expect(appSource).toContain('TemporalOmensPage');
    expect(navSource).toContain("path: '/omens'");
    expect(apiSource).toContain('getTemporalOmens');
    expect(apiSource).toContain('readTemporalOmen');
    expect(apiSource).toContain('normalizeTemporalOmenEntry');
    expect(omenEntitySource).toContain('TemporalOmenEntry');
    expect(omenEntitySource).toContain('TemporalOmenStatusResponse');
    expect(omenEntitySource).toContain('TemporalOmenChain');
    expect(apiSource).toContain('normalizeTemporalOmenChain');
    expect(omensPageSource).toContain('Temporal Omens');
    expect(omensPageSource).toContain('Read Omen');
    expect(omensPageSource).toContain('Prophecy Chains');
    expect(omensPageSource).toContain('consistencyNote');
  });

  it('keeps magic discovery gameplay wired to the magic page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const magicEntitySource = readFileSync(src('entities', 'magicDiscovery.ts'), 'utf8');
    const magicPageSource = readFileSync(src('pages', 'MagicDiscoveryPage.tsx'), 'utf8');

    expect(appSource).toContain('MagicDiscoveryPage');
    expect(navSource).toContain("path: '/magic'");
    expect(apiSource).toContain('getMagicDiscovery');
    expect(apiSource).toContain('researchMagic');
    expect(apiSource).toContain('normalizeMagicDiscoveryPath');
    expect(apiSource).toContain('normalizeMagicDiscoveryProgression');
    expect(magicEntitySource).toContain('MagicDiscoveryPath');
    expect(magicEntitySource).toContain('MagicDiscoveryStatusResponse');
    expect(magicEntitySource).toContain('MagicDiscoveryProgression');
    expect(magicEntitySource).toContain('MagicDiscoveryTickSummary');
    expect(magicEntitySource).toContain('betType: string');
    expect(magicPageSource).toContain('Magic Discovery');
    expect(magicPageSource).toContain('Research Magic');
    expect(magicPageSource).toContain('Autonomous Progression');
    expect(magicPageSource).toContain('magic_progression');
    expect(magicPageSource).toContain('Betting Hooks');
  });

  it('keeps mythology gameplay wired to the myths page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const mythologyEntitySource = readFileSync(src('entities', 'mythology.ts'), 'utf8');
    const mythologyPageSource = readFileSync(src('pages', 'MythologyPage.tsx'), 'utf8');

    expect(appSource).toContain('MythologyPage');
    expect(navSource).toContain("path: '/myths'");
    expect(apiSource).toContain('getMythology');
    expect(apiSource).toContain('promoteMyth');
    expect(apiSource).toContain('normalizePromotedMyth');
    expect(mythologyEntitySource).toContain('MythologyStatusResponse');
    expect(mythologyEntitySource).toContain('PromotedMyth');
    expect(mythologyEntitySource).toContain('MythEcho');
    expect(apiSource).toContain('normalizeMythEcho');
    expect(mythologyPageSource).toContain('Mythology');
    expect(mythologyPageSource).toContain('Promote Myth');
    expect(mythologyPageSource).toContain('Candidate Legends');
    expect(mythologyPageSource).toContain('Recent Myth Echoes');
    expect(mythologyPageSource).toContain('Autonomous Evolution');
    expect(mythologyPageSource).toContain('Latest Myth Echo');
  });

  it('keeps civilization gameplay wired to the civilization page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const civilizationEntitySource = readFileSync(src('entities', 'civilization.ts'), 'utf8');
    const civilizationPageSource = readFileSync(src('pages', 'CivilizationPage.tsx'), 'utf8');
    const lastTickSource = readFileSync(src('components', 'DashboardLastTickPanel.tsx'), 'utf8');
    const dashboardPanelSource = readFileSync(
      src('components', 'DashboardCivilizationPanel.tsx'),
      'utf8'
    );

    expect(appSource).toContain('CivilizationPage');
    expect(navSource).toContain("path: '/civilization'");
    expect(apiSource).toContain('getCivilization');
    expect(apiSource).toContain('advanceCivilization');
    expect(apiSource).toContain('normalizeCivilizationRegionAgenda');
    expect(apiSource).toContain('normalizeCivilizationDiplomacy');
    expect(civilizationEntitySource).toContain('CivilizationStatusResponse');
    expect(civilizationEntitySource).toContain('CivilizationDecision');
    expect(civilizationEntitySource).toContain('CivilizationDiplomacy');
    expect(civilizationPageSource).toContain('Civilization');
    expect(civilizationPageSource).toContain('Advance Civic Agenda');
    expect(civilizationPageSource).toContain('Civic Diplomacy');
    expect(civilizationPageSource).toContain('Regional Agendas');
    expect(civilizationPageSource).toContain('Recent Decisions');
    expect(dashboardPanelSource).toContain('Top Agenda');
    expect(lastTickSource).toContain('Civic Diplomacy');
  });

  it('keeps AI pantheon gameplay wired to the pantheon page', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'NavigationBar.tsx'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const pantheonEntitySource = readFileSync(src('entities', 'pantheon.ts'), 'utf8');
    const pantheonPageSource = readFileSync(src('pages', 'PantheonPage.tsx'), 'utf8');
    const dashboardPanelSource = readFileSync(
      src('components', 'DashboardPantheonPanel.tsx'),
      'utf8'
    );

    expect(appSource).toContain('PantheonPage');
    expect(navSource).toContain("path: '/pantheon'");
    expect(apiSource).toContain('getPantheon');
    expect(apiSource).toContain('counterplayPantheon');
    expect(apiSource).toContain('normalizePantheonStatusResponse');
    expect(apiSource).toContain('normalizePantheonCounterplayResponse');
    expect(apiSource).toContain('normalizePantheonBettingHook');
    expect(apiSource).toContain('normalizePantheonRelationshipArc');
    expect(pantheonEntitySource).toContain('PantheonStatusResponse');
    expect(pantheonEntitySource).toContain('PantheonIntervention');
    expect(pantheonEntitySource).toContain('PantheonCounterplayStatus');
    expect(pantheonEntitySource).toContain('PantheonPoliticsStatus');
    expect(pantheonEntitySource).toContain('PantheonRelationshipArc');
    expect(pantheonEntitySource).toContain('PantheonBettingHook');
    expect(pantheonEntitySource).toContain('pantheon_intervention');
    expect(pantheonEntitySource).toContain('pantheon_relationship_arc');
    expect(pantheonPageSource).toContain('AI Pantheon');
    expect(pantheonPageSource).toContain('Pantheon Pressure');
    expect(pantheonPageSource).toContain('Pantheon Betting Hooks');
    expect(pantheonPageSource).toContain('Alliance and Rival Arcs');
    expect(pantheonPageSource).toContain('Divine Actors');
    expect(pantheonPageSource).toContain('Player Counterplay');
    expect(pantheonPageSource).toContain('Appease');
    expect(pantheonPageSource).toContain('Challenge');
    expect(pantheonPageSource).toContain('Recent Interventions');
    expect(dashboardPanelSource).toContain('Top Divine Pressure');
    expect(dashboardPanelSource).toContain('Latest Political Arc');
  });

  it('keeps region detail resource and history tabs wired', () => {
    const panelSource = readFileSync(src('components', 'RegionDetailPanel.tsx'), 'utf8');
    const navSource = readFileSync(src('components', 'RegionTabs', 'RegionTabNav.tsx'), 'utf8');
    const historyTabSource = readFileSync(
      src('components', 'RegionTabs', 'RegionHistoryTab.tsx'),
      'utf8'
    );
    const characteristicsSource = readFileSync(
      src('components', 'RegionTabs', 'RegionCharacteristics.tsx'),
      'utf8'
    );

    expect(panelSource).toContain('RegionResourcesTab');
    expect(panelSource).toContain('RegionHistoryTab');
    expect(navSource).toContain("'history'");
    expect(historyTabSource).toContain('recentEvents');
    expect(historyTabSource).toContain('Timeline');
    expect(characteristicsSource).toContain('Trade Routes:');
    expect(characteristicsSource).toContain('connected region');
  });

  it('keeps region divine resonance visible in influence gameplay', () => {
    const regionEntitySource = readFileSync(src('entities', 'region.ts'), 'utf8');
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const panelSource = readFileSync(src('components', 'RegionInfluencePanel.tsx'), 'utf8');
    const costsSource = readFileSync(
      src('components', 'RegionTabs', 'RegionInfluenceCosts.tsx'),
      'utf8'
    );

    expect(regionEntitySource).toContain('influenceEffectiveness');
    expect(apiSource).toContain('source.influenceEffectiveness');
    expect(panelSource).toContain('Divine Resonance:');
    expect(panelSource).toContain('Effect: {actionPreview.summary}');
    expect(costsSource).toContain('resonance.summary');
  });

  it('keeps betting summaries and payout tuning visible', () => {
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const bettingSource = readFileSync(src('components', 'DivineBettingPanel.tsx'), 'utf8');
    const betEntitySource = readFileSync(src('entities', 'divineBet.ts'), 'utf8');

    expect(apiSource).toContain('getDivineBetSummary');
    expect(apiSource).toContain("'bets/summary'");
    expect(bettingSource).toContain('Bet Portfolio');
    expect(bettingSource).toContain('PayoutProfileLine');
    expect(betEntitySource).toContain('BetPayoutProfile');
    expect(betEntitySource).toContain("'cultural_shift'");
    expect(betEntitySource).toContain("'corruption_spread'");
    expect(betEntitySource).toContain("'magic_discovery'");
    expect(betEntitySource).toContain("'civilization_agenda'");
  });

  it('keeps hero lifecycle, relationship, and history context visible', () => {
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');
    const heroEntitySource = readFileSync(src('entities', 'hero.ts'), 'utf8');
    const heroCardSource = readFileSync(src('components', 'HeroCard.tsx'), 'utf8');

    expect(heroEntitySource).toContain('HeroLifecycleSummary');
    expect(heroEntitySource).toContain('relationshipContext');
    expect(heroEntitySource).toContain('recentHistory');
    expect(heroEntitySource).toContain('HeroChampionStatus');
    expect(heroEntitySource).toContain('ChampionOutcome');
    expect(heroEntitySource).toContain('ChampionBettingHook');
    expect(apiSource).toContain('normalizeHeroLifecycleSummary');
    expect(apiSource).toContain('source.relationshipContext');
    expect(apiSource).toContain('source.recentHistory');
    expect(apiSource).toContain('normalizeChampionStatus');
    expect(apiSource).toContain('normalizeChampionOutcome');
    expect(apiSource).toContain('GameTickChampionSummary');
    expect(heroCardSource).toContain('Lifecycle');
    expect(heroCardSource).toContain('Region Ties');
    expect(heroCardSource).toContain('Recent History');
    expect(heroCardSource).toContain('Champion Bond');
    expect(heroCardSource).toContain('Latest outcome');
    expect(heroCardSource).toContain('/events?heroId=${encodeURIComponent(hero.id)}');
  });

  it('keeps mortal champion controls visible on the heroes page', () => {
    const heroesPageSource = readFileSync(src('pages', 'HeroesPage.tsx'), 'utf8');
    const championPanelSource = readFileSync(src('components', 'HeroChampionPanel.tsx'), 'utf8');

    expect(heroesPageSource).toContain('HeroChampionPanel');
    expect(championPanelSource).toContain('Mortal Champion Bond');
    expect(championPanelSource).toContain('Designate Champion');
    expect(championPanelSource).toContain('Cultivate {option.label}');
    expect(championPanelSource).toContain('designateChampion');
    expect(championPanelSource).toContain('cultivateChampion');
    expect(championPanelSource).toContain('Latest Outcome');
    expect(championPanelSource).toContain('Recent Champion Outcomes');
    expect(championPanelSource).toContain('/events/${champion.latestOutcome.eventId}');
  });
});
