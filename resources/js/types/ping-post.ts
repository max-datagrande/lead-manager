import type { EnvironmentDB } from './integrations'

export interface Company {
  id: number
  name: string
}

export interface Integration {
  id: number
  name: string
  type: 'ping-post' | 'post-only' | 'offerwall'
  is_active: boolean
  company_id: number | null
  environments?: EnvironmentDB[]
}

export interface BuyerConfig {
  id: number
  integration_id: number
  ping_timeout_ms: number
  post_timeout_ms: number
  pricing_type: 'fixed' | 'min_bid' | 'conditional' | 'postback'
  fixed_price: number | null
  min_bid: number | null
  conditional_pricing_rules: Array<{ conditions: Array<{ field: string; op: string; value: any }>; price: number }> | null
  postback_pending_days: number
}

export interface EligibilityRule {
  id?: number
  integration_id?: number
  field: string
  operator: 'eq' | 'neq' | 'gt' | 'gte' | 'lt' | 'lte' | 'in' | 'not_in'
  value: string | string[]
  sort_order: number
}

export interface CapRule {
  id?: number
  integration_id?: number
  period: 'day' | 'week' | 'month' | 'year'
  max_leads: number | null
  max_revenue: number | null
}

export interface Buyer {
  id: number
  name: string
  integration_id: number
  is_active: boolean
  company_id: number | null
  company?: Company | null
  integration?: Integration | null
  buyerConfig?: BuyerConfig | null
  eligibilityRules?: EligibilityRule[]
  capRules?: CapRule[]
  created_at: string
  updated_at: string
}

export interface WorkflowBuyer {
  id?: number
  workflow_id?: number
  buyer_id?: number
  integration_id: number
  position: number
  is_fallback: boolean
  buyer_group: 'primary' | 'secondary'
  is_active: boolean
  integration?: Integration
  buyer?: Buyer
}

export interface Workflow {
  id: number
  name: string
  execution_mode: 'sync' | 'async'
  strategy: 'best_bid' | 'waterfall' | 'combined'
  global_timeout_ms: number
  is_active: boolean
  user_id: number
  cascade_on_post_rejection: boolean
  cascade_max_retries: number
  advance_on_rejection: boolean
  advance_on_timeout: boolean
  advance_on_error: boolean
  workflow_buyers?: WorkflowBuyer[]
  workflow_buyers_count?: number
  user?: { id: number; name: string }
  created_at: string
  updated_at: string
}

export interface Lead {
  id: number
  fingerprint: string
  created_at: string
}

export interface PingResult {
  id: number
  lead_dispatch_id: number
  integration_id: number
  idempotency_key: string
  status: 'skipped' | 'duplicate' | 'ineligible' | 'cap_exceeded' | 'accepted' | 'rejected' | 'timeout' | 'error'
  bid_price: number | null
  http_status_code: number | null
  request_url: string | null
  request_payload: Record<string, any> | null
  request_headers: Record<string, any> | null
  response_body: Record<string, any> | null
  duration_ms: number | null
  skip_reason: string | null
  attempt_count: number
  integration?: Integration
  created_at: string
}

export interface PostResult {
  id: number
  lead_dispatch_id: number
  ping_result_id: number | null
  integration_id: number
  status: 'posted' | 'accepted' | 'rejected' | 'error' | 'timeout' | 'retry_queued' | 'pending_postback' | 'postback_resolved' | 'skipped'
  price_offered: number | null
  price_final: number | null
  http_status_code: number | null
  request_url: string | null
  request_payload: Record<string, any> | null
  request_headers: Record<string, any> | null
  response_body: Record<string, any> | null
  duration_ms: number | null
  rejection_reason: string | null
  attempt_count: number
  postback_received_at: string | null
  postback_expires_at: string | null
  integration?: Integration
  pingResult?: PingResult | null
  created_at: string
}

export interface LeadDispatch {
  id: number
  dispatch_uuid: string
  workflow_id: number
  lead_id: number
  fingerprint: string
  status: 'pending' | 'running' | 'sold' | 'not_sold' | 'error' | 'timeout'
  strategy_used: string
  winner_integration_id: number | null
  final_price: number | null
  fallback_activated: boolean
  total_duration_ms: number | null
  error_message: string | null
  started_at: string | null
  completed_at: string | null
  workflow?: Workflow
  lead?: Lead
  winnerIntegration?: Integration | null
  pingResults?: PingResult[]
  postResults?: PostResult[]
  created_at: string
  updated_at: string
}

export interface PricingTypeOption {
  value: string
  label: string
}

export interface StrategyOption {
  value: string
  label: string
}
