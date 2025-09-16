import { useForm } from '@inertiajs/react';
import { produce } from 'immer';
import { createContext, useContext } from 'react';
import { route } from 'ziggy-js';

// Helper to parse headers from a JSON string into a key-value array
const parseHeaders = (headersJson) => {
    try {
        const parsed = JSON.parse(headersJson || '{}');
        return Object.entries(parsed).map(([key, value]) => ({ key, value: String(value) }));
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
        request_body: '{ "key": "value" }',
        content_type: 'application/json',
        authentication_type: 'none',
    };

    const envs = {
        development: { ...defaultEnv },
        production: { ...defaultEnv },
    };

    environments.forEach(env => {
        if (env.environment === 'development' || env.environment === 'production') {
            envs[env.environment] = { ...env, request_headers: parseHeaders(env.request_headers) };
        }
    });
    return envs;
};

export const IntegrationsContext = createContext();

export const IntegrationsProvider = ({ children, integration = null }) => {
    const isEdit = !!integration;

    const { data, setData, post, put, processing, errors } = useForm({
        name: integration?.name ?? '',
        type: integration?.type ?? 'post-only',
        is_active: integration?.is_active ?? true,
        company_id: integration?.company_id ?? 1, // Placeholder
        environments: transformEnvironmentsForForm(integration?.environments),
    });

    const handleEnvironmentChange = (env, field, value) => {
        const nextState = produce(data, draftState => {
            draftState.environments[env][field] = value;
        });
        setData(nextState);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const transformedData = produce(data, draftState => {
            // Helper to reduce headers array to an object
            const reduceHeaders = (headers) => (headers || []).reduce((acc, curr) => {
                if (curr.key) acc[curr.key] = curr.value;
                return acc;
            }, {});

            if (draftState.environments.development) {
                draftState.environments.development.request_headers = JSON.stringify(reduceHeaders(draftState.environments.development.request_headers));
            }
            if (draftState.environments.production) {
                draftState.environments.production.request_headers = JSON.stringify(reduceHeaders(draftState.environments.production.request_headers));
            }
        });

        if (isEdit) {
            put(route('integrations.update', integration.id), {
                data: transformedData,
                preserveScroll: true,
            });
        } else {
            post(route('integrations.store'), {
                data: transformedData,
            });
        }
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