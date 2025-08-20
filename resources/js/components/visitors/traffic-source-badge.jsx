import { Badge } from '@/components/ui/badge';

/**
 * Componente para mostrar fuente de tráfico
 * 
 * @param {Object} props - Propiedades del componente
 * @param {string} props.source - Fuente de tráfico (organic, paid, social, direct)
 * @param {string} props.medium - Medio de tráfico
 * @returns {JSX.Element} Badge con color según la fuente de tráfico
 */
const TrafficSourceBadge = ({ source, medium }) => {
  const getSourceColor = (src) => {
    switch (src?.toLowerCase()) {
      case 'organic':
        return 'bg-green-500';
      case 'paid':
        return 'bg-red-500';
      case 'social':
        return 'bg-blue-500';
      case 'direct':
        return 'bg-gray-500';
      default:
        return 'bg-orange-500';
    }
  };

  const displayText = medium ? `${source}/${medium}` : source || 'Unknown';

  return <Badge className={getSourceColor(source)}>{displayText}</Badge>;
};

export default TrafficSourceBadge;