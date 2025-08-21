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
  console.log(data);
  return data;
}


export { formatDateTime, formatDateTimeUTC, formatVisitDate, getSortState, serializeSort };
