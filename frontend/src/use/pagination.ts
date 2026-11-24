/** Page links stay bounded even for large server-side result sets. */
export function paginationPages(page: number, pages: number): (number | 'ellipsis')[] {
  const current = Math.min(Math.max(1, page), pages);
  const start = Math.max(2, Math.min(current - 1, pages - 4));
  const end = Math.min(pages - 1, Math.max(current + 1, 5));
  const result: (number | 'ellipsis')[] = [1];

  if (pages <= 7) return Array.from({ length: pages }, (_, index) => index + 1);

  if (start > 2) result.push('ellipsis');

  for (let value = start; value <= end; value++) result.push(value);

  if (end < pages - 1) result.push('ellipsis');

  result.push(pages);

  return result;
}
