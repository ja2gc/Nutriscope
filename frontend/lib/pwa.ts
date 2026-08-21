export interface DeviceCapabilities {
  coarsePointer: boolean;
  viewportWidth: number;
  standalone?: boolean;
}

export function isPhoneOrTablet({ coarsePointer, viewportWidth, standalone = false }: DeviceCapabilities): boolean {
  return standalone || coarsePointer || viewportWidth <= 1024;
}
