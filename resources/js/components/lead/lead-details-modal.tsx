import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Loader2, Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';

interface LeadDetailsModalProps {
  fingerprint: string;
}

export default function LeadDetailsModal({ fingerprint }: LeadDetailsModalProps) {
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<any>(null);
  const [error, setError] = useState<string | null>(null);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    const fetchData = async () => {
      try {
        setLoading(true);
        setError(null);
        // @ts-ignore
        const url = route('api.leads.details', fingerprint);
        const response = await fetch(url);

        if (!response.ok) {
          if (response.status === 404) {
            setError('Lead not found for this visitor.');
          } else {
            throw new Error('Failed to fetch lead details');
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
  }, [fingerprint]);

  const filteredFields = data?.fields
    ? data.fields
        .filter((field: any) => {
          const term = searchTerm.toLowerCase();
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
        })
    : [];

  return (
    <>
      <DialogHeader>
        <DialogTitle>Lead Details</DialogTitle>
        <DialogDescription>View detailed information about this lead.</DialogDescription>
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
                  <p className="truncate font-mono font-medium" title={data.lead.fingerprint}>
                    {data.lead.fingerprint}
                  </p>
                </div>
                <div>
                  <span className="block text-xs text-muted-foreground">Created At</span>
                  <p className="font-medium">{new Date(data.lead.created_at).toLocaleString()}</p>
                </div>
              </div>
            </div>

            {/* Fields Section */}
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">Form Data ({filteredFields.length})</h4>
              </div>

              <div className="relative">
                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search fields..." className="pl-9" value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} />
              </div>

              {filteredFields.length === 0 ? (
                <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                  {searchTerm ? 'No matching fields found.' : 'No field data recorded for this lead.'}
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {filteredFields.map((field: any, index: number) => (
                    <div key={index} className="flex flex-col space-y-1 rounded-md border p-3 transition-colors hover:bg-muted/30">
                      <div className="flex items-center justify-between gap-2">
                        <span className="truncate text-xs font-medium text-muted-foreground uppercase" title={field.label}>
                          {field.label || field.name}
                        </span>
                        {field.id && <span className="shrink-0 font-mono text-[10px] text-muted-foreground/50">#{field.id}</span>}
                      </div>
                      <span className="text-sm font-medium break-words">{field.value}</span>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        ) : null}
      </div>
    </>
  );
}
