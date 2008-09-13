<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi
 * http://www.mediawiki.org/wiki/Extension:Wikilog
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

/**
 * @addtogroup Extensions
 * @author Juliano F. Ravasi < dev juliano info >
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();


class WikilogSummaryPager extends ReverseChronologicalPager {

	# Override default limits.
	public $mLimitsShown = array( 5, 10, 20, 50 );

	# Local variables.
	protected $mQuery;			///< Wikilog item query data

	function __construct( WikilogItemQuery $query, $limit = false ) {
		# WikilogItemQuery object drives our queries.
		$this->mQuery = $query;

		# Parent constructor.
		parent::__construct();

		# Fix our limits, Pager's defaults are too high.
		global $wgUser, $wgWikilogNumArticles;
		$this->mDefaultLimit = intval( $wgUser->getOption( 'searchlimit' ) );

		if ( $limit ) {
			$this->mLimit = $limit;
		} else {
			list( $this->mLimit, /* $offset */ ) =
				$this->mRequest->getLimitOffset( $wgWikilogNumArticles, 'searchlimit' );
		}

		# This is too expensive, limit listing.
		global $wgWikilogSummaryLimit;
		if ( $this->mLimit > $wgWikilogSummaryLimit )
			$this->mLimit = $wgWikilogSummaryLimit;
	}

	function getQueryInfo() {
		return $this->mQuery->getQueryInfo( $this->mDb );
	}

	function getDefaultQuery() {
		return parent::getDefaultQuery() + $this->mQuery->getDefaultQuery();
	}

	function getIndexField() {
		return 'wlp_pubdate';
	}

	function getStartBody() {
		return "<div class=\"wl-roll visualClear\">\n";
	}

	function getEndBody() {
		return "</div>\n";
	}

	function getEmptyBody() {
		return wfMsgWikiHtml( 'wikilog-pager-empty' );
	}

	function getNavigationBar( $pos = false ) {
		if ( !isset( $this->mNavigationBar ) ) {
			global $wgLang;
			$nicenumber = $wgLang->formatNum( $this->mLimit );
			$linkTexts = array(
				'prev'	=> wfMsgExt( 'wikilog-pager-newer-n', array( 'parsemag' ), $nicenumber ),
				'next'	=> wfMsgExt( 'wikilog-pager-older-n', array( 'parsemag' ), $nicenumber ),
				'first'	=> wfMsgHtml( 'wikilog-pager-newest' ),
				'last'	=> wfMsgHtml( 'wikilog-pager-oldest' )
			);
			$pagingLinks = $this->getPagingLinks( $linkTexts );
			$limitLinks = $this->getLimitLinks();
			$limits = implode( ' | ', $limitLinks );
			$this->mNavigationBar = wfMsgWikiHtml( 'wikilog-navigation-bar',
				/* $1 */ $pagingLinks['first'],
				/* $2 */ $pagingLinks['prev'],
				/* $3 */ $pagingLinks['next'],
				/* $4 */ $pagingLinks['last'],
				/* $5 */ $limits
			);
		}
		return $this->mNavigationBar;
	}

	function formatRow( $row ) {
		global $wgParser, $wgEnableParserCache, $wgUser, $wgContLang;

		$skin = $this->getSkin();

		# Get titles.
		list( $wikilogTitleName, $itemName ) =
			explode( '/', str_replace( '_', ' ', $row->page_title ), 2 );
		$wikilogTitleTitle =& Title::makeTitle( $row->page_namespace, $wikilogTitleName );
		$itemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		# Retrieve article parser output and other data.
		list( $article, $parserOutput ) = Wikilog::parsedArticle( $itemTitle );
		list( $summary, $content ) = Wikilog::splitSummaryContent( $parserOutput );
		$authors = unserialize( $row->wlp_authors );
		$authors = Wikilog::authorList( array_keys( $authors ) );
		$pubdate = $wgContLang->timeanddate( $row->wlp_pubdate, true );

		# Entry div class.
		$divclass = 'wl-entry' . ( $row->wlp_publish ? '' : ' wl-draft' );
		$result = "<div class=\"{$divclass} visualClear\">";

		# Edit section link.
		if ( $itemTitle->userCanEdit() ) {
			$result .= $this->editLink( $itemTitle );
		}

		# Title heading, with link.
		$heading = $skin->makeKnownLinkObj( $itemTitle, $itemName .
			( $row->wlp_publish ? '' : ' '. wfMsgForContent( 'wikilog-draft-title-mark' ) ) );
		$result .= "<h2>{$heading}</h2>\n";

		# Item header.
		$result .= wfMsgExt( 'wikilog-item-brief-header',
			array( 'parse', 'content' ),
			/* $1 */ $wikilogTitleTitle->getPrefixedURL(),
			/* $2 */ $wikilogTitleName,
			/* $3 */ $itemTitle->getPrefixedURL(),
			/* $4 */ $itemName,
			/* $5 */ $authors,
			/* $6 */ $pubdate
		) . "\n";

		if ( $summary ) {
			$more = wfMsgExt( 'wikilog-item-more',
				array( 'parse', 'content' ),
				/* $1 */ $wikilogTitleTitle->getPrefixedURL(),
				/* $2 */ $wikilogTitleName,
				/* $3 */ $itemTitle->getPrefixedURL(),
				/* $4 */ $itemName
			);
			$result .= "<div class=\"wl-summary\">{$summary}{$more}</div>\n";
		} else {
			$result .= "<div class=\"wl-summary\">{$content}</div>\n";
		}

		# Item footer.
		$result .= wfMsgExt( 'wikilog-item-brief-footer',
			array( 'parse', 'content' ),
			/* $1 */ $wikilogTitleTitle->getPrefixedURL(),
			/* $2 */ $wikilogTitleName,
			/* $3 */ $itemTitle->getPrefixedURL(),
			/* $4 */ $itemName,
			/* $5 */ $authors,
			/* $6 */ $pubdate
		);

		$result .= "</div>\n\n";
		return $result;
	}

	private function editLink( $title ) {
		$skin = $this->getSkin();
		$url = $skin->makeKnownLinkObj( $title, wfMsg('editsection'), 'action=edit' );
		$result = wfMsg( 'editsection-brackets', $url );
		return "<span class=\"editsection\">$result</span>";
	}

}


