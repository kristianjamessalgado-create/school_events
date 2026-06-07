<?php
/** @var bool $geo_required */
/** @var bool $focus_confirm_mobile */
$geo_required = !empty($geo_required);
$focus_confirm_mobile = !empty($focus_confirm_mobile);
?>
<script>
(function() {
  var form = document.getElementById('checkinForm');
  if (!form) return;
  var fLat = document.getElementById('geo_lat');
  var fLng = document.getElementById('geo_lng');
  var fAcc = document.getElementById('geo_accuracy');
  var fTs = document.getElementById('geo_ts');
  var fHash = document.getElementById('device_hash');
  var confirmBtn = document.getElementById('confirmBtn');
  var geoRequired = <?= json_encode($geo_required) ?>;
  var focusConfirmMobile = <?= json_encode($focus_confirm_mobile) ?>;

  function setCanConfirm(canConfirm) {
    if (!confirmBtn) return;
    confirmBtn.disabled = !canConfirm;
  }

  async function buildDeviceHash() {
    var fpRaw = [
      navigator.userAgent || '',
      navigator.platform || '',
      navigator.language || '',
      (screen && screen.width ? screen.width : 0) + 'x' + (screen && screen.height ? screen.height : 0),
      Intl.DateTimeFormat().resolvedOptions().timeZone || '',
      String(navigator.hardwareConcurrency || 0),
      String(navigator.maxTouchPoints || 0)
    ].join('|');
    try {
      var enc = new TextEncoder();
      var data = enc.encode(fpRaw);
      var hashBuf = await crypto.subtle.digest('SHA-256', data);
      var hashArr = Array.from(new Uint8Array(hashBuf));
      return hashArr.map(function(b) { return b.toString(16).padStart(2, '0'); }).join('');
    } catch (e) {
      return btoa(unescape(encodeURIComponent(fpRaw))).slice(0, 96);
    }
  }

  function requestLocation() {
    if (!geoRequired) {
      setCanConfirm(!!(fHash && fHash.value));
      return;
    }
    if (!navigator.geolocation) {
      setCanConfirm(false);
      return;
    }
    setCanConfirm(false);
    navigator.geolocation.getCurrentPosition(function(pos) {
      var c = pos.coords || {};
      if (fLat) fLat.value = String(c.latitude || '');
      if (fLng) fLng.value = String(c.longitude || '');
      if (fAcc) fAcc.value = String(c.accuracy || '');
      if (fTs) fTs.value = String(Date.now());
      setCanConfirm(!!(fHash && fHash.value));
    }, function() {
      setCanConfirm(false);
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  }

  buildDeviceHash().then(function(h) {
    if (fHash) fHash.value = h || '';
    requestLocation();
  });

  if (focusConfirmMobile && confirmBtn && window.matchMedia && window.matchMedia('(max-width: 768px)').matches) {
    setTimeout(function() {
      try { confirmBtn.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
    }, 200);
  }

  form.addEventListener('submit', function(e) {
    if (!fHash || !fHash.value) {
      e.preventDefault();
      return;
    }
    if (geoRequired && (!fLat || !fLat.value || !fLng || !fLng.value)) {
      e.preventDefault();
      requestLocation();
    }
  });
})();
</script>
