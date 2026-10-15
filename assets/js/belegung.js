// Belegungskalender (belegung.php)
//
// Hinweis "waagerecht scrollen" nur zeigen, wenn das Raster wirklich
// breiter als sein Bereich ist (bei schmalen Fenstern). Die Datei wird am
// Ende der Seite eingebunden, die Elemente existieren also schon.
(function () {
  var bereich = document.getElementById('slotScroll');
  var hinweis = document.getElementById('scrollHinweis');
  if (!bereich || !hinweis) {
    return;
  }

  function pruefen() {
    hinweis.hidden = bereich.scrollWidth <= bereich.clientWidth + 1;
  }

  window.addEventListener('resize', pruefen);
  pruefen();
})();
