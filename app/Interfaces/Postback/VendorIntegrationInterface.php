<?php

namespace App\Interfaces\Postback;

interface VendorIntegrationInterface
{
    /**
     * Busca un click específico en los reportes del vendor y retorna el payout.
     *
     * @param string $clickId
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return float|null
     */
    public function getPayoutForClickId(string $clickId, ?string $fromDate, ?string $toDate): ?float;
}
