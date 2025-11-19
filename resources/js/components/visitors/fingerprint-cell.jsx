import CopyToClipboard from '@/components/copy-to-clipboard';

/**
 * Componente para mostrar fingerprint truncado con tooltip y funcionalidad de copia
 *
 * @param {Object} props - Propiedades del componente
 * @param {string} props.fingerprint - Fingerprint completo del visitante
 * @returns {JSX.Element} Fingerprint truncado con tooltip y botón de copia
 */
const FingerprintCell = ({ fingerprint }) => {
  /* const truncated = fingerprint ? fingerprint.substring(0, 20) + '...' : 'N/A'; */
  //Show the last 10 characters of the fingerprint
  const truncated = fingerprint ? fingerprint.substring(fingerprint.length - 20) : 'N/A';
  const textToCopy = fingerprint || 'N/A';

  return (
    <CopyToClipboard textToCopy={textToCopy}>
      <span className="font-mono text-xs" title={fingerprint}>
        {truncated}
      </span>
    </CopyToClipboard>
  );
};

export default FingerprintCell;
