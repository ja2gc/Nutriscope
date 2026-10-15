type TabPressEvent = { target?: string; preventDefault: () => void };
type TabNavigation = {
  getState: () => { routes: Array<{ key: string; name: string }> };
  navigate: (name: string) => void;
};
type TabPressGuard = (event: TabPressEvent, navigation: TabNavigation) => void;

let guard: TabPressGuard | null = null;

export function registerVendorReceivingTabGuard(next: TabPressGuard) {
  guard = next;
  return () => {
    if (guard === next) guard = null;
  };
}

export function handleVendorReceivingTabPress(event: TabPressEvent, navigation: TabNavigation) {
  guard?.(event, navigation);
}
