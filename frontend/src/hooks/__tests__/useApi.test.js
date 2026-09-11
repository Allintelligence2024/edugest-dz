import { renderHook, act, waitFor } from '@testing-library/react';
import { useApi } from '@hooks/useApi';
import api from '@api/axiosInstance';

vi.mock('@api/axiosInstance', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns data after successful fetch', async () => {
    const mockData = { data: { id: 1, nom: 'Test' }, meta: { total: 1 } };
    api.get.mockResolvedValue(mockData);

    const { result } = renderHook(() => useApi('/test'));

    await act(async () => {
      await result.current.fetch();
    });

    expect(result.current.data).toEqual({ id: 1, nom: 'Test' });
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('handles error state', async () => {
    const mockError = { error: { message: 'Not found' } };
    api.get.mockRejectedValue(mockError);

    const { result } = renderHook(() => useApi('/test', { showError: false }));

    await act(async () => {
      await result.current.fetch();
    });

    expect(result.current.data).toBeNull();
    expect(result.current.error).toEqual(mockError);
    expect(result.current.isLoading).toBe(false);
  });

  it('handles loading state', async () => {
    const mockData = { data: { id: 1 } };
    api.get.mockResolvedValue(mockData);

    const { result } = renderHook(() => useApi('/test'));

    let promise;
    act(() => {
      promise = result.current.fetch();
    });

    expect(result.current.isLoading).toBe(true);

    await act(async () => {
      await promise;
    });

    expect(result.current.isLoading).toBe(false);
  });

  it('performs create mutation via POST', async () => {
    const mockResponse = { data: { id: 2 }, message: 'Created' };
    api.post.mockResolvedValue(mockResponse);

    const { result } = renderHook(() => useApi('/test'));

    let res;
    await act(async () => {
      res = await result.current.create({ nom: 'New' });
    });

    expect(api.post).toHaveBeenCalledWith('/test', { nom: 'New' });
    expect(res).toEqual(mockResponse);
  });

  it('performs update mutation via PUT', async () => {
    api.put.mockResolvedValue({ data: { id: 1 } });

    const { result } = renderHook(() => useApi('/test'));

    await act(async () => {
      await result.current.update(1, { nom: 'Updated' });
    });

    expect(api.put).toHaveBeenCalledWith('/test/1', { nom: 'Updated' });
  });

  it('performs remove mutation via DELETE', async () => {
    api.delete.mockResolvedValue({});

    const { result } = renderHook(() => useApi('/test'));

    await act(async () => {
      await result.current.remove(1);
    });

    expect(api.delete).toHaveBeenCalledWith('/test/1', null);
  });
});
