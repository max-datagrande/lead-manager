import { LandingPagesContext } from '@/context/landing-provider';
import { LandingPagesVersionContext } from '@/context/landing-version-provider';
import { useContext } from 'react';

export function useLandings() {
  const context = useContext(LandingPagesContext);
  if (!context) throw new Error('useLandingPages must be used within LandingPagesProvider');
  return context;
}

export function useVersions() {
  const context = useContext(LandingPagesVersionContext);
  if (!context) throw new Error('useLandingVersions must be used within LandingPagesVersionsProvider');
  return context;
}

