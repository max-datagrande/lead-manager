import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STAGE_COLORS: Record<string, string> = {
  pre_dispatch: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
  ping: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
  post: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
};

const STAGE_LABELS: Record<string, string> = {
  pre_dispatch: 'Pre-Dispatch',
  ping: 'Ping',
  post: 'Post',
};

interface Props {
  stage: string;
  className?: string;
}

export function StageBadge({ stage, className }: Props) {
  const color = STAGE_COLORS[stage] ?? 'bg-gray-100 text-gray-600';
  const label = STAGE_LABELS[stage] ?? stage;
  return (
    <Badge variant="outline" className={cn('border-0 font-medium', color, className)}>
      {label}
    </Badge>
  );
}
