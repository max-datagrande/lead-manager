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
    $sort = $request->get('sort', 'created_at:desc');
    [$col, $dir] = get_sort_data($sort);
    $entries = WhitelistEntry::query()
      ->orderBy($col, $dir)
      ->get();
    return Inertia::render('whitelist/index', [
      'rows' => $entries,
      'filters' => compact('sort')
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
  public function update(WhitelistEntry $whitelist, Request $request)
  {
    $isDomainType = $request->type === 'domain';
    $rules = [
      'type'      => ['required', 'in:domain,ip'],
      'name'      => ['required', 'string', 'max:255'],
      'value'     => ['required', 'string', 'max:255'],
      'is_active' => ['nullable', 'boolean'],
    ];
    $rules['value'][] = $isDomainType ? 'url' : 'ip';
    $newData = $request->validate($rules);

    // Asegurar el booleano
    $newData['is_active'] = $request->boolean('is_active');
    $whitelist->update($newData);
    add_flash_message('success', 'Field updated successfully.');
    return back();
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
