export const TREASURY = {
  navy: '#102F49',
  navySoft: '#16445D',
  blue: '#2563EB',
  cyan: '#4FC3F7',
  teal: '#087D78',
  green: '#15966F',
  amber: '#EB941F',
  red: '#C84B45',
  bg: 'rgba(247, 245, 238, 0.74)',
  surface: 'rgba(255, 253, 247, 0.92)',
  text: '#10202C',
  muted: '#526B64',
  line: 'rgba(16, 47, 73, 0.24)',
};

export const peso = (value: number, decimals = 2) =>
  `\u20B1${Number(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })}`;

export const formatTreasuryDate = (value?: string) => {
  if (!value) return '—';
  const parsed = new Date(value.replace(' ', 'T'));
  return Number.isNaN(parsed.getTime())
    ? value
    : parsed.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
};
