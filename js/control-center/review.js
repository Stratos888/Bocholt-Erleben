// Legacy static-contract marker: review-render.js?v=2026-07-24-mobile-exception-review-v1
import { configureReviewRenderer, renderReview } from './review-render.js?v=2026-08-21-startpartner-ai-review-v1';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-exception-review-v1';
import { handleStartpartnerAction } from './startpartner-review.js?v=2026-08-21-startpartner-ai-review-v1';
import { handleGate4Action } from './startpartner-gate4.js?v=2026-08-01-startpartner-gate4-audit-v1';

let reload = async () => {};
async function routeReviewAction(item, action, context = {}) {
  if (item?.case_kind === 'startpartner_candidate') {
    if (String(action || '').startsWith('gate4:')) {
      return handleGate4Action(item, action, reload);
    }
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
