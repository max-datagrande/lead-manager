import { useContext } from 'react';
import { WhitelistContext } from '@/context/whitelist-provider';

function useWhitelist() {
  const context = useContext(WhitelistContext);
  if (!context) {
    throw new Error('useWhitelist must be used within a WhitelistProvider');
  }
  return context;
}

export { useWhitelist };