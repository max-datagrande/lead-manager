import { useUserTimezone } from '@/hooks/use-user-timezone';
import { createContext, useContext, type ReactNode } from 'react';

/**
 * Id sintetico usado para persistir el timezone activo de la vista dentro de
 * los columnFilters de la tabla. Viaja en el round-trip de la URL junto al
 * resto de los filtros y el backend lo ignora (no tiene filterConfig), por lo
 * que no afecta el query. Es la fuente de verdad del TZ con el que se filtro
 * el rango de fechas, para poder renderizar las celdas en el mismo TZ.
 */
export const TIMEZONE_FILTER_ID = '_tz';

const TableTimezoneContext = createContext<string | null>(null);

/**
 * Provee el timezone activo de la tabla a los componentes de render de fechas.
 * `timezone === null` significa "sin override de vista" -> los componentes caen
 * al TZ del perfil del usuario.
 */
export function TableTimezoneProvider({ timezone, children }: { timezone: string | null; children: ReactNode }) {
  return <TableTimezoneContext.Provider value={timezone}>{children}</TableTimezoneContext.Provider>;
}

/**
 * Devuelve el timezone con el que se deben renderizar las fechas:
 * - El override de vista (TZ elegido en el DateRangePicker) si existe.
 * - Si no, el TZ del perfil del usuario (`useUserTimezone`).
 */
export function useDisplayTimezone(): string {
  const viewTimezone = useContext(TableTimezoneContext);
  const { timezone } = useUserTimezone();

  return viewTimezone ?? timezone;
}

/**
 * Extrae el TZ activo de un array de columnFilters (o null si no hay override).
 */
export function getTimezoneFromFilters(columnFilters: Array<{ id: string; value: unknown }>): string | null {
  const entry = columnFilters?.find((filter) => filter.id === TIMEZONE_FILTER_ID);

  return typeof entry?.value === 'string' ? entry.value : null;
}
