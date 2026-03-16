import { PostbackQueueContext } from '@/context/postback-queue-provider'
import { useContext } from 'react'

function usePostbackQueue() {
  const context = useContext(PostbackQueueContext)
  if (!context) {
    throw new Error('usePostbackQueue must be used within a PostbackQueueProvider')
  }
  return context
}

export { usePostbackQueue }
