
import { Result, Search } from "./core";
import { Pager } from "./pager";
import * as ReactDOM from "react-dom";
import * as React from "react";

/**
 * Spawn the dialog in the given element, beware that the element will be
 * replaced by the dialog, DO NOT GIVE AN NON EMPTY ELEMENT here.
 */
export function renderDialog(props: SelectorProps, element: any) {
    ReactDOM.render(<Selector{...props}/>, element);
}

export interface SelectorProps {
    /**
     * Dialog title
     */
    readonly title: string;

    /**
     * Search box placeholder
     */
    readonly placeholder?: string;

    /**
     * Close dialog handler: you must hide dialog when this is clicked.
     */
    readonly doClose: () => void;

    /**
     * Refresh result handler: this is where you run your AJAX query.
     */
    readonly doRefresh: (search: Search) => Promise<Result>;

    /**
     * A new value has been selected by the user, if undefined or empty this
     * means the user clicked the "remove" button.
     */
    readonly doUpdate: (value?: string) => void;
};

interface SelectorState {
    result?: Result;
    types?: string[];
    value?: string;
};

export class Selector extends React.Component<SelectorProps, SelectorState> {

    constructor(props: SelectorProps) {
        super(props);

        // https://reactjs.org/docs/state-and-lifecycle.html
        this.state = {};

        // https://reactjs.org/docs/handling-events.html
        this.onCloseClick = this.onCloseClick.bind(this);
        this.onPageChange = this.onPageChange.bind(this);
        this.onRemoveClick = this.onRemoveClick.bind(this);
        this.onSearchChange = this.onSearchChange.bind(this);
        this.onSubmitClick = this.onSubmitClick.bind(this);
    }

    componentDidMount() {
        this.refresh();
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

        this.props
            .doRefresh(search)
            .then((result) => {
                this.setState((prevState) => {
                    return {
                        result: result,
                        types: this.state.types,
                        value: prevState.value,
                    };
                });
            })
            .catch((error) => {
                console.log(error);
            })
        ;
    }

    private changeValue(value?: string) {
        this.props.doUpdate(this.state.value);
        this.setState((prevState) => {
            return {
                result: prevState.result,
                types: prevState.types,
                value: value,
            };
        });
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
        this.props.doUpdate();
        this.props.doClose();
    }

    private onSubmitClick() {
        this.props.doUpdate(this.state.value);
        this.props.doClose();
    }

    private onCloseClick() {
        this.props.doClose();
    }

    private onPageChange(page: number) {
        this.refresh({page: page});
    }

    render() {
        const searchValue = this.state.result ? this.state.result.search : "";

        const result = [];
        if (this.state.result) {
            result.push(this.state.result.result.map((item) => {
                return (
                    <li key={item.id} data-active={item.id === this.state.value}>
                        <a onClick={() => {this.changeValue(item.id)}}>{item.title}</a>
                    </li>
                );
            }));
        }

        let pager;
        if (this.state.result) {
            pager = <Pager onClick={this.onPageChange} autoHide={false} page={this.state.result.page || 1} total={this.state.result.total || 0} limit={this.state.result.limit || 1}/>
        } else {
            pager = <Pager onClick={() => {}} autoHide={false} page={1} total={0} limit={1}/>
        }

        return (
            <div>
                <div className="node-search-overlay"/>
                <div className="node-search-dialog">
                    <div className="node-search-selector">
                        <h1 className="title">
                          {this.props.title}
                          <button name="close" onClick={this.onCloseClick} className="close">
                              <span className="sr-only">Close</span>
                          </button>
                        </h1>
                        <div className="header">
                            <input type="text" name="search" onChange={this.onSearchChange} placeholder={this.props.placeholder} value={searchValue}/>
                        </div>
                        <ul className="results">{result}</ul>
                        {pager}
                        <div className="footer">
                            <button className="btn btn-danger" name="remove" disabled={!this.state.value} onClick={this.onRemoveClick}>Remove</button>
                            <button className="btn btn-success" name="submit" disabled={!this.state.value} onClick={this.onSubmitClick}>Select</button>
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}
