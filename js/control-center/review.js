import { configureReviewRenderer, renderReview } from './review-render.js?v=2026-07-24-mobile-exception-review-v1';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-exception-review-v1';
configureReviewRenderer({ handleAction: handleReviewAction });
export function configureReview(callbacks = {}) { configureReviewActions(callbacks); }
export { renderReview };
