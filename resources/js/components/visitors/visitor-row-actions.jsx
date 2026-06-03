import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Braces, Check, Copy, Eye, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

// Ghost icon buttons only carry the base `disabled:opacity-50`, which is barely
// visible on a thin icon. Pin the enabled color to foreground and override the
// disabled state to a clearly muted grey so "no data" reads at a glance.
const ICON_BTN = 'relative h-6 w-6 text-foreground disabled:opacity-100 disabled:text-muted-foreground/40';

/**
 * Small notification-style count badge pinned to a button corner.
 */
function CountBadge({ count }) {
  return (
    <span className="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-none font-semibold text-primary-foreground">
      {count}
    </span>
  );
}

/**
 * Per-row actions for the visitors listing.
 *
 * Two independent buttons, each disabled (the disabled state IS the "no data"
 * indicator) and badged with its count:
 * - Query Params: opens a popover rendered straight from the row payload (the
 *   JSON is already present at list time, so no fetch).
 * - Field Data: opens the existing lead-details modal (which fetches on demand).
 */
export default function VisitorRowActions({ row, onOpenFields }) {
  const [search, setSearch] = useState('');
  const [copiedAll, setCopiedAll] = useState(false);

  // Copy every captured query param as a pretty JSON blob so it can be pasted
  // anywhere as-is. Lives at header level (top-right) instead of per-row.
  const handleCopyAll = async () => {
    try {
      await navigator.clipboard.writeText(JSON.stringify(row?.query_params ?? {}, null, 2));
      setCopiedAll(true);
      setTimeout(() => setCopiedAll(false), 2000);
    } catch (error) {
      console.error('Error copying query params:', error);
    }
  };

  const queryParams = useMemo(() => {
    const raw = row?.query_params;
    if (!raw || typeof raw !== 'object') return [];
    return Object.entries(raw).map(([key, value]) => ({
      key,
      value: value === null || value === undefined ? '' : typeof value === 'object' ? JSON.stringify(value) : String(value),
    }));
  }, [row?.query_params]);

  const filteredParams = useMemo(() => {
    const term = search.toLowerCase();
    return queryParams
      .filter((param) => param.key.toLowerCase().includes(term) || param.value.toLowerCase().includes(term))
      .sort((a, b) => a.key.localeCompare(b.key));
  }, [queryParams, search]);

  const queryParamsCount = queryParams.length;
  const fieldDataCount = row?.field_data_count ?? 0;

  return (
    <div className="flex items-center gap-1">
      {/* Query Params -> popover (data already on the row) */}
      <Popover>
        <Tooltip>
          <TooltipTrigger asChild>
            {/* span wrapper keeps the tooltip working even when the button is disabled */}
            <span className={`inline-flex ${queryParamsCount === 0 ? 'cursor-not-allowed' : ''}`}>
              <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className={ICON_BTN} disabled={queryParamsCount === 0}>
                  <Braces className="h-4 w-4" />
                  {queryParamsCount > 0 && <CountBadge count={queryParamsCount} />}
                </Button>
              </PopoverTrigger>
            </span>
          </TooltipTrigger>
          <TooltipContent>{queryParamsCount > 0 ? 'View query params' : 'No query params'}</TooltipContent>
        </Tooltip>

        <PopoverContent align="start" className="w-80 p-0">
          <div className="space-y-3 p-3">
            <div className="flex items-center justify-between gap-2">
              <h4 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">Query Params ({queryParamsCount})</h4>
              <Button
                variant="ghost"
                size="icon"
                className="h-6 w-6 shrink-0"
                onClick={handleCopyAll}
                title={copiedAll ? 'Copied!' : 'Copy all as JSON'}
              >
                {copiedAll ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
              </Button>
            </div>
            {queryParamsCount > 4 && (
              <div className="relative">
                <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Search params..." className="h-8 pl-9" value={search} onChange={(e) => setSearch(e.target.value)} />
              </div>
            )}
            <div className="max-h-64 divide-y overflow-y-auto rounded-md border">
              {filteredParams.length === 0 ? (
                <div className="p-4 text-center text-xs text-muted-foreground">No matching params.</div>
              ) : (
                filteredParams.map((param) => (
                  <div key={param.key} className="flex items-center gap-3 p-2 transition-colors hover:bg-muted/30">
                    {/* CSS-truncated (not JS): the full text stays in the DOM, so a
                        double-click selects and copies the whole value. */}
                    <span className="w-28 shrink-0 truncate font-mono text-xs font-medium text-muted-foreground" title={param.key}>
                      {param.key}
                    </span>
                    <span className="min-w-0 flex-1 truncate text-xs font-medium" title={param.value}>
                      {param.value || '—'}
                    </span>
                  </div>
                ))
              )}
            </div>
          </div>
        </PopoverContent>
      </Popover>

      {/* Field Data -> existing lead-details modal */}
      <Tooltip>
        <TooltipTrigger asChild>
          <span className={`inline-flex ${fieldDataCount === 0 ? 'cursor-not-allowed' : ''}`}>
            <Button variant="ghost" size="icon" className={ICON_BTN} disabled={fieldDataCount === 0} onClick={() => onOpenFields?.(row)}>
              <Eye className="h-4 w-4" />
              {fieldDataCount > 0 && <CountBadge count={fieldDataCount} />}
            </Button>
          </span>
        </TooltipTrigger>
        <TooltipContent>{fieldDataCount > 0 ? 'View field data' : 'No field data'}</TooltipContent>
      </Tooltip>
    </div>
  );
}
