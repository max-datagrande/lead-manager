export type BuyerEventStage = 'pre_dispatch' | 'ping' | 'post';

export type BuyerEventSource = 'post_result' | 'ping_result' | 'buyer_event';

export interface BuyerEventRow {
  source: BuyerEventSource;
  stage: BuyerEventStage;
  id: number;
  lead_dispatch_id: number;
  integration_id: number | null;
  event_type: string;
  reason: string | null;
  ping_bid: string | number | null;
  post_bid: string | number | null;
  final_payout: string | number | null;
  http_status_code: number | null;
  duration_ms: number | null;
  created_at: string;
  dispatch_uuid: string | null;
  fingerprint: string | null;
  workflow_id: number | null;
  integration_name: string | null;
  company_id: number | null;
  company_name: string | null;
  workflow_name: string | null;
}
