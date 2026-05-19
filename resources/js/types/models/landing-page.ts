export type LandingPageColumnSource = 'field' | 'traffic';

export interface LandingPageColumn {
  source: LandingPageColumnSource;
  reference: string;
}

export interface ColumnCatalogItem {
  id: number;
  name: string;
  label: string;
  group: string;
}

export interface AvailableColumns {
  fields: ColumnCatalogItem[];
  traffic: ColumnCatalogItem[];
}

export interface Vertical {
  id: number;
  name: string;
}

export interface Company {
  id: number;
  name: string;
}

export interface LandingPage {
  id: number;
  name: string;
  url: string;
  is_external: boolean;
  vertical_id: number;
  company_id: number | null;
  active: boolean;
  vertical?: Vertical;
  company?: Company | null;
  columns?: LandingPageColumn[];
  created_at: string;
  updated_at: string;
}
