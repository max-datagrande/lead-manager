import { useForm } from '@inertiajs/react';
import { produce } from 'immer';
import { createContext, useMemo } from 'react';
import { route } from 'ziggy-js';

const defaultEnv = () => ({
  url: '',
  method: 'POST',
  request_headers: [],
  request_body: { template: '', parsers: {} },
  response_config: null,
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
    request_mapping_config: integration?.request_mapping_config ?? {},
    payload_transformer: integration?.payload_transformer ?? '',
    use_custom_transformer: integration?.use_custom_transformer ?? false,
  });

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

  const value = {
    isEdit,
    data,
    errors,
    processing,
    handleEnvironmentChange,
    handleTypeChange,
    handleSubmit,
    setData,
  };

  return <IntegrationsContext.Provider value={value}>{children}</IntegrationsContext.Provider>;
};
