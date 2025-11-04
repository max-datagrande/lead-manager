<?php

namespace App\Services\Types;

/**
 * Tipos para las respuestas de Natural Intelligence API
 */
class NaturalIntelligenceTypes
{
    /**
     * Estructura de un elemento de conversión individual
     */
    public static function conversionItem(): array
    {
        return [
            'data_type' => 'string',           // Tipo de dato (ej: "payout")
            'device' => 'string',              // Dispositivo (ej: "Mobile", "Desktop")
            'pub_param_1' => 'string',         // Click ID
            'pub_param_2' => 'string',         // Nombre de la campaña/oferta
            'external_campaign_id' => 'string', // ID de campaña externa
            'external_traffic_source' => 'string', // Fuente de tráfico
            'clickouts' => 'int',              // Número de clickouts
            'leads' => 'int',                  // Número de leads
            'payout' => 'float',               // Payout en USD
            'sales' => 'int',                  // Número de ventas
            'visits' => 'int',                 // Número de visitas
            'date_time' => 'string',           // Fecha y hora ISO 8601
        ];
    }

    /**
     * Estructura de la respuesta completa del reporte de conversiones
     */
    public static function conversionsReportResponse(): array
    {
        return [
            'success' => 'bool',               // Indica si la operación fue exitosa
            'data' => 'array<ConversionItem>', // Array de elementos de conversión
        ];
    }
}

/**
 * Interface para un elemento de conversión individual
 */
interface ConversionItemInterface
{
    public function getDataType(): string;
    public function getDevice(): string;
    public function getClickId(): string;           // pub_param_1
    public function getCampaignName(): string;      // pub_param_2
    public function getExternalCampaignId(): string;
    public function getExternalTrafficSource(): string;
    public function getClickouts(): int;
    public function getLeads(): int;
    public function getPayout(): float;
    public function getSales(): int;
    public function getVisits(): int;
    public function getDateTime(): string;
}

/**
 * Interface para la respuesta completa del reporte
 */
interface ConversionsReportResponseInterface
{
    public function isSuccess(): bool;
    public function getData(): array;
    public function getConversions(): array;
}