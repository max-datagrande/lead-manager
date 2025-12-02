# Catalyst SDK - Documentación de Integración

Esta documentación detalla cómo integrar y utilizar el SDK de Catalyst en tus Landing Pages. El SDK gestiona automáticamente la identificación de visitantes y proporciona una interfaz basada en eventos para el registro y actualización de leads.

## 1. Instalación

Inserta el siguiente snippet en el `<head>` de tu HTML.

```html
<script src="https://tu-dominio.com/catalyst/engine.js?version=1.0"></script>
```

> **Nota:** El parámetro `landing_id` en la URL es opcional. La configuración del SDK se carga automáticamente desde el servidor.

### Opción B: Carga Manual (Avanzado)
Si prefieres tener control total y evitar el loader de Laravel, puedes usar este snippet. Este método carga directamente la versión compilada del SDK.

> **Importante:** Al usar este método, debes configurar la URL de la API manualmente en el objeto `config`.

```html
<script>
(function(w,d,s,u,c){
  w.Catalyst=w.Catalyst||{_q:[],config:c};
  ['on','dispatch','registerLead','updateLead'].forEach(function(m){
    w.Catalyst[m]=function(){w.Catalyst._q.push([m].concat([].slice.call(arguments)))};
  });
  var j=d.createElement(s),f=d.getElementsByTagName(s)[0];
  j.async=1;j.type='module';j.src=u;f.parentNode.insertBefore(j,f);
})(window,document,'script','https://tu-dominio.com/catalyst/v1.0.js',{
  api_url:'https://api.tu-dominio.com',debug:true
});
</script>
```

### Resumen de Diferencias

| Característica | Opción A (Loader) | Opción B (Manual) |
| :--- | :--- | :--- |
| **Script** | `/engine.js` | `/v1.0.js` (Directo) |
| **Configuración** | Automática (Inyectada por servidor) | Manual (En el HTML) |
| **Uso Ideal** | Landings estándar, Laravel Blade | SPAs, WordPress, Estáticos |

---

## 2. Ciclo de Vida y Eventos

El SDK funciona con una arquitectura orientada a eventos (Pub/Sub). No llamas a métodos síncronos; en su lugar, escuchas eventos o disparas acciones.

### Evento: `ready`
Se dispara una única vez cuando el SDK ha cargado Y la sesión del visitante está confirmada (ya sea recuperada de caché local o registrada en la API).

**Siempre** debes envolver tu lógica dentro de este listener.

```javascript
Catalyst.on('ready', function(eventData) {
    console.log('SDK Listo. Datos del visitante:', eventData.visitor);
    // Aquí ya es seguro intentar registrar leads
});
```

### Evento: `lead:status`
Es el evento unificado para saber el resultado de tus operaciones (registro o actualización).

Estructura del evento:
```typescript
{
    type: 'register' | 'update', // Qué operación se intentó
    success: boolean,            // Si funcionó o no
    data?: object,               // Datos devueltos por la API (si success: true)
    error?: string               // Mensaje de error (si success: false)
}
```

---

## 3. Acciones Disponibles (Modo Eventos)

Para interactuar con el SDK, utilizas `Catalyst.dispatch(actionName, payload)`.

### A. Registrar un Lead (`lead:register`)
Envía los datos del formulario para convertirlos en un Lead asociado al visitante actual.

```javascript
Catalyst.dispatch('lead:register', {
    name: 'Juan Pérez',
    email: 'juan@example.com',
    phone: '+52 555 555 5555',
    custom_field: 'valor'
});
```

### B. Actualizar un Lead (`lead:update`)
Añade o modifica información de un lead ya registrado.
> **Importante:** Esta acción fallará si el visitante no ha sido registrado previamente como lead.

```javascript
Catalyst.dispatch('lead:update', {
    company: 'Empresa S.A.',
    role: 'Gerente'
});
```

---

## 4. Uso Avanzado: Async/Await

Si prefieres un control de flujo más estricto o moderno, puedes invocar directamente los métodos del SDK en lugar de usar `dispatch`. Estos métodos devuelven una `Promise`.

> **Nota:** Para usar esto, debes asegurarte primero de que el SDK esté cargado (usando el evento `ready` inicial).

### Registrar Lead (Async)

```javascript
Catalyst.on('ready', async () => {
    try {
        // Esperar la respuesta directamente
        await Catalyst.registerLead({
            email: 'test@example.com',
            name: 'Test User'
        });
        console.log('Lead registrado con éxito (Async)');
        
        // Ahora es seguro actualizar
        await Catalyst.updateLead({ role: 'Admin' });
        console.log('Lead actualizado con éxito (Async)');
        
    } catch (error) {
        console.error('Hubo un error en el flujo async:', error);
    }
});
```

Este enfoque es ideal si necesitas realizar validaciones complejas o cadenas de acciones dependientes sin anidar múltiples listeners de eventos.

---

## 5. Preguntas Frecuentes (FAQ)

### ¿Por qué mis cambios en el Lead no se guardan si llamo a `update` inmediatamente?
El `lead:update` requiere que el visitante ya tenga un "Lead ID" asociado en el backend. Si disparas `lead:register` y `lead:update` simultáneamente (o muy rápido), el update podría llegar antes de que el registro termine.
**Solución:** Usa el enfoque de eventos esperando `lead:status` (type: 'register', success: true) O usa `await Catalyst.registerLead(...)` antes de actualizar.

### ¿Cada recarga de página cuenta como una nueva visita?
**No.** El SDK implementa un sistema de "Throttle" de 15 minutos usando `localStorage` y Cookies.
- Si un usuario recarga la página dentro de los 15 minutos, el SDK recupera la sesión localmente (sin llamar a la API).
- El evento `ready` se dispara igual de rápido, pero con datos cacheados.

### ¿Qué pasa si el API falla al iniciar (Error 500)?
El SDK capturará el error internamente y lo mostrará en la consola si `debug: true` está activo. El evento `ready` **NO** se disparará, impidiendo que intentes registrar leads sin una sesión válida.

### ¿Dónde veo los logs de depuración?
Asegúrate de pasar `{ debug: true }` en el segundo argumento de la configuración inicial del loader. Esto imprimirá en la consola del navegador cada evento disparado y cada respuesta de la API.
