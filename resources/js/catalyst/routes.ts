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
} as const;
