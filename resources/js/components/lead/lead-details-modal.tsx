import CopyToClipboard from '@/components/copy-to-clipboard';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { route } from 'ziggy-js';

interface LeadDetailsModalProps {
  fingerprint: string;
  trafficLogId?: string;
}

type QueryParam = { key: string; value: string };

export default function LeadDetailsModal({ fingerprint, trafficLogId }: LeadDetailsModalProps) {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [querySearch, setQuerySearch] = useState('');
  const [fieldSearch, setFieldSearch] = useState('');

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        // @ts-ignore
        const url = route('api.leads.details', fingerprint) + (trafficLogId ? `?traffic_log_id=${encodeURIComponent(trafficLogId)}` : '');
        const response = await fetch(url);

        if (!response.ok) {
          if (response.status === 404) {
            setError('No details found for this visitor.');
          } else {
            throw new Error('Failed to fetch visitor details');
          }
          return;
        }

        const result = await response.json();
        setData(result);
      } catch (err) {
        setError('Error loading details');
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    if (fingerprint) fetchData();
  }, [fingerprint, trafficLogId]);

  const queryParams = useMemo<QueryParam[]>(() => {
    const raw = data?.query_params;
    if (!raw || typeof raw !== 'object') return [];
    return Object.entries(raw).map(([key, value]) => ({
      key,
      value: value === null || value === undefined ? '' : typeof value === 'object' ? JSON.stringify(value) : String(value),
    }));
  }, [data]);

  const filteredQueryParams = useMemo(() => {
    const term = querySearch.toLowerCase();
    return queryParams
      .filter((param) => param.key.toLowerCase().includes(term) || param.value.toLowerCase().includes(term))
      .sort((a, b) => a.key.localeCompare(b.key));
  }, [queryParams, querySearch]);

  const filteredFields = useMemo(() => {
    if (!data?.fields) return [];
    const term = fieldSearch.toLowerCase();
    return data.fields
      .filter((field: any) => {
        return (
          field.label?.toLowerCase().includes(term) ||
          field.name?.toLowerCase().includes(term) ||
          field.value?.toString().toLowerCase().includes(term)
        );
      })
      .sort((a: any, b: any) => {
        const labelA = (a.label || a.name || '').toLowerCase();
        const labelB = (b.label || b.name || '').toLowerCase();
        return labelA.localeCompare(labelB);
      });
  }, [data, fieldSearch]);

  return (
    <>
      <DialogHeader>
        <DialogTitle>Visitor Details</DialogTitle>
        <DialogDescription>View the query params and lead form data for this visitor.</DialogDescription>
      </DialogHeader>

      <div className="flex-1 pr-2">
        {loading ? (
          <div className="flex h-40 items-center justify-center">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
          </div>
        ) : error ? (
          <div className="flex h-40 flex-col items-center justify-center space-y-2 text-center">
            <p className="font-medium text-destructive">{error}</p>
          </div>
        ) : data ? (
          <div className="space-y-6">
            {/* Basic Info */}
            <div className="space-y-2 rounded-lg bg-muted/50 p-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="block text-xs text-muted-foreground">Fingerprint</span>
                  <p className="truncate font-mono font-medium" title={data.visitor?.fingerprint}>
                    {data.visitor?.fingerprint}
                  </p>
                </div>
                <div>
                  <span className="block text-xs text-muted-foreground">Created At</span>
                  <p className="font-medium">{data.visitor?.created_at ? new Date(data.visitor.created_at).toLocaleString() : '—'}</p>
                </div>
              </div>
            </div>

            <Tabs defaultValue="query_params" className="space-y-4">
              <TabsList>
                <TabsTrigger value="query_params">Query Params</TabsTrigger>
                <TabsTrigger value="field_data">Field Data</TabsTrigger>
              </TabsList>

              {/* Query Params Tab */}
              <TabsContent value="query_params" className="space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                    Query Params ({filteredQueryParams.length})
                  </h4>
                </div>

                <div className="relative">
                  <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                  <Input placeholder="Search query params..." className="pl-9" value={querySearch} onChange={(e) => setQuerySearch(e.target.value)} />
                </div>

                {filteredQueryParams.length === 0 ? (
                  <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {querySearch ? 'No matching query params found.' : 'No query params recorded for this visit.'}
                  </div>
                ) : (
                  <div className="divide-y rounded-md border">
                    {filteredQueryParams.map((param) => (
                      <div key={param.key} className="flex items-start justify-between gap-4 p-3 transition-colors hover:bg-muted/30">
                        <CopyToClipboard textToCopy={param.key}>
                          <span className="shrink-0 truncate font-mono text-xs font-medium text-muted-foreground" title={param.key}>
                            {param.key}
                          </span>
                        </CopyToClipboard>
                        <span className="text-right text-sm font-medium wrap-break-word">{param.value || '—'}</span>
                      </div>
                    ))}
                  </div>
                )}
              </TabsContent>

              {/* Field Data Tab */}
              <TabsContent value="field_data" className="space-y-4">
                <div className="flex items-center justify-between">
                  <h4 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">Form Data ({filteredFields.length})</h4>
                </div>

                <div className="relative">
                  <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                  <Input placeholder="Search fields..." className="pl-9" value={fieldSearch} onChange={(e) => setFieldSearch(e.target.value)} />
                </div>

                {filteredFields.length === 0 ? (
                  <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {fieldSearch ? 'No matching fields found.' : 'No field data recorded for this lead.'}
                  </div>
                ) : (
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {filteredFields.map((field: any, index: number) => (
                      <div key={index} className="flex flex-col space-y-1 rounded-md border p-3 transition-colors hover:bg-muted/30">
                        <div className="flex items-center justify-between gap-2">
                          <CopyToClipboard textToCopy={field.name?.toString() || ''}>
                            <span className="truncate text-xs font-medium text-muted-foreground uppercase" title={field.label}>
                              {field.label || field.name}
                            </span>
                          </CopyToClipboard>
                          {field.id && <span className="shrink-0 font-mono text-[10px] text-muted-foreground/50">#{field.id}</span>}
                        </div>
                        <span className="text-sm font-medium wrap-break-word">{field.value}</span>
                      </div>
                    ))}
                  </div>
                )}
              </TabsContent>
            </Tabs>
          </div>
        ) : null}
      </div>
    </>
  );
}
