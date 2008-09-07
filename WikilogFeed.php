<?php
/**
 * MediaWiki Wikilog extension
 * Copyright © 2008 Juliano F. Ravasi < dev at juliano info >
 * http://juliano.info/en/Projects/MediaWiki_Wikilog
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
 *
 * Adds blogging features to MediaWiki, through a special namespace,
 * making it a wiki-blog hybrid, like a Bliki.
 */

if ( !defined( 'MEDIAWIKI' ) )
	die();

class WikilogFeed {

	public $mTitle;
	public $mQuery;
	public $mDb;

	protected $mParserOptions;
	protected $mParser;
	protected $mFrame;

	/**
	 * WikilogFeed constructor.
	 *
	 * @param $title Feed title and URL.
	 * @param $query WikilogItemQuery options.
	 */
	function __construct( $title, WikilogItemQuery $query ) {
		$this->mTitle = $title;
		$this->mQuery = $query;
		$this->mDb = wfGetDB( DB_SLAVE );
	}

	function feed( $type, $limit ) {
		global $wgFeed, $wgWikilogFeedClasses, $wgContLanguageCode, $wgOut;
		global $wgFavicon;

		if ( !$wgFeed ) {
			$wgOut->addWikiMsg( 'feed-unavailable' );
			return;
		}

		if( !isset( $wgWikilogFeedClasses[$type] ) ) {
			wfHttpError( 500, "Internal Server Error", "Unsupported feed type." );
			return;
		}

		# Expand URLs.
		$saveExpUrls = Wikilog::expandLocalUrls();

		# Feed title: default to "{{SITENAME}} - title [lang]",
		# like Special:RecentChanges.
		$title = $this->mQuery->getWikilogTitle();
		if ( $title !== null ) {
			$name = wfMsgForContent( 'wikilog-feed-title',
				/* $1 */ $title->getPrefixedText(),
				/* $2 */ $wgContLanguageCode
			);
			# !!TODO!! fetch description/subtitle from wikilog main page.
			$descr = wfMsgForContent( 'wikilog-feed-description' );
		} else {
			$name = wfMsgForContent( 'wikilog-feed-title',
				/* $1 */ wgMsgForContent( 'wikilog' ),
				/* $2 */ $wgContLanguageCode
			);
			# Default description/subtitle.
			$descr = wfMsgForContent( 'wikilog-feed-description' );
		}

		$feed = new $wgWikilogFeedClasses[$type](
			$this->mTitle->getFullUrl(),
			$name,
			wfTimestampNow(),
			$this->mTitle->getFullUrl()
		);

		if ( $wgFavicon !== false ) {
			# !!TODO!! custom wikilog icon.
			$feed->setIcon( wfExpandUrl( $wgFavicon ) );
		}

		$feed->setSubtitle( $descr );

		$feed->outHeader();

		$this->doQuery();
		$numRows = min( $this->mResult->numRows(), $limit );

		if ( $numRows ) {
			$this->mResult->rewind();
			for ( $i = 0; $i < $numRows; $i++ ) {
				$row = $this->mResult->fetchObject();
				$feed->outEntry( $this->feedEntry( $row ) );
			}
		}

		$feed->outFooter();

		# Revert state.
		Wikilog::expandLocalUrls( $saveExpUrls );
	}

	function feedEntry( $row ) {
		global $wgParser, $wgEnableParserCache, $wgUser;
		
		list( $wikilogTitleName, $itemName ) =
			explode( '/', str_replace( '_', ' ', $row->page_title ), 2 );
		$wikilogTitleTitle =& Title::makeTitle( $row->page_namespace, $wikilogTitleName );
		$itemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		# Retrieve article parser output
		list( $article, $parserOutput ) = Wikilog::parsedArticle( $itemTitle );

		# Generate some fixed bits
		$authors = unserialize( $row->wlp_authors );
		$pubdate = $row->wlp_pubdate;

		# Create new syndication entry.
		$entry = new WlSyndicationEntry(
			$itemTitle->getFullUrl(),
			$itemName,
			$pubdate,
			$itemTitle->getFullUrl()
		);

		# Summary, if available.
		if ( isset( $parserOutput->mExtWikilog ) && $parserOutput->mExtWikilog->mSummary ) {
			$summary = Sanitizer::removeHTMLcomments( $parserOutput->mExtWikilog->mSummary );
			$entry->setSummary( new WlTextConstruct( 'html', $summary ) );
		}

		# Content.
		$content = Sanitizer::removeHTMLcomments( $parserOutput->getText() );
		$entry->setContent( new WlTextConstruct( 'html', $content ) );

		# Authors.
		foreach ( $authors as $user => $userid ) {
			$usertitle = Title::makeTitle( NS_USER, $user );
			$entry->addAuthor( $user, $usertitle->getFullUrl() );
		}

		$entry->setPublished( $pubdate );

		return $entry;
	}

	function feedEmpty() {
	}

	function doQuery() {
		$this->mIndexField = 'wlp_pubdate';
		$this->mResult = $this->reallyDoQuery( 20 );
	}

	function reallyDoQuery( $limit ) {
		$fname = __METHOD__ . ' (' . get_class( $this ) . ')';
		$info = $this->getQueryInfo();
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = $info['conds'];
		$options = $info['options'];
		$options['ORDER BY'] = $this->mIndexField . ' DESC';
		$options['LIMIT'] = intval( $limit );
		$res = $this->mDb->select( $tables, $fields, $conds, $fname, $options );
		return new ResultWrapper( $this->mDb, $res );
	}

	function getQueryInfo() {
		return $this->mQuery->getQueryInfo( $this->mDb );
	}


	function getParser() {
		if ( $this->mParser === null ) {
			global $wgParser;
			$this->mParserOptions = new ParserOptions();
			$this->mParserOptions->setEditSection( false );
			$this->mParser = clone $wgParser;
			$this->mParser->startExternalParse( $this->mTitle, $this->mParserOptions, Parser::OT_HTML );
			$this->mFrame = $this->mParser->getPreprocessor()->newFrame();
		}
		return $this->mParser;
	}
	
}
