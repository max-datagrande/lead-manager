import React, { createContext, useContext, useState, useCallback, useMemo, useEffect } from 'react'
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { router } from '@inertiajs/react'
import ConfirmDialog from '@/components/confirm-dialog'

/**
 * Representa un modal individual en la pila de modales
 */
type ModalItem = {
  id: number
  node: React.ReactNode
  resolve?: (value: any) => void
  reject?: (reason?: any) => void
}

/**
 * Opciones para el diálogo de confirmación
 */
type ConfirmOptions = {
  title?: string
  description?: string
  confirmText?: string
  cancelText?: string
  destructive?: boolean
}

/**
 * Tipo del contexto de modales que expone toda la API
 */
type ModalContextType = {
  open: (node: React.ReactNode) => number
  openAsync: <T = unknown>(node: React.ReactNode) => Promise<T>
  confirm: (opts: ConfirmOptions) => Promise<boolean>
  close: (id?: number) => void
  closeAll: () => void
  resolve: <T = unknown>(id: number, value: T) => void
  reject: (id: number, reason?: unknown) => void
  topId: number | null
}

// Contextos de React
const ModalContext = createContext<ModalContextType | null>(null)
const ModalIdContext = createContext<number | null>(null)

// Contador global para IDs únicos
let _id = 0

/**
 * Provider principal que maneja la pila de modales y expone la API
 */
export function ModalProvider({
  children,
  autoCloseOnNavigate = true
}: {
  children: React.ReactNode
  autoCloseOnNavigate?: boolean
}) {
  const [stack, setStack] = useState<ModalItem[]>([])

  /**
   * Abre un modal con contenido React arbitrario
   */
  const open = useCallback((node: React.ReactNode) => {
    const id = ++_id
    setStack(s => [...s, { id, node: <ModalScope id={id}>{node}</ModalScope> }])
    return id
  }, [])

  /**
   * Abre un modal y retorna una promesa tipada con el resultado
   */
  const openAsync = useCallback(<T,>(node: React.ReactNode) => {
    const id = ++_id
    return new Promise<T>((resolve, reject) => {
      setStack(s => [...s, { id, resolve, reject, node: <ModalScope id={id}>{node}</ModalScope> }])
    })
  }, [])

  /**
   * Resuelve un modal específico con un valor tipado
   */
  const resolve = useCallback(<T,>(id: number, value: T) => {
    setStack(s => {
      const it = s.find(x => x.id === id)
      it?.resolve?.(value)
      return s.filter(x => x.id !== id)
    })
  }, [])

  /**
   * Rechaza un modal específico con un error
   */
  const reject = useCallback((id: number, reason?: unknown) => {
    setStack(s => {
      const it = s.find(x => x.id === id)
      it?.reject?.(reason)
      return s.filter(x => x.id !== id)
    })
  }, [])

  /**
   * Cierra un modal específico o el último si no se especifica ID
   */
  const close = useCallback((id?: number) => {
    setStack(s => {
      const target = id ?? s[s.length - 1]?.id
      if (target == null) return s
      const it = s.find(x => x.id === target)
      it?.resolve?.(undefined)
      return s.filter(x => x.id !== target)
    })
  }, [])

  /**
   * Cierra todos los modales abiertos
   */
  const closeAll = useCallback(() => {
    setStack(s => {
      s.forEach(i => i.resolve?.(undefined))
      return []
    })
  }, [])

  /**
   * Abre un diálogo de confirmación estándar
   */
  const confirm = useCallback((opts: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      const id = ++_id
      const node = (
        <ModalScope id={id}>
          <ConfirmDialog id={id} {...opts} />
        </ModalScope>
      )
      setStack(s => [...s, { id, node, resolve: (v) => resolve(Boolean(v)) }])
    })
  }, [])

  // Auto-cierre al navegar con Inertia (opcional)
  useEffect(() => {
    if (!autoCloseOnNavigate) return
    const unsubs = [router.on('before', () => closeAll())]
    return () => { unsubs.forEach(u => u && u()) }
  }, [autoCloseOnNavigate, closeAll])

  const value = useMemo<ModalContextType>(() => ({
    open, openAsync, confirm, close, closeAll, resolve, reject, topId: stack.at(-1)?.id ?? null,
  }), [open, openAsync, confirm, close, closeAll, resolve, reject, stack])

  return (
    <ModalContext.Provider value={value}>
      {children}
      <ModalRoot stack={stack} onCloseId={close} />
    </ModalContext.Provider>
  )
}

/**
 * Componente que proporciona el ID del modal actual a sus hijos
 */
function ModalScope({ id, children }: { id: number; children: React.ReactNode }) {
  return <ModalIdContext.Provider value={id}>{children}</ModalIdContext.Provider>
}

/**
 * Hook principal para usar el sistema de modales
 */
export function useModal() {
  const ctx = useContext(ModalContext)
  if (!ctx) throw new Error('useModal must be used within ModalProvider')
  return ctx
}

/**
 * Hook para obtener el ID del modal actual (solo funciona dentro de un modal)
 */
export function useCurrentModalId() {
  const id = useContext(ModalIdContext)
  if (id == null) throw new Error('useCurrentModalId must be used inside a Modal opened by ModalProvider')
  return id
}

/**
 * Componente que renderiza la pila de modales
 */
function ModalRoot({ stack, onCloseId }: { stack: ModalItem[]; onCloseId: (id: number) => void }) {
  return (
    <>
      {stack.map((item) => (
        <Dialog key={item.id} open onOpenChange={(open) => !open && onCloseId(item.id)}>
          <DialogContent className="sm:max-w-lg">
            {item.node}
          </DialogContent>
        </Dialog>
      ))}
    </>
  );
}
