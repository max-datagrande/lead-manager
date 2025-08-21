import ConfirmDialog from '@/components/confirm-dialog';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { router } from '@inertiajs/react';
import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

/**
 * Represents an individual modal in the modal stack
 */
type ModalItem = {
  id: number;
  node: React.ReactNode;
  resolve?: (value: any) => void;
  reject?: (reason?: any) => void;
};

/**
 * Options for the confirmation dialog
 */
type ConfirmOptions = {
  title?: string;
  description?: string;
  confirmText?: string;
  cancelText?: string;
  destructive?: boolean;
};

/**
 * Modal context type that exposes the entire API
 */
type ModalContextType = {
  open: (node: React.ReactNode) => number;
  openAsync: <T = unknown>(node: React.ReactNode) => Promise<T>;
  confirm: (opts: ConfirmOptions) => Promise<boolean>;
  close: (id?: number) => void;
  closeAll: () => void;
  resolve: <T = unknown>(id: number, value: T) => void;
  reject: (id: number, reason?: unknown) => void;
  topId: number | null;
};

// React contexts
const ModalContext = createContext<ModalContextType | null>(null);
const ModalIdContext = createContext<number | null>(null);

// Global counter for unique IDs
let _id = 0;

/**
 * Main provider that manages the modal stack and exposes the API
 */
export function ModalProvider({ children, autoCloseOnNavigate = true }: { children: React.ReactNode; autoCloseOnNavigate?: boolean }) {
  const [stack, setStack] = useState<ModalItem[]>([]);

  /**
   * Opens a modal with arbitrary React content
   */
  const open = useCallback((node: React.ReactNode) => {
    const id = ++_id;
    setStack((s) => [...s, { id, node: <ModalScope id={id}>{node}</ModalScope> }]);
    return id;
  }, []);

  /**
   * Opens a modal and returns a typed promise with the result
   */
  const openAsync = useCallback(<T,>(node: React.ReactNode) => {
    const id = ++_id;
    return new Promise<T>((resolve, reject) => {
      setStack((s) => [...s, { id, resolve, reject, node: <ModalScope id={id}>{node}</ModalScope> }]);
    });
  }, []);

  /**
   * Resolves a specific modal with a typed value
   */
  const resolve = useCallback(<T,>(id: number, value: T) => {
    setStack((s) => {
      const it = s.find((x) => x.id === id);
      it?.resolve?.(value);
      return s.filter((x) => x.id !== id);
    });
  }, []);

  /**
   * Rejects a specific modal with an error
   */
  const reject = useCallback((id: number, reason?: unknown) => {
    setStack((s) => {
      const it = s.find((x) => x.id === id);
      it?.reject?.(reason);
      return s.filter((x) => x.id !== id);
    });
  }, []);

  /**
   * Closes a specific modal or the last one if no ID is specified
   */
  const close = useCallback((id?: number) => {
    setStack((s) => {
      const target = id ?? s[s.length - 1]?.id;
      if (target == null) return s;
      const it = s.find((x) => x.id === target);
      it?.resolve?.(undefined);
      return s.filter((x) => x.id !== target);
    });
  }, []);

  /**
   * Closes all open modals
   */
  const closeAll = useCallback(() => {
    setStack((s) => {
      s.forEach((i) => i.resolve?.(undefined));
      return [];
    });
  }, []);

  /**
   * Opens a standard confirmation dialog
   */
  const confirm = useCallback((opts: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      const id = ++_id;
      const node = (
        <ModalScope id={id}>
          <ConfirmDialog id={id} {...opts} />
        </ModalScope>
      );
      setStack((s) => [...s, { id, node, resolve: (v) => resolve(Boolean(v)) }]);
    });
  }, []);

  // Auto-close when navigating with Inertia (optional)
  useEffect(() => {
    if (!autoCloseOnNavigate) return;
    const unsubs = [router.on('before', () => closeAll())];
    return () => {
      unsubs.forEach((u) => u && u());
    };
  }, [autoCloseOnNavigate, closeAll]);

  const value = useMemo<ModalContextType>(
    () => ({
      open,
      openAsync,
      confirm,
      close,
      closeAll,
      resolve,
      reject,
      topId: stack.at(-1)?.id ?? null,
    }),
    [open, openAsync, confirm, close, closeAll, resolve, reject, stack],
  );

  return (
    <ModalContext.Provider value={value}>
      {children}
      <ModalRoot stack={stack} onCloseId={close} />
    </ModalContext.Provider>
  );
}

/**
 * Component that provides the current modal ID to its children
 */
function ModalScope({ id, children }: { id: number; children: React.ReactNode }) {
  return <ModalIdContext.Provider value={id}>{children}</ModalIdContext.Provider>;
}

/**
 * Main hook for using the modal system
 */
export function useModal() {
  const ctx = useContext(ModalContext);
  if (!ctx) throw new Error('useModal must be used within ModalProvider');
  return ctx;
}

/**
 * Hook to get the current modal ID (only works inside a modal)
 */
export function useCurrentModalId() {
  const id = useContext(ModalIdContext);
  if (id == null) throw new Error('useCurrentModalId must be used inside a Modal opened by ModalProvider');
  return id;
}

/**
 * Component that renders the modal stack
 */
function ModalRoot({ stack, onCloseId }: { stack: ModalItem[]; onCloseId: (id: number) => void }) {
  return (
    <>
      {stack.map((item) => (
        <Dialog key={item.id} open onOpenChange={(open) => !open && onCloseId(item.id)}>
          <DialogContent className="sm:max-w-lg">{item.node}</DialogContent>
        </Dialog>
      ))}
    </>
  );
}
