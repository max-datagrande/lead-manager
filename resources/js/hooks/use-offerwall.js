import { OfferwallContext } from '@/context/offerwall/provider';
import { useContext } from 'react';

export const useOfferwall = () => {
  const context = useContext(OfferwallContext);
  if (!context) {
    throw new Error('useOfferwall must be used within a OfferwallProvider');
  }
  return context;
};
