<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Field;
use App\Models\LeadFieldResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadController extends Controller
{
  /**
   * Display a listing of the resource.
   */
  public function index(Request $request)
  {
    $perPage    = 15;
    $search     = $request->input('search');
    $host       = $request->input('host');
    $startDate  = $request->input('start_date');
    $endDate    = $request->input('end_date');
    $sort       = $request->input('sort', 'created_at'); // Valor por defecto
    $direction  = $request->input('direction', 'desc');  // Valor por defecto


    $leadQuery = Lead::with('fields:id,name,label')
      ->select('leads.*');

    // (Opcional) filtro de búsqueda:
    if ($search) {
      $leadQuery->where(function($query) use ($search) {
        $query->where('city', 'ilike', "%{$search}%")
          ->orWhere('state', 'ilike', "%{$search}%")
          ->orWhere('fingerprint', 'ilike', "%{$search}%")
          ->orWhere('website', 'ilike', "%{$search}%");
      });
    }

    if ($host) {
      $leadQuery->where('website', $host);
    }

    if ($startDate) {
      $leadQuery->whereDate('leads.created_at', '>=', $startDate);
    }

    if ($endDate) {
      $leadQuery->whereDate('leads.created_at', '<=', $endDate);
    }

    // Ordenamiento (ahora siempre se aplica con valores por defecto)
    $leadQuery->orderBy($sort, $direction);
    // Paginado + conservar filtros explícitos
    $leads = $leadQuery->paginate($perPage)
      ->appends(
        $request->only('search', 'host', 'start_date', 'end_date', 'sort', 'direction')
      );
    $data = [
      'fields' => Field::select('id', 'name', 'label')
        ->orderBy('name')
        ->get(),
      'leads'  => $leads,
      // Cambiar esta línea para obtener hosts desde leads en lugar de traffic_logs
      'hosts'  => Lead::distinct()->whereNotNull('website')->where('website', '!=', '')->orderBy('website')->pluck('website'),
      'filters' => [
        'search'     => $search,
        'host'       => $host,
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'sort'       => $sort,
        'direction'  => $direction,
      ],
    ];
    return Inertia::render('Leads/Index', $data);
  }

  /**
   * Exportar leads filtrados a CSV usando chunks para optimizar memoria
   * 
   * Esta función permite descargar todos los leads que coincidan con los filtros
   * aplicados en formato CSV, incluyendo todos los campos dinámicos asociados.
   * Usa técnicas de optimización para manejar grandes volúmenes de datos.
   *
   * @param Request $request - Objeto que contiene todos los parámetros de la petición HTTP
   * @return \Symfony\Component\HttpFoundation\StreamedResponse - Respuesta de descarga directa
   */
  public function export(Request $request)
  {
    // PASO 1: CONFIGURACIÓN INICIAL DE MEMORIA Y TIEMPO
    // ================================================
    
    // Incrementar límite de memoria temporalmente para esta operación
    // Por defecto PHP tiene 128MB, lo aumentamos a 512MB para exportaciones grandes
    ini_set('memory_limit', '512M');
    
    // Aumentar tiempo máximo de ejecución a 5 minutos (300 segundos)
    // Por defecto PHP tiene 30 segundos, pero exportar muchos datos puede tomar más tiempo
    set_time_limit(300);

    // PASO 2: OBTENER PARÁMETROS DE FILTRADO DESDE LA PETICIÓN
    // ========================================================
    
    // $request->input() obtiene valores enviados desde el frontend (formulario, URL, etc.)
    // Si no existe el parámetro, devuelve null (excepto sort y direction que tienen valores por defecto)
    $search     = $request->input('search');        // Texto de búsqueda general
    $host       = $request->input('host');          // Filtro por website específico
    $startDate  = $request->input('start_date');    // Fecha de inicio del rango
    $endDate    = $request->input('end_date');      // Fecha de fin del rango
    $sort       = $request->input('sort', 'created_at');     // Campo por el cual ordenar (por defecto: fecha de creación)
    $direction  = $request->input('direction', 'desc');      // Dirección del ordenamiento (desc = más reciente primero)

    // PASO 3: OBTENER TODOS LOS CAMPOS (FIELDS) ÚNICOS QUE NECESITAMOS PARA EL CSV
    // ============================================================================
    
    // Field::select() - Selecciona solo las columnas que necesitamos de la tabla 'fields'
    // Esto es más eficiente que traer todas las columnas con Field::all()
    $allFields = Field::select('id', 'name', 'label')
      
      // whereHas() - Filtra solo los Fields que tienen al menos un Lead asociado que cumpla las condiciones
      // Es como decir: "Dame solo los campos que están siendo usados por leads que coinciden con mis filtros"
      ->whereHas('leads', function($query) use ($search, $host, $startDate, $endDate) {
        
        // Aplicar los mismos filtros que usamos para los leads
        // Esto asegura que solo obtengamos campos relevantes para la exportación actual
        
        if ($search) {
          // where(function()) crea un grupo de condiciones con paréntesis en SQL
          // Es como: WHERE (city LIKE '%search%' OR state LIKE '%search%' OR ...)
          $query->where(function($subQuery) use ($search) {
            // 'ilike' es insensible a mayúsculas/minúsculas (funciona en PostgreSQL)
            // "%{$search}%" significa que el texto puede estar en cualquier parte del campo
            $subQuery->where('city', 'ilike', "%{$search}%")
              ->orWhere('state', 'ilike', "%{$search}%")
              ->orWhere('fingerprint', 'ilike', "%{$search}%")
              ->orWhere('website', 'ilike', "%{$search}%");
          });
        }
        
        if ($host) {
          // Filtrar por website específico
          $query->where('website', 'ilike', "%{$host}%");
        }
        
        if ($startDate) {
          // whereDate() compara solo la parte de fecha, ignorando la hora
          // '>=' significa "desde esta fecha en adelante"
          $query->whereDate('leads.created_at', '>=', $startDate);
        }
        
        if ($endDate) {
          // '<=' significa "hasta esta fecha"
          $query->whereDate('leads.created_at', '<=', $endDate);
        }
      })
      
      // Ordenar los campos alfabéticamente por nombre para consistencia
      ->orderBy('name')
      
      // get() ejecuta la consulta y devuelve una Collection de Laravel
      ->get();

    // PASO 4: GENERAR NOMBRE DEL ARCHIVO CON TIMESTAMP
    // ================================================
    
    // date() genera una fecha/hora actual en formato específico
    // 'Y-m-d_H-i-s' produce algo como: 2025-07-14_15-30-45
    $filename = 'leads_export_' . date('Y-m-d_H-i-s') . '.csv';

    // PASO 5: CREAR Y RETORNAR RESPUESTA DE DESCARGA STREAMING
    // ========================================================
    
    // response()->streamDownload() crea una descarga que se genera en tiempo real
    // Esto significa que no cargamos todo en memoria, sino que enviamos datos al navegador
    // mientras los vamos generando, lo cual es mucho más eficiente
    return response()->streamDownload(function() use ($search, $host, $startDate, $endDate, $sort, $direction, $allFields) {
      
      // PASO 6: ABRIR HANDLE PARA ESCRIBIR AL OUTPUT DEL NAVEGADOR
      // ==========================================================
      
      // fopen('php://output', 'w') abre un "archivo" especial que escribe directamente
      // al navegador del usuario. Es como si fuera un archivo, pero en realidad
      // los datos van directo al navegador para la descarga
      $handle = fopen('php://output', 'w');

      // PASO 7: CONSTRUIR ENCABEZADOS DEL CSV
      // =====================================
      
      // Definir las columnas básicas que siempre estarán en el CSV
      $headers = [
        'ID',           // ID único del lead
        'Fingerprint',  // Identificador único del visitante
        'Website',      // Sitio web de origen
        'City',         // Ciudad del lead
        'State',        // Estado/provincia del lead
        'Zip',          // Código postal
        'Created At',   // Fecha de creación
        'Updated At'    // Fecha de última actualización
      ];

      // Agregar campos dinámicos como columnas adicionales
      // Cada field personalizado se convierte en una columna del CSV
      foreach ($allFields as $field) {
        // Usar 'label' si existe, sino usar 'name' como fallback
        // El operador ?: es equivalente a: $field->label ? $field->label : $field->name
        $headers[] = $field->label ?: $field->name;
      }

      // fputcsv() escribe una línea en formato CSV
      // Automáticamente maneja las comas, comillas y caracteres especiales
      fputcsv($handle, $headers);

      // PASO 8: PROCESAR LEADS EN CHUNKS (LOTES) PARA OPTIMIZAR MEMORIA
      // ===============================================================
      
      // Definir tamaño del chunk: procesaremos 1000 leads a la vez
      // Esto evita cargar millones de registros en memoria simultáneamente
      $chunkSize = 1000;

      // Lead::select() inicia una consulta a la tabla leads
      Lead::select('leads.*')  // Seleccionar todas las columnas de la tabla leads
        
        // when() es un helper de Laravel que aplica condiciones solo si el primer parámetro es verdadero
        // Es más limpio que usar múltiples if statements
        
        ->when($search, function($query) use ($search) {
          // Si hay búsqueda, aplicar filtros de texto
          $query->where(function($subQuery) use ($search) {
            $subQuery->where('city', 'ilike', "%{$search}%")
              ->orWhere('state', 'ilike', "%{$search}%")
              ->orWhere('fingerprint', 'ilike', "%{$search}%")
              ->orWhere('website', 'ilike', "%{$search}%");
          });
        })
        
        ->when($host, function($query) use ($host) {
          // Si hay filtro de host, aplicarlo
          $query->where('website', 'ilike', "%{$host}%");
        })
        
        ->when($startDate, function($query) use ($startDate) {
          // Si hay fecha de inicio, filtrar desde esa fecha
          $query->whereDate('leads.created_at', '>=', $startDate);
        })
        
        ->when($endDate, function($query) use ($endDate) {
          // Si hay fecha de fin, filtrar hasta esa fecha
          $query->whereDate('leads.created_at', '<=', $endDate);
        })
        
        // Aplicar ordenamiento
        ->orderBy($sort, $direction)
        
        // chunk() es la magia de Laravel para procesar grandes datasets
        // En lugar de hacer get() y cargar todo, chunk() procesa de a pedazos
        // Ejecuta la función callback para cada grupo de 1000 registros
        ->chunk($chunkSize, function($leads) use ($handle, $allFields) {
          
          // PASO 9: PROCESAR CADA CHUNK DE LEADS
          // ====================================
          
          // pluck('id') extrae solo los IDs de los leads del chunk actual
          // Resultado: [1, 2, 3, 4, ..., 1000] (array de IDs)
          $leadIds = $leads->pluck('id');
          
          // PASO 10: OBTENER VALORES DE CAMPOS PARA ESTE CHUNK
          // ==================================================
          
          // LeadFieldResponse es la tabla pivot que conecta leads con fields
          // whereIn('lead_id', $leadIds) busca solo los registros de los leads actuales
          $fieldValues = LeadFieldResponse::whereIn('lead_id', $leadIds)
            ->get()                    // Ejecutar consulta
            ->groupBy('lead_id');      // Agrupar por lead_id para fácil acceso

          // PASO 11: PROCESAR CADA LEAD DEL CHUNK ACTUAL
          // ============================================
          
          foreach ($leads as $lead) {
            
            // PASO 12: CONSTRUIR FILA CON DATOS BÁSICOS DEL LEAD
            // ==================================================
            
            $row = [
              $lead->id,
              $lead->fingerprint,
              $lead->website,
              $lead->city,
              $lead->state,
              $lead->zip,
              // format() convierte la fecha a string legible
              $lead->created_at->format('Y-m-d H:i:s'),
              $lead->updated_at->format('Y-m-d H:i:s')
            ];

            // PASO 13: AGREGAR VALORES DE CAMPOS DINÁMICOS
            // ============================================
            
            // Obtener los field values para este lead específico
            // get($lead->id, collect()) busca el grupo de este lead, o devuelve colección vacía
            $leadFieldValues = $fieldValues->get($lead->id, collect());
            
            // keyBy('field_id') reorganiza la colección usando field_id como clave
            // Esto permite acceso rápido: $map[field_id] en lugar de buscar en loop
            $leadFieldsMap = $leadFieldValues->keyBy('field_id');

            // Para cada campo que debe aparecer en el CSV
            foreach ($allFields as $field) {
              // Buscar el valor de este campo para este lead
              // get($field->id)?->value busca el registro y obtiene su valor
              // ?? '' significa "si no existe, usar string vacío"
              $fieldValue = $leadFieldsMap->get($field->id)?->value ?? '';
              
              // Agregar el valor a la fila
              $row[] = $fieldValue;
            }

            // PASO 14: ESCRIBIR FILA AL CSV
            // =============================
            
            // fputcsv() escribe la fila completa al archivo CSV
            // Maneja automáticamente el escape de caracteres especiales
            fputcsv($handle, $row);
          }

          // PASO 15: LIBERAR MEMORIA DESPUÉS DE CADA CHUNK
          // ==============================================
          
          // unset() libera las variables de la memoria
          // Esto es importante para evitar acumulación de memoria entre chunks
          unset($leads, $leadIds, $fieldValues);
          
          // gc_collect_cycles() fuerza al garbage collector de PHP a limpiar memoria
          // Esto asegura que la memoria se libere inmediatamente
          gc_collect_cycles();
        });

      // PASO 16: CERRAR EL HANDLE DEL ARCHIVO
      // =====================================
      
      // fclose() cierra el handle y finaliza la escritura del archivo
      fclose($handle);
      
    }, $filename, [
      // PASO 17: CONFIGURAR HEADERS HTTP PARA LA DESCARGA
      // =================================================
      
      // Content-Type le dice al navegador qué tipo de archivo es
      'Content-Type' => 'text/csv',
      
      // Content-Disposition le dice al navegador que debe descargar el archivo
      // 'attachment' fuerza la descarga en lugar de mostrar en el navegador
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
  }

  /**
   * Show the form for creating a new resource.
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   */
  public function store(Request $request)
  {
    //
  }

  /**
   * Display the specified resource.
   */
  public function show(Lead $lead)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   */
  public function edit(Lead $lead)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   */
  public function update(Request $request, Lead $lead)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   */
  public function destroy(Lead $lead)
  {
    //
  }
}
