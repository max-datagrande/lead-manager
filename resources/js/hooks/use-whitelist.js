import { WhitelistContext } from '@/context/whitelist-provider';
import { useContext } from 'react';

function useWhitelist() {
  const context = useContext(WhitelistContext);
  if (!context) {
    throw new Error('useWhitelist must be used within a WhitelistProvider');
  }
  return context;
}

export { useWhitelist };
