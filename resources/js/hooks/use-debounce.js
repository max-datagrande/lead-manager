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
export function useDebouncedCallback(callback, delay = 500) {
  const [debouncedCallback, setDebouncedCallback] = useState(callback);

  useEffect(() => {
    // Crear un timer que actualice el valor debounced después del delay
    const handler = setTimeout(() => {
      setDebouncedCallback(callback);
    }, delay);

    // Limpiar el timeout si el valor cambia antes de que se complete el delay
    // Esto previene que se ejecute la función si el usuario sigue escribiendo
    return () => {
      clearTimeout(handler);
    };
  }, [callback, delay]);

  return debouncedCallback;
}

/**
 * Crea una versión debounced de una función
 * @param {Function} func - La función a la que aplicar debounce
 * @param {number} delay - El delay en milisegundos (por defecto 500ms)
 * @returns {Function} - La función con debounce aplicado
 */
export function createDebouncedFunction(func, delay = 500) {
  let timeoutId;

  return function debouncedFunction(...args) {
    // Limpiar el timeout anterior si existe
    clearTimeout(timeoutId);

    // Crear un nuevo timeout
    timeoutId = setTimeout(() => {
      func.apply(this, args);
    }, delay);
  };
}

/**
 * Hook de React que retorna una versión debounced de una función
 * @param {Function} callback - La función a la que aplicar debounce
 * @param {number} delay - El delay en milisegundos (por defecto 500ms)
 * @returns {Function} - La función con debounce aplicado
 */
export function useDebouncedFunction(callback, delay = 500) {
  const debouncedFn = useMemo(() => {
    return createDebouncedFunction(callback, delay);
  }, [callback, delay]);

  // Limpiar el timeout cuando el componente se desmonte
  useEffect(() => {
    return () => {
      // Si la función debounced tiene un timeout pendiente, lo limpiamos
      if (debouncedFn.timeoutId) {
        clearTimeout(debouncedFn.timeoutId);
      }
    };
  }, [debouncedFn]);

  return debouncedFn;
}
