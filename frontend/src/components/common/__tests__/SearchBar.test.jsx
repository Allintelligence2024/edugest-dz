import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import SearchBar from '@components/common/SearchBar';
import { I18nProvider } from '@context/I18nContext';

describe('SearchBar', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('renders input', () => {
    render(
      <I18nProvider>
        <SearchBar />
      </I18nProvider>
    );
    expect(screen.getByRole('textbox')).toBeInTheDocument();
  });

  it('shows placeholder', () => {
    render(
      <I18nProvider>
        <SearchBar placeholder="Chercher..." />
      </I18nProvider>
    );
    expect(screen.getByPlaceholderText('Chercher...')).toBeInTheDocument();
  });

  it('calls onSearch after delay when typing', () => {
    const onSearch = vi.fn();
    render(
      <I18nProvider>
        <SearchBar onSearch={onSearch} delay={400} />
      </I18nProvider>
    );

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'test' } });
    vi.advanceTimersByTime(400);

    expect(onSearch).toHaveBeenCalledWith('test');
  });

  it('clears input when clear button is clicked', () => {
    const onSearch = vi.fn();
    render(
      <I18nProvider>
        <SearchBar onSearch={onSearch} delay={0} />
      </I18nProvider>
    );

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'hello' } });
    vi.advanceTimersByTime(0);

    const clearBtn = screen.getByRole('button', { name: 'Effacer la recherche' });
    fireEvent.click(clearBtn);

    expect(onSearch).toHaveBeenCalledWith('');
  });
});
