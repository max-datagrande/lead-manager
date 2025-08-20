import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link } from '@inertiajs/react';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import ReactCountryFlag from 'react-country-flag';
import BotBadge from './bot-badge';
import DeviceBadge from './device-badge';
import FingerprintCell from './fingerprint-cell';
import LocationInfo from './location-info';
import TrafficSourceBadge from './traffic-source-badge';

// Configurar plugins de dayjs
dayjs.extend(utc);
dayjs.extend(timezone);

/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.visitors - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = ({ visitors }) => {
  /**
   * Formatea la fecha de visita (solo fecha, sin hora)
   */
  const formatVisitDate = (dateString) => {
    if (!dateString) return 'N/A';
    return dayjs(dateString).format('MM/DD/YYYY');
  };

  /**
   * Formatea la fecha con hora en formato legible (en-US)
   */
  const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return dayjs(dateString).format('MM/DD/YYYY hh:mm A');
  };

  /**
   * Formatea la fecha en UTC
   */
  const formatDateTimeUTC = (dateString) => {
    if (!dateString) return 'N/A';
    return dayjs(dateString).utc().format('MM/DD/YYYY hh:mm A UTC');
  };

  return (
    <>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Fingerprint</TableHead>
              <TableHead>Visit Date</TableHead>
              <TableHead>Logged</TableHead>
              <TableHead>City</TableHead>
              <TableHead>State</TableHead>
              <TableHead>Country</TableHead>
              <TableHead>Device</TableHead>
              <TableHead>Browser/OS</TableHead>
              <TableHead>Traffic Source</TableHead>
              <TableHead>Visits</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Host</TableHead>
              <TableHead>Created At</TableHead>
              <TableHead>Updated At</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {visitors.data.map((visitor) => (
              <TableRow key={visitor.id}>
                <TableCell>
                  <FingerprintCell fingerprint={visitor.fingerprint} />
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    <div className="font-medium">{formatVisitDate(visitor.visit_date)}</div>
                    {visitor.visit_date && <div className="text-xs text-gray-500">{dayjs(visitor.visit_date).utc().format('MM/DD/YYYY UTC')}</div>}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm font-medium">
                    {formatDateTime(visitor.created_at)}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    {visitor.city || 'N/A'}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    {visitor.state || 'N/A'}
                  </div>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2 text-sm">
                    {visitor.country_code && (
                      <ReactCountryFlag 
                        countryCode={visitor.country_code} 
                        svg 
                        style={{
                          width: '1.2em',
                          height: '1.2em',
                        }}
                        title={visitor.country_code}
                      />
                    )}
                    <span>{visitor.country_code || 'N/A'}</span>
                  </div>
                </TableCell>
                <TableCell>
                  <DeviceBadge deviceType={visitor.device_type} />
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    <div>{visitor.browser || 'Unknown'}</div>
                    <div className="text-xs text-gray-500">{visitor.os || 'Unknown'}</div>
                  </div>
                </TableCell>
                <TableCell>
                  <TrafficSourceBadge source={visitor.traffic_source} medium={visitor.traffic_medium} />
                </TableCell>
                <TableCell>
                  <Badge variant="outline">{visitor.visit_count || 1}</Badge>
                </TableCell>
                <TableCell>
                  <BotBadge isBot={visitor.is_bot} />
                </TableCell>
                <TableCell>
                  <span className="text-sm text-gray-600">{visitor.host}</span>
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    <div className="font-medium">{formatDateTime(visitor.created_at)}</div>
                    <div className="text-xs text-gray-500">{formatDateTimeUTC(visitor.created_at)}</div>
                  </div>
                </TableCell>
                <TableCell>
                  <div className="text-sm">
                    <div className="font-medium">{formatDateTime(visitor.updated_at)}</div>
                    <div className="text-xs text-gray-500">{formatDateTimeUTC(visitor.updated_at)}</div>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {/* Paginación */}
      {visitors.links && visitors.links.length > 3 && (
        <div className="flex items-center justify-between px-2 py-4">
          <div className="text-sm text-gray-500">
            Showing {visitors.from} to {visitors.to} of {visitors.total} results
          </div>
          <div className="flex items-center space-x-2">
            {visitors.links.map((link, index) => {
              if (link.label.includes('Previous')) {
                return (
                  <Button key={index} variant="outline" size="sm" disabled={!link.url} asChild={!!link.url}>
                    {link.url ? (
                      <Link href={link.url}>
                        <ChevronLeft className="h-4 w-4" />
                        Previous
                      </Link>
                    ) : (
                      <>
                        <ChevronLeft className="h-4 w-4" />
                        Previous
                      </>
                    )}
                  </Button>
                );
              }

              if (link.label.includes('Next')) {
                return (
                  <Button key={index} variant="outline" size="sm" disabled={!link.url} asChild={!!link.url}>
                    {link.url ? (
                      <Link href={link.url}>
                        Next
                        <ChevronRight className="h-4 w-4" />
                      </Link>
                    ) : (
                      <>
                        Next
                        <ChevronRight className="h-4 w-4" />
                      </>
                    )}
                  </Button>
                );
              }

              // Páginas numeradas
              return (
                <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} asChild={!!link.url}>
                  {link.url ? <Link href={link.url}>{link.label}</Link> : link.label}
                </Button>
              );
            })}
          </div>
        </div>
      )}
    </>
  );
};

/**
 * Componente para mostrar mensaje cuando no hay datos
 *
 * @returns {JSX.Element} Mensaje de estado vacío
 */
export const NoData = () => <div className="py-8 text-center text-gray-500">No visitors found.</div>;
