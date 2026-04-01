/* Environment types */
type EnvironmentType = 'development' | 'production';
type EnvType = 'ping' | 'post' | 'offerwall';

interface EnvironmentBase {
  url: string;
  method: string;
  request_body: string;
}

interface OfferwallResponseConfig {
  offer_list_path?: string;
  mapping?: Record<string, string>;
  fallbacks?: Record<string, string>;
}

interface PingResponseConfig {
  bid_price_path?: string;
  accepted_path?: string;
  accepted_value?: string;
  lead_id_path?: string;
}

interface PostResponseConfig {
  accepted_path?: string;
  accepted_value?: string;
  rejected_path?: string;
}

type ResponseConfig = OfferwallResponseConfig | PingResponseConfig | PostResponseConfig;

interface EnvironmentForm extends EnvironmentBase {
  request_headers: Array<{ key: string; value: string }>;
  response_config?: ResponseConfig | null;
}

interface ResponseConfigField {
  label: string;
  hint: string;
  value: string | Record<string, string | null> | null;
}

interface EnvironmentDB extends EnvironmentBase {
  id: number;
  integration_id: number;
  env_type: EnvType;
  request_headers: string;
  environment: EnvironmentType;
  response_config?: ResponseConfig | null;
  response_config_fields?: Record<string, ResponseConfigField>;
  update_at: string;
}

interface MappingEntry {
  type?: string;
  defaultValue?: string;
  value_mapping?: Record<string, string>;
}

interface IntegrationBase {
  name: string;
  type: string;
  is_active: boolean;
  company_id: number;
}
interface TokenMapping {
  id: number;
  integration_id: number;
  field_id: number;
  data_type: string;
  default_value: string | null;
  value_mapping: Record<string, string> | null;
}

interface FieldMappingEntry {
  field_id: number;
  data_type: string;
  default_value: string | null;
  value_mapping?: Record<string, string> | null;
}

interface IntegrationDB extends IntegrationBase {
  id: number;
  environments: EnvironmentDB[];
  request_mapping_config?: Record<string, MappingEntry>;
  token_mappings?: TokenMapping[];
  updated_at?: string;
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
  FieldMappingEntry,
  FlatEnvironments,
  IntegrationBase,
  IntegrationDB,
  IntegrationForm,
  MappingEntry,
  PingPostEnvironments,
  TokenMapping,
  EnvironmentTabProps,
  OfferwallResponseConfig,
  PingResponseConfig,
  PostResponseConfig,
  ResponseConfig,
  ResponseConfigField
};
