import { Button } from '@/components/ui/button';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import axios from 'axios';
import { Loader2, Search, Zap } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface InternalPostback {
  id: number;
  uuid: string;
  name: string;
  base_url: string;
  is_active: boolean;
}

interface Props {
  source: string;
  sourceId: number;
  postbacks: InternalPostback[];
  associatedIds: number[];
}

export function PostbackAssociationModal({ source, sourceId, postbacks, associatedIds }: Props) {
  const modalId = useCurrentModalId();
  const modal = useModal();
  const [search, setSearch] = useState('');
  const [saving, setSaving] = useState(false);

  const available = postbacks.filter((p) => !associatedIds.includes(p.id) && p.name.toLowerCase().includes(search.toLowerCase()));

  const handleSelect = async (postback: InternalPostback) => {
    setSaving(true);
    try {
      await axios.post(route('postbacks.associations.store'), {
        source,
        source_id: sourceId,
        postback_id: postback.id,
      });
      modal.resolve(modalId, postback);
    } catch {
      setSaving(false);
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Add Internal Postback</DialogTitle>
        <DialogDescription>Select an internal postback to associate with this entity.</DialogDescription>
      </DialogHeader>

      <div className="relative">
        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input placeholder="Search postbacks..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9" autoFocus />
      </div>

      <div className="max-h-64 space-y-1 overflow-y-auto">
        {available.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">
            {postbacks.length === associatedIds.length ? 'All internal postbacks are already associated.' : 'No postbacks match your search.'}
          </p>
        ) : (
          available.map((p) => (
            <button
              key={p.id}
              type="button"
              disabled={saving}
              onClick={() => handleSelect(p)}
              className="flex w-full items-center gap-3 rounded-md border border-transparent px-3 py-2.5 text-left text-sm transition-colors hover:border-border hover:bg-muted/50 disabled:opacity-50"
            >
              <Zap className="h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{p.name}</p>
                <p className="truncate font-mono text-xs text-muted-foreground">{p.base_url}</p>
              </div>
              {saving && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
            </button>
          ))
        )}
      </div>

      <DialogFooter>
        <Button variant="outline" onClick={() => modal.resolve(modalId, false)}>
          Cancel
        </Button>
      </DialogFooter>
    </>
  );
}
