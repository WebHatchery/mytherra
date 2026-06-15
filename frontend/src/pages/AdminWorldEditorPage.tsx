import React, { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import {
  createAdminWorldEntity,
  getAdminWorldEditor,
  updateAdminWorldEntity,
  type AdminWorldEditorEntityType,
  type AdminWorldEditorPayload,
  type AdminWorldEditorStatusResponse,
} from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useAuth } from '../contexts/useAuth';
import { useGameStatus } from '../hooks/useGameStatus';

type EditorMode = 'create' | 'update';
type FieldKind = 'text' | 'number' | 'select' | 'textarea' | 'checkbox' | 'csv';

interface FieldConfig {
  key: string;
  label: string;
  kind: FieldKind;
  options?: string[];
  min?: number;
  max?: number;
  required?: boolean;
}

type DraftState = Record<string, string | boolean>;

const ENTITY_TYPES: AdminWorldEditorEntityType[] = [
  'regions',
  'settlements',
  'landmarks',
  'resources',
  'heroes',
];

const ENTITY_LABELS: Record<AdminWorldEditorEntityType, string> = {
  regions: 'Regions',
  settlements: 'Settlements',
  landmarks: 'Landmarks',
  resources: 'Resources',
  heroes: 'Heroes',
};

const ENTITY_SINGULAR: Record<AdminWorldEditorEntityType, string> = {
  regions: 'Region',
  settlements: 'Settlement',
  landmarks: 'Landmark',
  resources: 'Resource',
  heroes: 'Hero',
};

const camelToSnake = (value: string): string => value.replace(/[A-Z]/g, match => `_${match.toLowerCase()}`);

const labelize = (value: string): string => value.replace(/_/g, ' ');

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Admin world editor action failed';
};

const stringList = (value: unknown): string => {
  if (Array.isArray(value)) {
    return value.filter(item => typeof item === 'string').join(', ');
  }
  return typeof value === 'string' ? value : '';
};

const recordValue = (record: Record<string, unknown> | null, key: string): unknown => {
  if (!record) return undefined;
  const snake = camelToSnake(key);
  if (key in record) return record[key];
  if (snake in record) return record[snake];
  if (key === 'outputValue' && 'output' in record) return record.output;
  return undefined;
};

