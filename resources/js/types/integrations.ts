/* Environment types */
type EnvironmentType = 'development' | 'production';
type EnvType = 'ping' | 'post' | 'offerwall';

interface EnvironmentBase {
  url: string;
  method: string;
  request_body: string;
}

interface EnvironmentForm extends EnvironmentBase {
  request_headers: Array<{ key: string; value: string }>;
}

interface EnvironmentDB extends EnvironmentBase {
  id: number;
  integration_id: number;
  env_type: EnvType;
  request_headers: string;
  environment: EnvironmentType;
  update_at: string;
}

interface IntegrationBase {
  name: string;
  type: string;
  is_active: boolean;
  company_id: number;
}
interface IntegrationDB extends IntegrationBase {
  id: number;
  environments: EnvironmentDB[];
}

/** Flat structure used by offerwall and post-only forms */
type FlatEnvironments = {
  development: Partial<EnvironmentForm>;
  production: Partial<EnvironmentForm>;
};

/** Nested structure used by ping-post forms (ping/post × dev/prod) */
type PingPostEnvironments = {
  ping: FlatEnvironments;
  post: FlatEnvironments;
};

interface IntegrationForm extends IntegrationBase {
  environments: FlatEnvironments | PingPostEnvironments;
  parser_config: {
    offer_list_path: string;
    mapping: {
      title: string;
      description: string;
      logo_url: string;
      click_url: string;
      impression_url: string;
      cpc: string;
      display_name: string;
    };
  };
}

interface EnvironmentTabProps {
  env: 'development' | 'production';
  envType?: 'ping' | 'post' | null;
  fields?: any[];
}

export type {
  EnvType,
  EnvironmentDB,
  EnvironmentForm,
  EnvironmentType,
  FlatEnvironments,
  IntegrationBase,
  IntegrationDB,
  IntegrationForm,
  PingPostEnvironments,
  EnvironmentTabProps,
};
