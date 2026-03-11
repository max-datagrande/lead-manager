import { type Company } from '@/types/models/company';

interface Platform {
  id: number;
  name: string;
  company_id: number | null;
  tokens: string[];
  company?: Company | null;
  created_at: string;
}

interface IndexProps {
  platforms: Platform[];
  companies: Company[];
}

export type { IndexProps, Platform };

