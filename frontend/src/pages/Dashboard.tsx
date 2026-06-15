import React, { useEffect, useState } from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
  ArcElement,
} from 'chart.js';
import { Bar, Pie } from 'react-chartjs-2';
import {
  statisticsService,
  GameSummary,
  HeroStatistics,
  RegionStatistics,
  FinancialStatistics,
} from '../api/StatisticsService';
import PageLayout from '../components/PageLayout';
import {
  getEntityHistorySummary,
  getGameStatus,
  EntityHistorySummaryResponse,
  GameStatus,
} from '../api/apiService';
import { apiClient } from '../api/apiClient';
import DashboardLastTickPanel from '../components/DashboardLastTickPanel';
import DashboardHistoryPanel from '../components/DashboardHistoryPanel';
import DashboardEraPressurePanel from '../components/DashboardEraPressurePanel';
import DashboardEraLegacyPanel from '../components/DashboardEraLegacyPanel';
import DashboardEraTransitionPanel from '../components/DashboardEraTransitionPanel';
import DashboardEraComparisonPanel from '../components/DashboardEraComparisonPanel';
import DashboardCivilizationPanel from '../components/DashboardCivilizationPanel';
import DashboardPantheonPanel from '../components/DashboardPantheonPanel';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend, ArcElement);

