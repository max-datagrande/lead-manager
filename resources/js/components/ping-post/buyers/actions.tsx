import { Button } from '@/components/ui/button';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Plus, RefreshCw } from 'lucide-react';
import { route } from 'ziggy-js';

export function BuyerActions() {
  const modal = useModal();
  const { addMessage } = useToast();
  const {
    props: { app },
  } = usePage<SharedData>();
  const isLocalEnv = app.env === 'local';

  const confirmSync = async () => {
    const confirmed = await modal.confirm({
      title: 'Sync buyers',
      description: 'This will sync all buyers, configs, eligibility rules and cap rules from production. Your local data will be TRUNCATED!',
      confirmText: 'Sync',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      addMessage('Syncing buyers from production...', 'info');
      try {
        const response = await axios.post(route('api.buyers.import'));
        addMessage(response.data.message || 'Sync completed!', 'success');
        router.reload();
      } catch (error: any) {
        addMessage(error.response?.data?.error || 'Sync failed.', 'error');
      }
    }
  };

  return (
    <div className="flex items-center gap-2">
      {isLocalEnv && (
        <Button onClick={confirmSync} variant="outline" className="flex items-center gap-2">
          <RefreshCw className="h-4 w-4" />
          Sync
        </Button>
      )}
      <Button asChild>
        <Link href={route('ping-post.buyers.create')}>
          <Plus className="mr-2 h-4 w-4" />
          Add Buyer
        </Link>
      </Button>
    </div>
  );
}
