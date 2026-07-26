// Legacy static-contract marker: review-render.js?v=2026-07-24-mobile-exception-review-v1
import { configureReviewRenderer, renderReview as renderReviewBase } from './review-render.js?v=2026-07-25-startpartner-gate2-v1';
import { configureReviewActions, handleReviewAction } from './review-actions.js?v=2026-07-16-exception-review-v1';
import { handleStartpartnerAction } from './startpartner-review.js?v=2026-07-25-startpartner-gate2-v1';

let reload = async () => {};
let resizeBound = false;

async function routeReviewAction(item, action, context = {}) {
  if (item?.case_kind === 'startpartner_candidate') {
    return handleStartpartnerAction(item, action, reload);
  }
  return handleReviewAction(item, action, context);
}

function setStyle(node, property, value) {
  if (node) node.style[property] = value;
}

function applyStartpartnerTabletLayout() {
  const layout = document.querySelector('.cc-work-layout');
  const detail = layout?.querySelector('.cc-work-detail[data-case-kind="startpartner_candidate"]');
  if (!layout || !detail) return;

  const tablet = window.matchMedia('(min-width: 760px) and (max-width: 1023px)').matches;
  const queue = layout.querySelector('.cc-queue');
  const main = layout.querySelector('main');
  const title = detail.querySelector('.cc-work-head h2');
  const constrained = [
    main,
    detail,
    detail.querySelector('.cc-work-head'),
    detail.querySelector('.cc-startpartner-review'),
    detail.querySelector('.cc-startpartner-priority'),
    ...detail.querySelectorAll('.cc-startpartner-panel, .cc-startpartner-grid, .cc-startpartner-facts'),
  ];

  setStyle(layout, 'gridTemplateColumns', tablet ? 'minmax(0, 1fr)' : '');
  setStyle(layout, 'minWidth', tablet ? '0' : '');
  setStyle(queue, 'display', tablet ? 'grid' : '');
  setStyle(queue, 'gridTemplateColumns', tablet ? 'minmax(0, 1fr)' : '');
  setStyle(queue, 'maxHeight', tablet ? 'none' : '');
  setStyle(queue, 'overflow', tablet ? 'visible' : '');
  setStyle(queue, 'width', tablet ? '100%' : '');
  setStyle(queue, 'minWidth', tablet ? '0' : '');

  constrained.forEach(node => {
    setStyle(node, 'minWidth', tablet ? '0' : '');
    setStyle(node, 'maxWidth', tablet ? '100%' : '');
  });
  setStyle(title, 'overflowWrap', tablet ? 'anywhere' : '');
  setStyle(title, 'wordBreak', tablet ? 'break-word' : '');
}

configureReviewRenderer({ handleAction: routeReviewAction });

export function configureReview(callbacks = {}) {
  if (callbacks.reload) reload = callbacks.reload;
  configureReviewActions(callbacks);
  if (!resizeBound) {
    window.addEventListener('resize', applyStartpartnerTabletLayout, { passive: true });
    resizeBound = true;
  }
}

export function renderReview() {
  renderReviewBase();
  applyStartpartnerTabletLayout();
}
