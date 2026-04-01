import { useForm } from '@inertiajs/react';
import { produce } from 'immer';
import { createContext, useMemo, useRef, useState } from 'react';
import { route } from 'ziggy-js';

const defaultEnv = () => ({
  url: '',
  method: 'POST',
  request_headers: [],
  request_body: { template: '', parsers: {} },
  response_config: null,
  field_hashes: [],
});

// Helper to parse headers from a JSON string into a key-value array
const parseHeaders = (headersJson = '{}') => {
  try {
    const parsed = JSON.parse(headersJson);
    const entries = Object.entries(parsed);
    if (entries.length === 0) return [];
    return entries.map(([key, value]) => ({ key, value: String(value) }));
  } catch (e) {
    console.error('Failed to parse request headers:', e);
    return [];
  }
};

const normalizeEnvRecord = (env) => ({
  ...env,
  request_headers: parseHeaders(env.request_headers),
  request_body:
    typeof env.request_body === 'string'
      ? JSON.parse(env.request_body || '{}')
      : (env.request_body ?? { template: '', parsers: {} }),
  response_config: env.response_config ?? null,
  field_hashes: Array.isArray(env.field_hashes)
    ? env.field_hashes.map((h) => ({
        field_id: h.field_id,
        is_hashed: h.is_hashed ?? false,
        hash_algorithm: h.hash_algorithm ?? null,
        hmac_secret: h.hmac_secret ?? null,
      }))
    : [],
});

// Helper to transform environments from the server for the form
const transformEnvironmentsForForm = (environments = [], type = 'offerwall') => {
  if (type === 'ping-post') {
    const findEnv = (et, e) => {
      const match = environments.find((env) => env.env_type === et && env.environment === e);
      return match ? normalizeEnvRecord(match) : defaultEnv();
    };
    return {
      ping: { development: findEnv('ping', 'development'), production: findEnv('ping', 'production') },
      post: { development: findEnv('post', 'development'), production: findEnv('post', 'production') },
    };
  }

  // offerwall / post-only: flat structure
  const envs = { development: defaultEnv(), production: defaultEnv() };
  environments.forEach((env) => {
    if (env.environment !== 'development' && env.environment !== 'production') return;
    envs[env.environment] = normalizeEnvRecord(env);
  });
  return envs;
};

const buildEmptyEnvironments = (type) => {
  if (type === 'ping-post') {
    return {
      ping: { development: defaultEnv(), production: defaultEnv() },
      post: { development: defaultEnv(), production: defaultEnv() },
    };
  }
  return { development: defaultEnv(), production: defaultEnv() };
};

/**
 * Serialize form environments to the array format expected by the backend.
 * Returns an array of { env_type, environment, url, method, ... } records.
 */
const serializeEnvs = (type, environments) => {
  const flattenEnv = (envData = {}) => ({
    url: envData.url ?? '',
    method: envData.method ?? 'POST',
    request_headers: envData.request_headers ?? [],
    request_body: envData.request_body ?? null,
    content_type: envData.content_type ?? 'application/json',
    authentication_type: envData.authentication_type ?? 'none',
    response_config: envData.response_config ?? null,
    field_hashes: envData.field_hashes ?? [],
  });

  if (type === 'ping-post') {
    return ['ping', 'post'].flatMap((et) =>
      ['development', 'production'].map((e) => ({
        env_type: et,
        environment: e,
        ...flattenEnv(environments[et]?.[e]),
      })),
    );
  }

  const envType = type === 'offerwall' ? 'offerwall' : 'post';
  const prodConfig = type === 'offerwall' ? (environments['production']?.response_config ?? null) : undefined;
  return ['development', 'production'].map((e) => ({
    env_type: envType,
    environment: e,
    ...flattenEnv(environments[e]),
    ...(type === 'offerwall' ? { response_config: prodConfig } : {}),
  }));
};

/**
 * Scan a request_body string/object for {$N} tokens and return a Set of field IDs.
 */
