import { PlatformsContext } from '@/context/platforms-provider'
import { useContext } from 'react'

function usePlatforms() {
  const context = useContext(PlatformsContext)
  if (!context) {
    throw new Error('usePlatforms must be used within a PlatformsProvider')
  }
  return context
}

export { usePlatforms }
