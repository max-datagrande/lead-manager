import { BuyersContext } from '@/context/buyers-provider'
import { useContext } from 'react'

export function useBuyers() {
  const ctx = useContext(BuyersContext)
  if (!ctx) throw new Error('useBuyers must be used within BuyersProvider')
  return ctx
}
