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


class WikilogFeed {

	public $mTitle;
	public $mQuery;
	public $mDb;

	protected $mCopyright;

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

		# Retrieve copyright notice.
		global $wgUser;
		$skin = $wgUser->getSkin();
		$this->mCopyright = $skin->getCopyright( 'normal' );
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
				/* $1 */ wfMsgForContent( 'wikilog' ),
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

		if ( $this->mCopyright ) {
			$feed->setRights( new WlTextConstruct( 'html', $this->mCopyright ) );
		}

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
	}

	function feedEntry( $row ) {
		global $wgServerName, $wgParser, $wgUser, $wgEnableParserCache;
		global $wgWikilogFeedSummary, $wgWikilogFeedContent;

		
		list( $wikilogTitleName, $itemName ) =
			explode( '/', str_replace( '_', ' ', $row->page_title ), 2 );
		$wikilogTitleTitle =& Title::makeTitle( $row->page_namespace, $wikilogTitleName );
		$itemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		# Retrieve article parser output
		list( $article, $parserOutput ) = Wikilog::parsedArticle( $itemTitle, true );

		# Generate some fixed bits
		$authors = unserialize( $row->wlp_authors );
		$pubdate = $row->wlp_pubdate;

		# Create new syndication entry.
		$entry = new WlSyndicationEntry(
			self::makeEntryId( $itemTitle ),
			$itemName,
			$article->getTimestamp(),	# or $article->getTouched()?
			$itemTitle->getFullUrl()
		);

		# Retrieve summary and content.
		list( $summary, $content ) = Wikilog::splitSummaryContent( $parserOutput );

		if ( $wgWikilogFeedSummary && $summary ) {
			$entry->setSummary( new WlTextConstruct( 'html', $summary ) );
		}
		if ( $wgWikilogFeedContent && $content ) {
			$entry->setContent( new WlTextConstruct( 'html', $content ) );
		}

		# Authors.
		foreach ( $authors as $user => $userid ) {
			$usertitle = Title::makeTitle( NS_USER, $user );
			$entry->addAuthor( $user, $usertitle->getFullUrl() );
		}

		if ( $row->wlp_pubdate ) {
			$entry->setPublished( $row->wlp_pubdate );
		}

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

	static function makeEntryId( $title ) {
		global $wgTaggingEntity;
		if ( $wgTaggingEntity ) {
			$qstr = wfArrayToCGI( array( 'wk' => wfWikiID(), 'id' => $title->getArticleId() ) );
			return "tag:{$wgTaggingEntity}:wikilog?{$qstr}";
		} else {
			return $title->getFullUrl();
		}
	}
}
