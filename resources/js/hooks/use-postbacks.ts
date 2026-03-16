import { PostbacksContext } from '@/context/postbacks-provider'
import { useContext } from 'react'

function usePostbacks() {
  const context = useContext(PostbacksContext)
  if (!context) {
    throw new Error('usePostbacks must be used within a PostbacksProvider')
  }
  return context
}

export { usePostbacks }
