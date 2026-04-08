import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import type { AlertChannelSummary, WorkflowAlert } from '@/types/ping-post';
import axios from 'axios';
import { Bell, Loader2, Search } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface Props {
  workflowId: number;
  alertChannels: AlertChannelSummary[];
  associatedIds: number[];
}

export function WorkflowAlertModal({ workflowId, alertChannels, associatedIds }: Props) {
  const modalId = useCurrentModalId();
  const modal = useModal();
  const [search, setSearch] = useState('');
  const [saving, setSaving] = useState(false);

  const available = alertChannels.filter((ch) => !associatedIds.includes(ch.id) && ch.name.toLowerCase().includes(search.toLowerCase()));

  const handleSelect = async (channel: AlertChannelSummary) => {
    if (saving) return;
    setSaving(true);
    try {
      const res = await axios.post(route('ping-post.workflows.alerts.store', workflowId), {
        alert_channel_id: channel.id,
      });
      modal.resolve(modalId, res.data.data as WorkflowAlert);
    } catch {
      setSaving(false);
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Add Alert Channel</DialogTitle>
        <DialogDescription>Select an alert channel to notify when this workflow encounters errors.</DialogDescription>
      </DialogHeader>

      <div className="relative">
        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input placeholder="Search channels..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9" autoFocus />
      </div>

      <div className="max-h-64 space-y-1 overflow-y-auto">
        {available.length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">
            {alertChannels.length === associatedIds.length ? 'All alert channels are already associated.' : 'No channels match your search.'}
          </p>
        ) : (
          available.map((ch) => (
            <button
              key={ch.id}
              type="button"
              disabled={saving}
              onClick={() => handleSelect(ch)}
              className="flex w-full items-center gap-3 rounded-md border border-transparent px-3 py-2.5 text-left text-sm transition-colors hover:border-border hover:bg-muted/50 disabled:opacity-50"
            >
              <Bell className="h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{ch.name}</p>
              </div>
              <Badge variant="outline" className="shrink-0 text-xs capitalize">
                {ch.type}
              </Badge>
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
