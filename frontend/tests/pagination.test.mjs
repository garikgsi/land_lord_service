import test from 'node:test';
import assert from 'node:assert/strict';
import { paginationPages } from '../src/use/pagination.ts';

test('pagination shows every page of small lists and a bounded window of large lists', () => {
  assert.deepEqual(
    [
      paginationPages(1, 1),
      paginationPages(3, 5),
      paginationPages(1, 100),
      paginationPages(50, 100),
      paginationPages(100, 100),
    ],
    [
      [1],
      [1, 2, 3, 4, 5],
      [1, 2, 3, 4, 5, 'ellipsis', 100],
      [1, 'ellipsis', 49, 50, 51, 'ellipsis', 100],
      [1, 'ellipsis', 96, 97, 98, 99, 100],
    ],
  );
});
