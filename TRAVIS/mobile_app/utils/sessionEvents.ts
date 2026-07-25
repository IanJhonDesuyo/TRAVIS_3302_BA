type SessionExpiredListener = () => void;

let listener: SessionExpiredListener | null = null;

export function registerSessionExpiredListener(next: SessionExpiredListener) {
  listener = next;
  return () => {
    if (listener === next) listener = null;
  };
}

export function notifySessionExpired() {
  listener?.();
}
