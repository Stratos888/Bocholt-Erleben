import { configureReviewRenderer, renderReview } from './review-render.js?v=2026-07-15-control-center-editorial-v1';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-inbox-writeback-v3';
configureReviewRenderer({ handleAction: handleReviewAction });
export function configureReview(callbacks = {}) { configureReviewActions(callbacks); }
export { renderReview };
