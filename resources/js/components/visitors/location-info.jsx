/**
 * Componente para mostrar información de ubicación
 * 
 * @param {Object} props - Propiedades del componente
 * @param {string} props.city - Ciudad del visitante
 * @param {string} props.state - Estado/provincia del visitante
 * @param {string} props.countryCode - Código de país del visitante
 * @returns {JSX.Element} Información de ubicación formateada
 */
const LocationInfo = ({ city, state, countryCode }) => {
  const location = [city, state, countryCode].filter(Boolean).join(', ');
  return <span className="text-sm text-gray-600">{location || 'Unknown'}</span>;
};

export default LocationInfo;