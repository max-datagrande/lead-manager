import { useDisplayTimezone } from '@/components/data-table/table-timezone';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';

/**
 * Fecha/hora en una sola linea, renderizada en el timezone del usuario.
 * Al hacer hover muestra un tooltip con el valor en UTC, para poder confirmar
 * la hora real sin ocupar dos renglones.
 *
 * Pensado para contextos NO-tabla (cards, drawers, modales, filas inline).
 * En tablas usar <FormattedDateTime /> (dos lineas: TZ usuario + subtitulo UTC).
 */
const LocalDateTime = ({ date, className = '' }) => {
  const timezone = useDisplayTimezone();

  if (!date) {
    return <span className={className}>—</span>;
  }

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <span className={className}>{formatDateTime(date, timezone)}</span>
      </TooltipTrigger>
      <TooltipContent>{formatDateTimeUTC(date)}</TooltipContent>
    </Tooltip>
  );
};

export { LocalDateTime };
