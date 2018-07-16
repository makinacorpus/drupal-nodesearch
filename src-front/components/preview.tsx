
// import * as Dragula from 'react-dragula';
import * as React from "react";
import { ResultItem } from "../core";

/**
 * Single result preview placeholder component
 */
export class ResultPreviewPlaceholder extends React.Component {
    render() {
        return <div className="node-selector-placeholder"></div>;
    }
}

/**
 * Single result preview properties
 */
export interface ResultPreviewProps {
    readonly active?: boolean;
    readonly item: ResultItem;
    readonly onClick?: () => void;
}

/**
 * Single result preview component
 */
export class ResultPreview extends React.Component<ResultPreviewProps> {

    constructor(props: ResultPreviewProps) {
        super(props);

        this.onClick = this.onClick.bind(this);
    }

    private onClick(event: React.MouseEvent<HTMLAnchorElement>) {
        event.preventDefault();

        if (this.props.onClick) {
            this.props.onClick();
        }
    }

    render() {
        let image;
        if (this.props.item.image) {
            image = <img alt="" src={this.props.item.image}/>;
        }

        const content = <div><span className="title">{this.props.item.title}</span>{image}</div>;

        if (this.props.onClick) {
            return (
                <a onClick={this.onClick} className="node-selector-item" data-active={this.props.active}>
                    {content}
                </a>
            );
        }

        return (
            <div className="node-selector-item" data-active={this.props.active}>
                {content}
            </div>
        );
    }
}

/**
 * Single result preview properties
 */
export interface ResultPreviewListProps {
    readonly active: string[];
    readonly data: ResultItem[];
    readonly onItemClick?: (item: ResultItem) => void;
    readonly onItemSort?: (item: ResultItem[]) => void;
    readonly sortable?: boolean;
    readonly maxItemCount?: number;
}

/**
 * Single result preview component
 */
export class ResultPreviewList extends React.Component<ResultPreviewListProps> {

    private readonly sortable: React.RefObject<HTMLDivElement>;

    constructor(props: ResultPreviewListProps) {
        super(props);

        this.onItemClick = this.onItemClick.bind(this);
        this.sortable = React.createRef();
    }

    componentDidMount() {
        /*
        // Initialise the sortable behavior on main display.
        /* const handler = * / Dragula([this.sortableContainer.current], {
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
        handler.on("dragend", () => {
            const values = this.state.values.concat([]);

        });
         */
    }

    private onItemClick(item: ResultItem) {
        if (this.props.onItemClick) {
            this.props.onItemClick(item);
        }
    }

    render() {
        const classNameSuffix = this.props.sortable ? " sortable" : "";
        const placeholders = [];
        if (this.props.maxItemCount) {
            for (let i = this.props.data.length; i < this.props.maxItemCount; ++i) {
                placeholders.push("placeholder-" + i);
            }
        }

        return (
            <div ref={this.sortable} className={"results" + classNameSuffix}>
                {this.props.data.map((item) => <ResultPreview
                    key={item.id}
                    item={item}
                    active={-1 !== this.props.active.indexOf(item.id)}
                    onClick={() => this.onItemClick(item)}
                />)}
                {placeholders.map((key) => <ResultPreviewPlaceholder key={key}/>)}
            </div>
        );
    }
}
