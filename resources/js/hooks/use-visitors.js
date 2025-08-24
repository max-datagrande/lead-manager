import { useContext } from 'react';
import { VisitorsContext } from '@/context/visitors-provider';

function useVisitors() {
  const context = useContext(VisitorsContext);
  if (!context) {
    throw new Error('useVisitors must be used within a VisitorsProvider');
  }
  return context;
}

export { useVisitors };