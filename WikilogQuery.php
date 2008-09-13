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


/**
 * Wikilog post SQL query driver.
 * This class drives queries for wikilog posts, given the fields to filter.
 */
class WikilogItemQuery {

	# Valid filter values for publish status.
	const PS_ALL       = 0;		///< Return all items
	const PS_PUBLISHED = 1;		///< Return only published items
	const PS_DRAFTS    = 2;		///< Return only drafts

	# Local variables.
	private $mWikilogTitle = null;			///< Filter by wikilog.
	private $mPubStatus = self::PS_ALL;		///< Filter by published status.
	private $mCategory = false;				///< Filter by category.
	private $mAuthor = false;				///< Filter by author.
	private $mTag = false;					///< Filter by tag.
	private $mDate = false;					///< Filter by date.
	private $mNeedWikilogParam = false;		///< Need wikilog param in queries.

	/**
	 * Constructor. Creates a new instance and optionally sets the Wikilog
	 * title to query.
	 * @param $wikilogTitle Wikilog title object to query for.
	 */
	function __construct( $wikilogTitle = null ) {
		$this->setWikilogTitle( $wikilogTitle );

		# If constructed without a title (from Special:Wikilog), it means that
		# the listing is global, and needs wikilog parameter to filter.
		$this->mNeedWikilogParam = ($wikilogTitle == null);
	}

	/**
	 * Sets the wikilog title to query for.
	 * @param $wikilogTitle Wikilog title object to query for.
	 */
	function setWikilogTitle( $wikilogTitle ) {
		$this->mWikilogTitle = $wikilogTitle;
	}

	/**
	 * Sets the publish status to query for.
	 * @param $pubStatus Publish status, string or integer.
	 */
	function setPubStatus( $pubStatus ) {
		if ( is_null( $pubStatus ) ) {
			$pubStatus = self::PS_PUBLISHED;
		} else if ( is_string( $pubStatus ) ) {
			$pubStatus = self::parsePubStatusText( $pubStatus );
		}
		$this->mPubStatus = intval( $pubStatus );
	}

	/**
	 * Sets the category to query for.
	 * @param $category Category title object or text.
	 */
	function setCategory( $category ) {
		if ( is_object( $category ) ) {
			$this->mCategory = $category;
		} else if ( is_string( $category ) ) {
			$t = Title::makeTitleSafe( NS_CATEGORY, $category );
			if ( $t !== null ) {
				$this->mCategory = $t;
			}
		}
	}

	/**
	 * Sets the author to query for.
	 * @param $category User page title object or text.
	 */
	function setAuthor( $author ) {
		if ( is_object( $author ) ) {
			$this->mAuthor = $author;
		} else if ( is_string( $author ) ) {
			$t = Title::makeTitleSafe( NS_USER, $author );
			if ( $t !== null ) {
				$this->mAuthor = $t;
			}
		}
	}

	/**
	 * Sets the tag to query for.
	 * @param $category Tag text.
	 */
	function setTag( $tag ) {
		global $wgWikilogEnableTags;
		if ( $wgWikilogEnableTags ) {
			$this->mTag = $tag;
		}
	}

	/**
	 * Sets the date to query for.
	 * @param $year Publish date year.
	 * @param $month Publish date month, optional. If ommited, queries for
	 *   items during the whole year.
	 * @param $day Publish date day, optional. If ommited, queries for items
	 *   during the whole month or year.
	 */
	function setDate( $year, $month = false, $day = false ) {
		$year  = ($year  > 0 && $year  <= 9999) ? $year  : false;
		$month = ($month > 0 && $month <=   12) ? $month : false;
		$day   = ($day   > 0 && $day   <=   31) ? $day   : false;

		if ( $year ) {
			$date_end = str_pad( $year+1, 4, '0', STR_PAD_LEFT );
			$date_start = str_pad( $year, 4, '0', STR_PAD_LEFT );
			if ( $month ) {
				$date_end = $date_start . str_pad( $month+1, 2, '0', STR_PAD_LEFT );
				$date_start = $date_start . str_pad( $month, 2, '0', STR_PAD_LEFT );
				if ( $day ) {
					$date_end = $date_start . str_pad( $day+1, 2, '0', STR_PAD_LEFT );
					$date_start = $date_start . str_pad( $day, 2, '0', STR_PAD_LEFT );
				}
			}

			$this->mDate = (object)array(
				'year'  => $year,
				'month' => $month,
				'day'   => $day,
				'start' => str_pad( $date_start, 14, '0', STR_PAD_RIGHT ),
				'end'   => str_pad( $date_end,   14, '0', STR_PAD_RIGHT )
			);
		}
	}

