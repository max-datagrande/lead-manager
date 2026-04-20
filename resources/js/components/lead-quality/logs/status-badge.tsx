import { Badge } from '@/components/ui/badge';
import type { ValidationLogStatusValue } from '@/types/models/lead-quality';
import { AlertTriangle, CheckCircle2, Clock, Send, ShieldX, SkipForward, XCircle } from 'lucide-react';

const CONFIG: Record<
  ValidationLogStatusValue,
  { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; className?: string; Icon: typeof CheckCircle2 }
> = {
  verified: { label: 'Verified', variant: 'default', Icon: CheckCircle2 },
  sent: { label: 'Sent', variant: 'outline', className: 'border-blue-300 text-blue-700 dark:border-blue-900/60 dark:text-blue-300', Icon: Send },
  pending: { label: 'Pending', variant: 'secondary', Icon: Clock },
  failed: { label: 'Failed', variant: 'destructive', Icon: XCircle },
  expired: {
    label: 'Expired',
    variant: 'outline',
    className: 'border-amber-300 text-amber-700 dark:border-amber-900/60 dark:text-amber-300',
    Icon: AlertTriangle,
  },
  skipped: { label: 'Skipped', variant: 'outline', Icon: SkipForward },
  error: {
    label: 'Error',
    variant: 'outline',
    className: 'border-rose-300 text-rose-700 dark:border-rose-900/60 dark:text-rose-300',
    Icon: ShieldX,
  },
};

export function StatusBadge({ status }: { status: ValidationLogStatusValue }) {
  const { label, variant, className, Icon } = CONFIG[status] ?? CONFIG.pending;

  return (
    <Badge variant={variant} className={`gap-1 ${className ?? ''}`}>
      <Icon className="h-3 w-3" />
      <span>{label}</span>
    </Badge>
  );
}
