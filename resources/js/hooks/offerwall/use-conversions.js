import { OfferwallConversionsContext } from '@/context/offerwall/conversion-provider';
import { useContext } from 'react';

function useOfferwallConversions() {
  const context = useContext(OfferwallConversionsContext);
  if (!context) {
    throw new Error('useOfferwallConversions must be used within a OfferwallConversionsProvider');
  }
  return context;
}

export { useOfferwallConversions };