	function getWikilogTitle()	{ return $this->mWikilogTitle; }
	function getPubStatus()		{ return $this->mPubStatus; }
	function getCategory()		{ return $this->mCategory; }
	function getAuthor()		{ return $this->mAuthor; }
	function getTag()			{ return $this->mTag; }
	function getDate()			{ return $this->mDate; }

	function getQueryInfo( $db, $extraFields = false ) {
		# Basic defaults.

		$tables = array(
			'page',
			'wikilog_posts'
		);
		$fields = array(
			'page_id',
			'page_namespace',
			'page_title',
			'wlp_publish',
			'wlp_pubdate',
			'wlp_authors',
			'wlp_tags',
		);
		$conds = array(
			'page_is_redirect' => 0,
			'page_id = wlp_page',
		);
		$options = array( );

		# Customizations.

		## Filter by wikilog name.
		if ( $this->mWikilogTitle !== null ) {
			$conds['page_namespace'] = $this->mWikilogTitle->getNamespace();
			$conds[] = 'page_title LIKE \'' . $db->escapeLike( $this->mWikilogTitle->getDBkey() ) . '/%\'';
		}

		## Filter by published status.
		if ( $this->mPubStatus === self::PS_PUBLISHED ) {
			$conds['wlp_publish'] = 1;
		} else if ( $this->mPubStatus === self::PS_DRAFTS ) {
			$conds['wlp_publish'] = 0;
		}

		## Filter by category.
		if ( $this->mCategory ) {
			$tables[] = 'categorylinks';
			$conds[] = 'page_id = cl_from';
			$conds['cl_to'] = $this->mCategory->getDBkey();
		}

		## Filter by author.
		if ( $this->mAuthor ) {
			$tables[] = 'wikilog_authors';
			$conds[] = 'page_id = wla_page';
			$conds['wla_author_text'] = $this->mAuthor->getDBkey();
		}

		## Filter by tag.
		if ( $this->mTag ) {
			$tables[] = 'wikilog_tags';
			$conds[] = 'page_id = wlt_page';
			$conds['wlt_tag'] = $this->mTag;
		}

		## Filter by date.
		if ( $this->mDate ) {
			$conds[] = 'wlp_pubdate >= ' . $db->addQuotes( $this->mDate->start );
			$conds[] = 'wlp_pubdate < ' . $db->addQuotes( $this->mDate->end );
		}

		## Add some expensive extra fields, only if requested.
		if ( $extraFields ) {
			# These fields are necessary to browse the archives.
			## XXX: They make queries slower, specially when ordering results
			## XXX: using them... some better solution must be found.
			$fields[] = "SUBSTRING(page_title FROM 1 FOR POSITION('/' IN page_title)-1) AS _wl_wikilog";
			$fields[] = "SUBSTRING(page_title FROM POSITION('/' IN page_title)+1) AS _wl_title";
		}

// 		$tables[] = 'x';	// DEBUG

		return array(
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
			'options' => $options
		);
	}

	function getDefaultQuery() {
		$query = array();

		if ( $this->mNeedWikilogParam && $this->mWikilogTitle ) {
			$query['wikilog'] = $this->mWikilogTitle->getPrefixedDBKey();
		}

		if ( $this->mPubStatus == self::PS_ALL ) {
			$query['show'] = 'all';
		} else if ( $this->mPubStatus == self::PS_DRAFTS ) {
			$query['show'] = 'drafts';
		}

		if ( $this->mCategory ) {
			$query['category'] = $this->mCategory->getDBKey();
		}

		if ( $this->mAuthor ) {
			$query['author'] = $this->mAuthor->getDBKey();
		}

		if ( $this->mTag ) {
			$query['tag'] = $this->mTag;
		}

		if ( $this->mDate ) {
			$query['year']  = $this->mDate->year;
			$query['month'] = $this->mDate->month;
			$query['day']   = $this->mDate->day;
		}

		return $query;
	}

	function isSingleWikilog() {
		return $this->mWikilogTitle !== null;
	}

	static function parsePubStatusText( $show = 'published' ) {
		if ( $show == 'all' || $show == 'any' ) {
			return self::PS_ALL;
		} else if ( $show == 'draft' || $show == 'drafts' ) {
			return self::PS_DRAFTS;
		} else {
			return self::PS_PUBLISHED;
		}
	}
}


