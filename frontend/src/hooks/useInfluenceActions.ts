import { useState } from 'react';
import {
  sendInfluenceAction,
  type InfluenceActionPayload,
  type InfluenceActionResponse,
} from '../api/apiService';

export interface InfluenceAction {
  action: string;
  entityId: string;
  entityName: string;
  entityType: 'region' | 'hero';
}

export interface UseInfluenceActionsReturn {
  isLoadingAction: Record<string, boolean>;
  actionMessage: string | null;
  handleInfluenceAction: (
    action: string,
    entityId: string,
    entityName: string,
    entityType: 'region' | 'hero'
  ) => Promise<void>;
  getButtonClass: (baseClass: string, cost?: number, actionKey?: string) => string;
}

const isRecord = (value: unknown): value is Record<string, unknown> => {
  return typeof value === 'object' && value !== null;
};

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  if (isRecord(error) && typeof error.message === 'string') return error.message;
  return 'Network error';
};

const getResponseMessage = (response: InfluenceActionResponse): string => {
  if (response.success === false) {
    return `Failed: ${response.message || 'Action failed'}`;
  }
  if (typeof response.message === 'string' && response.message.length > 0) return response.message;
  return 'Action completed successfully!';
};

export const useInfluenceActions = (
  currentDivineFavor: number,
  onActionSuccess: () => void
): UseInfluenceActionsReturn => {
  const [isLoadingAction, setIsLoadingAction] = useState<Record<string, boolean>>({});
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const handleInfluenceAction = async (
    action: string,
    entityId: string,
    _entityName: string,
    entityType: 'region' | 'hero'
  ) => {
    const actionKey = `${action}-${entityId}`;

    setIsLoadingAction(prev => ({ ...prev, [actionKey]: true }));
    setActionMessage(null);

    const payload: InfluenceActionPayload = {
      action,
      entityId,
      entityType,
    };

    try {
      const response = await sendInfluenceAction(payload);
      setActionMessage(getResponseMessage(response));
      if (response.success !== false) {
        onActionSuccess();
      }
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setIsLoadingAction(prev => ({ ...prev, [actionKey]: false }));
    }
  };

  const getButtonClass = (baseClass: string, cost?: number, actionKey?: string) => {
    let finalClass = baseClass;
    if (cost !== undefined && currentDivineFavor < cost) {
      finalClass += ' opacity-50 cursor-not-allowed';
    } else if (actionKey && isLoadingAction[actionKey]) {
      finalClass += ' opacity-75 cursor-wait';
    }
    return finalClass;
  };

  return {
    isLoadingAction,
    actionMessage,
    handleInfluenceAction,
    getButtonClass,
  };
};
