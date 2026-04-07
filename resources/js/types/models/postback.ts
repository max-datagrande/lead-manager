import { type Platform } from '@/types/models/platform';

export interface Postback {
  id: number;
  name: string;
  type: 'external' | 'internal';
  platform_id: number | null;
  base_url: string;
  param_mappings: Record<string, string>;
  result_url: string;
  generated_url: string;
  fire_mode: string;
  is_active: boolean;
  is_public: boolean;
  created_at: string;
  updated_at: string;
  platform?: Platform | null;
}

export interface FireModeOption {
  value: string;
  label: string;
}

export interface DomainOption {
  value: string;
  label: string;
  url: string;
}

export interface PostbackTypeOption {
  value: string;
  label: string;
}
