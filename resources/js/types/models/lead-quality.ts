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

export type ValidationLogStatusValue = 'pending' | 'sent' | 'verified' | 'failed' | 'expired' | 'skipped' | 'error';

export interface ValidationLogStatusOption {
  value: ValidationLogStatusValue;
  label: string;
}

export interface ValidationLogRow {
  id: number;
  status: ValidationLogStatusValue;
  status_label: string;
  result: string | null;
  attempts_count: number;
  fingerprint: string | null;
  challenge_reference: string | null;
  message: string | null;
  started_at: string | null;
  resolved_at: string | null;
  expires_at: string | null;
  created_at: string;
  rule: { id: number; name: string; validation_type: string | null } | null;
  provider: { id: number; name: string; type: string | null } | null;
  buyer: { id: number; name: string } | null;
}

export interface ValidationLogDetail extends ValidationLogRow {
  context: Record<string, unknown> | null;
  lead: { id: number; fingerprint: string | null } | null;
  lead_dispatch: { id: number; dispatch_uuid: string | null; status: string | null } | null;
  rule_detail: {
    id: number;
    name: string;
    validation_type: string | null;
    status: string | null;
    is_enabled: boolean;
  } | null;
  provider_detail: {
    id: number;
    name: string;
    type: string | null;
    status: string | null;
    is_enabled: boolean;
  } | null;
}

export interface ExternalRequestRow {
  id: number;
  operation: string | null;
  service_name: string | null;
  request_method: string;
  request_url: string;
  request_headers: Record<string, unknown> | null;
  request_body: Record<string, unknown> | null;
  response_status_code: number | null;
  response_headers: Record<string, unknown> | null;
  response_body: Record<string, unknown> | null;
  status: string;
  error_message: string | null;
  duration_ms: number | null;
  requested_at: string | null;
  responded_at: string | null;
}
