export const API_ROUTES = {
  VISITOR: {
    REGISTER: '/v1/visitor/register',
  },
  LEADS: {
    REGISTER: '/v1/leads/register',
    UPDATE: '/v1/leads/update',
  },
  OFFERWALL: {
    TRIGGER: '/v1/offerwall/mix/', // + ID
    CONVERSION: '/v1/offerwall/events/conversion',
  },
  SHARE_LEADS: {
    DISPATCH: '/v1/share-leads/dispatch/', // + workflow ID
  },
  LEAD_QUALITY: {
    CHALLENGE_SEND: '/v1/lead-quality/challenge/send',
    CHALLENGE_VERIFY: '/v1/lead-quality/challenge/verify',
  },
  METRICS: {
    PERFORMANCE: '/v1/metrics/performance',
  },
} as const;
