import React from 'react';

export type RegionTabType =
  | 'overview'
  | 'settlements'
  | 'landmarks'
  | 'resources'
  | 'heroes'
  | 'history';

interface RegionTabNavProps {
  activeTab: RegionTabType;
  setActiveTab: (tab: RegionTabType) => void;
  settlementsCount: number;
  landmarksCount: number;
  resourcesCount: number;
  heroesCount: number;
  historyCount: number;
  loading: boolean;
  historyLoading: boolean;
}

const RegionTabNav: React.FC<RegionTabNavProps> = ({
  activeTab,
  setActiveTab,
  settlementsCount,
  landmarksCount,
  resourcesCount,
  heroesCount,
  historyCount,
  loading,
  historyLoading,
}) => {
  return (
    <div className="flex border-b border-gray-600 mb-4 overflow-x-auto">
      <TabButton
        active={activeTab === 'overview'}
        onClick={() => setActiveTab('overview')}
        label="Overview"
      />
      <TabButton
        active={activeTab === 'settlements'}
        onClick={() => setActiveTab('settlements')}
        label={`Settlements (${settlementsCount})`}
      />
      <TabButton
        active={activeTab === 'landmarks'}
        onClick={() => setActiveTab('landmarks')}
        label={`Landmarks (${landmarksCount})`}
      />
      <TabButton
        active={activeTab === 'resources'}
        onClick={() => setActiveTab('resources')}
        label={`Resources (${loading ? '...' : resourcesCount})`}
      />
      <TabButton
        active={activeTab === 'heroes'}
        onClick={() => setActiveTab('heroes')}
        label={`Heroes (${loading ? '...' : heroesCount})`}
      />
      <TabButton
        active={activeTab === 'history'}
        onClick={() => setActiveTab('history')}
        label={`History (${historyLoading ? '...' : historyCount})`}
      />
    </div>
  );
};

interface TabButtonProps {
  active: boolean;
  onClick: () => void;
  label: string;
}

const TabButton: React.FC<TabButtonProps> = ({ active, onClick, label }) => (
  <button
    className={`py-2 px-4 font-medium text-sm whitespace-nowrap ${
      active ? 'text-yellow-400 border-b-2 border-yellow-400' : 'text-gray-400 hover:text-gray-200'
    }`}
    onClick={onClick}
  >
    {label}
  </button>
);

export default RegionTabNav;
