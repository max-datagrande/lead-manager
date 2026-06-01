export type LeadColumnSource = 'field' | 'traffic' | 'meta';

export interface ColumnDescriptor {
  key: string;
  label: string;
  source: LeadColumnSource;
  reference: string;
}

export interface LandingPageVersionRef {
  id: number;
  path: string;
  name?: string;
}

export interface LeadRow {
  id: number;
  created_at: string | null;
  version: LandingPageVersionRef | null;
  values: Record<string, string | null>;
}

export interface LeadsViewerData {
  landing_page: { id: number; name: string; url: string };
  descriptors: ColumnDescriptor[];
  versions: LandingPageVersionRef[];
  selected_versions: number[];
  using_defaults: boolean;
}
