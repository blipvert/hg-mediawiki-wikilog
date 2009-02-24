<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008, 2009 Juliano F. Ravasi
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
		global $wgParser, $wgUser, $wgContLang;

		$skin = $this->getSkin();

		# Retrieve article parser output and other data.
		$item = WikilogItem::newFromRow( $row );
		list( $article, $parserOutput ) = WikilogUtils::parsedArticle( $item->mTitle );
		list( $summary, $content ) = WikilogUtils::splitSummaryContent( $parserOutput );

		# Some general data.
		$authors = WikilogUtils::authorList( array_keys( $item->mAuthors ) );
		$pubdate = $wgContLang->timeanddate( $item->getPublishDate(), true );
		$comments = self::getCommentsWikiText( $item );

		# Entry div class.
		$divclass = 'wl-entry' . ( $item->getIsPublished() ? '' : ' wl-draft' );
		$result = "<div class=\"{$divclass} visualClear\">";

		# Edit section link.
		if ( $item->mTitle->userCanEdit() ) {
			$result .= $this->editLink( $item->mTitle );
		}

		# Title heading, with link.
		$heading = $skin->makeKnownLinkObj( $item->mTitle, $item->mName .
			( $item->getIsPublished() ? '' : ' '. wfMsgForContent( 'wikilog-draft-title-mark' ) ) );
		$result .= "<h2>{$heading}</h2>\n";

		# Item header.
		$result .= wfMsgExt( 'wikilog-item-brief-header',
			array( 'parse', 'content' ),
			/* $1 */ $item->mParentTitle->getPrefixedURL(),
			/* $2 */ $item->mParentName,
			/* $3 */ $item->mTitle->getPrefixedURL(),
			/* $4 */ $item->mName,
			/* $5 */ $authors,
			/* $6 */ $pubdate,
			/* $7 */ $comments
		) . "\n";

		# Item text.
		if ( $summary ) {
			$more = wfMsgExt( 'wikilog-item-more',
				array( 'parse', 'content' ),
				/* $1 */ $item->mParentTitle->getPrefixedURL(),
				/* $2 */ $item->mParentName,
				/* $3 */ $item->mTitle->getPrefixedURL(),
				/* $4 */ $item->mName
			);
			$result .= "<div class=\"wl-summary\">{$summary}{$more}</div>\n";
		} else {
			$result .= "<div class=\"wl-summary\">{$content}</div>\n";
		}

		# Item footer.
		$result .= wfMsgExt( 'wikilog-item-brief-footer',
			array( 'parse', 'content' ),
			/* $1 */ $item->mParentTitle->getPrefixedURL(),
			/* $2 */ $item->mParentName,
			/* $3 */ $item->mTitle->getPrefixedURL(),
			/* $4 */ $item->mName,
			/* $5 */ $authors,
			/* $6 */ $pubdate,
			/* $7 */ $comments
		);

		$result .= "</div>\n\n";
		return $result;
	}

	private function editLink( $title ) {
		$skin = $this->getSkin();
		$url = $skin->makeKnownLinkObj( $title, wfMsg('wikilog-edit-lc'), 'action=edit' );
		$result = wfMsg( 'editsection-brackets', $url );
		return "<span class=\"editsection\">$result</span>";
	}

	protected static function getCommentsWikiText( WikilogItem &$item ) {
		$commentsNum = $item->getNumComments();
		$commentsMsg = ( $commentsNum ? 'wikilog-has-comments' : 'wikilog-no-comments' );
		$commentsUrl = $item->mTitle->getTalkPage()->getPrefixedURL();
		$commentsTxt = wfMsgExt( $commentsMsg, array( 'parseinline', 'parsemag', 'content' ), $commentsNum );
		return "[[{$commentsUrl}|{$commentsTxt}]]";
	}

}


class WikilogTemplatePager extends WikilogSummaryPager {

	protected $mTemplate, $mTemplateTitle;
	protected $mParser, $mParserOpt;

	function __construct( WikilogItemQuery $query, Title $template, $limit = false ) {
		global $wgParser, $wgUser, $wgTitle;

		# Parent constructor.
		parent::__construct( $query, $limit );

		# Private parser.
		$this->mParserOpt = ParserOptions::newFromUser( $wgUser );
		$this->mParser = clone $wgParser;
		$this->mParser->startExternalParse( $wgTitle, $this->mParserOpt, Parser::OT_HTML );

		# Load template
		list( $this->mTemplate, $this->mTemplateTitle ) =
			$this->mParser->getTemplateDom( $template );
	}

	function getDefaultQuery() {
		$query = parent::getDefaultQuery();
		$query['template'] = $this->mTemplateTitle->getPartialURL();
		return $query;
	}

	function getStartBody() {
		return "<div class=\"wl-tpl-roll\">\n";
	}

	function getEndBody() {
		return "</div>\n";
	}

