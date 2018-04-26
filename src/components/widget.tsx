
import * as React from "react";
import * as ReactDOM from "react-dom";
import { Dialog } from "./dialog";
import { doSearch, Result, ResultItem, Search } from "../core";
import { Pager } from "./pager";
import * as Dragula from 'react-dragula'

/**
 * Single result preview properties
 */
interface ResultPreviewProps {
    readonly data: ResultItem;
}

/**
 * Single result preview component
 */
class ResultPreview extends React.Component<ResultPreviewProps> {
    render() {
        return (
            <div className="node-selector-item">
                <span className="title">{this.props.data.title}</span>
            </div>
        );
    }
}

/**
 * Represent an initial value for the widget: can be a string (node identifier)
 * or an object (ResultItem instance).
 */
type StringOrResultItem = string | ResultItem;

/**
 * Widget properties
 */
export interface WidgetProps {
    /**
     * Default values
     */
    readonly values: StringOrResultItem[];

    /**
     * Widget title
     */
    readonly title?: string;

    /**
     * Open dialog button title
     */
    readonly buttonTitle?: string;

    /**
     * Search box placeholder
     */
    readonly placeholder?: string;

    /**
     * Minimum item count
     */
    readonly minCount?: number;

    /**
     * Maximum item count
     */
    readonly maxCount?: number;

    /**
     * A new value has been selected by the user, if undefined or empty this
     * means the user clicked the "remove" button.
     */
    readonly onUpdate?: (values: ResultItem[]) => void;
};

/**
 * Widget state
 */
interface WidgetState {
    dialogOpened?: boolean;
    result?: Result;
    values: ResultItem[];
};

/**
 * Selector widget, see WidgetProps documentation for options
 */
export class SelectorWidget extends React.Component<WidgetProps, WidgetState> {

    private readonly sortableContainer: React.RefObject<HTMLUListElement>;

    constructor(props: WidgetProps) {
        super(props);

        const values = this.props.values.map((item: StringOrResultItem): ResultItem => {
            if ("string" === typeof item) {
                return {
                    id: item,
                    title: item,
                    status: 0,
                    created: "",
                    updated: "",
                    type: "",
                    human_type: "",
                    output: "",
                };
            }
            return item;
        });

        this.state = {values};

        this.onCloseClick = this.onCloseClick.bind(this);
        this.onDialogOpenClick = this.onDialogOpenClick.bind(this);
        this.onPageChange = this.onPageChange.bind(this);
        this.onRemoveClick = this.onRemoveClick.bind(this);
        this.onSearchChange = this.onSearchChange.bind(this);
        this.onSubmitClick = this.onSubmitClick.bind(this);

        this.sortableContainer = React.createRef();
    }

    componentDidMount() {
        this.refresh();

        // Initialise the sortable behavior on main display.
        /* const handler = */ Dragula([this.sortableContainer.current], {
            isContainer: function (el: any) {
                return false;
              },
              moves: function (el: any, source: any, handle: any, sibling: any) {
                return true;
              },
              accepts: function (el: any, target: any, source: any, sibling: any) {
                return true;
              },
              invalid: function (el: any, handle: any) {
                return false;
              },
              direction: 'horizontal',
              copy: false,
              copySortSource: false,
              revertOnSpill: false,
              removeOnSpill: false,
              mirrorContainer: document.body,
              ignoreInputTextSelection: true,
        });
        // Re-order items in our values on drop
        /*
        handler.on("dragend", () => {
            const values = this.state.values.concat([]);

        });
         */
    }

    refresh(search?: Search) {

        if (!search) {
            search = {};
        }

        // Populate search from state
        if (this.state.result) {
            if (!search.page) {
                search.page = this.state.result.page;
            }
            if (!search.search) {
                search.search = this.state.result.search;
            }
            if (!search.sort_field) {
                search.sort_field = this.state.result.sort_field;
            }
            if (!search.sort_order) {
                search.sort_order = this.state.result.sort_order;
            }
        }

        doSearch(search)
            .then((result) => {
                this.setState((prevState) => {
                    return {
                        result: result,
                        values: prevState.values,
                    };
                });
            })
            .catch((error) => {
                console.log(error);
            })
        ;
    }

    private changeValues(values: ResultItem[]) {
        if (this.props.onUpdate) {
            this.props.onUpdate(values);
        }
        this.setState((prevState) => {
            return {
                result: prevState.result,
                values: values,
            };
        });
    }

    private removeValue(value: ResultItem) {
        // Magic concat() based array clone
        const values = this.state.values.concat([]);
        for (let i = 0; i < values.length; i++) {
            if (values[i].id === value.id) {
                values.splice(i, 1);
                break;
            }
        }
        this.changeValues(values);
    }

    private addValue(value: ResultItem) {
        // Magic concat() based array clone
        const values = this.state.values.concat([]);
        values.push(value);
        this.changeValues(values);
    }

    private onChangeDebounceTimer: any;

    private onSearchChange(event: React.ChangeEvent<HTMLInputElement>) {
        // Very primitive implementation of debounce, see
        // https://gist.github.com/ca0v/73a31f57b397606c9813472f7493a940
        const value = event.target.value;
        (() => {
            clearTimeout(this.onChangeDebounceTimer);
            this.onChangeDebounceTimer = setTimeout(() => this.refresh({search: value}), 200);
        })();
    }

