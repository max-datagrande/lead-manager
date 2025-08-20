import { Badge } from '@/components/ui/badge';

/**
 * Componente para mostrar badge de dispositivo con color apropiado
 * 
 * @param {Object} props - Propiedades del componente
 * @param {string} props.deviceType - Tipo de dispositivo (mobile, desktop, tablet)
 * @returns {JSX.Element} Badge con color según el tipo de dispositivo
 */
const DeviceBadge = ({ deviceType }) => {
  const getDeviceColor = (type) => {
    switch (type?.toLowerCase()) {
      case 'mobile':
        return 'bg-blue-500';
      case 'desktop':
        return 'bg-green-500';
      case 'tablet':
        return 'bg-purple-500';
      default:
        return 'bg-gray-500';
    }
  };

  return <Badge className={getDeviceColor(deviceType)}>{deviceType || 'Unknown'}</Badge>;
};

export default DeviceBadge;