<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhitelistEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

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
      'entries' => $entries,
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

    $entry = WhitelistEntry::create($request->all());

    return response()->json([
      'success' => true,
      'data' => $entry,
      'message' => $request->type === 'domain' ? 'Dominio agregado exitosamente' : 'IP agregada exitosamente'
    ]);
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
      'message' => $request->type === 'domain' ? 'Dominio actualizado exitosamente' : 'IP actualizada exitosamente'
    ]);
  }

  /**
   * Eliminar entrada de whitelist
   */
  public function destroy(WhitelistEntry $entry)
  {
    $type = $entry->type;
    $entry->delete();

    return response()->json([
      'success' => true,
      'message' => $type === 'domain' ? 'Dominio eliminado exitosamente' : 'IP eliminada exitosamente'
    ]);
  }

  /**
   * Validar formato de dominio o IP en tiempo real
   */
  public function validate(Request $request)
  {
    $type = $request->input('type');
    $value = $request->input('value');

    $errors = [];
    $isValid = false;

    if ($type === 'domain') {
      // Validación estricta para dominios usando filter_var
      $isValid = filter_var($value, FILTER_VALIDATE_URL);

      // Validación adicional para asegurar que tenga protocolo HTTP/HTTPS
      $isHttps = preg_match('/^https?:\/\//i', $value);

      if (!$isValid) {
        $errors[] = 'El formato del dominio no es válido';
      }
      if (!$isHttps) {
        $errors[] = 'El dominio debe incluir protocolo HTTP o HTTPS';
      }

      $isValid = $isValid && $isHttps;
    } elseif ($type === 'ip') {
      // Validación para direcciones IP
      $isValid = filter_var($value, FILTER_VALIDATE_IP);

      if (!$isValid) {
        $errors[] = 'El formato de la dirección IP no es válido';
      }
    } else {
      $errors[] = 'Tipo no válido. Debe ser "domain" o "ip"';
    }

    return response()->json([
      'valid' => $isValid,
      'errors' => $errors
    ]);
  }
}
