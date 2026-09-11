export default function Card({ children, className = '', padding = true, hover = false, onClick }) {
  return (
    <div
      onClick={onClick}
      className={[
        'bg-surface border border-border rounded-xl',
        padding && 'p-5',
        hover && 'hover:bg-surface2 cursor-pointer transition-colors',
        className,
      ].filter(Boolean).join(' ')}
    >
      {children}
    </div>
  );
}
