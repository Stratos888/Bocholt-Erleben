import { configureManageRenderer, renderManage } from './manage-render.js';
import { configureManageActions, openContentDetails, openContentEditor } from './manage-actions.js';
configureManageRenderer({ openDetails: openContentDetails, openEditor: openContentEditor });
configureManageActions({ renderManage });
export { renderManage };
