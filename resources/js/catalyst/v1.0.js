(function() {
  const currentScript = document.currentScript;
  if (!currentScript) {
    console.error("SDK v1.0: No se pudo determinar el script actual.");
    return;
  }

  const scriptUrl = new URL(currentScript.src);
  const landingId = scriptUrl.searchParams.get('landing_id');

  if (!landingId) {
    console.error("SDK v1.0: El parámetro 'landing_id' es requerido.");
    return;
  }

  console.log(`SDK v1.0 inicializado para Landing ID: ${landingId}`);
  
  // Aquí irá la lógica específica de la v1.0
})();
