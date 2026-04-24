import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const categoryColors: Record<string, string> = {
  dispatch: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
  eligibility: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  buyer: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
  ping: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  price: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',
  post: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',
  cascade: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  fallback: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  outcome: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
  postback: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
  validation: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
  quality: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300',
};

// Override for specific negative outcomes
const eventOverrides: Record<string, string> = {
  'outcome.not_sold': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  'outcome.pending_postback': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  'dispatch.error': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  'postback.fire_failed': 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
  'validation.completed': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
  'validation.forced': 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-900/30 dark:text-fuchsia-300',
};

function getCategory(event: string): string {
  return event.split('.')[0];
}

function getColorClass(event: string): string {
  return eventOverrides[event] ?? categoryColors[getCategory(event)] ?? categoryColors.dispatch;
}

export function getDotColor(event: string): string {
  const overrideMap: Record<string, string> = {
    'outcome.not_sold': 'bg-red-500',
    'outcome.pending_postback': 'bg-purple-500',
    'dispatch.error': 'bg-red-500',
    'postback.fire_failed': 'bg-red-500',
    'validation.completed': 'bg-emerald-500',
    'validation.forced': 'bg-fuchsia-500',
  };
  const categoryDotMap: Record<string, string> = {
    dispatch: 'bg-blue-500',
    eligibility: 'bg-amber-500',
    buyer: 'bg-amber-500',
    ping: 'bg-purple-500',
    price: 'bg-cyan-500',
    post: 'bg-indigo-500',
    cascade: 'bg-yellow-500',
    fallback: 'bg-orange-500',
    outcome: 'bg-green-500',
    postback: 'bg-sky-500',
    validation: 'bg-rose-500',
    quality: 'bg-rose-500',
  };
  return overrideMap[event] ?? categoryDotMap[getCategory(event)] ?? 'bg-gray-500';
}

interface Props {
  event: string;
  className?: string;
}

export function TimelineEventBadge({ event, className }: Props) {
  return (
    <Badge variant="outline" className={cn('border-0 font-mono text-[10px]', getColorClass(event), className)}>
      {event}
    </Badge>
  );
}
