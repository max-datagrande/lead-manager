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

export type RuleStatusValue = 'draft' | 'active' | 'inactive';

export interface ValidationTypeOption {
  value: string;
  label: string;
  is_async: boolean;
}

export interface RuleStatusOption {
  value: RuleStatusValue;
  label: string;
}

export interface ProviderOption {
  id: number;
  name: string;
  type: string;
  type_label: string;
  is_usable: boolean;
}

export interface BuyerOption {
  id: number;
  name: string;
  type: string;
}

export interface RuleBuyerBadge {
  id: number;
  name: string;
  type: string;
  is_enabled: boolean;
}

export interface RuleRow {
  id: number;
  name: string;
  slug: string;
  validation_type: string;
  validation_type_label: string;
  status: RuleStatusValue;
  is_enabled: boolean;
  description: string | null;
  priority: number;
  provider: { id: number; name: string; type: string; is_usable: boolean } | null;
  buyers: RuleBuyerBadge[];
  buyers_count: number;
  updated_at: string;
}

export interface RuleDetail {
  id: number;
  name: string;
  slug: string;
  validation_type: string;
  provider_id: number;
  status: RuleStatusValue;
  is_enabled: boolean;
  description: string | null;
  settings: Record<string, string | number | boolean | null>;
  priority: number;
  buyer_ids: number[];
}
