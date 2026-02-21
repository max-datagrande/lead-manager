import { Button } from '@/components/ui/button';
import { useUsers } from '@/hooks/use-users';
import { Plus } from 'lucide-react';

export const UsersActions = () => {
  const { showCreateModal } = useUsers();

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add User
    </Button>
  );
};
