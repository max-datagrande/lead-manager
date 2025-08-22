import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
// Configurar plugins de dayjs
dayjs.extend(utc);
dayjs.extend(timezone);

function serializeSort(sorting) {
  if (!sorting?.length) return undefined;
  const { id, desc } = sorting[0];
  return `${id}:${desc ? 'desc' : 'asc'}`;
}
function formatOnlyDate(d) {
  if (!d) return '—';
  return dayjs(d).format('YYYY-MM-DD');
}
function formatOnlyDateUTC(d) {
  if (!d) return '—';
  return dayjs(d).utc().format('YYYY-MM-DD [UTC]');
}
function formatDateTime(d) {
  if (!d) return '—';
  return dayjs(d).format('YYYY-MM-DD HH:mm');
}

function formatDateTimeUTC(d) {
  if (!d) return '—';
  return dayjs(d).utc().format('YYYY-MM-DD HH:mm [UTC]');
}

function formatVisitDate(d) {
  if (!d) return '—';
  return dayjs(d).format('YYYY-MM-DD');
}

function getSortState(sortData) {
  const data = [{ id: sortData.split(':')[0], desc: sortData.endsWith(':desc') }];
  return data;
}

/**
 * Maneja el ciclo de ordenamiento para columnas de tabla
 * Implementa el patrón: sin orden -> ascendente -> descendente -> sin orden
 *
 * @param {Array} currentSorting - Array actual de sorting (formato TanStack Table)
 * @param {string} columnId - ID de la columna a ordenar
 * @returns {Array} Nuevo array de sorting
 *
 * Ciclo de ordenamiento:
 * 1. Si no hay ordenamiento actual para esta columna: establece ascendente
 * 2. Si está en ascendente: cambia a descendente
 * 3. Si está en descendente: quita el ordenamiento
 */
function toggleColumnSorting(currentSorting, columnId) {
  // Buscar si ya existe un ordenamiento para esta columna
  const currentSort = currentSorting[0]?.id === columnId ? currentSorting[0] : null;

  // Si no hay ordenamiento actual para esta columna, establecer ascendente
  if (!currentSort) {
    return [{ id: columnId, desc: false }];
  }

  // Si está en ascendente, cambiar a descendente
  if (currentSort && !currentSort.desc) {
    return [{ id: columnId, desc: true }];
  }

  // Si está en descendente, quitar ordenamiento
  return [];
}


export { formatDateTime, formatDateTimeUTC, formatVisitDate, getSortState, serializeSort, formatOnlyDate, formatOnlyDateUTC, toggleColumnSorting };
