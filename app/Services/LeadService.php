<?php

namespace App\Services;

use App\Models\Field;
use App\Models\Lead;
use App\Models\TrafficLog;
use App\Models\LeadFieldResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maxidev\Logger\TailLogger;

/**
 * @description Service to handle lead business logic operations.
 */
class LeadService
{
  /**
   * Validate that the fingerprint exists in traffic logs and is not a bot.
   *
   * @param string $fingerprint
   * @return TrafficLog
   * @throws ValidationException
   */
  public function validateTrafficLog(string $fingerprint): TrafficLog
  {
    // Validar que el fingerprint exista en la tabla traffic_logs
    $visitorLog = TrafficLog::where('fingerprint', $fingerprint)->first();
    if (!$visitorLog) {
      TailLogger::saveLog('Fingerprint not found in traffic logs', 'leads/service', 'warning', compact('fingerprint'));
      throw ValidationException::withMessages([
        'services' => 'Fingerprint not found',
      ]);
    }

    // Verificar si es un bot
    if ($visitorLog->is_bot) {
      TailLogger::saveLog('Bot detected, lead creation blocked', 'leads/service', 'warning', compact('fingerprint'));
      throw ValidationException::withMessages([
        'services' => 'Bot detected',
      ]);
    }
    return $visitorLog;
  }

  /**
   * Create or find an existing lead based on fingerprint.
   *
   * @param TrafficLog $visitorLog
   * @return Lead
   */
  public function createOrFindLead(TrafficLog $visitorLog): Lead
  {
    // Usar firstOrCreate como en el controlador original
    $lead = Lead::firstOrCreate([
      'fingerprint' => $visitorLog->fingerprint,
      'website' => $visitorLog->host,
      'ip_address' => $visitorLog->ip_address,
    ]);

    $isNew = $lead->wasRecentlyCreated;
    $message = $isNew ? 'New lead created' : 'Existing lead found';
    TailLogger::saveLog($message, 'leads/service', 'info', [
      'fingerprint' => $visitorLog->fingerprint,
      'lead_id' => $lead->id,
    ]);

    return $lead;
  }
  /**
   * Find an existing lead based on fingerprint.
   *
   * @param TrafficLog $visitorLog
   * @return Lead
   */
  public function findLead(TrafficLog $visitorLog): Lead
  {
    // Usar firstOrCreate como en el controlador original
    $fingerprint = $visitorLog->fingerprint;
    $lead = Lead::where('fingerprint', $fingerprint)->first();
    if (!$lead) {
      TailLogger::saveLog('Lead not found in database', 'leads/service', 'warning', compact('fingerprint'));
      throw ValidationException::withMessages([
        'services' => 'Lead not found. Lead must be registered first.',
      ]);
    }
    return $lead;
  }

  /**
   * Process and save lead field responses.
   *
   * @param Lead $lead
   * @param array $fields
   * @return void
   */
  public function processLeadFields(Lead $lead, array $fields): array
  {
    $createdCount = 0;
    $updatedCount = 0;
    $createdFields = [];
    $updatedFields = [];
    $fingerprint = $lead->fingerprint;

    DB::beginTransaction();
    try {
      foreach ($fields as $fieldName => $fieldValue) {
        if (is_null($fieldValue)) {
          continue; // Omitir valores nulos
        }
        //If is array
        if (is_array($fieldValue)) {
          $fieldValue = json_encode($fieldValue);
        }

        // Buscar el field por name (como en el controlador original)
        $field = Field::where('name', $fieldName)->first();
        if (!$field) {
          TailLogger::saveLog("Field '{$fieldName}' not found for fingerprint '{$fingerprint}'", 'leads/store', 'warning', compact('fieldValue'));
          continue; // Saltar este field si no existe
        }
        // Crear o actualizar la respuesta del field
        $response = LeadFieldResponse::updateOrCreate(
          [
            'lead_id' => $lead->id,
            'field_id' => $field->id,
            'fingerprint' => $fingerprint,
          ],
          ['value' => $fieldValue],
        );

        // Verificar si fue creado o actualizado
        if ($response->wasRecentlyCreated) {
          $createdCount++;
          $createdFields[$fieldName] = $fieldValue;
        } elseif ($response->wasChanged()) {
          $updatedCount++;
          $updatedFields[$fieldName] = $fieldValue;
        }
        TailLogger::saveLog('Field response processed', 'leads/service', 'info', [
          'field_name' => $fieldName,
          'field_id' => $field->id,
          'lead_id' => $lead->id,
          'value' => $fieldValue,
        ]);
      }
      DB::commit();
      return [
        'created_count' => $createdCount,
        'updated_count' => $updatedCount,
        'created_fields' => $createdFields,
        'updated_fields' => $updatedFields,
      ];
    } catch (\Exception $e) {
      DB::rollBack();
      TailLogger::saveLog('Error processing lead fields', 'leads/service', 'error', [
        'lead_id' => $lead->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);
      throw $e;
    }
  }

  /**
   * Remove specific fields from a lead by field names.
   *
   * @param Lead $lead
   * @param array<int, string> $fieldNames
   * @return int Number of fields removed
   */
  public function removeLeadFields(Lead $lead, array $fieldNames): int
  {
    $fieldIds = Field::whereIn('name', $fieldNames)->pluck('id');

    if ($fieldIds->isEmpty()) {
      TailLogger::saveLog('No matching fields found to remove', 'leads/service', 'info', [
        'lead_id' => $lead->id,
        'requested_fields' => $fieldNames,
      ]);
      return 0;
    }

    $deletedCount = LeadFieldResponse::where('lead_id', $lead->id)->whereIn('field_id', $fieldIds)->delete();

    TailLogger::saveLog('Lead fields removed', 'leads/service', 'info', [
      'lead_id' => $lead->id,
      'fields' => $fieldNames,
      'deleted_count' => $deletedCount,
    ]);

    return $deletedCount;
  }

  /**
   * Log successful lead creation.
   *
   * @param Lead $lead
   * @return void
   */
  public function logLeadSuccess(Lead $lead): void
  {
    TailLogger::saveLog('Lead successfully processed', 'leads/service', 'info', [
      'lead_id' => $lead->id,
      'fingerprint' => $lead->fingerprint,
    ]);
  }

  /**
   * Log successful lead update.
   *
   * @param Lead $lead
   * @return void
   */
  public function logLeadUpdateSuccess(Lead $lead): void
  {
    TailLogger::saveLog('Lead successfully updated', 'leads/service', 'info', [
      'lead_id' => $lead->id,
      'fingerprint' => $lead->fingerprint,
    ]);
  }
}