const fieldConfigs = (
  editor: AdminWorldEditorStatusResponse | null,
  selectedType: AdminWorldEditorEntityType
): FieldConfig[] => {
  const regionOptions = (editor?.entities.regions ?? [])
    .map(region => String(region.id ?? ''))
    .filter(Boolean);
  const settlementOptions = ['', ...(editor?.entities.settlements ?? [])
    .map(settlement => String(settlement.id ?? ''))
    .filter(Boolean)];

  switch (selectedType) {
    case 'regions':
      return [
        { key: 'id', label: 'ID', kind: 'text' },
        { key: 'name', label: 'Name', kind: 'text', required: true },
        { key: 'color', label: 'Color', kind: 'text' },
        { key: 'status', label: 'Status', kind: 'select', options: editor?.options.regions.statuses },
        { key: 'prosperity', label: 'Prosperity', kind: 'number', min: 0, max: 100 },
        { key: 'chaos', label: 'Chaos', kind: 'number', min: 0, max: 100 },
        { key: 'magicAffinity', label: 'Magic', kind: 'number', min: 0, max: 100 },
        { key: 'dangerLevel', label: 'Danger', kind: 'number', min: 0, max: 100 },
        { key: 'divineResonance', label: 'Resonance', kind: 'number', min: 0, max: 100 },
        { key: 'climateType', label: 'Climate', kind: 'select', options: editor?.options.regions.climateTypes },
        {
          key: 'culturalInfluence',
          label: 'Culture',
          kind: 'select',
          options: editor?.options.regions.culturalInfluences,
        },
        { key: 'tags', label: 'Tags', kind: 'csv' },
        { key: 'regionalTraits', label: 'Traits', kind: 'csv' },
      ];
    case 'settlements':
      return [
        { key: 'id', label: 'ID', kind: 'text' },
        { key: 'regionId', label: 'Region', kind: 'select', options: regionOptions, required: true },
        { key: 'name', label: 'Name', kind: 'text', required: true },
        { key: 'type', label: 'Type', kind: 'select', options: editor?.options.settlements.types },
        { key: 'status', label: 'Status', kind: 'select', options: editor?.options.settlements.statuses },
        { key: 'population', label: 'Population', kind: 'number', min: 0 },
        { key: 'prosperity', label: 'Prosperity', kind: 'number', min: 0, max: 100 },
        { key: 'defensibility', label: 'Defense', kind: 'number', min: 0, max: 100 },
        { key: 'foundedYear', label: 'Founded', kind: 'number', min: 1 },
        { key: 'specializations', label: 'Specializations', kind: 'csv' },
        { key: 'traits', label: 'Traits', kind: 'csv' },
      ];
    case 'landmarks':
      return [
        { key: 'id', label: 'ID', kind: 'text' },
        { key: 'regionId', label: 'Region', kind: 'select', options: regionOptions, required: true },
        { key: 'name', label: 'Name', kind: 'text', required: true },
        { key: 'type', label: 'Type', kind: 'select', options: editor?.options.landmarks.types },
        { key: 'status', label: 'Status', kind: 'select', options: editor?.options.landmarks.statuses },
        { key: 'magicLevel', label: 'Magic', kind: 'number', min: 0, max: 100 },
        { key: 'dangerLevel', label: 'Danger', kind: 'number', min: 0, max: 100 },
        { key: 'discoveredYear', label: 'Discovered', kind: 'number', min: 1 },
        { key: 'description', label: 'Description', kind: 'textarea' },
        { key: 'traits', label: 'Traits', kind: 'csv' },
      ];
    case 'resources':
      return [
        { key: 'id', label: 'ID', kind: 'text' },
        { key: 'regionId', label: 'Region', kind: 'select', options: regionOptions, required: true },
        { key: 'settlementId', label: 'Settlement', kind: 'select', options: settlementOptions },
        { key: 'name', label: 'Name', kind: 'text', required: true },
        { key: 'type', label: 'Type', kind: 'select', options: editor?.options.resources.types },
        { key: 'status', label: 'Status', kind: 'select', options: editor?.options.resources.statuses },
        { key: 'outputValue', label: 'Output', kind: 'number', min: 0, max: 100 },
      ];
    case 'heroes':
      return [
        { key: 'id', label: 'ID', kind: 'text' },
        { key: 'regionId', label: 'Region', kind: 'select', options: regionOptions, required: true },
        { key: 'name', label: 'Name', kind: 'text', required: true },
        { key: 'role', label: 'Role', kind: 'select', options: editor?.options.heroes.roles },
        { key: 'status', label: 'Status', kind: 'select', options: editor?.options.heroes.statuses },
        { key: 'level', label: 'Level', kind: 'number', min: 1, max: 100 },
        { key: 'age', label: 'Age', kind: 'number', min: 0, max: 500 },
        { key: 'isAlive', label: 'Alive', kind: 'checkbox' },
        { key: 'description', label: 'Description', kind: 'textarea' },
        { key: 'feats', label: 'Feats', kind: 'csv' },
        { key: 'personalityTraits', label: 'Traits', kind: 'csv' },
        { key: 'alignmentGood', label: 'Good', kind: 'number', min: 0, max: 100 },
        { key: 'alignmentChaotic', label: 'Chaotic', kind: 'number', min: 0, max: 100 },
      ];
  }
};

