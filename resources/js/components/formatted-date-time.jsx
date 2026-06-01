import { useUserTimezone } from '@/hooks/use-user-timezone';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';

const FormattedDateTime = ({ date }) => {
  const { timezone } = useUserTimezone();

  if (!date) {
    return null;
  }

  return (
    <div className="text-sm">
      <div className="font-medium">{formatDateTime(date, timezone)}</div>
      <div className="text-xs whitespace-nowrap text-gray-500">{formatDateTimeUTC(date)}</div>
    </div>
  );
};

export { FormattedDateTime };
