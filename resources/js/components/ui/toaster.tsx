import { useEffect, useState, createContext, useContext, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { Toaster as Sonner } from 'sonner';
import { type SharedData } from '@/types';
import { toast } from 'sonner';

/**
 * Toast notification component that displays flash messages from Laravel backend
 *
 * This component automatically shows success and error messages that are passed
 * from the Laravel backend through Inertia.js flash data. It handles both single
 * messages and arrays of messages.
 *
 * @returns JSX element containing the Sonner toast container
 */
export function Toaster() {
  // Extract flash messages from Inertia.js shared props
  const { flash } = usePage<SharedData>().props;

  useEffect(() => {
    /**
     * Helper function to display toast messages
     * Handles both single strings and arrays of strings uniformly
     *
     * @param messages - Single message string or array of message strings
     * @param toastFn - Toast function to call (toast.success or toast.error)
     */
    const showMessages = (messages: string | string[], toastFn: (message: string) => void) => {
      if (Array.isArray(messages)) {
        // If messages is an array, show each message individually
        messages.forEach(toastFn);
      } else {
        // If messages is a single string, show it directly
        toastFn(messages);
      }
    };

    // Display success messages if they exist
    if (flash.success) {
      showMessages(flash.success, toast.success);
    }

    // Display error messages if they exist
    if (flash.error) {
      showMessages(flash.error, toast.error);
    }
  }, [flash]); // Re-run effect when flash messages change

  return (
    // Render the Sonner toast container with custom configuration
    <Sonner
      position="bottom-right" // Position toasts in bottom-right corner
      expand={false}          // Don't expand toasts on hover
      richColors              // Use rich color scheme for different toast types
    />
  );
}
type ToastContextType = {
  addMessage: (message: string, type?: 'success' | 'error' | 'info') => void;
}

export const ToastContext = createContext<ToastContextType | null>(null);

export function ToastProvider({ children }: { children: ReactNode }) {
  const addMessage = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
    // Mostrar el toast inmediatamente sin usar estado
    switch (type) {
      case 'success':
        toast.success(message);
        break;
      case 'error':
        toast.error(message);
        break;
      case 'info':
        toast.info(message);
        break;
    }
  };

  return (
    <ToastContext.Provider value={{ addMessage }}>
      {children}
    </ToastContext.Provider>
  );
}

// Hook para usar el contexto