const scanBodyForTokens = (body) => {
  if (!body) return new Set()
  const text = typeof body === 'object' ? JSON.stringify(body) : String(body)
  const regex = /\{\$(\d+)\}/g
  const ids = new Set()
  let m
  while ((m = regex.exec(text)) !== null) {
    ids.add(parseInt(m[1], 10))
  }
  return ids
}

/**
 * Build the initial envTokenSets Map by scanning all environments on mount (edit mode).
 * Returns Map<envKey, Set<fieldId>>.
 */
const buildInitialEnvTokenSets = (integration) => {
  if (!integration?.environments?.length) return new Map()
  const sets = new Map()
  for (const env of integration.environments) {
    const key = integration.type === 'ping-post'
      ? `${env.env_type}-${env.environment}`
      : env.environment
    const ids = scanBodyForTokens(env.request_body)
    if (ids.size > 0) sets.set(key, ids)
  }
  return sets
}

/**
 * Build the initial field_mappings array for useForm.
 * Merges DB records (token_mappings) with any {$N} tokens found in request bodies
 * that have no DB record yet — avoids empty modal on pre-S6 integrations.
 */
const buildInitialFieldMappings = (integration) => {
  const dbMappings = integration?.token_mappings?.map((tm) => ({
    field_id: tm.field_id,
    data_type: tm.data_type ?? 'string',
    default_value: tm.default_value ?? null,
    value_mapping: tm.value_mapping ?? null,
  })) ?? []

  const dbIds = new Set(dbMappings.map((m) => m.field_id))

  // Collect all field_ids found in request bodies across all environments
  const envSets = buildInitialEnvTokenSets(integration)
  for (const set of envSets.values()) {
    for (const fieldId of set) {
      if (!dbIds.has(fieldId)) {
        dbMappings.push({ field_id: fieldId, data_type: 'string', default_value: null, value_mapping: null })
        dbIds.add(fieldId)
      }
    }
  }

  return dbMappings
}

export const IntegrationsContext = createContext(null);

