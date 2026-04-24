import { WorkflowSnippets } from '@/components/ping-post/workflows/snippets';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { Workflow } from '@/types/ping-post';

interface Props {
  workflow: Pick<Workflow, 'id' | 'name'>;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function WorkflowSnippetsModal({ workflow, open, onOpenChange }: Props) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Integration snippets — {workflow.name}</DialogTitle>
          <DialogDescription>Copy and paste to dispatch leads to this workflow.</DialogDescription>
        </DialogHeader>
        <WorkflowSnippets workflow={workflow} />
      </DialogContent>
    </Dialog>
  );
}