const defaultDraft = (
  editor: AdminWorldEditorStatusResponse | null,
  selectedType: AdminWorldEditorEntityType
): DraftState => {
  const firstRegionId = String(editor?.entities.regions?.[0]?.id ?? '');
  const currentYear = String(editor?.currentYear ?? 1);

  switch (selectedType) {
    case 'regions':
      return {
        id: '',
        name: '',
        color: '#64748b',
        status: 'stable',
        prosperity: '50',
        chaos: '25',
        magicAffinity: '50',
        dangerLevel: '25',
        divineResonance: '50',
        climateType: 'temperate',
        culturalInfluence: 'pastoral',
        tags: '',
        regionalTraits: '',
      };
    case 'settlements':
      return {
        id: '',
        regionId: firstRegionId,
        name: '',
        type: 'village',
        status: 'stable',
        population: '100',
        prosperity: '50',
        defensibility: '35',
        foundedYear: currentYear,
        specializations: '',
        traits: '',
      };
    case 'landmarks':
      return {
        id: '',
        regionId: firstRegionId,
        name: '',
        type: 'monument',
        status: 'weathered',
        magicLevel: '25',
        dangerLevel: '20',
        discoveredYear: '',
        description: '',
        traits: '',
      };
    case 'resources':
      return {
        id: '',
        regionId: firstRegionId,
        settlementId: '',
        name: '',
        type: 'mine',
        status: 'active',
        outputValue: '50',
      };
    case 'heroes':
      return {
        id: '',
        regionId: firstRegionId,
        name: '',
        role: 'undecided',
        status: 'living',
        level: '1',
        age: '20',
        isAlive: true,
        description: '',
        feats: '',
        personalityTraits: '',
        alignmentGood: '50',
        alignmentChaotic: '50',
      };
  }
};

const draftFromEntity = (
  editor: AdminWorldEditorStatusResponse | null,
  selectedType: AdminWorldEditorEntityType,
  entity: Record<string, unknown> | null
): DraftState => {
  const draft = defaultDraft(editor, selectedType);
  if (!entity) return draft;

  for (const key of Object.keys(draft)) {
    if (key === 'alignmentGood') {
      const alignment = recordValue(entity, 'alignment');
      draft[key] =
        alignment && typeof alignment === 'object' && 'good' in alignment
          ? String((alignment as Record<string, unknown>).good ?? 50)
          : '50';
      continue;
    }
    if (key === 'alignmentChaotic') {
      const alignment = recordValue(entity, 'alignment');
      draft[key] =
        alignment && typeof alignment === 'object' && 'chaotic' in alignment
          ? String((alignment as Record<string, unknown>).chaotic ?? 50)
          : '50';
      continue;
    }

    const value = recordValue(entity, key);
    if (Array.isArray(value)) draft[key] = stringList(value);
    else if (typeof value === 'boolean') draft[key] = value;
    else if (value !== undefined && value !== null) draft[key] = String(value);
  }

  return draft;
};

const payloadFromDraft = (
  fields: FieldConfig[],
  draft: DraftState,
  mode: EditorMode
): AdminWorldEditorPayload => {
  const payload: AdminWorldEditorPayload = {};

  for (const field of fields) {
    if (field.key === 'id' && mode === 'update') continue;
    const value = draft[field.key];
    if (field.key === 'id' && value === '') continue;

    if (field.kind === 'number') {
      if (value === '') continue;
      payload[field.key] = Number(value);
    } else if (field.kind === 'checkbox') {
      payload[field.key] = Boolean(value);
    } else if (field.kind === 'csv') {
      payload[field.key] = String(value ?? '')
        .split(',')
        .map(item => item.trim())
        .filter(Boolean);
    } else if (field.key === 'settlementId' && value === '') {
      payload[field.key] = null;
    } else if (field.key === 'alignmentGood' || field.key === 'alignmentChaotic') {
      continue;
    } else {
      payload[field.key] = String(value ?? '');
    }
  }

  if ('alignmentGood' in draft || 'alignmentChaotic' in draft) {
    payload.alignment = {
      good: Number(draft.alignmentGood ?? 50),
      chaotic: Number(draft.alignmentChaotic ?? 50),
      lastChange: 'Admin world editor edit',
    };
  }

  return payload;
};

