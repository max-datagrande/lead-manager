<?php

namespace App\Console\Commands;

use App\Enums\WebhookLeadStatus;
use App\Models\WebhookLead;
use App\Support\SlackMessageBundler;
use Illuminate\Console\Command;
use App\Libraries\Twyne;
use Maxidev\Logger\TailLogger;

use App\Services\GeolocationService; // Import the service
use Exception;

class ProcessTwyneFormLeads extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'app:process-twyne-form-leads';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Process pending Twyne form leads by mapping data and sending to client API.';

  /**
   * The specific form ID for Twyne leads to process.
   *
   * @var string
   */
  protected string $formId = '1981121326072730';

  /**
   * Execute the console command.
   */
  public function handle(GeolocationService $geolocationService)
  {
    $this->info('Starting to process pending Twyne form leads...');
    // Define the mapping for this specific command, including transformations.
    $twyneMapping = [
      'first' => ['source' => 'first_name'],
      'last' => ['source' => 'last_name'],
      'email' => ['source' => 'email'],
      'address1' => ['source' => 'address1'],
      'zip' => ['source' => 'zipcode'],
      'city' => ['source' => 'city'],
      'state' => ['source' => 'state'],
      'phone' => ['source' => 'phone1'],
      'trustedform' => ['source' => 'trustedform_cert_url'],
      'subid1' => ['source' => 'fb_form_id'],
      'externalid' => ['source' => 'fb_lead_id'],
      'ip' => ['source' => 'ip_address'],
      'dob' => [
        'source' => 'DOB',
        'transform' => fn($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('m/d/Y') : null
      ],
      'cq1' => [
        'source' => 'desired_amount_of_coverage',
        'transform' => fn($value) => $value ? preg_replace('/[^\d]/', '', $value) : null
      ],
      'cq2' => ['source' => 'please_enter_your_beneficiary_s_name'],
      'cq3' => ['source' => 'what_is_your_relationship_to_the_beneficiary'],
    ];

    $unprocessedLeads = WebhookLead::where('source', 'trusted')
      ->where('status', WebhookLeadStatus::PENDING)
      ->whereJsonContains('payload->fb_form_id', $this->formId)
      ->get();

    if ($unprocessedLeads->isEmpty()) {
      $this->info('No pending Twyne form leads found to process.');
      return Command::SUCCESS;
    }

    $this->info("Found {$unprocessedLeads->count()} Twyne form leads to process.");

    foreach ($unprocessedLeads as $webhookLead) {
      $response = null;
      $request = null;
      try {
        $this->info("Processing webhook_lead_id: {$webhookLead->id}");

        // --- Geolocation Step (before Twyne instantiation) ---
        $zipcode = data_get($webhookLead->payload, 'zipcode');
        if (empty($zipcode)) {
          throw new \App\Exceptions\MissingRequiredFieldsException(
            'Zipcode is required.',
            ['zipcode']
          );
        }

        // --- Extract 5-digit Zipcode ---
        $rawZipcode = (string) $zipcode; // Ensure it's a string
        $cleanZipcode = null;
        if (preg_match('/\b(\d{5})\b/', $rawZipcode, $matches)) {
          $cleanZipcode = $matches[1];
        }

        if (empty($cleanZipcode)) {
          throw new Exception("Could not extract a 5-digit zipcode from '{$rawZipcode}'.");
        }

        $addressInfo = $geolocationService->getCityAndStateFromZipcode($cleanZipcode);
        if (is_null($addressInfo)) {
          throw new Exception("Geolocation failed for zipcode: {$cleanZipcode}.");
        }
        $ipAddress = data_get($webhookLead->payload, 'ip_address', null) ?? $webhookLead->ip_origin;
        $payload = [...$webhookLead->payload, 'city' => $addressInfo['city'],  'state' => $addressInfo['state'], 'ip_address' => $ipAddress, 'zipcode' => $cleanZipcode];

        // 1. Instantiate the Twyne library with payload, ID, and the specific mapping
        $twyneRequest = new Twyne($payload, $twyneMapping);

        // 2. Submit the pre-built request to the client API
        $response = $twyneRequest->submit();
        $request = $twyneRequest->getRequest();
        // 3. Check if the request failed
        if ($response->failed()) {
          $response->throw();
        }
        $data = $response->json();
        //Check if "Accepted" is into status string
        $isAccepted = isset($data['status']) && strpos($data['status'], 'Accepted') !== false;
        if (!$isAccepted) {
          throw new \Exception("Lead {$webhookLead->id} not Accepted by Twyne. Status: " . ($data['status'] ?? 'Unknown'));
        }

        $this->info("Lead {$webhookLead->id} Accepted");

        // 4. If the call is successful, process the response
        $webhookLead->status = WebhookLeadStatus::PROCESSED;
        $webhookLead->data = $request;
        $webhookLead->response = $data;
        $webhookLead->processed_at = now();
        $webhookLead->save();

        $this->info("Successfully processed webhook_lead_id: {$webhookLead->id}");

        // Delay of 2 seconds between requests
        sleep(2);
      } catch (\App\Exceptions\MissingRequiredFieldsException $e) {
        // Catch the specific validation exception to SKIP the lead
        $missingFields = $e->getMissingFields();
        $this->warn("Skipping webhook_lead_id: {$webhookLead->id}. Reason: " . $e->getMessage() . " Missing: " . implode(', ', $missingFields));

        $webhookLead->status = WebhookLeadStatus::SKIPPED;
        $webhookLead->response = ['error' => $e->getMessage(), 'missing_fields' => $missingFields];
        $webhookLead->processed_at = now();
        $webhookLead->save();

        // Continue to the next lead without stopping the whole command
        continue;
      } catch (\Throwable $e) {
        // Catch all other exceptions as a FAILED attempt
        $errorMessage = $e->getMessage();
        $responseBody = $response ? $response->body() : (method_exists($e, 'response') ? $e->response->body() : 'N/A');
        $errorContext = [
          'webhook_lead_id' => $webhookLead->id,
          'error' => $errorMessage,
          'file' => $e->getFile() . ':' . $e->getLine(),
          'response_body' => $responseBody,
        ];

        // Store error information in the database
        $webhookLead->status = WebhookLeadStatus::FAILED;
        $webhookLead->data = $request;
        $webhookLead->response = ['error' => $errorContext];
        $webhookLead->processed_at = now();
        $webhookLead->save();

        // Send Slack notification
        $slack = new SlackMessageBundler();
        $slack->addTitle('Twyne Lead Processing Failed', '🚨')
          ->addSection('The cron job failed to process a Twyne lead.')
          ->addDivider()
          ->addKeyValue('Webhook Lead ID', $webhookLead->id)
          ->addKeyValue('Error', $errorMessage, true)
          ->addSection('Response Details: ```' . substr((string)$responseBody, 0, 200) . '```')
          ->sendDirect('error');

        $this->error("Failed to process Twyne lead. Error: {$errorMessage}");
        TailLogger::saveLog('Twyne Lead Processing Failed', 'webhooks/leads/twyne', 'error', $errorContext);

        // Stop the command on error
        continue;
        /* return Command::FAILURE; */
      }
    }

    $this->info('All pending Twyne form leads have been processed.');
    return Command::SUCCESS;
  }
}
