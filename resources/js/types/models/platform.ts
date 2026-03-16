import { type Company } from '@/types/models/company';

interface Platform {
  id: number;
  name: string;
  company_id: number | null;
  token_mappings: Record<string, string>;
  company?: Company | null;
  created_at: string;
}

interface InternalTokenOption {
  value: string;
  label: string;
}

interface IndexProps {
  platforms: Platform[];
  companies: Company[];
  internalTokens: InternalTokenOption[];
}

export type { IndexProps, InternalTokenOption, Platform };