export const Dashboard: React.FC = () => {
  const [gameStatus, setGameStatus] = useState<GameStatus | null>(null);
  const [summary, setSummary] = useState<GameSummary | null>(null);
  const [heroStats, setHeroStats] = useState<HeroStatistics | null>(null);
  const [regionStats, setRegionStats] = useState<RegionStatistics | null>(null);
  const [financialStats, setFinancialStats] = useState<FinancialStatistics | null>(null);
  const [historySummary, setHistorySummary] = useState<EntityHistorySummaryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [exportingChronicle, setExportingChronicle] = useState(false);

  const downloadExport = async (
    path: string,
    filename: string,
    setBusy: React.Dispatch<React.SetStateAction<boolean>>
  ) => {
    setBusy(true);
    try {
      const response = await apiClient.get(path, {
        responseType: 'blob',
      });

      const blob = response.data as Blob;
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (error) {
      console.error('Export failed:', error);
    } finally {
      setBusy(false);
    }
  };

  const handleExport = async () => {
    await downloadExport('/export/full', 'mytherra-world-snapshot.json', setExporting);
  };

  const handleChronicleExport = async () => {
    await downloadExport(
      '/export/chronicle-share',
      'mytherra-chronicle-share.json',
      setExportingChronicle
    );
  };

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [statusData, summaryData, heroData, regionData, financialData, historyData] =
          await Promise.all([
            getGameStatus(),
            statisticsService.getSummary(),
            statisticsService.getHeroStats(),
            statisticsService.getRegionStats(),
            statisticsService.getFinancialStats(),
            getEntityHistorySummary(),
          ]);

        setGameStatus(statusData);
        setSummary(summaryData);
        setHeroStats(heroData);
        setRegionStats(regionData);
        setFinancialStats(financialData);
        setHistorySummary(historyData);
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []);

  if (loading) {
    return (
      <PageLayout gameStatus={null} isLoading={true} loadingMessage="Loading statistics...">
        <div />
      </PageLayout>
    );
  }

  const heroRoleChartData = heroStats
    ? {
        labels: Object.keys(heroStats.roleDistribution),
        datasets: [
          {
            label: 'Heroes by Role',
            data: Object.values(heroStats.roleDistribution),
            backgroundColor: [
              'rgba(255, 99, 132, 0.5)',
              'rgba(54, 162, 235, 0.5)',
              'rgba(255, 206, 86, 0.5)',
              'rgba(75, 192, 192, 0.5)',
              'rgba(153, 102, 255, 0.5)',
            ],
            borderColor: [
              'rgba(255, 99, 132, 1)',
              'rgba(54, 162, 235, 1)',
              'rgba(255, 206, 86, 1)',
              'rgba(75, 192, 192, 1)',
              'rgba(153, 102, 255, 1)',
            ],
            borderWidth: 1,
          },
        ],
      }
    : null;

  const regionStatusChartData = regionStats
    ? {
        labels: Object.keys(regionStats.statusDistribution),
        datasets: [
          {
            label: 'Regions by Status',
            data: Object.values(regionStats.statusDistribution),
            backgroundColor: [
              'rgba(75, 192, 192, 0.5)',
              'rgba(255, 99, 132, 0.5)',
              'rgba(255, 206, 86, 0.5)',
            ],
            borderWidth: 1,
          },
        ],
      }
    : null;

  return (
    <PageLayout gameStatus={gameStatus}>
      {/* Header with Export Button */}
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-white">World Dashboard</h1>
        <div className="flex flex-col gap-2 sm:flex-row">
          <button
            onClick={handleChronicleExport}
            disabled={exportingChronicle}
            className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {exportingChronicle ? (
              <>
                <span className="animate-spin">⏳</span>
                Exporting...
              </>
            ) : (
              <>📜 Export Chronicle</>
            )}
          </button>
          <button
            onClick={handleExport}
            disabled={exporting}
            className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-600 text-white rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            {exporting ? (
              <>
                <span className="animate-spin">⏳</span>
                Exporting...
              </>
            ) : (
              <>📥 Export World</>
            )}
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
          <div className="text-gray-400 text-sm">Current Era</div>
          <div className="text-2xl font-bold text-blue-400">
            {summary?.currentEra} (Year {summary?.currentYear})
          </div>
        </div>
        <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
          <div className="text-gray-400 text-sm">Total Heroes</div>
          <div className="text-2xl font-bold text-green-400">{summary?.totalHeroes}</div>
        </div>
        <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
          <div className="text-gray-400 text-sm">Total Population</div>
          <div className="text-2xl font-bold text-yellow-400">
            {regionStats?.totalPopulation.toLocaleString()}
          </div>
        </div>
        <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
          <div className="text-gray-400 text-sm">Active Bets</div>
          <div className="text-2xl font-bold text-purple-400">{summary?.activeBets}</div>
        </div>
      </div>

      <DashboardEraPressurePanel eraPressure={gameStatus?.eraPressure} />
      <DashboardEraLegacyPanel eraLegacy={gameStatus?.eraLegacy} />
      <DashboardEraTransitionPanel eraTransition={gameStatus?.eraTransition} />
      <DashboardEraComparisonPanel eraComparison={gameStatus?.eraComparison} />
      <DashboardCivilizationPanel civilization={gameStatus?.civilization} />
      <DashboardPantheonPanel pantheon={gameStatus?.pantheon} />
      <DashboardLastTickPanel simulation={gameStatus?.simulation} />
      <DashboardHistoryPanel summary={historySummary} />

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {/* Hero Stats */}
        <div className="bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
          <h3 className="text-xl font-bold text-white mb-4">Hero Distribution</h3>
          {heroRoleChartData && <Pie data={heroRoleChartData} />}
          <div className="mt-4 grid grid-cols-2 gap-4">
            <div className="text-center p-2 bg-[#16161e] rounded">
              <div className="text-sm text-gray-400">Avg Level</div>
              <div className="text-lg font-bold text-blue-300">{heroStats?.averageLevel}</div>
            </div>
            <div className="text-center p-2 bg-[#16161e] rounded">
              <div className="text-sm text-gray-400">Living Heroes</div>
              <div className="text-lg font-bold text-green-300">{summary?.livingHeroes}</div>
            </div>
          </div>
        </div>

        {/* Region Stats */}
        <div className="bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
          <h3 className="text-xl font-bold text-white mb-4">Regional Status</h3>
          {regionStatusChartData && (
            <Bar
              data={regionStatusChartData}
              options={{
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } },
              }}
            />
          )}
          <div className="mt-4 space-y-2">
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-400">Avg Prosperity</span>
              <span className="text-yellow-300">{regionStats?.averageProsperity}%</span>
            </div>
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-400">Avg Chaos</span>
              <span className="text-red-300">{regionStats?.averageChaos}%</span>
            </div>
            <div className="flex justify-between items-center text-sm">
              <span className="text-gray-400">Avg Magic Affinity</span>
              <span className="text-purple-300">{regionStats?.averageMagicAffinity}%</span>
            </div>
          </div>
        </div>
      </div>

      {/* Financial Stats */}
      <div className="mt-8 bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
        <h3 className="text-xl font-bold text-white mb-4">Divine Economy</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <div className="text-gray-400 text-sm">Total Wagered</div>
            <div className="text-xl font-bold text-yellow-500">
              {financialStats?.totalInfluenceWagered}
            </div>
          </div>
          <div>
            <div className="text-gray-400 text-sm">Bets Won</div>
            <div className="text-xl font-bold text-green-500">{financialStats?.betsWon}</div>
          </div>
          <div>
            <div className="text-gray-400 text-sm">Bets Lost</div>
            <div className="text-xl font-bold text-red-500">{financialStats?.betsLost}</div>
          </div>
          <div>
            <div className="text-gray-400 text-sm">Payout Ratio</div>
            <div className="text-xl font-bold text-blue-500">{financialStats?.payoutRatio}x</div>
          </div>
        </div>
      </div>
    </PageLayout>
  );
};
