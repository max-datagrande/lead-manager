import { ToastContext } from '@/components/ui/toaster';
import { useContext } from 'react';

export const useToast = () => {
  const context = useContext(ToastContext);
  if (!context) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};
