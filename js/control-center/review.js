import { configureReviewRenderer, renderReview } from './review-render.js?v=2026-07-16-e2e-state-v5';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-e2e-state-v5';
configureReviewRenderer({ handleAction: handleReviewAction });
export function configureReview(callbacks = {}) { configureReviewActions(callbacks); }
export { renderReview };
