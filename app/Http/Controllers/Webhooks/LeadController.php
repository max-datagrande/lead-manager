<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\WebhookLead;
use Illuminate\Http\Request;

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
    try {
      $webhookLead = WebhookLead::create([
        'source' => $request->route('source', 'default'),
        'payload' => $request->all(),
      ]);

      return response()->json([
        'message' => 'Webhook processed successfully',
        'id' => $webhookLead->id,
      ], 201);
    } catch (\Exception $e) {
      $slack = new \App\Support\SlackMessageBundler();

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
