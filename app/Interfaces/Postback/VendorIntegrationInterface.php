<?php

namespace App\Interfaces\Postback;

interface VendorIntegrationInterface
{
    /**
     * Busca un click específico en los reportes del vendor y retorna el payout o el objeto de conversión completo.
     *
     * @param string $clickId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @param bool $returnConversionObject Si es true, devuelve el array de la conversión. Si es false, devuelve el float del payout.
     * @return float|array|null
     */
    public function getPayoutForClickId(string $clickId, ?string $fromDate, ?string $toDate, bool $returnConversionObject = false): float|array|null;
}
