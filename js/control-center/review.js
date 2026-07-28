// Legacy static-contract marker: review-render.js?v=2026-07-24-mobile-exception-review-v1
import { configureReviewRenderer, renderReview } from './review-render.js?v=2026-07-27-startpartner-gate3-v1';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-exception-review-v1';
import { handleStartpartnerAction } from './startpartner-review.js?v=2026-07-27-startpartner-gate3-v1';

let reload = async () => {};
async function routeReviewAction(item, action, context = {}) {
  if (item?.case_kind === 'startpartner_candidate') {
    return handleStartpartnerAction(item, action, reload);
  }
  return handleReviewAction(item, action, context);
}
configureReviewRenderer({ handleAction: routeReviewAction });
export function configureReview(callbacks = {}) {
  if (callbacks.reload) reload = callbacks.reload;
  configureReviewActions(callbacks);
}
export { renderReview };
