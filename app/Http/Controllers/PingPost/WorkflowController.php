<?php

namespace App\Http\Controllers\PingPost;

use App\Enums\WorkflowStrategy;
use App\Http\Controllers\Controller;
use App\Http\Requests\PingPost\StoreWorkflowRequest;
use App\Http\Requests\PingPost\UpdateWorkflowRequest;
use App\Models\Buyer;
use App\Models\Workflow;
use App\Services\PingPost\WorkflowService;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
  public function __construct(private readonly WorkflowService $workflowService) {}

  public function index(): Response
  {
    $workflows = Workflow::query()->with('user')->withCount('workflowBuyers')->latest()->paginate(25);

    return Inertia::render('ping-post/workflows/index', [
      'workflows' => $workflows,
    ]);
  }

  public function create(): Response
  {
    return Inertia::render('ping-post/workflows/create', [
      'buyers' => Buyer::with('integration')
        ->where('is_active', true)
        ->get(['id', 'name', 'integration_id']),
      'strategies' => WorkflowStrategy::toArray(),
    ]);
  }

  public function store(StoreWorkflowRequest $request): \Illuminate\Http\RedirectResponse
  {
    $data = $request->safe()->except('buyers');
    $buyers = $request->input('buyers', []);

    $workflow = $this->workflowService->create($data);
    $this->workflowService->syncBuyers($workflow, $buyers);

    return redirect()->route('ping-post.workflows.index')->with('success', 'Workflow created successfully.');
  }

  public function show(Workflow $workflow): Response
  {
    $workflow->load(['workflowBuyers.integration.company', 'user']);

    return Inertia::render('ping-post/workflows/show', [
      'workflow' => $workflow,
    ]);
  }

  public function edit(Workflow $workflow): Response
  {
    $workflow->load(['workflowBuyers.integration']);

    return Inertia::render('ping-post/workflows/edit', [
      'workflow' => $workflow,
      'buyers' => Buyer::with('integration')
        ->where('is_active', true)
        ->get(['id', 'name', 'integration_id']),
      'strategies' => WorkflowStrategy::toArray(),
    ]);
  }

  public function update(UpdateWorkflowRequest $request, Workflow $workflow): \Illuminate\Http\RedirectResponse
  {
    $data = $request->safe()->except('buyers');
    $buyers = $request->input('buyers');

    $this->workflowService->update($workflow, $data);

    if ($buyers !== null) {
      $this->workflowService->syncBuyers($workflow, $buyers);
    }

    return redirect()->route('ping-post.workflows.show', $workflow)->with('success', 'Workflow updated successfully.');
  }

  public function duplicate(Workflow $workflow): \Illuminate\Http\RedirectResponse
  {
    $clone = $this->workflowService->duplicate($workflow);

    return redirect()->route('ping-post.workflows.edit', $clone)->with('success', 'Workflow duplicated successfully.');
  }

  public function destroy(Workflow $workflow): \Illuminate\Http\RedirectResponse
  {
    $workflow->delete();

    return redirect()->route('ping-post.workflows.index')->with('success', 'Workflow deleted successfully.');
  }
}
