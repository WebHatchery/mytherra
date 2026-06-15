import React from 'react';
import HeroList from '../components/HeroList';
import PageLayout from '../components/PageLayout';
import PageHeader from '../components/PageHeader';
import EmptyState from '../components/EmptyState';
import HeroInfluencePanel from '../components/HeroInfluencePanel';
import HeroChampionPanel from '../components/HeroChampionPanel';
import { useGameStatus } from '../hooks/useGameStatus';
import { useHeroes } from '../hooks/useHeroes';

const HeroesPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingGameStatus,
    error: gameStatusError,
    refetch: refetchGameStatus,
  } = useGameStatus();
  const {
    heroes,
    isLoading: isLoadingHeroes,
    error: heroesError,
    selectedHero,
    selectHero,
    refetch: refetchHeroes,
  } = useHeroes({
    autoRefresh: true,
    refreshInterval: 30000,
  });

  const handleActionSuccess = () => {
    refetchGameStatus();
    refetchHeroes();
  };

  const isLoading = isLoadingGameStatus || isLoadingHeroes;
  const error = gameStatusError || heroesError;

  const topContent = (
    <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <HeroInfluencePanel
        selectedHero={selectedHero}
        currentDivineFavor={gameStatus?.divineFavor || 0}
        onActionSuccess={handleActionSuccess}
      />
      <HeroChampionPanel
        selectedHero={selectedHero}
        currentDivineFavor={gameStatus?.divineFavor || 0}
        onActionSuccess={handleActionSuccess}
      />
    </div>
  );
  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading heroes..."
      errorPrefix="Error loading heroes"
      showEventLog={true}
      topContent={topContent}
      selectedHeroId={selectedHero?.id}
    >
      <PageHeader
        title="Heroes of Mytherra"
        subtitle={`${heroes.length} heroes shape the world's destiny`}
        icon="⚔️"
      />

      {heroes.length === 0 ? (
        <EmptyState
          title="No Heroes Yet"
          message="The world awaits the rise of its first heroes. They will emerge as the need arises."
          icon="🏛️"
          actionButton={{
            label: 'Refresh Heroes',
            onClick: refetchHeroes,
          }}
        />
      ) : (
        <HeroList heroes={heroes} selectedHero={selectedHero} onSelectHero={selectHero} />
      )}
    </PageLayout>
  );
};

export default HeroesPage;
