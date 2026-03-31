<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;
use App\Models\LeadDispatch;
use App\Models\Workflow;
use Inertia\Inertia;
use Inertia\Response;

class LeadDispatchLogController extends Controller
{
    public function index(): Response
    {
        $dispatches = LeadDispatch::query()
            ->with(['workflow', 'lead', 'winnerIntegration'])
            ->latest()
            ->paginate(50);

        return Inertia::render('ping-post/dispatches/index', [
            'dispatches' => $dispatches,
            'workflows' => Workflow::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(LeadDispatch $dispatch): Response
    {
        $dispatch->load([
            'workflow',
            'lead',
            'winnerIntegration.company',
            'pingResults.integration.company',
            'postResults.integration.company',
            'postResults.pingResult',
        ]);

        return Inertia::render('ping-post/dispatches/show', [
            'dispatch' => $dispatch,
        ]);
    }
}
