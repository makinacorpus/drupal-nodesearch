
import * as React from "react";

export interface DialogProps {
    /**
     * Dialog title
     */
    readonly title: string;

    /**
     * Close button label (hidden, but displayed by scren readers)
     */
    readonly closeLabel?: string;

    /**
     * Close dialog handler: you must hide dialog when this is clicked.
     */
    readonly doClose: () => void;
};

export class Dialog extends React.Component<DialogProps> {

    constructor(props: DialogProps) {
        super(props);

        this.onCloseClick = this.onCloseClick.bind(this);
    }

    private onCloseClick() {
        this.props.doClose();
    }

    render() {
        return (
            <div className="node-selector-dialog">
                <div className="overlay"/>
                <div className="dialog">
                    <div className="inner">
                        <h1 className="title">
                            {this.props.title}
                            <button name="close" onClick={this.onCloseClick} className="close">
                                <span className="sr-only">{this.props.closeLabel || "Close"}</span>
                            </button>
                        </h1>
                        <div className="content">
                            {this.props.children}
                        </div>
                    </div>
                </div>
            </div>
        );
    }
}
