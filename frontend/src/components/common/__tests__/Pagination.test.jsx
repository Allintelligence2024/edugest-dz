import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Pagination from '@components/common/Pagination';
import { I18nProvider } from '@context/I18nContext';

const defaultMeta = { current_page: 3, last_page: 10, total: 97, from: 21, to: 30 };

describe('Pagination', () => {
  it('renders page numbers', () => {
    render(
      <I18nProvider>
        <Pagination meta={defaultMeta} onChange={vi.fn()} />
      </I18nProvider>
    );
    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('10')).toBeInTheDocument();
  });

  it('calls onChange when a page button is clicked', async () => {
    const onChange = vi.fn();
    render(
      <I18nProvider>
        <Pagination meta={defaultMeta} onChange={onChange} />
      </I18nProvider>
    );
    await userEvent.click(screen.getByText('5'));
    expect(onChange).toHaveBeenCalledWith(5);
  });

  it('disables prev button on first page', () => {
    const meta = { ...defaultMeta, current_page: 1 };
    render(
      <I18nProvider>
        <Pagination meta={meta} onChange={vi.fn()} />
      </I18nProvider>
    );
    const prevBtn = screen.getByText('◀');
    expect(prevBtn).toBeDisabled();
  });

  it('disables next button on last page', () => {
    const meta = { ...defaultMeta, current_page: 10 };
    render(
      <I18nProvider>
        <Pagination meta={meta} onChange={vi.fn()} />
      </I18nProvider>
    );
    const nextBtn = screen.getByText('▶');
    expect(nextBtn).toBeDisabled();
  });

  it('shows current page as active', () => {
    const meta = { ...defaultMeta, current_page: 4 };
    render(
      <I18nProvider>
        <Pagination meta={meta} onChange={vi.fn()} />
      </I18nProvider>
    );
    const activeBtn = screen.getByText('4');
    expect(activeBtn.className).toContain('bg-primary-600');
  });

  it('returns null when last_page is 1 or less', () => {
    const { container } = render(
      <I18nProvider>
        <Pagination meta={{ ...defaultMeta, last_page: 1 }} onChange={vi.fn()} />
      </I18nProvider>
    );
    expect(container.innerHTML).toBe('');
  });

  it('renders result summary text', () => {
    render(
      <I18nProvider>
        <Pagination meta={defaultMeta} onChange={vi.fn()} />
      </I18nProvider>
    );
    expect(screen.getByText(content => content.includes('21') && content.includes('30'))).toBeInTheDocument();
    // Résumé traduit via t('showing') : une seule phrase, plus de spans séparés.
    expect(screen.getByText(/sur 97/)).toBeInTheDocument();
  });
});
