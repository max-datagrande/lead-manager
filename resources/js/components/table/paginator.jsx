import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

const Paginator = ({ pages, rows }) => {
  if (!pages.length) {
    return null;
  }
  if (pages.length <= 3) {
    return null;
  }
  return (
    <div className="flex items-center justify-between px-2 py-4">
      <div className="text-sm text-gray-500">
        Showing {rows.from} to {rows.to} of {rows.total} results
      </div>
      <div className="flex items-center space-x-2">
        {pages.map((page, index) => {
          if (page.label.includes('Previous')) {
            return (
              <Button key={index} variant="outline" size="sm" disabled={!page.url} asChild={!!page.url}>
                {page.url ? (
                  <Link href={page.url} prefetch="intent">
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                  </Link>
                ) : (
                  <>
                    <ChevronLeft className="h-4 w-4" />
                    Previous
                  </>
                )}
              </Button>
            );
          }

          if (page.label.includes('Next')) {
            return (
              <Button key={index} variant="outline" size="sm" disabled={!page.url} asChild={!!page.url}>
                {page.url ? (
                  <Link href={page.url} prefetch="intent">
                    Next
                    <ChevronRight className="h-4 w-4" />
                  </Link>
                ) : (
                  <>
                    Next
                    <ChevronRight className="h-4 w-4" />
                  </>
                )}
              </Button>
            );
          }

          // Páginas numeradas
          return (
            <Button key={index} variant={page.active ? 'default' : 'outline'} size="sm" disabled={!page.url} asChild={!!page.url}>
              {page.url ? (
                <Link href={page.url} prefetch="intent">
                  {page.label}
                </Link>
              ) : (
                page.label
              )}
            </Button>
          );
        })}
      </div>
    </div>
  );
};

export default Paginator;
