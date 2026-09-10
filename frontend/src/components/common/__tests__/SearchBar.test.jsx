import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import SearchBar from '@components/common/SearchBar';

describe('SearchBar', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders input', () => {
    render(<SearchBar />);
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('shows placeholder', () => {
    render(<SearchBar placeholder="Chercher..." />);
    expect(screen.getByPlaceholderText('Chercher...')).toBeInTheDocument();
  });

  it('calls onSearch after delay when typing', () => {
    const onSearch = vi.fn();
    render(<SearchBar onSearch={onSearch} delay={400} />);

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'test' } });
    vi.advanceTimersByTime(400);

    expect(onSearch).toHaveBeenCalledWith('test');
  });

  it('clears input when clear button is clicked', () => {
    const onSearch = vi.fn();
    render(<SearchBar onSearch={onSearch} delay={0} />);

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'hello' } });
    vi.advanceTimersByTime(0);

    const clearBtn = screen.getByText('✕');
    fireEvent.click(clearBtn);

    expect(onSearch).toHaveBeenCalledWith('');
  });
});
