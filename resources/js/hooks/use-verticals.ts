import { VerticalsContext } from '@/context/verticals-provider';
import { useContext } from 'react';

export function useVerticals() {
  const context = useContext(VerticalsContext);
  if (!context) throw new Error('useVerticals must be used within VerticalsProvider');
  return context;
}