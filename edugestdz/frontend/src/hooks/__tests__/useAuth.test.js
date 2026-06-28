import { renderHook } from '@testing-library/react';
import { useAuth } from '@hooks/useAuth';

describe('useAuth', () => {
  it('throws error if used outside AuthProvider', () => {
    const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      renderHook(() => useAuth());
    }).toThrow('useAuth must be used within <AuthProvider>');

    consoleSpy.mockRestore();
  });
});