export const IntegrationsProvider = ({ children, integration = null }) => {
  const isEdit = !!integration;

  const initialEnvironments = useMemo(
    () => transformEnvironmentsForForm(integration?.environments, integration?.type),
    [integration?.environments, integration?.type],
  );

  const { data, setData, post, put, processing, errors, transform } = useForm({
    name: integration?.name ?? '',
    type: integration?.type ?? 'post-only',
    is_active: integration?.is_active ?? true,
    company_id: integration?.company_id ?? '',
    environments: initialEnvironments,
    field_mappings: buildInitialFieldMappings(integration),
    payload_transformer: integration?.payload_transformer ?? '',
    use_custom_transformer: integration?.use_custom_transformer ?? false,
  });

  // ── Segregated token sets per environment (not in useForm — local state only) ──
  // Map<envKey, Set<fieldId>>, where envKey = e.g. "ping-development" or "development"
  const [envTokenSets, setEnvTokenSets] = useState(() => buildInitialEnvTokenSets(integration))

  // ── Stable refs for always-fresh closure access (assigned during render) ──────
  const fieldMappingsRef = useRef(null)
  fieldMappingsRef.current = data.field_mappings

  const envTokenSetsRef = useRef(null)
  envTokenSetsRef.current = envTokenSets

  // Transform environments to array format before submission
  transform((formData) => ({
    ...formData,
    environments: serializeEnvs(formData.type, formData.environments),
  }));

  /**
   * Change a field inside a specific environment slot.
   * - For offerwall/post-only: handleEnvironmentChange('development', 'url', '...')
   * - For ping-post:          handleEnvironmentChange('development', 'url', '...', 'ping')
   */
  const handleEnvironmentChange = (env, field, value, envType = null) => {
    const nextState = produce(data, (draftState) => {
      if (envType) {
        draftState.environments[envType][env][field] = value;
      } else {
        draftState.environments[env][field] = value;
      }
    });
    setData(nextState);
  };

  /**
   * When the type changes, reset environments to a clean structure for the new type.
   */
  const handleTypeChange = (newType) => {
    setData({ ...data, type: newType, environments: buildEmptyEnvironments(newType) });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, preserveState: true };
    const url = isEdit ? route('integrations.update', integration.id) : route('integrations.store');
    isEdit ? put(url, options) : post(url, options);
  };

  /**
   * Called by JsonEditor when a field token is inserted into a request_body.
   * Adds the fieldId to the env's token set and registers it in field_mappings if new.
   *
   * @param {string} envKey  e.g. "ping-development" | "development"
   * @param {number} fieldId
   */
  const onTokenInsert = (envKey, fieldId) => {
    const prevSets = envTokenSetsRef.current
    const next = new Map(prevSets)
    const set = new Set(next.get(envKey) ?? [])
    set.add(fieldId)
    next.set(envKey, set)
    setEnvTokenSets(next)

    const current = fieldMappingsRef.current
    if (!current.some((m) => m.field_id === fieldId)) {
      setData('field_mappings', [...current, { field_id: fieldId, data_type: 'string', default_value: null, value_mapping: null }])
    }
  }

  /**
   * Called by JsonEditor when a field token pill is deleted from a request_body.
   * Removes the fieldId from the env's token set and purges it from field_mappings
   * if it no longer appears in any environment body.
   *
   * @param {string} envKey  e.g. "ping-development" | "development"
   * @param {number} fieldId
   */
  const onTokenRemove = (envKey, fieldId) => {
    const prevSets = envTokenSetsRef.current
    const next = new Map(prevSets)
    const set = new Set(next.get(envKey) ?? [])
    set.delete(fieldId)
    if (set.size === 0) next.delete(envKey)
    else next.set(envKey, set)
    setEnvTokenSets(next)

    const usedElsewhere = [...next.values()].some((s) => s.has(fieldId))
    if (!usedElsewhere) {
      setData('field_mappings', fieldMappingsRef.current.filter((m) => m.field_id !== fieldId))
    }
  }

  /**
   * Update the global config for a specific field mapping (data_type, default_value, value_mapping).
   *
   * @param {number} fieldId
   * @param {Partial<{ data_type: string, default_value: string|null, value_mapping: object|null }>} patch
   */
  const updateFieldMapping = (fieldId, patch) => {
    setData('field_mappings', fieldMappingsRef.current.map((m) =>
      m.field_id === fieldId ? { ...m, ...patch } : m,
    ))
  }

  /**
   * Update the hash config for a specific (envKey, fieldId) pair.
   * envKey format: "${envType}-${env}" for ping-post, "${env}" for flat.
   *
   * @param {string} envKey
   * @param {number} fieldId
   * @param {Partial<{ is_hashed: boolean, hash_algorithm: string|null, hmac_secret: string|null }>} patch
   */
  const updateFieldHash = (envKey, fieldId, patch) => {
    const nextState = produce(data, (draft) => {
      // Resolve the env slot from the envKey
      let envSlot
      if (envKey.includes('-')) {
        const dashIdx = envKey.indexOf('-')
        const envType = envKey.substring(0, dashIdx)
        const env = envKey.substring(dashIdx + 1)
        envSlot = draft.environments[envType]?.[env]
      } else {
        envSlot = draft.environments[envKey]
      }
      if (!envSlot) return

      const hashes = envSlot.field_hashes ?? []
      const idx = hashes.findIndex((h) => h.field_id === fieldId)
      if (idx >= 0) {
        Object.assign(hashes[idx], patch)
      } else {
        hashes.push({ field_id: fieldId, is_hashed: false, hash_algorithm: null, hmac_secret: null, ...patch })
      }
      envSlot.field_hashes = hashes
    })
    setData(nextState)
  }

  const value = {
    isEdit,
    data,
    errors,
    processing,
    handleEnvironmentChange,
    handleTypeChange,
    handleSubmit,
    setData,
    onTokenInsert,
    onTokenRemove,
    updateFieldMapping,
    updateFieldHash,
  };

  return <IntegrationsContext.Provider value={value}>{children}</IntegrationsContext.Provider>;
};
