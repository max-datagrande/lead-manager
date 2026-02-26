import { UsersContext } from '@/context/users-provider';
import { useContext } from 'react';

function useUsers() {
  const context = useContext(UsersContext);

  if (!context) {
    throw new Error('useUsers must be used within UsersProvider');
  }

  return context;
}

export { useUsers };
