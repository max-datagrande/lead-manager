import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useIntegrations } from '@/hooks/use-integrations';
import { Eraser, FileText, Loader2 } from 'lucide-react';
import { Suspense, lazy, useEffect, useMemo, useState } from 'react';

const MDEditor = lazy(async () => {
  await import('@uiw/react-md-editor/markdown-editor.css');
  await import('./notes-modal.css');
  return import('@uiw/react-md-editor');
});

const MDPreview = lazy(async () => {
  await import('@uiw/react-md-editor/markdown-editor.css');
  await import('./notes-modal.css');
  const mod = await import('@uiw/react-md-editor');
  return { default: mod.default.Markdown };
});

function NotesEditorSkeleton() {
  return (
    <div className="flex h-full w-full flex-col items-center justify-center gap-3">
      <Loader2 className="size-8 animate-spin text-muted-foreground" />
      <p className="text-sm text-muted-foreground">Loading editor…</p>
    </div>
  );
}

function useIsDark() {
  const [isDark, setIsDark] = useState(() => {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
  });

  useEffect(() => {
    const observer = new MutationObserver(() => {
      setIsDark(document.documentElement.classList.contains('dark'));
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    return () => observer.disconnect();
  }, []);

  return isDark;
}

export function NotesModal() {
  const { data, setData } = useIntegrations();
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState<string>(data.notes ?? '');
  const isDark = useIsDark();

  const hasNote = useMemo(() => (data.notes ?? '').trim().length > 0, [data.notes]);

  const handleOpenChange = (next: boolean) => {
    if (next) {
      setDraft(data.notes ?? '');
    }
    setOpen(next);
  };

  const handleClean = () => setDraft('');
  const handleCancel = () => setOpen(false);
  const handleSave = () => {
    setData('notes', draft);
    setOpen(false);
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" className="gap-2">
          <FileText className="size-4" />
          Notes{hasNote ? ' •' : ''}
        </Button>
      </DialogTrigger>

      <DialogContent
        className="flex h-[95vh] w-[98vw] max-w-none flex-col gap-0 overflow-hidden p-0 sm:max-w-none"
        data-color-mode={isDark ? 'dark' : 'light'}
      >
        <DialogTitle className="sr-only">Integration notes</DialogTitle>

        <header className="flex shrink-0 items-center justify-between border-b px-6 py-3">
          <div className="flex flex-col">
            <h2 className="text-base font-semibold">Notes</h2>
            <p className="text-xs text-muted-foreground">Markdown supported — write API specs, payload samples, buyer quirks.</p>
          </div>
          <Button type="button" variant="ghost" size="sm" onClick={handleClean} className="gap-2">
            <Eraser className="size-4" />
            Clean
          </Button>
        </header>

        <div className="grid min-h-0 flex-1 grid-cols-[70%_30%]">
          <div className="notes-md-editor-wrapper min-h-0 overflow-hidden border-r">
            <Suspense fallback={<NotesEditorSkeleton />}>
              <MDEditor
                value={draft}
                onChange={(v) => setDraft(v ?? '')}
                height="100%"
                preview="edit"
                visibleDragbar={false}
                style={{ height: '100%', border: 'none', borderRadius: 0 }}
              />
            </Suspense>
          </div>

          <div className="min-h-0 overflow-auto bg-muted/30 px-6 py-4">
            <Suspense fallback={<NotesEditorSkeleton />}>
              <MDPreview source={draft} className="notes-md-preview" style={{ background: 'transparent' }} />
            </Suspense>
          </div>
        </div>

        <footer className="flex shrink-0 items-center justify-end gap-2 border-t px-6 py-3">
          <Button type="button" variant="ghost" onClick={handleCancel}>
            Cancel
          </Button>
          <Button type="button" onClick={handleSave}>
            Save
          </Button>
        </footer>
      </DialogContent>
    </Dialog>
  );
}
