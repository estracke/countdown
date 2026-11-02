// Server-authoritative, monotonic clock shared by all three original UIs.
// Keep UI markup and styles unchanged.
window.BroadcastSync = (() => {
  let anchorServer = 0, anchorMono = 0, revision = -1, latest = null, bestRtt = Infinity;
  function now() { return anchorServer ? anchorServer + performance.now() - anchorMono : Date.now(); }
  async function read() {
    const sent = performance.now();
    const response = await fetch('status.php?t=' + Date.now(), {cache:'no-store'});
    if (!response.ok) throw new Error('State unavailable: ' + response.status);
    const state = await response.json();
    const arrived = performance.now(), rtt = arrived - sent;
    if (typeof state.server_time_ms !== 'number') throw new Error('No server time');
    if (Number(state.revision || 0) < revision) return latest;
    // Timestamp is captured while handling request, approximately near its midpoint.
    // Prefer lowest-RTT samples to reduce jitter and slow/queued network corrections.
    const estimatedServer = state.server_time_ms + rtt / 2;
    if (!anchorServer || rtt <= bestRtt * 1.5 || Number(state.revision||0) > revision) {
      anchorServer = estimatedServer;
      anchorMono = arrived;
      bestRtt = Math.min(bestRtt, rtt);
    }
    revision = Number(state.revision || 0);
    latest = state;
    return state;
  }
  function remaining(state) {
    if (!state || state.mode !== 'running') return 0;
    const target = Number(state.target_epoch_ms);
    return Number.isFinite(target) && target > 0 ? target - now() : 0;
  }
  return {read, now, remaining};
})();
