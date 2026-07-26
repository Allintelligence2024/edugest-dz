module.exports = {
  isDevice: true,
  Brand: 'Test',
  Manufacturer: 'Test',
  ModelName: 'Test',
  ModelId: 'test',
  DeviceYearClass: 2024,
  TotalMemory: 8000000000,
  PlatformApiLevel: 34,
  osName: 'android',
  osVersion: '14',
  getDeviceTypeAsync: jest.fn().mockResolvedValue('phone'),
  isRootedExperimentalAsync: jest.fn().mockResolvedValue(false),
};