const AdminWorldEditorPage: React.FC = () => {
  const { isAdmin } = useAuth();
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
  } = useGameStatus();
  const [editor, setEditor] = useState<AdminWorldEditorStatusResponse | null>(null);
  const [selectedType, setSelectedType] = useState<AdminWorldEditorEntityType>('regions');
  const [mode, setMode] = useState<EditorMode>('create');
  const [selectedId, setSelectedId] = useState('');
  const [draft, setDraft] = useState<DraftState>(() => defaultDraft(null, 'regions'));
  const [isLoadingEditor, setIsLoadingEditor] = useState(false);
  const [editorError, setEditorError] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchEditor = useCallback(async () => {
    try {
      setIsLoadingEditor(true);
      setEditor(await getAdminWorldEditor());
      setEditorError(null);
    } catch (error: unknown) {
      setEditorError(getErrorMessage(error));
    } finally {
      setIsLoadingEditor(false);
    }
  }, []);

  useEffect(() => {
    if (isAdmin()) {
      void fetchEditor();
    }
  }, [fetchEditor, isAdmin]);

  const entities = useMemo(() => editor?.entities[selectedType] ?? [], [editor, selectedType]);
  const selectedEntity = useMemo(
    () => entities.find(entity => String(entity.id ?? '') === selectedId) ?? null,
    [entities, selectedId]
  );
  const fields = useMemo(() => fieldConfigs(editor, selectedType), [editor, selectedType]);

  useEffect(() => {
    if (mode === 'update') {
      const firstId = String(entities[0]?.id ?? '');
      if (!selectedId && firstId) {
        setSelectedId(firstId);
      }
      setDraft(draftFromEntity(editor, selectedType, selectedEntity ?? entities[0] ?? null));
      return;
    }

    setDraft(defaultDraft(editor, selectedType));
  }, [editor, entities, mode, selectedEntity, selectedId, selectedType]);

  const updateDraft = (key: string, value: string | boolean) => {
    setDraft(previous => ({ ...previous, [key]: value }));
  };

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    try {
      setEditorError(null);
      setActionMessage(null);
      const payload = payloadFromDraft(fields, draft, mode);
      const result =
        mode === 'create'
          ? await createAdminWorldEntity(selectedType, payload)
          : await updateAdminWorldEntity(selectedType, selectedId, payload);
      setEditor(result.status);
      setActionMessage(`${result.summary} Event ${result.eventId}.`);
      if (mode === 'create') {
        setSelectedId(String(result.entity.id ?? ''));
        setMode('update');
      }
    } catch (error: unknown) {
      setEditorError(getErrorMessage(error));
    }
  };

  const renderField = (field: FieldConfig) => {
    const value = draft[field.key] ?? '';
    const commonClass =
      'mt-1 w-full rounded border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100 focus:border-cyan-500 focus:outline-none';

    if (field.kind === 'select') {
      return (
        <select
          value={String(value)}
          onChange={event => updateDraft(field.key, event.target.value)}
          className={commonClass}
          required={field.required}
          disabled={field.key === 'id' && mode === 'update'}
        >
          {(field.options ?? []).map(option => (
            <option key={`${field.key}-${option}`} value={option}>
              {option ? labelize(option) : 'None'}
            </option>
          ))}
        </select>
      );
    }

    if (field.kind === 'textarea') {
      return (
        <textarea
          value={String(value)}
          onChange={event => updateDraft(field.key, event.target.value)}
          className={`${commonClass} min-h-24`}
          required={field.required}
        />
      );
    }

    if (field.kind === 'checkbox') {
      return (
        <input
          type="checkbox"
          checked={Boolean(value)}
          onChange={event => updateDraft(field.key, event.target.checked)}
          className="mt-3 h-5 w-5 rounded border-gray-700 bg-gray-900 text-cyan-500"
        />
      );
    }

    return (
      <input
        type={field.kind === 'number' ? 'number' : 'text'}
        min={field.min}
        max={field.max}
        value={String(value)}
        onChange={event => updateDraft(field.key, event.target.value)}
        className={commonClass}
        required={field.required}
        disabled={field.key === 'id' && mode === 'update'}
      />
    );
  };

  if (!isAdmin()) {
    return (
      <PageLayout
        gameStatus={gameStatus}
        isLoading={isLoadingStatus}
        error={statusError}
        errorPrefix="Status"
      >
        <PageHeader title="World Editor" subtitle="Admin Tools" icon="⚙" />
        <EmptyState
          title="Admin Access Required"
          message="World editing is restricted to WebHatchery admin accounts."
          icon="⚙"
        />
      </PageLayout>
    );
  }

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoadingStatus || isLoadingEditor}
      error={statusError || editorError}
      loadingMessage="Loading world editor..."
      errorPrefix="World editor"
    >
      <PageHeader title="World Editor" subtitle="Admin Tools" icon="⚙" />

      {editor && (
        <section className="grid grid-cols-2 gap-3 md:grid-cols-5">
          {ENTITY_TYPES.map(entityType => (
            <button
              type="button"
              key={entityType}
              onClick={() => {
                setSelectedType(entityType);
                setSelectedId('');
                setMode('create');
              }}
              className={`rounded border px-3 py-3 text-left transition-colors ${
                selectedType === entityType
                  ? 'border-cyan-400 bg-cyan-950/40 text-cyan-100'
                  : 'border-[#2f334d] bg-[#1a1b26] text-gray-200 hover:border-cyan-700'
              }`}
            >
              <div className="text-xs uppercase text-gray-500">{ENTITY_LABELS[entityType]}</div>
              <div className="mt-1 text-2xl font-bold">{editor.summary[entityType] ?? 0}</div>
            </button>
          ))}
        </section>
      )}

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">{ENTITY_SINGULAR[selectedType]} Editor</h2>
            <div className="mt-1 text-sm text-gray-400">Year {editor?.currentYear ?? gameStatus?.currentYear ?? 1}</div>
          </div>
          <div className="flex flex-col gap-3 md:flex-row md:items-center">
            <div className="flex rounded border border-gray-700 bg-gray-900 p-1">
              {(['create', 'update'] as const).map(nextMode => (
                <button
                  key={nextMode}
                  type="button"
                  onClick={() => setMode(nextMode)}
                  className={`rounded px-3 py-1 text-sm font-semibold ${
                    mode === nextMode ? 'bg-cyan-600 text-white' : 'text-gray-300 hover:bg-gray-800'
                  }`}
                >
                  {nextMode === 'create' ? 'Create' : 'Edit'}
                </button>
              ))}
            </div>
            {mode === 'update' && (
              <select
                value={selectedId}
                onChange={event => setSelectedId(event.target.value)}
                className="rounded border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-100"
              >
                {entities.map(entity => (
                  <option key={String(entity.id)} value={String(entity.id)}>
                    {String(entity.name ?? entity.id)}
                  </option>
                ))}
              </select>
            )}
          </div>
        </div>

        {actionMessage && (
          <div className="mt-4 rounded border border-emerald-600/50 bg-emerald-950/30 px-3 py-2 text-sm text-emerald-100">
            {actionMessage}
          </div>
        )}

        {mode === 'update' && entities.length === 0 ? (
          <EmptyState
            title={`No ${ENTITY_LABELS[selectedType]}`}
            message={`Create a ${ENTITY_SINGULAR[selectedType].toLowerCase()} before editing this entity type.`}
            icon="⚙"
            className="mt-5"
          />
        ) : (
          <form onSubmit={submit} className="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
            {fields.map(field => (
              <label
                key={`${selectedType}-${field.key}`}
                className={field.kind === 'textarea' ? 'md:col-span-2' : ''}
              >
                <span className="text-xs font-semibold uppercase text-gray-400">{field.label}</span>
                {renderField(field)}
              </label>
            ))}
            <div className="md:col-span-2">
              <button
                type="submit"
                disabled={mode === 'update' && !selectedId}
                className="rounded bg-cyan-600 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-cyan-500 disabled:cursor-not-allowed disabled:bg-gray-600"
              >
                {mode === 'create' ? `Create ${ENTITY_SINGULAR[selectedType]}` : `Save ${ENTITY_SINGULAR[selectedType]}`}
              </button>
            </div>
          </form>
        )}
      </section>
    </PageLayout>
  );
};

export default AdminWorldEditorPage;
