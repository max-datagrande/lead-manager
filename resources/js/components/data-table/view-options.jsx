import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenuLabel, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { capitalize } from '@/utils/string';
import { Eye, EyeOff } from 'lucide-react';

export function DataTableViewOptions({ columns }) {
  // Verificar si hay columnas ocultas
  const hiddenColumns = columns
    .filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide())
    .filter((column) => !column.getIsVisible());
  
  const hasHiddenColumns = hiddenColumns.length > 0;

  return (
    <>
      <DropdownMenu modal={false}>
        <Tooltip>
          <TooltipTrigger asChild>
            <DropdownMenuTrigger asChild>
              <Button 
                variant={hasHiddenColumns ? "secondary" : "outline"} 
                className="ms-auto hidden lg:flex"
              >
                {hasHiddenColumns ? (
                  <EyeOff className="size-4" />
                ) : (
                  <Eye className="size-4" />
                )}
              </Button>
            </DropdownMenuTrigger>
          </TooltipTrigger>
          <TooltipContent>
            <p>Column Visibility</p>
          </TooltipContent>
        </Tooltip>
        <DropdownMenuContent align="end" className="w-[150px]">
          <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
          <DropdownMenuSeparator />
          {columns
            .filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide())
            .map((column) => {
              const columnName = column.id.replaceAll('_', ' ');
              return (
                <DropdownMenuCheckboxItem
                  key={column.id}
                  className="capitalize"
                  checked={column.getIsVisible()}
                  onCheckedChange={(value) => column.toggleVisibility(!!value)}
                >
                  {capitalize(columnName)}
                </DropdownMenuCheckboxItem>
              );
            })}
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  );
}
