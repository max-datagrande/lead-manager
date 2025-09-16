/* Environment types */
type EnvironmentType = 'development' | 'production';
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
  environments: EnvironmentDB[];
}

interface IntegrationForm extends IntegrationBase {
  environments: {
    development: Partial<EnvironmentForm>;
    production: Partial<EnvironmentForm>;
  };
}


export type { EnvironmentDB, EnvironmentForm, EnvironmentType, IntegrationBase, IntegrationDB, IntegrationForm };
