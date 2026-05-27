import { Card, CardContent } from '@/components/ui/card';
import JsonViewer from '@/components/ui/json-viewer';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Braces } from 'lucide-react';

interface SchemaSectionProps {
  /** The deferred `{ meta, schema }` payload. Undefined while the deferred prop resolves. */
  schema?: Record<string, unknown>;
  description?: string;
  className?: string;
  /** Wrap the section in a Card — for card-based pages (e.g. workflow show). */
  card?: boolean;
}

const DEFAULT_DESCRIPTION =
  'Validation schema of every field used across the request bodies. Copy it and hand it to an AI agent so the frontend payload matches what gets dispatched.';

export function SchemaSection({ schema, description, className, card = false }: SchemaSectionProps) {
  const body = (
    <>
      <div className="space-y-1">
        <h3 className="flex items-center gap-2 text-lg font-semibold">
          <Braces className="h-4 w-4" />
          Schema
        </h3>
        <p className="text-sm text-muted-foreground">{description ?? DEFAULT_DESCRIPTION}</p>
      </div>
      {schema ? <JsonViewer data={schema} title="Schema" /> : <SchemaSkeleton />}
    </>
  );

  if (card) {
    return (
      <Card className={className}>
        <CardContent className="space-y-3 pt-6">{body}</CardContent>
      </Card>
    );
  }

  return <div className={cn('space-y-3', className)}>{body}</div>;
}

function SchemaSkeleton() {
  return (
    <div className="space-y-2 rounded-md border p-3">
      <Skeleton className="h-4 w-1/3" />
      <Skeleton className="h-4 w-2/3" />
      <Skeleton className="h-4 w-1/2" />
      <Skeleton className="h-4 w-3/5" />
      <Skeleton className="h-4 w-2/5" />
    </div>
  );
}
