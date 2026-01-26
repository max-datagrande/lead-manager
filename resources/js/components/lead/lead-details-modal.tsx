import { DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
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
            <Loader2 className="text-primary h-8 w-8 animate-spin" />
          </div>
        ) : error ? (
          <div className="flex h-40 flex-col items-center justify-center space-y-2 text-center">
            <p className="text-destructive font-medium">{error}</p>
          </div>
        ) : data ? (
          <div className="space-y-6">
            {/* Basic Info */}
            <div className="bg-muted/50 rounded-lg p-4 space-y-2">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground block text-xs">Fingerprint</span>
                  <p className="font-mono truncate font-medium" title={data.lead.fingerprint}>
                    {data.lead.fingerprint}
                  </p>
                </div>
                <div>
                  <span className="text-muted-foreground block text-xs">Created At</span>
                  <p className="font-medium">{new Date(data.lead.created_at).toLocaleString()}</p>
                </div>
              </div>
            </div>

            {/* Fields Section */}
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <h4 className="text-muted-foreground text-sm font-semibold tracking-wider uppercase">
                  Form Data ({filteredFields.length})
                </h4>
              </div>

              <div className="relative">
                <Search className="text-muted-foreground absolute top-2.5 left-2.5 h-4 w-4" />
                <Input
                  placeholder="Search fields..."
                  className="pl-9"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                />
              </div>

              {filteredFields.length === 0 ? (
                <div className="text-muted-foreground rounded-lg border border-dashed p-8 text-center text-sm">
                  {searchTerm ? 'No matching fields found.' : 'No field data recorded for this lead.'}
                </div>
              ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  {filteredFields.map((field: any, index: number) => (
                    <div
                      key={index}
                      className="hover:bg-muted/30 flex flex-col space-y-1 rounded-md border p-3 transition-colors"
                    >
                      <div className="flex items-center justify-between gap-2">
                        <span
                          className="text-muted-foreground truncate text-xs font-medium uppercase"
                          title={field.label}
                        >
                          {field.label || field.name}
                        </span>
                        {field.id && (
                          <span className="text-muted-foreground/50 shrink-0 font-mono text-[10px]">
                            #{field.id}
                          </span>
                        )}
                      </div>
                      <span className="break-words text-sm font-medium">{field.value}</span>
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
