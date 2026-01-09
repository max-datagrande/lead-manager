import { useForm } from '@inertiajs/react';
import { produce } from 'immer';
import { createContext, useMemo } from 'react';
import { route } from 'ziggy-js';

// Helper to parse headers from a JSON string into a key-value array
const parseHeaders = (headersJson = '{}') => {
  try {
    const parsed = JSON.parse(headersJson);
    const entries = Object.entries(parsed);
    if (entries.length === 0) {
      return [];
    }
    // Convert all values to strings
    const pairs = entries.map(([key, value]) => ({ key, value: String(value) }));
    return pairs;
  } catch (e) {
    console.error('Failed to parse request headers:', e);
    return [];
  }
};

// Helper to transform environments from the server for the form
const transformEnvironmentsForForm = (environments = []) => {
  const defaultEnv = {
    url: '',
    method: 'POST',
    request_headers: [],
    request_body: { template: '', parsers: {} }, // Updated structure
  };

  const envs = {
    development: { ...defaultEnv },
    production: { ...defaultEnv },
  };

  environments.forEach((env) => {
    const isValidEnv = env.environment === 'development' || env.environment === 'production';
    if (!isValidEnv) {
      console.warn(`Invalid environment: ${env.environment}`);
      return;
    }
    const currentEnv = env.environment;
    const request_headers = parseHeaders(env.request_headers);
    // Ensure request_body is an object, not a string
    const request_body = typeof env.request_body === 'string' ? JSON.parse(env.request_body) : env.request_body;

    envs[currentEnv] = { ...env, request_headers, request_body };
  });
  return envs;
};

export const IntegrationsContext = createContext(null);

export const IntegrationsProvider = ({ children, integration = null }) => {
  const isEdit = !!integration;

  const initialEnvironments = useMemo(() => transformEnvironmentsForForm(integration?.environments), [integration?.environments]);

  const { data, setData, post, put, processing, errors } = useForm({
    name: integration?.name ?? '',
    type: integration?.type ?? 'post-only',
    is_active: integration?.is_active ?? true,
    company_id: integration?.company_id ?? '',
    environments: initialEnvironments,
    response_parser_config: integration?.response_parser_config ?? {
      offer_list_path: '',
      mapping: {
        title: '',
        description: '',
        logo_url: '',
        click_url: '',
        impression_url: '',
        cpc: '',
        display_name: '',
      },
    },
    request_mapping_config: integration?.request_mapping_config ?? {},
    payload_transformer: integration?.payload_transformer ?? '',
    use_custom_transformer: integration?.use_custom_transformer ?? false,
  });

  const handleEnvironmentChange = (env, field, value) => {
    const nextState = produce(data, (draftState) => {
      draftState.environments[env][field] = value;
    });
    setData(nextState);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const options = {
      preserveScroll: true,
      preserveState: true,
    };
    const url = isEdit ? route('integrations.update', integration.id) : route('integrations.store');
    isEdit ? put(url, options) : post(url, options);
  };

  const value = {
    isEdit,
    data,
    errors,
    processing,
    handleEnvironmentChange,
    handleSubmit,
    setData,
  };

  return <IntegrationsContext.Provider value={value}>{children}</IntegrationsContext.Provider>;
};
