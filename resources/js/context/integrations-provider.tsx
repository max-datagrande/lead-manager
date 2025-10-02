import { type EnvironmentDB, type EnvironmentForm, type EnvironmentType, type IntegrationForm } from '@/types/integrations';
import { useForm } from '@inertiajs/react';
import { produce } from 'immer';
import { createContext, useCallback, useMemo } from 'react';
import { route } from 'ziggy-js';

export const IntegrationsContext = createContext<any | null>(null);

// Helper to parse headers from a JSON string into a key-value array
const parseHeaders = (headersJson: string = '{}') => {
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
const transformEnvironmentsForForm = (environments = [] as EnvironmentDB[]) => {
  const defaultEnv = {
    url: '',
    method: 'POST',
    request_headers: [],
    request_body: '{ "key": "value" }',
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
    envs[currentEnv] = { ...env, request_headers };
  });
  return envs;
};

export const IntegrationsProvider = ({ children, integration = null }) => {
  const isEdit = !!integration;

  // Memoizar environments iniciales para evitar recalcular en cada render
  const initialEnvironments = useMemo(() => transformEnvironmentsForForm(integration?.environments), [integration?.environments]);

  const { data, setData, post, put, processing, errors } = useForm<IntegrationForm & { parser_config: any }>({
    name: integration?.name ?? '',
    type: integration?.type ?? 'post-only',
    is_active: integration?.is_active ?? true,
    company_id: integration?.company_id ?? '',
    environments: initialEnvironments,
    parser_config: integration?.parser_config ?? {
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
  });

  const handleEnvironmentChange = (env: EnvironmentType, field: keyof EnvironmentForm, value: any) => {
    const nextState = produce(data, (draftState) => {
      draftState.environments[env][field] = value;
    });
    setData(nextState);
  };

  const handleSubmit = (e: React.FormEvent) => {
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
