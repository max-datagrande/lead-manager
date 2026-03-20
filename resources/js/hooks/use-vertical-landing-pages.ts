import { VerticalLandingPagesContext } from '@/context/vertical-landing-pages-provider';
import { useContext } from 'react';

export function useVerticalLandingPages() {
  const context = useContext(VerticalLandingPagesContext);
  if (!context) throw new Error('useVerticalLandingPages must be used within VerticalLandingPagesProvider');
  return context;
}