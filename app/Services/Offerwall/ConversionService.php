<?php

namespace App\Services\Offerwall;

use App\Models\OfferwallConversion;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service class for handling offerwall conversion logic.
 */
class ConversionService
{
    /**
     * Create a new offerwall conversion record.
     *
     * @param array $data The data for creating the conversion.
     * @return OfferwallConversion
     */
    public function createConversion(array $data): OfferwallConversion
    {
        try {
            // Here you would typically validate the data before creation.
            // For now, we'll assume the data is valid and proceed with creation.

            $conversion = OfferwallConversion::create($data);

            Log::info('Offerwall conversion created successfully.', ['id' => $conversion->id]);

            return $conversion;
        } catch (Exception $e) {
            Log::error('Failed to create offerwall conversion.', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            // Re-throw the exception to be handled by the controller or a global exception handler.
            throw $e;
        }
    }
}
