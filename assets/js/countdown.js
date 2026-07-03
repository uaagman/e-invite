(function () {
  const el = document.getElementById('countdown');
  if (!el) return;

  const target = new Date(el.dataset.target).getTime();

  function pad(n) { return String(n).padStart(2, '0'); }

  function tick() {
    const now  = Date.now();
    const diff = Math.max(0, target - now);
    const days    = Math.floor(diff / 86400000);
    const hours   = Math.floor((diff % 86400000) / 3600000);
    const minutes = Math.floor((diff % 3600000)  / 60000);
    const seconds = Math.floor((diff % 60000)     / 1000);

    document.getElementById('cd-days').textContent    = days;
    document.getElementById('cd-hours').textContent   = pad(hours);
    document.getElementById('cd-minutes').textContent = pad(minutes);
    document.getElementById('cd-seconds').textContent = pad(seconds);
  }

  tick();
  setInterval(tick, 1000);
}());
