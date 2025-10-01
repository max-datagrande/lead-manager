export { createPostbackColumns } from './list-columns';
export { default as TablePostbacks } from './table-postbacks';
export { PostbackApiRequestsViewer } from './postback-api-requests-viewer';

export type Postback = {
  id: number;
  vendor: string;
  click_id: string;
  payout: string;
  transaction_id: string | null;
  currency: string;
  event: string;
  offer_id: string;
  status: string;
  response_data: {
    postback_id: number;
    click_id: string;
    payout: number;
    total_time_ms: number;
    response_time_ms: number;
  };
  processed_at: string;
  created_at: string;
  updated_at: string;
  message: string;
  external_campaign_id: string | null;
  external_traffic_source: string | null;
};
