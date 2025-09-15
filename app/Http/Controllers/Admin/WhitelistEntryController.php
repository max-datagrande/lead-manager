<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhitelistEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;
use Maxidev\Logger\TailLogger;

class WhitelistEntryController extends Controller
{
  /**
   * Mostrar lista paginada de entradas de whitelist
   */
  public function index(Request $request)
  {
    $entries = WhitelistEntry::query()
      ->when($request->search, function ($query, $search) {
        $query->where(function ($q) use ($search) {
          $q->where('name', 'like', "%{$search}%")
            ->orWhere('value', 'like', "%{$search}%");
        });
      })
      ->when($request->type, function ($query, $type) {
        $query->where('type', $type);
      })
      ->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
      ->paginate(10);

    return Inertia::render('whitelist/index', [
      'rows' => $entries,
      'filters' => $request->only(['search', 'sort', 'direction', 'type'])
    ]);
  }

  /**
   * Crear nueva entrada de whitelist
   */
  public function store(Request $request)
  {
    $rules = [
      'type' => ['required', 'in:domain,ip'],
      'name' => ['required', 'string', 'max:255'],
      'is_active' => ['boolean']
    ];

    // Validación específica según el tipo
    if ($request->type === 'domain') {
      $rules['value'] = [
        'required',
        'url',
        Rule::unique('whitelist_entries')->where(function ($query) use ($request) {
          return $query->where('type', $request->type);
        })
      ];
    } else {
      $rules['value'] = [
        'required',
        'ip',
        Rule::unique('whitelist_entries')->where(function ($query) use ($request) {
          return $query->where('type', $request->type);
        })
      ];
    }

    $request->validate($rules);
    $data = $request->all();
    $entry = WhitelistEntry::create($data);
    $message = $request->type === 'domain' ? 'Domain successfully added' : 'IP successfully added';
    add_flash_message('success', $message);
    return redirect()->route('whitelist.index');
  }

  /**
   * Actualizar entrada de whitelist existente
   */
  public function update(Request $request, WhitelistEntry $entry)
  {
    $rules = [
      'type' => ['required', 'in:domain,ip'],
      'name' => ['required', 'string', 'max:255'],
      'is_active' => ['boolean']
    ];

    // Validación específica según el tipo
    if ($request->type === 'domain') {
      $rules['value'] = [
        'required',
        'url',
        Rule::unique('whitelist_entries')->where(function ($query) use ($request) {
          return $query->where('type', $request->type);
        })->ignore($entry->id)
      ];
    } else {
      $rules['value'] = [
        'required',
        'ip',
        Rule::unique('whitelist_entries')->where(function ($query) use ($request) {
          return $query->where('type', $request->type);
        })->ignore($entry->id)
      ];
    }

    $request->validate($rules);

    $entry->update($request->all());

    return response()->json([
      'success' => true,
      'data' => $entry,
      'message' => $request->type === 'domain' ? 'Domain successfully updated' : 'IP successfully updated'
    ]);
  }

  /**
   * Eliminar entrada de whitelist
   */
  public function destroy(WhitelistEntry $whitelist)
  {
    TailLogger::saveLog('Destroying whitelist entry', 'testing/whitelist', 'info', ['entry_id' => $whitelist->id]);
    $type = $whitelist->type;
    $whitelist->delete();
    $message = $type === 'domain' ? 'Domain successfully deleted' : 'IP successfully removed';
    add_flash_message('success', $message);
    return redirect()->route('whitelist.index');
  }
}
