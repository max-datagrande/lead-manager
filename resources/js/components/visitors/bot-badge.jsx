import { Badge } from '@/components/ui/badge';

/**
 * Componente para mostrar estado de bot
 * 
 * @param {Object} props - Propiedades del componente
 * @param {boolean} props.isBot - Indica si el visitante es un bot
 * @returns {JSX.Element} Badge que indica si es bot o humano
 */
const BotBadge = ({ isBot }) => {
  return <Badge className={isBot ? 'bg-red-500' : 'bg-green-500'}>{isBot ? 'Bot' : 'Human'}</Badge>;
};

export default BotBadge;