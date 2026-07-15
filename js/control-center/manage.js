import { configureManageRenderer, renderManage } from './manage-render.js?v=2026-07-15-control-center-editorial-v1';
import { configureManageActions, openContentDetails, openContentEditor } from './manage-actions.js?v=2026-07-15-control-center-editorial-v1';
configureManageRenderer({ openDetails: openContentDetails, openEditor: openContentEditor });
configureManageActions({ renderManage });
export { renderManage };
