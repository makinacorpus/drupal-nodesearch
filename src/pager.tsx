
import * as React from "react";

export interface PagerProps {
    readonly page: number;
    readonly limit: number;
    readonly total: number;
    readonly onClick: (page: number) => void;
    readonly autoHide?: boolean;
    readonly ariaLabel?: string;
    readonly className?: string;
    readonly previousLabel?: string;
    readonly nextLabel?: string;
}

export class Pager extends React.Component<PagerProps> {

    constructor(props: PagerProps) {
        super(props);

        this.onPageLinkClick = this.onPageLinkClick.bind(this);
    }

    private onPageLinkClick(page: number): void {
        this.props.onClick(page);
    }

    render() {
        // Auto-hide if there is only one page
        if (this.props.autoHide && this.props.total <= this.props.limit) {
            return null;
        }

        // Build page links if revelant
        const pageLinks = [];
        const pageCount = Math.ceil(this.props.total / this.props.limit);
        for (let i = 1; i <= pageCount; ++i) {
            if (this.props.page === i) {
                pageLinks.push(<li key={i} className="active"><a onClick={() => {this.onPageLinkClick(i)}} href="#">{i}</a></li>);
            } else {
                pageLinks.push(<li key={i}><a onClick={() => {this.onPageLinkClick(i)}} href="#">{i}</a></li>);
            }
        }

        let firstPageLink = null, lastPageLink = null;
        if (pageCount > 1) {
            if (this.props.page === 1) {
                firstPageLink = (
                    <li className="disabled">
                        <a href="#" aria-label={this.props.previousLabel || "Previous"}>
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                );
            } else {
                firstPageLink = (
                    <li>
                        <a href="#" aria-label={this.props.previousLabel || "Previous"}>
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                );
            }
            if (this.props.page === pageCount) {
                lastPageLink = (
                    <li>
                        <a href="#" aria-label={this.props.nextLabel || "Next"}>
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                );
            } else {
                lastPageLink = (
                    <li className="disabled">
                        <a href="#" aria-label={this.props.nextLabel || "Next"}>
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                );
            }
        }

        return (
            <nav aria-label={this.props.ariaLabel || "Page navigation"}>
                <ul className={this.props.className || "pagination"}>
                    {firstPageLink}
                    {pageLinks}
                    {lastPageLink}
                </ul>
            </nav>
        );
    }
}
