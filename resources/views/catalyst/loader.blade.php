(function() {
  const script = document.createElement('script');
  script.src = '{{ $finalUrl }}';
  script.async = true;
  document.head.appendChild(script);
})();
