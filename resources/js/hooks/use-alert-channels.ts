import { AlertChannelsContext } from '@/context/alert-channels-provider';
import { useContext } from 'react';

export function useAlertChannels() {
  const context = useContext(AlertChannelsContext);
  if (!context) throw new Error('useAlertChannels must be used within AlertChannelsProvider');
  return context;
}
