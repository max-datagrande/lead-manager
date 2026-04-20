export type ProviderStatusValue = 'active' | 'inactive' | 'disabled';
export type ProviderEnvironmentValue = 'production' | 'sandbox' | 'test';

export interface ProviderTypeOption {
  value: string;
  label: string;
  is_implemented: boolean;
}

export interface ProviderStatusOption {
  value: ProviderStatusValue;
  label: string;
}

export interface EnvironmentOption {
  value: ProviderEnvironmentValue;
  label: string;
}

export interface ProviderRow {
  id: number;
  name: string;
  type: string;
  type_label: string;
  status: ProviderStatusValue;
  is_enabled: boolean;
  environment: ProviderEnvironmentValue;
  notes: string | null;
  validation_rules_count: number;
  created_at: string;
  updated_at: string;
  creator?: { id: number; name: string } | null;
}

export interface ProviderDetail {
  id: number;
  name: string;
  type: string;
  status: ProviderStatusValue;
  is_enabled: boolean;
  environment: ProviderEnvironmentValue;
  credentials: Record<string, string>;
  settings: Record<string, string | number | boolean | null | string[]>;
  notes: string | null;
}
