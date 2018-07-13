import './types/drupal';

import { SelectorWidgetInit } from "./components/widget";

Drupal.behaviors.nodeSearch = {
    attach: (context: Element, settings: any) => {
        for (let widget of <HTMLInputElement[]><any>context.querySelectorAll(`[data-nodesearch="true"]`)) {
            SelectorWidgetInit(widget);
            console.log("gotcha");
        }
    }
};
