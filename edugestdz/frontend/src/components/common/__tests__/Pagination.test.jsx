import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Pagination from '@components/common/Pagination';

const defaultMeta = { current_page: 3, last_page: 10, total: 97, from: 21, to: 30 };

describe('Pagination', () => {
  it('renders page numbers', () => {
    render(<Pagination meta={defaultMeta} onChange={vi.fn()} />);
    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('1')).toBeInTheDocument();
    expect(screen.getByText('10')).toBeInTheDocument();
  });

  it('calls onChange when a page button is clicked', async () => {
    const onChange = vi.fn();
    render(<Pagination meta={defaultMeta} onChange={onChange} />);
    await userEvent.click(screen.getByText('5'));
    expect(onChange).toHaveBeenCalledWith(5);
  });

  it('disables prev button on first page', () => {
    const meta = { ...defaultMeta, current_page: 1 };
    render(<Pagination meta={meta} onChange={vi.fn()} />);
    const prevBtn = screen.getByText('◀');
    expect(prevBtn).toBeDisabled();
  });

  it('disables next button on last page', () => {
    const meta = { ...defaultMeta, current_page: 10 };
    render(<Pagination meta={meta} onChange={vi.fn()} />);
    const nextBtn = screen.getByText('▶');
    expect(nextBtn).toBeDisabled();
  });

  it('shows current page as active', () => {
    const meta = { ...defaultMeta, current_page: 4 };
    render(<Pagination meta={meta} onChange={vi.fn()} />);
    const activeBtn = screen.getByText('4');
    expect(activeBtn.className).toContain('bg-primary-600');
  });

  it('returns null when last_page is 1 or less', () => {
    const { container } = render(<Pagination meta={{ ...defaultMeta, last_page: 1 }} onChange={vi.fn()} />);
    expect(container.innerHTML).toBe('');
  });

  it('renders result summary text', () => {
    render(<Pagination meta={defaultMeta} onChange={vi.fn()} />);
    expect(screen.getByText(content => content.includes('21') && content.includes('30'))).toBeInTheDocument();
    expect(screen.getByText('97')).toBeInTheDocument();
  });
});
