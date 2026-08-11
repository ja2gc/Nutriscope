export interface DeviceCapabilities {
  coarsePointer: boolean;
  viewportWidth: number;
}

export function isPhoneOrTablet({ coarsePointer, viewportWidth }: DeviceCapabilities): boolean {
  return coarsePointer || viewportWidth <= 1024;
}
