import { formatDateTime, formatDateTimeUTC } from '@/utils/table';

const FormattedDateTime = ({ date }) => {
  if (!date) {
    return null;
  }

  return (
    <div className="text-sm">
      <div className="font-medium">{formatDateTime(date)}</div>
      <div className="text-xs whitespace-nowrap text-gray-500">{formatDateTimeUTC(date)}</div>
    </div>
  );
};

export { FormattedDateTime };
