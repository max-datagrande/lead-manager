import { createContext } from 'react';
import { useServerTable } from '@/hooks/use-server-table';
import { useContext } from 'react';

/**
 * Crea un provider de tabla server-side de forma automática
 *
 * @param {string} routeName - Nombre de la ruta Inertia
 * @param {Object} defaultConfig - Configuración adicional por defecto
 * @returns {Object} Provider y hook personalizados
 *
 * @example
 * // Crear provider
 * export const UsersProvider = createServerTableProvider('users.index');
 *
 * // Usar en página
 * <UsersProvider initialState={state}>
 *   <UsersTable />
 * </UsersProvider>
 *
 * // Usar hook
 * const { pagination, setPagination, isLoading } = useUsersTable();
 */
export function createServerTableProvider(routeName, defaultConfig = {}) {
  const Context = createContext(null);

  function Provider({ children, initialState }) {
    const serverTable = useServerTable({
      routeName,
      initialState,
      ...defaultConfig
    });

    return (
      <Context.Provider value={serverTable}>
        {children}
      </Context.Provider>
    );
  }

  function useTable() {
    const context = useContext(Context);
    if (!context) {
      throw new Error(`useTable debe usarse dentro de ${routeName}Provider`);
    }
    return context;
  }

  return { Provider, useTable };
}
