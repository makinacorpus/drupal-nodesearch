
import { renderDialog } from "./dialog";

export interface Search {
    page?: number;
    limit?: number;
    search?: string;
    sort_field?: string;
    sort_order?: string;
}

export interface ResultItem {
    readonly id: string
    readonly title: string
    readonly status: number;
    readonly created: string;
    readonly updated: string
    readonly type: string;
    readonly human_type: string;
    readonly output: string
}

export interface Result extends Search {
    total: number;
    result: ResultItem[];
}

function encodeComponent(name: string, value: any): string {
    return `${encodeURIComponent(name)}=${encodeURIComponent(value.toString())}`;
}

export function doSearch(search: Search): Promise<Result> {
    return new Promise<Result>((resolve: (result: Result) => void, reject: (err: any) => void) => {

        const parameters: string[] = [];
        if (search.page) {
            parameters.push(encodeComponent('page', search.page));
        }
        if (search.limit) {
            parameters.push(encodeComponent('limit', search.limit));
        }
        if (search.search) {
            parameters.push(encodeComponent('search', search.search));
        }
        if (search.sort_field) {
            parameters.push(encodeComponent('sort_field', search.sort_field));
        }
        if (search.sort_order) {
            parameters.push(encodeComponent('sort_order', search.sort_order));
        }

        const req = new XMLHttpRequest();
        req.open('GET', '/ajax/node/search?' + parameters.join('&'));
        req.setRequestHeader("Accept", "application/json" );
        req.addEventListener("load", () => {
            if (req.status !== 200) {
                reject(`${req.status}: ${req.statusText}: ${req.responseText}`);
            } else {
                try {
                    const result = <Result>JSON.parse(req.responseText);
                    // Populate mandatory parameters to ensure that the caller
                    // will always have something to NOT crash upon.
                    if (!result.result) {
                        result.result = [];
                    }
                    if (!result.total) {
                        result.total = 0;
                    }
                    resolve(result);
                } catch (error) {
                    reject(`${req.status}: ${req.statusText}: cannot parse JSON: ${error}`);
                }
            }
        });
        req.addEventListener("error", () => {
            reject(`${req.status}: ${req.statusText}: ${req.responseText}`);
        });
        req.send();
    });
}

export function SelectorDialog(target: HTMLInputElement, title?: string): void {

    // Prepare arguments from data attributes
    let types: string[] | null = null;
    if (target.hasAttribute("data-type")) {
        types = (<string>target.getAttribute("data-type")).split(",").map(value => value.trim());
    }
    if (!title) {
        title = target.getAttribute("data-title") || "";
    }

    target.addEventListener("click", (event) => {
        event.stopPropagation();
        event.preventDefault();

        const dialogElement = target.ownerDocument.createElement('div');
        target.ownerDocument.body.appendChild(dialogElement);

        renderDialog({
            title: title || "Select content",
            // types: types,
            doRefresh: (search: Search) => {
                return doSearch(search);
            },
            doUpdate: (value?: string) : void => {
                target.value = value || "";
            },
            doClose: () => {
                if (dialogElement.parentNode) {
                    dialogElement.parentNode.removeChild(dialogElement);
                }
            },
        }, dialogElement);
    });
}
