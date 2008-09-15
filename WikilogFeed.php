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

	/**
	 * Feed title (i.e., not Wikilog title). For Special:Wikilog, 'wikilog'
	 * system message should be used.
	 */
	protected $mTitle;

	/**
	 * Feed format, either 'atom' or 'rss'.
	 * @warning Insecure string, from query string. Shouldn't be displayed. 
	 */
	protected $mFormat;

	/**
	 * Wikilog query object. Contains the options that drives the database
	 * queries.
	 */
	protected $mQuery;

	/**
	 * Number of feed items to output.
	 */
	protected $mLimit;

	/**
	 * Either if this is a site feed (Special:Wikilog) or not.
	 */
	protected $mSiteFeed;

	/**
	 * Database object.
	 */
	protected $mDb;

	/**
	 * Copyright notice.
	 */
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
		$this->mSiteFeed = $this->mQuery->getWikilogTitle() === NULL;

		$this->mDb = wfGetDB( DB_SLAVE );

		# Retrieve copyright notice.
		$skin = $wgUser->getSkin();
		$this->mCopyright = $skin->getCopyright( 'normal' );
	}

	public function execute() {
		global $wgOut;

		if ( !$this->checkFeedOutput() )
			return;

		$feed = $this->mSiteFeed
			? $this->getSiteFeedObject()
			: $this->getWikilogFeedObject( $this->mQuery->getWikilogTitle() );

		if ( $feed === false ) {
			wfHttpError( 404, "Not found",
				"There is no such wikilog feed available from this site." );
			return;
		}

		list( $timekey, $feedkey ) = $this->getCacheKeys();
		FeedUtils::checkPurge( $timekey, $feedkey );

		if ( $feed->isCacheable() ) {
			# Check if client cache is ok.
			if ( $wgOut->checkLastModified( $feed->getUpdated() ) ) {
				# Client cache is fresh. OutputPage takes care of sending
				# the appropriate headers, nothing else to do.
				return;
			}

			# Try to load the feed from our cache.
			$cached = $this->loadFromCache( $feed->getUpdated(), $timekey, $feedkey );

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
		} else {
			# This feed is not cacheable.
			$this->feed( $feed );
		}
	}

	public function feed( $feed ) {
		global $wgOut, $wgFavicon;

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
		global $wgServerName, $wgEnableParserCache, $wgMimeType;
		global $wgWikilogFeedSummary, $wgWikilogFeedContent;
		global $wgParser, $wgUser;

		# Make titles.
		$wikilogName = str_replace( '_', ' ', $row->wlw_title );
		$wikilogTitle =& Title::makeTitle( $row->wlw_namespace, $row->wlw_title );
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

		# Comments link.
		$entry->addLinkRel( 'replies', array(
			'href' => $itemTitle->getTalkPage()->getFullUrl(),
			'type' => $wgMimeType
		) );

		# Source feed.
		if ( $this->mSiteFeed ) {
			$privfeed = $this->getWikilogFeedObject( $wikilogTitle, true );
			if ( $privfeed ) {
				$entry->setSource( $privfeed );
			}
		}

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
	 * Generates and populates a WlSyndicationFeed object for the site.
	 *
	 * @return Feed object.
	 */
	public function getSiteFeedObject() {
		global $wgContLanguageCode, $wgWikilogFeedClasses, $wgFavicon, $wgLogo;
		$title = wfMsgForContent( 'wikilog' );

		$updated = $this->mDb->selectField( 'wikilog_wikilogs',
			'MAX(wlw_updated)', false, __METHOD__ );
		if ( !$updated ) $updated = wfTimestampNow();

		$feed = new $wgWikilogFeedClasses[$this->mFormat](
			$this->mTitle->getFullUrl(),
			wfMsgForContent( 'wikilog-feed-title', $title, $wgContLanguageCode ),
			$updated,
			$this->mTitle->getFullUrl()
		);
		$feed->setSubtitle( wfMsgForContent( 'wikilog-feed-description' ) );
		$feed->setLogo( wfExpandUrl( $wgLogo ) );
		if ( $wgFavicon !== false ) {
			$feed->setIcon( wfExpandUrl( $wgFavicon ) );
		}
		if ( $this->mCopyright ) {
			$feed->setRights( new WlTextConstruct( 'html', $this->mCopyright ) );
		}
		return $feed;
	}

	/**
	 * Generates and populates a WlSyndicationFeed object for the given
	 * wikilog. Caches objects whenever possible.
	 *
	 * @param $wikilogTitle Title object for the wikilog.
	 * @return Feed object, or NULL if wikilog doesn't exist.
	 */
	public function getWikilogFeedObject( $wikilogTitle, $forsource = false ) {
		static $wikilogCache = array();
		global $wgContLanguageCode, $wgWikilogFeedClasses;
		$title = $wikilogTitle->getPrefixedText();
		if ( !isset( $wikilogCache[$title] ) ) {
			$row = $this->mDb->selectRow( 'wikilog_wikilogs',
				array(
					'wlw_page', 'wlw_subtitle',
					'wlw_icon', 'wlw_logo',
					'wlw_updated'
				),
				array( 'wlw_page' => $wikilogTitle->getArticleId() ),
				__METHOD__
			);
			if ( $row !== false ) {
				$self = $forsource
					 ? $wikilogTitle->getFullUrl( "feed={$this->mFormat}" )
					 : NULL;
				$feed = new $wgWikilogFeedClasses[$this->mFormat](
					$wikilogTitle->getFullUrl(),
					wfMsgForContent( 'wikilog-feed-title', $title, $wgContLanguageCode ),
					$row->wlw_updated, $wikilogTitle->getFullUrl(), $self
				);
				if ( $row->wlw_subtitle ) {
					$feed->setSubtitle( $row->wlw_subtitle );
				}
				if ( $this->mCopyright ) {
					$feed->setRights( new WlTextConstruct( 'html', $this->mCopyright ) );
				}
				/// TODO: parse $row->wlw_icon and output.
				/// TODO: parse $row->wlw_logo and output.
			} else {
				$feed = false;
			}
			$wikilogCache[$title] =& $feed;
		}
		return $wikilogCache[$title];
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
