import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useLeadQualityRules } from '@/hooks/use-lead-quality-rules';
import type { RuleRow, RuleStatusValue } from '@/types/models/lead-quality';
import { formatDateTime } from '@/utils/table';
import { AlertCircle, Edit, Trash2 } from 'lucide-react';

const STATUS_VARIANT: Record<RuleStatusValue, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  active: 'default',
  draft: 'outline',
  inactive: 'secondary',
};

export function TableRules({ entries }: { entries: RuleRow[] }) {
  const { goToEdit, showDeleteModal } = useLeadQualityRules();

  if (entries.length === 0) {
    return (
      <div className="rounded-md border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
        No validation rules yet. Create one and link it to the buyers that require it.
      </div>
    );
  }

  return (
    <TooltipProvider delayDuration={200}>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Rule</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Provider</TableHead>
              <TableHead>Buyers</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Updated</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {entries.map((row) => (
              <TableRow key={row.id}>
                <TableCell>
                  <div className="font-medium">{row.name}</div>
                  {row.description && <div className="line-clamp-1 text-xs text-muted-foreground">{row.description}</div>}
                </TableCell>
                <TableCell>
                  <Badge variant="outline">{row.validation_type_label}</Badge>
                </TableCell>
                <TableCell>
                  {row.provider ? (
                    <div className="flex items-center gap-1.5">
                      <span className="text-sm">{row.provider.name}</span>
                      {!row.provider.is_usable && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <AlertCircle className="h-3.5 w-3.5 text-amber-500" />
                          </TooltipTrigger>
                          <TooltipContent>Provider is not usable (inactive or disabled).</TooltipContent>
                        </Tooltip>
                      )}
                    </div>
                  ) : (
                    <span className="text-xs text-muted-foreground">—</span>
                  )}
                </TableCell>
                <TableCell>
                  {row.buyers.length === 0 ? (
                    <span className="text-xs text-muted-foreground">unassigned</span>
                  ) : (
                    <div className="flex flex-wrap gap-1">
                      {row.buyers.slice(0, 3).map((b) => (
                        <Badge key={b.id} variant="secondary" className="font-normal">
                          {b.name}
                        </Badge>
                      ))}
                      {row.buyers.length > 3 && (
                        <Tooltip>
                          <TooltipTrigger asChild>
                            <Badge variant="outline" className="cursor-help">
                              +{row.buyers.length - 3}
                            </Badge>
                          </TooltipTrigger>
                          <TooltipContent>
                            <div className="flex flex-col gap-1">
                              {row.buyers.slice(3).map((b) => (
                                <span key={b.id}>{b.name}</span>
                              ))}
                            </div>
                          </TooltipContent>
                        </Tooltip>
                      )}
                    </div>
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Badge variant={STATUS_VARIANT[row.status]} className="capitalize">
                      {row.status}
                    </Badge>
                    {!row.is_enabled && <span className="text-xs text-muted-foreground">not enabled</span>}
                  </div>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">{formatDateTime(row.updated_at)}</TableCell>
                <TableCell>
                  <div className="flex items-center justify-end gap-1">
                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => goToEdit(row)}>
                      <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                      onClick={() => showDeleteModal(row)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    </TooltipProvider>
  );
}
