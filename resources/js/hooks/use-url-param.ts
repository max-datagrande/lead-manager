import { useState } from 'react'

export function useUrlParam(key: string, defaultValue: string): [string, (v: string) => void] {
  const [value, setValue] = useState(() => {
    if (typeof window === 'undefined') return defaultValue
    return new URLSearchParams(window.location.search).get(key) ?? defaultValue
  })

  const setParam = (newValue: string) => {
    const params = new URLSearchParams(window.location.search)
    params.set(key, newValue)
    window.history.replaceState(null, '', `?${params.toString()}`)
    setValue(newValue)
  }

  return [value, setParam]
}
