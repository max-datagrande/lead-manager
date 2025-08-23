import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-react';

export default function SortingIcon({ column, sorting }) {
  const sortState = sorting.find((s) => s.id === column.id);
  if (!column.getCanSort?.()) {
    return null;
  }

  if (!sortState) {
    return <ChevronsUpDown className="ml-2 h-4 w-4 text-muted-foreground" />;
  }
  return sortState.desc ? (
    <ChevronDown className="ml-2 h-4 w-4 text-black dark:text-white" strokeWidth="3" />
  ) : (
    <ChevronUp className="ml-2 h-4 w-4 text-black dark:text-white" strokeWidth="3" />
  );
}
