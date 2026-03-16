export type PostbackExecution = {
  id: number
  execution_uuid: string
  postback_id: number
  status: 'pending' | 'dispatching' | 'completed' | 'failed' | 'skipped'
  inbound_params: Record<string, string> | null
  resolved_tokens: Record<string, string> | null
  outbound_url: string | null
  ip_address: string | null
  user_agent: string | null
  attempts: number
  max_attempts: number
  next_retry_at: string | null
  dispatched_at: string | null
  completed_at: string | null
  idempotency_key: string
  created_at: string
  updated_at: string
  postback: {
    id: number
    name: string
    fire_mode: 'realtime' | 'deferred'
  } | null
}

export type DispatchLog = {
  id: number
  execution_id: number
  attempt_number: number
  request_url: string
  request_method: string
  request_headers: Record<string, string[]> | null
  response_status_code: number | null
  response_body: string | null
  response_headers: Record<string, string[]> | null
  response_time_ms: number | null
  error_message: string | null
  created_at: string
}
