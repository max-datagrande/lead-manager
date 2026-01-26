# Guía Completa: Tablas Server-Side con React

## 🎯 ¿Qué es esto?

Un sistema reutilizable para crear tablas con paginación, filtros y ordenamiento que funcionan con **server-side rendering**. Es decir, los datos se cargan desde el servidor página por página, no todos de golpe.

## 📁 Archivos del Sistema

```
📦 hooks/
  └── use-server-table.tsx          # Hook principal (obligatorio)
📦 components/data-table/
  └── server-table.tsx              # Componente UI (obligatorio)
📦 utils/
  └── create-server-table-provider.tsx  # Helper para providers (opcional)
```

## 🔧 Método 1: Implementación Directa (RECOMENDADO)

**Perfecto para cuando quieres algo simple y rápido.**

### Paso 1: Prepara tu página

```tsx
// resources/js/pages/usuarios/index.tsx
import { useServerTable } from '@/hooks/use-server-table';
import { ServerTable } from '@/components/data-table/server-table';
import { usuarioColumns } from './columns'; // tus columnas

export default function UsuariosIndex({ rows, meta, state, data }) {
  // ✅ Hook mágico que maneja TODO
  const table = useServerTable({
    routeName: 'usuarios.index',  // Nombre de tu ruta Inertia
    initialState: state,          // Datos iniciales del servidor
    defaultPageSize: 10           // Opcional
  });

  return (
    <>
      <h1>Lista de Usuarios</h1>
      
      {/* ✅ Tabla completa con un solo componente */}
      <ServerTable
        data={rows.data}
        columns={usuarioColumns}
        meta={meta}
        {...table} // 🎯 Aquí pasas TODO el poder del hook
        toolbarConfig={{
          searchPlaceholder: 'Buscar usuarios...',
          filters: [
            {
              columnId: 'rol',
              title: 'Rol',
              options: data.roles // viene del backend
            }
          ]
        }}
      />
    </>
  );
}
```

### Paso 2: ¿Qué hace cada cosa?

| Prop | Para qué sirve |
|------|----------------|
| `data` | Los datos de la página actual |
| `columns` | Definición de columnas (ver ejemplo abajo) |
| `meta` | Información de paginación (total de páginas) |
| `table` | **TODO** el estado y funciones del hook |
| `toolbarConfig` | Configuración del buscador y filtros |

### Paso 3: Crea tus columnas

```tsx
// resources/js/pages/usuarios/columns.tsx
export const usuarioColumns = [
  {
    accessorKey: 'nombre',      // Nombre del campo en tu BD
    header: 'Nombre Completo', // Título que aparece en la tabla
  },
  {
    accessorKey: 'email',
    header: 'Correo',
  },
  {
    accessorKey: 'rol',
    header: 'Rol',
  },
  {
    accessorKey: 'fecha_registro',
    header: 'Fecha de Registro',
  }
];
```

## 🏗️ Método 2: Con Provider Personalizado

**Útil cuando tienes muchas tablas y quieres consistencia.**

### Paso 1: Crea tu provider automáticamente

```tsx
// resources/js/context/tabla-providers.tsx
import { createServerTableProvider } from '@/utils/create-server-table-provider';

// ¡Una línea y listo!
export const { Provider: UsuariosProvider, useTable: useUsuariosTable } = 
  createServerTableProvider('usuarios.index');
```

### Paso 2: Usa en tu página

```tsx
// resources/js/pages/usuarios/index.tsx
import { UsuariosProvider, useUsuariosTable } from '@/context/tabla-providers';
import { ServerTable } from '@/components/data-table/server-table';

export default function UsuariosIndex({ rows, meta, state, data }) {
  return (
    <UsuariosProvider initialState={state}>
      <Contenido rows={rows} meta={meta} data={data} />
    </UsuariosProvider>
  );
}

function Contenido({ rows, meta, data }) {
  const table = useUsuariosTable(); // 🎯 Mismo poder, diferente nombre
  
  return (
    <ServerTable
      data={rows.data}
      columns={usuarioColumns}
      meta={meta}
      {...table}
      toolbarConfig={{
        searchPlaceholder: 'Buscar...',
        filters: [/* tus filtros */]
      }}
    />
  );
}
```

## 🎛️ Configuración del Backend (Laravel)

### Controlador ejemplo:

```php
// app/Http/Controllers/UsuarioController.php
public function index(Request $request)
{
    // Recibir parámetros del hook
    $page = $request->input('page', 1);
    $perPage = $request->input('per_page', 10);
    $search = $request->input('search');
    $sort = $request->input('sort');
    $filters = json_decode($request->input('filters', '[]'), true);

    // Query con filtros
    $query = Usuario::query();
    
    if ($search) {
        $query->where('nombre', 'like', "%{$search}%");
    }
    
    if ($sort) {
        $query->orderBy($sort['id'], $sort['desc'] ? 'desc' : 'asc');
    }

    // Paginar
    $usuarios = $query->paginate($perPage, ['*'], 'page', $page);

    return Inertia::render('usuarios/index', [
        'rows' => $usuarios,
        'meta' => [
            'last_page' => $usuarios->lastPage(),
        ],
        'state' => [
            'page' => $page,
            'per_page' => $perPage,
            'search' => $search,
            'sort' => $sort,
            'filters' => $filters,
        ],
        'data' => [
            'roles' => Rol::pluck('nombre') // Para los filtros
        ]
    ]);
}
```

## 🎯 Qué obtienes automáticamente

✅ **Paginación** que funciona perfecto  
✅ **Búsqueda global** (el buscador de arriba)  
✅ **Filtros por columna** (selects en cada columna)  
✅ **Ordenamiento** (click en headers)  
✅ **Loading states** (spinner mientras carga)  
✅ **Reset automático** a página 1 cuando filtras  

## 🔧 Personalización

### ¿Quieres más opciones en el hook?

```tsx
const table = useServerTable({
  routeName: 'usuarios.index',
  initialState: state,
  defaultPageSize: 25,        // Más filas por página
});
```

### ¿Quieres ocultar el toolbar?

```tsx
<ServerTable
  data={rows.data}
  columns={columns}
  meta={meta}
  {...table}
  toolbarConfig={undefined} // Sin toolbar
/>
```

### ¿Quieres filtros personalizados?

```tsx
toolbarConfig={{
  searchPlaceholder: 'Busca por nombre o email...',
  filterByColumn: 'created_at', // Columna para rango de fechas
  filters: [
    {
      columnId: 'estado',
      title: 'Estado',
      options: ['activo', 'inactivo', 'pendiente']
    },
    {
      columnId: 'pais',
      title: 'País',
      options: data.paises
    }
  ],
  dateRange: { 
    column: 'created_at', 
    label: 'Fecha de creación' 
  }
}}
```

## 🚨 Errores Comunes

### "useTable debe usarse dentro de Provider"
→ Estás usando un hook de provider sin el Provider. Usa el **Método 1** directo.

### "Cannot read property of undefined"
→ Asegúrate de pasar `initialState` al hook o provider.

### La tabla no actualiza datos
→ Verifica que tu backend esté recibiendo los parámetros correctos.

## 📊 Flujo de Datos

```
Usuario hace clic → Hook detecta cambio → Petición al backend → 
Backend filtra/pagina → Devuelve datos → Tabla se actualiza
```

## 🎉 Listo!
