// Countdown für die Login-Sperre (ausgelagert, damit die CSP keine Inline-Skripte erlauben muss)
(function () {
  const el = document.getElementById('count');
  if (!el) return;
  let remaining = parseInt(el.dataset.remaining, 10) || 0;
  const intv = setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    el.textContent = remaining;
    if (remaining <= 0) {
      clearInterval(intv);
      location.reload();
    }
  }, 1000);
})();