    private onRemoveClick() {
        if (this.props.onUpdate) {
            this.props.onUpdate([]);
        }
        this.onCloseClick();
    }

    private onSubmitClick() {
        if (this.props.onUpdate) {
            this.props.onUpdate(this.state.values);
        }
        this.onCloseClick();
    }

    private onCloseClick() {
        this.setState((prevState) => {
            return {
                dialogOpened: false,
                result: prevState.result,
                values: prevState.values,
            };
        });
    }

    private onDialogOpenClick(event: React.MouseEvent<HTMLAnchorElement>) {
        event.preventDefault();

        this.setState((prevState) => {
            return {
                dialogOpened: true,
                result: prevState.result,
                values: prevState.values,
            };
        });
    }

    private onPageChange(page: number) {
        this.refresh({page: page});
    }

    private isItemInValues(item: ResultItem): boolean {
        for (let candidate of this.state.values) {
            if (candidate.id === item.id) {
                return true;
            }
        }
        return false;
    }

    render() {
        let dialogDisplay = null;

        if (this.state.dialogOpened) {
            const searchValue = this.state.result ? this.state.result.search : "";
            const result = [];

            // Build result list from state
            if (this.state.result) {
                result.push(this.state.result.result.map((item) => (
                    <li key={item.id}>
                        <a onClick={() => {this.addValue(item)}} data-active={this.isItemInValues(item)}>
                            <ResultPreview data={item}/>
                        </a>
                    </li>
                )));
            }

            let pager;
            if (this.state.result) {
                pager = <Pager onClick={this.onPageChange} autoHide={false} page={this.state.result.page || 1} total={this.state.result.total || 0} limit={this.state.result.limit || 1}/>
            } else {
                pager = <Pager onClick={() => {}} autoHide={false} page={1} total={0} limit={1}/>
            }

            dialogDisplay = (
                <Dialog title={this.props.title || "Select content"} doClose={this.onCloseClick}>
                    <input type="text" name="search" onChange={this.onSearchChange} placeholder={this.props.placeholder} value={searchValue}/>
                    <ul className="results">{result}</ul>
                    {pager}
                    <div className="current">
                        <h2>Current selection</h2>
                        <ul className="results selection">
                            {this.state.values.map((item) => (
                                <li key={item.id}>
                                    <a onClick={() => this.removeValue(item)}>
                                        <ResultPreview data={item}/>
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                    <div className="footer">
                        <button className="btn btn-danger" name="remove" disabled={!this.state.values.length} onClick={this.onRemoveClick}>
                            Remove
                        </button>
                        <button className="btn btn-success" name="submit" disabled={!this.state.values.length} onClick={this.onSubmitClick}>
                            Select
                        </button>
                    </div>
                </Dialog>
            );
        }

        return (
            <div className="node-selector">
                <div className="current">
                    <ul className="results selection" ref={this.sortableContainer}>
                        {this.state.values.map((item) => (
                            <li key={item.id}>
                                <ResultPreview data={item}/>
                            </li>
                        ))}
                    </ul>
                </div>
                <a href="#" className="btn btn-default" onClick={this.onDialogOpenClick}>{this.props.buttonTitle || "Select"}</a>
                {dialogDisplay}
            </div>
        );
    }
}

/**
 * Spawn the dialog in the given element, beware that the element will be
 * replaced by the dialog, DO NOT GIVE AN NON EMPTY ELEMENT here.
 */
export function SelectorWidgetInit(target: HTMLInputElement) {

    // Prepare arguments from data attributes
    // let types: string[] | null = null;
    // if (target.hasAttribute("data-type")) {
    //     types = (<string>target.getAttribute("data-type")).split(",").map(value => value.trim());
    // }

    // Find a default value
    const defaults: StringOrResultItem[] = [];
    const stringValue = target.value.trim();

    // I am not proud of this one, but I needed a way to plug it in to raw HTML
    // for users that don't work with React.
    if (stringValue.length) {
        const idList = target.value.split(',');
        if (target.hasAttribute("data-default")) {
            try {
                const candidates = JSON.parse(target.getAttribute("data-default") || "");
                for (let id of idList) {
                    let found = false;
                    for (let candidate of candidates) {
                        if (candidate.id === id) {
                            found = true;
                            defaults.push(candidate);
                        }
                    }
                    if (!found) {
                        defaults.push(id);
                    }
                }
            } catch (error) {
                console.log(`error while reading the data-default attribute: ${error}`)
                idList.map(id => defaults.push(id));
            }
        } else {
            idList.map(id => defaults.push(id));
        }
    }

    const props = {
        title: target.getAttribute("title") ||target.getAttribute("data-title") || "",
        values: defaults,
        placeholder: target.getAttribute("placeholder") || target.getAttribute("data-placeholder") || "",
        minCount: parseInt(target.getAttribute("data-min") || "") || 0,
        maxCount: parseInt(target.getAttribute("data-max") || "") || 1,
        // Restore values into the hidden widget, that will be really POST'ed.
        onUpdate: (values: ResultItem[]) => target.value = values.map(item => item.id).join(","),
    };

    let element: HTMLElement = target.ownerDocument.createElement('div');
    if (target.parentElement) {
        target.parentElement.insertBefore(element, target);
    } else {
        element = target;
    }

    ReactDOM.render(<SelectorWidget{...props}/>, element);
}

