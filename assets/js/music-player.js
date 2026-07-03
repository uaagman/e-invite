(function () {
  const audio = document.getElementById('bg-music');
  const btn   = document.getElementById('music-btn');
  const label = document.getElementById('music-label');
  let pendingAutoplay = false;

  function setState(playing) {
    btn.textContent = playing ? '⏸' : '♫';
    btn.classList.toggle('pulse', !playing);
    btn.setAttribute('aria-label', playing ? 'Pause music' : 'Play music');
    label.textContent = playing ? 'Pause' : 'Play music';
    sessionStorage.setItem('bgMusic', playing ? '1' : '0');
  }

  function startAudio() {
    audio.play().then(function () { setState(true); }).catch(function () {});
  }

  // Don't autoplay if the user explicitly paused on a previous page
  if (sessionStorage.getItem('bgMusic') !== '0') {
    audio.play().then(function () {
      setState(true);
    }).catch(function () {
      // Browser blocked silent autoplay — fire on first interaction instead
      pendingAutoplay = true;
      ['click', 'touchstart', 'keydown', 'scroll'].forEach(function (evt) {
        document.addEventListener(evt, function onFirst() {
          if (pendingAutoplay) { pendingAutoplay = false; startAudio(); }
          document.removeEventListener(evt, onFirst);
        }, { passive: true });
      });
    });
  }

  btn.addEventListener('click', function () {
    pendingAutoplay = false;
    if (audio.paused) {
      startAudio();
    } else {
      audio.pause();
      setState(false);
    }
  });
}());