class WikilogArchivesPager extends TablePager {

	protected $mQuery;

	function __construct( WikilogItemQuery $query ) {
		# WikilogItemQuery object drives our queries.
		$this->mQuery = $query;

		# Parent constructor.
		parent::__construct();
	}

	function getQueryInfo() {
		return $this->mQuery->getQueryInfo( $this->mDb, true );
	}

	function getDefaultQuery() {
		$query = parent::getDefaultQuery() + $this->mQuery->getDefaultQuery();
		$query['list'] = 'archives';
		return $query;
	}

	function getTableClass() {
		return 'wl-archives TablePager';
	}

	function isFieldSortable( $field ) {
		return in_array( $field, array(
			'_wl_wikilog',
			'_wl_title',
			'wlp_pubdate'
		) );
	}

	function getNavigationBar( $pos = false ) {
		if ( !isset( $this->mNavigationBar ) ) {
			global $wgLang;
			$nicenumber = $wgLang->formatNum( $this->mLimit );
			$linkTexts = array(
				'prev'	=> wfMsgHtml( 'wikilog-pager-prev' ),
				'next'	=> wfMsgHtml( 'wikilog-pager-next' ),
				'first'	=> wfMsgHtml( 'wikilog-pager-first' ),
				'last'	=> wfMsgHtml( 'wikilog-pager-last' )
			);
			$pagingLinks = $this->getPagingLinks( $linkTexts );
			$limitLinks = $this->getLimitLinks();
			$limits = implode( ' | ', $limitLinks );
			$this->mNavigationBar = wfMsgWikiHtml( 'wikilog-navigation-bar',
				/* $1 */ $pagingLinks['first'],
				/* $2 */ $pagingLinks['prev'],
				/* $3 */ $pagingLinks['next'],
				/* $4 */ $pagingLinks['last'],
				/* $5 */ $limits
			);
		}
		return $this->mNavigationBar;
	}

	function formatRow( $row ) {
		list( $this->mCurrentBlogName, $this->mCurrentItemName ) =
			explode( '/', str_replace( '_', ' ', $row->page_title ), 2 );
		$this->mCurrentBlogTitle =& Title::makeTitle( $row->page_namespace, $this->mCurrentBlogName );
		$this->mCurrentItemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		return parent::formatRow( $row );
	}

	function formatValue( $name, $value ) {
		switch ( $name ) {
			case 'wlp_pubdate':
				global $wgContLang;
				return $value ? $wgContLang->timeanddate( $value, true ) : '';

			case 'wlp_authors':
				return $this->authorList( unserialize( $value ) );

			case '_wl_wikilog':
				return $this->getSkin()->makeKnownLinkObj( $this->mCurrentBlogTitle, $this->mCurrentBlogName );

			case '_wl_title':
				$s = $this->getSkin()->makeKnownLinkObj( $this->mCurrentItemTitle, $this->mCurrentItemName );
				if ( !$this->mCurrentRow->wlp_publish ) {
					$draft = wfMsg( 'wikilog-draft-title-mark' );
					$s = "<i>$s $draft</i>";
				}
				return $s;

			case '_wl_actions':
				if ( $this->mCurrentItemTitle->userCanEdit() ) {
					return $this->editLink( $this->mCurrentItemTitle );
				} else {
					return '';
				}

			default:
				return htmlentities( $value );
		}
	}

	function getDefaultSort() {
		return 'wlp_pubdate';
	}

	function getFieldNames() {
		$fields = array();

		$fields['wlp_pubdate']	= wfMsgHtml( 'wikilog-published' );
		$fields['wlp_authors']	= wfMsgHtml( 'wikilog-authors' );

		if ( !$this->mQuery->isSingleWikilog() )
			$fields['_wl_wikilog'] = wfMsgHtml( 'wikilog-wikilog' );

		$fields['_wl_title']	= wfMsgHtml( 'wikilog-title' );
		$fields['_wl_actions']	= wfMsgHtml( 'wikilog-actions' );
		return $fields;
	}

	private function authorList( $list ) {
		if ( is_string( $list ) ) {
			return $this->authorLink( $list );
		}
		else if ( is_array( $list ) ) {
			$list = array_keys( $list );
			return implode( ', ', array_map( array( &$this, 'authorLink' ), $list ) );
		}
		else {
			return '';
		}
	}

	private function authorLink( $name ) {
		$skin = $this->getSkin();
		$title = Title::makeTitle( NS_USER, $name );
		return $skin->makeLinkObj( $title, $name );
	}

	private function editLink( $title ) {
		$skin = $this->getSkin();
		$url = $skin->makeKnownLinkObj( $title, wfMsg('editsection'), 'action=edit' );
		return wfMsg( 'editsection-brackets', $url );
	}
}

