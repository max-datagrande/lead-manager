import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLeadQualityProviders } from '@/hooks/use-lead-quality-providers';
import type { ProviderRow, ProviderStatusValue } from '@/types/models/lead-quality';
import { formatDateTime } from '@/utils/table';
import { Edit, Trash2 } from 'lucide-react';

const STATUS_VARIANT: Record<ProviderStatusValue, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  active: 'default',
  inactive: 'secondary',
  disabled: 'destructive',
};

export function TableProviders({ entries }: { entries: ProviderRow[] }) {
  const { goToEdit, showDeleteModal } = useLeadQualityProviders();

  if (entries.length === 0) {
    return (
      <div className="rounded-md border bg-muted/20 p-8 text-center text-sm text-muted-foreground">
        No providers yet. Add your first one to start configuring validation rules.
      </div>
    );
  }

  return (
    <div className="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Environment</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Rules</TableHead>
            <TableHead>Updated</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {entries.map((row) => (
            <TableRow key={row.id}>
              <TableCell>
                <div className="font-medium">{row.name}</div>
                {row.notes && <div className="line-clamp-1 text-xs text-muted-foreground">{row.notes}</div>}
              </TableCell>
              <TableCell>
                <Badge variant="outline">{row.type_label}</Badge>
              </TableCell>
              <TableCell>
                <Badge variant="secondary" className="capitalize">
                  {row.environment}
                </Badge>
              </TableCell>
              <TableCell>
                <div className="flex items-center gap-2">
                  <Badge variant={STATUS_VARIANT[row.status]} className="capitalize">
                    {row.status}
                  </Badge>
                  {!row.is_enabled && <span className="text-xs text-muted-foreground">not enabled</span>}
                </div>
              </TableCell>
              <TableCell>
                <span className="font-mono text-sm">{row.validation_rules_count}</span>
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
  );
}
