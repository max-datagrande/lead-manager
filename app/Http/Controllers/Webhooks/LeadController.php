<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLead;
use Illuminate\Http\Request;
use Maxidev\Logger\TailLogger;

class LeadController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    $source = $request->route('source', 'default');
    $payload = $request->all();
    TailLogger::saveLog('Webhook received for source: ' . $source, 'webhooks/leads/store', 'info', ['payload' => $payload]);
    try {
      $webhookLead = WebhookLead::create([
        'source' => $source,
        'payload' => $payload,
      ]);
      TailLogger::saveLog('Webhook processed successfully for source: ' . $source, 'webhooks/leads/store', 'info', ['id' => $webhookLead->id]);
      return response()->json([
        'message' => 'Webhook processed successfully',
        'id' => $webhookLead->id,
      ], 201);
    } catch (\Exception $e) {
      $slack = new \App\Support\SlackMessageBundler();
      TailLogger::saveLog('Webhook processing failed for source: ' . $source, 'webhooks/leads/store', 'error', ['error' => $e->getMessage()]);
      $slack->addTitle('Webhook Processing Failed', '🚨')
        ->addSection('An exception occurred while processing an incoming webhook.')
        ->addDivider()
        ->addKeyValue('Source', $request->route('source', 'default'))
        ->addKeyValue('Error Message', $e->getMessage(), true)
        ->addKeyValue('File', $e->getFile() . ':' . $e->getLine())
        ->addKeyValue('Payload', json_encode($request->all()), true)
        ->addFooter('Reported automatically at ' . now()->toDateTimeString())
        ->sendDirect('error');

      return response()->json([
        'message' => 'An internal server error occurred.'
      ], 500);
    }
  }

  /**
   * Display the specified resource.
   */
  public function show(string $id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, string $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(string $id)
  {
    //
  }
}
