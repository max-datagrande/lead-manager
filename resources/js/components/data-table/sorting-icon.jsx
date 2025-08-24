import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react';

export function SortingIcon({ column }) {
  if (!column.getCanSort?.()) {
    return null;
  }
  const sortState = column.getIsSorted?.() || '';
  if (!sortState) {
    return <ChevronsUpDown className="ml-2 h-4 w-4 text-muted-foreground" />;
  }
  if (sortState === 'asc') {
    return <ChevronUp className="ml-2 h-4 w-4 text-black dark:text-white" strokeWidth="3" />;
  }
  if (sortState === 'desc') {
    return <ChevronDown className="ml-2 h-4 w-4 text-black dark:text-white" strokeWidth="3" />;
  }
}
