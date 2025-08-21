import { useEffect, useState } from 'react';

/**
 * Hook personalizado para debounce
 * @param {any} value - El valor a debounce
 * @param {number} delay - El delay en milisegundos (default: 500ms)
 * @returns {any} - El valor debounced
 */
export function useDebounce(value, delay = 500) {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    // Crear un timer que actualice el valor debounced después del delay
    const handler = setTimeout(() => {
      setDebouncedValue(value);
    }, delay);

    // Limpiar el timeout si el valor cambia antes de que se complete el delay
    // Esto previene que se ejecute la función si el usuario sigue escribiendo
    return () => {
      clearTimeout(handler);
    };
  }, [value, delay]);

  return debouncedValue;
}
