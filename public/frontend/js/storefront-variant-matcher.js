/* Pure SKU choice helpers: a change always resolves to a REAL configured SKU. */
(function (root, factory) {
  'use strict';
  const api = factory();
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  if (root) root.SfaVariantMatcher = api;
})(typeof window !== 'undefined' ? window : null, function () {
  'use strict';
  const same = (left, right) => String(left ?? '') === String(right ?? '');
  const purchasable = (row) => Number(row?.stock) > 0 && Number(row?.price) > 0;
  const matches = (row, optionId, valueId) => same(row?.selected?.[optionId], valueId);

  // Respect a prior SKU after validation redirects / a product?variant= link.
  function initial(rows, requestedId) {
    if (!Array.isArray(rows) || rows.length === 0) return null;
    return rows.find((row) => same(row.id, requestedId) && requestedId != null && requestedId !== '')
      || rows.find(purchasable) || rows[0];
  }

  // Keep the exact combination (even if out of stock), otherwise honor the
  // newly clicked choice and preserve as many previous choices as possible.
  // Only a server-provided real SKU is ever returned. No client-side pricing.
  function forChange(rows, optionIds, previous, changedOptionId, newValueId) {
    if (!Array.isArray(rows)) return null;
    const candidates = rows.filter((row) => matches(row, changedOptionId, newValueId));
    if (!candidates.length) return null;
    const exact = candidates.find((row) => optionIds.every((id) =>
      same(row.selected?.[id], id === changedOptionId ? newValueId : previous?.[id])
    ));
    if (exact) return exact;
    return candidates.map((row, index) => ({
      row, index,
      score: optionIds.reduce((count, id) => count +
        (id !== changedOptionId && previous?.[id] != null && matches(row, id, previous[id]) ? 1 : 0), 0),
    })).sort((a, b) =>
      Number(purchasable(b.row)) - Number(purchasable(a.row)) ||
      b.score - a.score || a.index - b.index
    )[0].row;
  }

  // Leatherket-style independent choices: NEVER silently change another chosen
  // dimension. A complete, real SKU must exist for this exact selection.
  function forChangeExact(rows, optionIds, previous, changedOptionId, newValueId) {
    if (!Array.isArray(rows)) return null;
    return rows.find((row) => optionIds.every((id) =>
      same(row.selected?.[id], id === changedOptionId ? newValueId : previous?.[id])
    )) || null;
  }

  function selectionOf(row, optionIds) {
    return Object.fromEntries(optionIds.map((id) => [id, row?.selected?.[id] ?? null]));
  }

  return { initial, forChange, forChangeExact, selectionOf, purchasable, matches };
});
