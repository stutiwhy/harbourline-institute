// Idle-timeout countdown on the Session inspector page
document.querySelectorAll('[data-countdown]').forEach(function (el) {
  var s = parseInt(el.dataset.countdown, 10);
  var t = setInterval(function () {
    s--;
    if (s <= 0) { clearInterval(t); el.textContent = 'expired (your next click signs you out)'; return; }
    el.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
  }, 1000);
});
