import { configureReviewRenderer, renderReview } from './review-render.js';
import { configureReviewActions, handleReviewAction } from './review-actions.js';
configureReviewRenderer({ handleAction: handleReviewAction });
export function configureReview(callbacks = {}) { configureReviewActions(callbacks); }
export { renderReview };
