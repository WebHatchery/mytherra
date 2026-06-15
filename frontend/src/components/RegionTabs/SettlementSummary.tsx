import React from 'react';
import { Settlement } from '../../entities/settlement';
import { getSettlementIcon } from '../../utils/regionUtils';

interface SettlementSummaryProps {
  settlementCounts: Partial<Record<Settlement['type'], number>>;
  totalPopulation: number;
}

const settlementTypeOrder: Settlement['type'][] = [
  'metropolis',
  'city',
  'town',
  'village',
  'hamlet',
  'outpost',
  'stronghold',
];

const formatSettlementType = (type: Settlement['type'], count: number): string => {
  const label = type.charAt(0).toUpperCase() + type.slice(1);
  return count === 1 ? label : `${label}s`;
};

const SettlementSummary: React.FC<SettlementSummaryProps> = ({
  settlementCounts,
  totalPopulation,
}) => {
  return (
    <div className="mb-4 p-3 bg-gray-700 rounded">
      <h3 className="text-lg font-semibold mb-2">Settlement Summary</h3>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm mb-3">
        {settlementTypeOrder.map(type => {
          const count = settlementCounts[type] ?? 0;
          if (count === 0) {
            return null;
          }

          return (
            <div key={type} className="flex items-center">
              <span className="mr-1">{getSettlementIcon(type)}</span>
              <span>
                {count} {formatSettlementType(type, count)}
              </span>
            </div>
          );
        })}
      </div>
      <div className="text-center p-2 bg-gray-600 rounded">
        <div className="text-sm text-gray-400">Total Population</div>
        <div className="text-xl font-bold text-blue-400">{totalPopulation.toLocaleString()}</div>
      </div>
    </div>
  );
};

export default SettlementSummary;
