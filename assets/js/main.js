// Gemeinsames JavaScript aller Seiten (eingebunden von templates/footer.php).
//
// Die Handler fuer Formulare haengen am document statt an jedem einzelnen
// Feld ("Event-Delegation"). So reicht ein Attribut im HTML, und es spielt
// keine Rolle, an welcher Stelle der Seite dieses Skript steht.

// Filterfelder mit data-auto-submit schicken ihr Formular bei jeder
// Aenderung sofort ab (Auswahllisten, Radio-Buttons, Datum in der Sidebar).
document.addEventListener('change', function (ereignis) {
  var feld = ereignis.target;
  if (feld.hasAttribute('data-auto-submit') && feld.form) {
    feld.form.submit();
  }
});

// Formulare mit data-frage="..." fragen vor dem Absenden nach
// (z. B. Benutzer sperren). Bei "Abbrechen" wird nichts gesendet.
document.addEventListener('submit', function (ereignis) {
  var frage = ereignis.target.getAttribute('data-frage');
  if (frage && !window.confirm(frage)) {
    ereignis.preventDefault();
  }
});

// Welle beim Scrollen sanft nach oben schieben und langsam ausblenden
// (Parallax: bewegt sich langsamer als der eigentliche Scroll, dadurch
// wirkt das Verschwinden hinter der Kopfzeile weich statt abrupt).
(function () {
  var wave = document.getElementById('waveBanner');
  var fadeDistance = 130;   // ab wie viel Scroll-px die Welle komplett weg ist
  var parallaxFactor = 0.4; // < 1 => Welle bewegt sich langsamer als der Scroll
  var ticking = false;

  // Nur Seiten mit Kopfzeile haben das Banner (nicht Login, Formularseiten).
  if (!wave) {
    return;
  }

  function updateWave() {
    var scrolled = window.pageYOffset || document.documentElement.scrollTop;
    var progress = Math.min(scrolled / fadeDistance, 1);

    wave.style.transform = 'translateY(' + (-scrolled * parallaxFactor) + 'px)';
    wave.style.opacity = String(1 - progress);
    ticking = false;
  }

  window.addEventListener('scroll', function () {
    if (!ticking) {
      window.requestAnimationFrame(updateWave);
      ticking = true;
    }
  }, { passive: true });

  updateWave();
})();
