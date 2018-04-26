import './types/drupal';

import { SelectorDialog } from "./core";

Drupal.behaviors.nodeSearch = {
    attach: (context: Element, settings: any) => {
        for (let widget of <HTMLInputElement[]><any>context.querySelectorAll(`[data-selector="true"]`)) {
            console.log("gotcha");
            SelectorDialog(widget);
        }
    }
};