	function formatRow( $row ) {
		global $wgTitle, $wgContLang;

		# Clear parser state.
		$this->mParser->startExternalParse( $wgTitle, $this->mParserOpt, Parser::OT_HTML );

		# Retrieve article parser output and other data.
		$item = WikilogItem::newFromRow( $row );
		list( $article, $parserOutput ) = WikilogUtils::parsedArticle( $item->mTitle, false, $this->mParser );
		list( $summary, $content ) = WikilogUtils::splitSummaryContent( $parserOutput );
		if ( !$summary ) $summary = $content;

		# Some general data.
		$authors = WikilogUtils::authorList( array_keys( $item->mAuthors ) );
		$tags = implode( wfMsgForContent( 'comma-separator' ), array_keys( $item->mTags ) );
		$pubdate = $wgContLang->timeanddate( $item->getPublishDate(), true );
		$updated = $wgContLang->timeanddate( $item->getUpdatedDate(), true );
		$comments = self::getCommentsWikiText( $item );
		$divclass = 'wl-entry' . ( $item->getIsPublished() ? '' : ' wl-draft' );

		# Template parameters.
		$vars = array(
			'class'         => $divclass,
			'wikilogTitle'  => $item->mParentName,
			'wikilogPage'   => $item->mParentTitle->getPrefixedText(),
			'title'         => $item->mName,
			'page'          => $item->mTitle->getPrefixedText(),
			'authors'       => $authors,
			'tags'          => $tags,
			'published'     => $item->getIsPublished(),
			'pubdate'       => $pubdate,
			'updated'       => $updated,
			'summary'       => $this->mParser->insertStripItem( $summary ),
			'comments'      => $comments
		);

		$frame = $this->mParser->getPreprocessor()->newCustomFrame( $vars );
		$text = $frame->expand( $this->mTemplate );

		$pout = $this->mParser->parse( $text, $wgTitle, $this->mParserOpt, true, false );
		return $pout->getText();
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
		$query['view'] = 'archives';
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
		$attribs = array();
		$columns = array();
		$this->mCurrentRow = $row;
		$this->mCurrentItem = WikilogItem::newFromRow( $row );
		if ( !$this->mCurrentItem->getIsPublished() ) {
			$attribs['class'] = 'wl-draft';
		}
		foreach ( $this->getFieldNames() as $field => $name ) {
			$value = isset( $row->$field ) ? $row->$field : null;
			$formatted = strval( $this->formatValue( $field, $value ) );
			if ( $formatted == '' ) {
				$formatted = '&nbsp;';
			}
			$class = 'TablePager_col_' . htmlspecialchars( $field );
			$columns[] = "<td class=\"$class\">$formatted</td>";
		}
		return Xml::tags( 'tr', $attribs, implode( "\n", $columns ) ) . "\n";
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
				return $this->authorList( $this->mCurrentItem->mAuthors );

			case 'wlw_title':
				$page = $this->mCurrentItem->mParentTitle;
				$text = $this->mCurrentItem->mParentName;
				return $this->getSkin()->makeKnownLinkObj( $page, $text );

			case 'wlp_title':
				$page = $this->mCurrentItem->mTitle;
				$text = $this->mCurrentItem->mName;
				$s = $this->getSkin()->makeKnownLinkObj( $page, $text );
				if ( !$this->mCurrentRow->wlp_publish ) {
					$draft = wfMsg( 'wikilog-draft-title-mark' );
					$s = Xml::wrapClass( "$s $draft", 'wl-draft-inline' );
				}
				return $s;

			case 'wlp_num_comments':
				$page = $this->mCurrentItem->mTitle->getTalkPage();
				$text = $this->mCurrentItem->getNumComments();
				return $this->getSkin()->makeKnownLinkObj( $page, $text );

			case '_wl_actions':
				if ( $this->mCurrentItem->mTitle->userCanEdit() ) {
					return $this->editLink( $this->mCurrentItem->mTitle );
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
		global $wgWikilogEnableComments;

		$fields = array();

		$fields['wlp_pubdate']			= wfMsgHtml( 'wikilog-published' );
// 		$fields['wlp_updated']			= wfMsgHtml( 'wikilog-updated' );
		$fields['wlp_authors']			= wfMsgHtml( 'wikilog-authors' );

		if ( !$this->mQuery->isSingleWikilog() )
			$fields['wlw_title']		= wfMsgHtml( 'wikilog-wikilog' );

		$fields['wlp_title']			= wfMsgHtml( 'wikilog-title' );

		if ( $wgWikilogEnableComments )
			$fields['wlp_num_comments']	= wfMsgHtml( 'wikilog-comments' );

		$fields['_wl_actions']			= wfMsgHtml( 'wikilog-actions' );
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
		$url = $skin->makeKnownLinkObj( $title, wfMsg('wikilog-edit-lc'), 'action=edit' );
		return wfMsg( 'wikilog-brackets', $url );
	}
}

