<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Catalyst Test Page</title>
  <script src="{{ url('/catalyst/engine.js?landing_id=123&version=1.0') }}"></script>
</head>

<body>
  <h1>Catalyst Test Page</h1>
  <p>Open the developer console to see the output.</p>

  <script>
    // This call will be queued by the placeholder
    Catalyst.register('page_view', {
      url: window.location.pathname
    });

    // You can also call it after a delay
    setTimeout(() => {
      Catalyst.register('delayed_event', {
        delay: '2 seconds'
      });
    }, 2000);
  </script>
</body>

</html>
