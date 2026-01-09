import { createServerTableProvider } from '@/utils/create-server-table-provider';

// Crear provider automáticamente
export const { Provider: UsersProvider, useTable: useUsersTable } = createServerTableProvider('users.index');

// Crear otro provider con configuración personalizada
export const { Provider: ProductsProvider, useTable: useProductsTable } = createServerTableProvider('products.index', {
  defaultPageSize: 25
});

// Crear provider para visitantes (más limpio que el original)
export const { Provider: VisitorsProviderV2, useTable: useVisitorsTableV2 } = createServerTableProvider('visitors.index');