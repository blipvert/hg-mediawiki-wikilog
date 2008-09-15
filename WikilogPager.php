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
		$wikilogName = str_replace( '_', ' ', $row->wlw_title );
		$wikilogTitle =& Title::makeTitle( $row->wlw_namespace, $row->wlw_title );
		$itemName = str_replace( '_', ' ', $row->wlp_title );
		$itemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		# Retrieve article parser output and other data.
		list( $article, $parserOutput ) = Wikilog::parsedArticle( $itemTitle );
		list( $summary, $content ) = Wikilog::splitSummaryContent( $parserOutput );
		$authors = (array)unserialize( $row->wlp_authors );
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
			/* $1 */ $wikilogTitle->getPrefixedURL(),
			/* $2 */ $wikilogName,
			/* $3 */ $itemTitle->getPrefixedURL(),
			/* $4 */ $itemName,
			/* $5 */ $authors,
			/* $6 */ $pubdate
		) . "\n";

		if ( $summary ) {
			$more = wfMsgExt( 'wikilog-item-more',
				array( 'parse', 'content' ),
				/* $1 */ $wikilogTitle->getPrefixedURL(),
				/* $2 */ $wikilogName,
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
			/* $1 */ $wikilogTitle->getPrefixedURL(),
			/* $2 */ $wikilogName,
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
		return $this->mQuery->getQueryInfo( $this->mDb );
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
		static $sortableFields = array(
			'wlp_pubdate',
			'wlp_updated',
			'wlw_title',
			'wlp_title',
		);
		return in_array( $field, $sortableFields );
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
		$this->mCurrWikilogTitle =& Title::makeTitle( $row->wlw_namespace, $row->wlw_title );
		$this->mCurrItemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );
		return parent::formatRow( $row );
	}

	function formatValue( $name, $value ) {
		global $wgContLang;

		switch ( $name ) {
			case 'wlp_pubdate':
				$s = $wgContLang->timeanddate( $value, true );
				if ( !$this->mCurrentRow->wlp_publish ) {
					$s = Xml::wrapClass( $s, 'wl-draft-inline' );
				}
				return $s;

			case 'wlp_updated':
				return $value;

			case 'wlp_authors':
				@$value = (array)unserialize( $value );
				return $this->authorList( $value );

			case 'wlw_title':
				$value = str_replace( '_', ' ', $value );
				return $this->getSkin()->makeKnownLinkObj( $this->mCurrWikilogTitle, $value );

			case 'wlp_title':
				$value = str_replace( '_', ' ', $value );
				$s = $this->getSkin()->makeKnownLinkObj( $this->mCurrItemTitle, $value );
				if ( !$this->mCurrentRow->wlp_publish ) {
					$draft = wfMsg( 'wikilog-draft-title-mark' );
					$s = Xml::wrapClass( "$s $draft", 'wl-draft-inline' );
				}
				return $s;

			case '_wl_actions':
				if ( $this->mCurrItemTitle->userCanEdit() ) {
					return $this->editLink( $this->mCurrItemTitle );
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
// 		$fields['wlp_updated']	= wfMsgHtml( 'wikilog-updated' );
		$fields['wlp_authors']	= wfMsgHtml( 'wikilog-authors' );

		if ( !$this->mQuery->isSingleWikilog() )
			$fields['wlw_title'] = wfMsgHtml( 'wikilog-wikilog' );

		$fields['wlp_title']	= wfMsgHtml( 'wikilog-title' );
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

