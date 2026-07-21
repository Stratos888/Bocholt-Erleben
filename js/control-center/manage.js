import { configureManageRenderer, renderManage } from './manage-render.js?v=2026-07-16-e2e-state-v5';
import { configureManageActions, openContentDetails, openContentEditor } from './manage-actions.js?v=2026-07-16-e2e-state-v5';
configureManageRenderer({ openDetails: openContentDetails, openEditor: openContentEditor });
configureManageActions({ renderManage });
export { renderManage };
