import React from 'react';
import { ResourceNode } from '../../entities/resourceNode';
import { Settlement } from '../../entities/settlement';

interface RegionResourcesTabProps {
  resources: ResourceNode[];
  settlements: Settlement[];
  loading: boolean;
}

const formatLabel = (value: string): string =>
  value
    .replace('status-', '')
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const getStatusColor = (status: ResourceNode['status']): string => {
  switch (status) {
    case 'active':
    case 'blessed':
    case 'status-flourishing':
      return 'text-green-300';
    case 'unstable':
    case 'overworked':
    case 'contested':
      return 'text-yellow-300';
    case 'corrupted':
    case 'depleted':
      return 'text-red-300';
    default:
      return 'text-gray-300';
  }
};

const getOutputColor = (output: number): string => {
  if (output >= 75) return 'text-green-300';
  if (output >= 45) return 'text-yellow-300';
  return 'text-red-300';
};

const RegionResourcesTab: React.FC<RegionResourcesTabProps> = ({
  resources,
  settlements,
  loading,
}) => {
  const settlementById = new Map(settlements.map(settlement => [settlement.id, settlement.name]));
  const productiveResources = resources.filter(
    resource => resource.status !== 'depleted' && resource.status !== 'corrupted'
  ).length;
  const averageOutput =
    resources.length > 0
      ? Math.round(
          resources.reduce((total, resource) => total + resource.outputValue, 0) / resources.length
        )
      : 0;

  if (loading) {
    return <div className="text-gray-400 py-6">Loading resources...</div>;
  }

  return (
    <div>
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div className="p-3 bg-gray-700 rounded">
          <h4 className="text-sm font-medium text-gray-400 mb-2">Resource Sites</h4>
          <div className="text-2xl font-bold text-amber-300">{resources.length}</div>
        </div>
        <div className="p-3 bg-gray-700 rounded">
          <h4 className="text-sm font-medium text-gray-400 mb-2">Productive</h4>
          <div className="text-2xl font-bold text-green-300">{productiveResources}</div>
        </div>
        <div className="p-3 bg-gray-700 rounded">
          <h4 className="text-sm font-medium text-gray-400 mb-2">Average Output</h4>
          <div className={`text-2xl font-bold ${getOutputColor(averageOutput)}`}>
            {averageOutput}
          </div>
        </div>
      </div>

      {resources.length === 0 ? (
        <div className="text-center text-gray-400 py-8">
          No resource nodes have been recorded in this region.
        </div>
      ) : (
        <div className="space-y-3">
          {resources.map(resource => (
            <div key={resource.id} className="p-3 bg-gray-700 rounded">
              <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                  <h4 className="font-semibold text-white">{resource.name}</h4>
                  <div className="text-sm text-gray-400">
                    {formatLabel(resource.type)}
                    {resource.settlementId &&
                      ` near ${settlementById.get(resource.settlementId) ?? 'a settlement'}`}
                  </div>
                </div>
                <div className="flex gap-2 text-xs">
                  <span
                    className={`px-2 py-1 rounded bg-gray-800 ${getStatusColor(resource.status)}`}
                  >
                    {formatLabel(resource.status)}
                  </span>
                  <span
                    className={`px-2 py-1 rounded bg-gray-800 ${getOutputColor(resource.outputValue)}`}
                  >
                    Output {resource.outputValue}
                  </span>
                </div>
              </div>
              <div className="mt-3 h-2 bg-gray-800 rounded overflow-hidden">
                <div
                  className="h-full bg-amber-400"
                  style={{ width: `${Math.max(0, Math.min(100, resource.outputValue))}%` }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default RegionResourcesTab;
