import { LandingPagesContext } from '@/context/landing-provider';
import { useContext } from 'react';

export function useLandings() {
  const context = useContext(LandingPagesContext);
  if (!context) throw new Error('useLandingPages must be used within LandingPagesProvider');
  return context;
}
