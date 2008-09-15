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
	public $mFormat;
	public $mQuery;
	public $mLimit;
	public $mDb;

	protected $mCopyright;

	/**
	 * WikilogFeed constructor.
	 *
	 * @param $title Feed title and URL.
	 * @param $format Feed format ('atom' or 'rss').
	 * @param $query WikilogItemQuery options.
	 * @param $limit Number of items to generate.
	 */
	public function __construct( $title, $format, WikilogItemQuery $query,
			$limit = false )
	{
		global $wgWikilogNumArticles, $wgUser;

		$this->mTitle = $title;
		$this->mFormat = $format;
		$this->mQuery = $query;
		$this->mLimit = $limit ? $limit : $wgWikilogNumArticles;

		$this->mDb = wfGetDB( DB_SLAVE );

		# Retrieve copyright notice.
		$skin = $wgUser->getSkin();
		$this->mCopyright = $skin->getCopyright( 'normal' );
	}

	public function getFeedObject( $title, $updated = false ) {
		global $wgContLanguageCode, $wgWikilogFeedClasses;

		return new $wgWikilogFeedClasses[$this->mFormat](
			$this->mTitle->getFullUrl(),
			wfMsgForContent( 'wikilog-feed-title', $title, $wgContLanguageCode ),
			( $updated ? $updated : wfTimestampNow() ),
			$this->mTitle->getFullUrl()
		);
	}

	public function execute() {
		if ( !$this->checkFeedOutput() )
			return;

		$title = $this->mQuery->getWikilogTitle();
		$feedTitle = $title ?
			$title->getPrefixedText() :
			wfMsgForContent( 'wikilog' );

        $lastmod = $this->checkLastModified();
		if ( $lastmod === false ) return;

		list( $timekey, $feedkey ) = $this->getCacheKeys();
		FeedUtils::checkPurge( $timekey, $feedkey );

		$feed = $this->getFeedObject( $feedTitle, $lastmod );
		$cached = $this->loadFromCache( $lastmod, $timekey, $feedkey );

		if( is_string( $cached ) ) {
			wfDebug( "Wikilog: Outputting cached feed\n" );
			$feed->httpHeaders();
			echo $cached;
		} else {
			wfDebug( "Wikilog: rendering new feed and caching it\n" );
			ob_start();
			$this->feed( $feed );
			$cached = ob_get_contents();
			ob_end_flush();
			$this->saveToCache( $cached, $timekey, $feedkey );
		}
	}

	public function feed( $feed ) {
		global $wgOut, $wgFavicon;

		/// TODO: fetch description/subtitle from wikilog main page.
		$descr = wfMsgForContent( 'wikilog-feed-description' );
		$feed->setSubtitle( $descr );

		if ( $wgFavicon !== false ) {
			$feed->setIcon( wfExpandUrl( $wgFavicon ) );
		}

		if ( $this->mCopyright ) {
			$feed->setRights( new WlTextConstruct( 'html', $this->mCopyright ) );
		}

		$feed->outHeader();

		$this->doQuery();
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

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

		# Make titles.
// 		$wikilogName = str_replace( '_', ' ', $row->wlw_title );
// 		$wikilogTitle =& Title::makeTitle( $row->wlw_namespace, $row->wlw_title );
		$itemName = str_replace( '_', ' ', $row->wlp_title );
		$itemTitle =& Title::makeTitle( $row->page_namespace, $row->page_title );

		# Retrieve article parser output
		list( $article, $parserOutput ) = Wikilog::parsedArticle( $itemTitle, true );

		# Generate some fixed bits
		$authors = unserialize( $row->wlp_authors );

		# Create new syndication entry.
		$entry = new WlSyndicationEntry(
			self::makeEntryId( $itemTitle ),
			$itemName,
			$row->wlp_updated,
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

		if ( $row->wlp_publish ) {
			$entry->setPublished( $row->wlp_pubdate );
		}

		return $entry;
	}

	function doQuery() {
		$this->mIndexField = 'wlp_pubdate';
		$this->mResult = $this->reallyDoQuery( $this->mLimit );
	}

	function reallyDoQuery( $limit ) {
		$fname = __METHOD__ . ' (' . get_class( $this ) . ')';
		$info = $this->getQueryInfo();
		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = $info['conds'];
		$options = $info['options'];
		$joins = $info['join_conds'];
		$options['ORDER BY'] = $this->mIndexField . ' DESC';
		$options['LIMIT'] = intval( $limit );
		$res = $this->mDb->select( $tables, $fields, $conds, $fname, $options, $joins );
		return new ResultWrapper( $this->mDb, $res );
	}

	function getQueryInfo() {
		return $this->mQuery->getQueryInfo( $this->mDb );
	}

	/**
	 * Checks if client cache is up-to-date.
	 *
	 * @return False if client cache is up-to-date, local data last change
	 *   timestamp otherwise.
	 */
	public function checkLastModified() {
		global $wgOut;
		$dbr = wfGetDB( DB_SLAVE );
		if ( ( $t = $this->mQuery->getWikilogTitle() ) ) {
			$lastmod = $dbr->selectField( 'wikilog_wikilogs', 'wlw_updated',
				array( 'wlw_page' => $t->getArticleId() ), __METHOD__ );
		} else {
			$lastmod = $dbr->selectField( 'wikilog_wikilogs', 'MAX(wlw_updated)',
				false, __METHOD__ );
		}
		if( $lastmod && $wgOut->checkLastModified( $lastmod ) ) {
			# Client cache fresh and headers sent, nothing more to do.
			return false;
		}
		return $lastmod;
	}

	/**
	 * Save feed output to cache.
	 *
	 * @param $feed Feed output.
	 * @param $timekey Object cache key for the cached feed timestamp.
	 * @param $feedkey Object cache key for the cached feed output.
	 */
	public function saveToCache( $feed, $timekey, $feedkey ) {
		global $messageMemc;
		$messageMemc->set( $feedkey, $feed );
		$messageMemc->set( $timekey, wfTimestamp( TS_MW ), 24 * 3600 );
	}

	/**
	 * Load feed output from cache.
	 *
	 * @param $tsData Timestamp of the last change of the local data.
	 * @param $timekey Object cache key for the cached feed timestamp.
	 * @param $feedkey Object cache key for the cached feed output.
	 * @return The cached feed output if cache is good, false otherwise.
	 */
	public function loadFromCache( $tsData, $timekey, $feedkey ) {
		global $messageMemc, $wgFeedCacheTimeout;
		$tsCache = $messageMemc->get( $timekey );

		if ( ( $wgFeedCacheTimeout > 0 ) && $tsCache ) {
			$age = time() - wfTimestamp( TS_UNIX, $tsCache );

			if ( $age < $wgFeedCacheTimeout ) {
				wfDebug( "Wikilog: loading feed from cache -- ".
					"too young: age ($age) < timeout ($wgFeedCacheTimeout) ".
					"($feedkey; $tsCache; $tsData)\n" );
				return $messageMemc->get( $feedkey );
			} else if ( $tsCache >= $tsData ) {
				wfDebug( "Wikilog: loading feed from cache -- ".
					"not modified: cache ($tsCache) >= data ($tsData)".
					"($feedkey)\n" );
				return $messageMemc->get( $feedkey );
			} else {
				wfDebug( "Wikilog: cached feed timestamp check failed -- ".
					"cache ($tsCache) < data ($tsData)\n" );
			}
		}
		return false;
	}

	/**
	 * Returns the keys for the timestamp and feed output in the object cache.
	 */
	function getCacheKeys() {
		$title = $this->mQuery->getWikilogTitle();
		$id = $title ? 'id:' . $title->getArticleId() : 'site';
		$ft = 'show:' . $this->mQuery->getPubStatus() .
			':limit:' . $this->mLimit;
		return array(
			wfMemcKey( 'wikilog', $this->mFormat, $id, 'timestamp' ),
			wfMemcKey( 'wikilog', $this->mFormat, $id, $ft )
		);
	}

	/**
	 * Shadowed from FeedUtils::checkFeedOutput(). The difference is that
	 * this version checks against $wgWikilogFeedClasses instead of
	 * $wgFeedClasses.
	 */
	public function checkFeedOutput() {
		global $wgFeed, $wgWikilogFeedClasses;
		if ( !$wgFeed ) {
			$wgOut->addWikiMsg( 'feed-unavailable' );
			return false;
		}
		if( !isset( $wgWikilogFeedClasses[$this->mFormat] ) ) {
			wfHttpError( 500, "Internal Server Error", "Unsupported feed type." );
			return false;
		}
		return true;
	}

	/**
	 * Creates an unique ID for a feed entry. Tries to use $wgTaggingEntity
	 * if possible in order to create an RFC 4151 tag, otherwise, we use the
	 * page URL.
	 */
	public static function makeEntryId( $title ) {
		global $wgTaggingEntity;
		if ( $wgTaggingEntity ) {
			$qstr = wfArrayToCGI( array( 'wk' => wfWikiID(), 'id' => $title->getArticleId() ) );
			return "tag:{$wgTaggingEntity}:wikilog?{$qstr}";
		} else {
			return $title->getFullUrl();
		}
	}

}
