import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Modal from '@components/common/Modal';

describe('Modal', () => {
  it('renders when open', () => {
    render(<Modal isOpen title="Test Modal"><p>Modal content</p></Modal>);
    expect(screen.getByText('Test Modal')).toBeInTheDocument();
    expect(screen.getByText('Modal content')).toBeInTheDocument();
  });

  it('does not render when closed', () => {
    render(<Modal isOpen={false} title="Test Modal"><p>Modal content</p></Modal>);
    expect(screen.queryByText('Test Modal')).not.toBeInTheDocument();
    expect(screen.queryByText('Modal content')).not.toBeInTheDocument();
  });

  it('calls onClose when backdrop is clicked', async () => {
    const onClose = vi.fn();
    render(<Modal isOpen onClose={onClose} title="Test"><p>Content</p></Modal>);
    await userEvent.click(screen.getByTestId('modal-backdrop'));
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('calls onClose when close button is clicked', async () => {
    const onClose = vi.fn();
    render(<Modal isOpen onClose={onClose} title="Test"><p>Content</p></Modal>);
    await userEvent.click(screen.getByTestId('modal-close-btn'));
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('renders title and children', () => {
    render(<Modal isOpen title="My Title"><div data-testid="child">Child element</div></Modal>);
    expect(screen.getByText('My Title')).toBeInTheDocument();
    expect(screen.getByTestId('child')).toHaveTextContent('Child element');
  });
});
