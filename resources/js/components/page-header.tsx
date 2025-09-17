import { cn } from '@/lib/utils';
interface Props {
  className?: string;
  title: string;
  description?: string;
  children?: React.ReactNode;
}

const PageHeader = ({ className = '', title, description, children }: Props) => {
  const childrenClassName = children ? 'flex flex-row items-center justify-between gap-4' : '';
  return (
    <div className={cn(`page-header ${childrenClassName}`, className)}>
      <div className="flex flex-col space-y-2">
        <h2 className="text-3xl font-bold tracking-tight">{title}</h2>
        {description && <p className="text-sm text-muted-foreground">{description}</p>}
      </div>
      {children}
    </div>
  );
};

export default PageHeader;
